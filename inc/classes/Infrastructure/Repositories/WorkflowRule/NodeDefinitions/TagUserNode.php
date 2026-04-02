<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 標籤用戶節點定義 */
final class TagUserNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'tag_user';

	/** @var string Node 名稱 */
	public string $name = '標籤用戶';

	/** @var string Node 描述 */
	public string $description = '標籤用戶';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::ACTION;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'tags'   => new FormFieldDTO(
				[
					'name'        => 'tags',
					'label'       => '標籤',
					'type'        => 'tags_input',
					'required'    => true,
					'description' => '輸入要操作的標籤',
					'sort'        => 0,
				]
			),
			'action' => new FormFieldDTO(
				[
					'name'     => 'action',
					'label'    => '動作',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'add',
							'label' => '新增標籤',
						],
						[
							'value' => 'remove',
							'label' => '移除標籤',
						],
					],
				]
			),
		];
	}

	/**
	 * 執行回調：新增或移除 LINE 用戶標籤
	 *
	 * 標籤以 pf_user_tags_{line_user_id} 為 key 儲存於 wp_options，
	 * 值為 JSON 編碼的字串陣列。
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		// 取得標籤列表（必填，非空陣列）
		$tags = $node->params['tags'] ?? [];
		if ( ! \is_array( $tags ) || empty( $tags ) ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TagUserNode 執行失敗：缺少 tags',
				]
			);
		}
		/** @var string[] $tags */
		$tags = \array_values( \array_filter( \array_map( static fn ( mixed $v ): string => (string) $v, $tags ) ) );

		// 取得動作（必填：add 或 remove）
		$action = (string) ( $node->params['action'] ?? '' );
		if ( $action !== 'add' && $action !== 'remove' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TagUserNode 執行失敗：action 必須為 add 或 remove',
				]
			);
		}

		// 從 workflow context 取得 line_user_id
		$line_user_id = (string) ( $workflow->context['line_user_id'] ?? '' );
		if ( $line_user_id === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TagUserNode 執行失敗：缺少 user_id',
				]
			);
		}

		// 讀取現有標籤（以 LINE user ID 為 key 儲存在 wp_options）
		$option_key    = "pf_user_tags_{$line_user_id}";
		$raw_tags      = \get_option( $option_key, '[]' );
		$existing_tags = \json_decode( \is_string( $raw_tags ) ? $raw_tags : '[]', true );
		if ( ! \is_array( $existing_tags ) ) {
			$existing_tags = [];
		}
		/** @var string[] $existing_tags */

		if ( $action === 'add' ) {
			// 計算真正新增的標籤（不在 existing_tags 中的）
			$new_tags = \array_values( \array_diff( $tags, $existing_tags ) );

			// 合併並去重
			$merged = \array_values( \array_unique( \array_merge( $existing_tags, $tags ) ) );
			\update_option( $option_key, \wp_json_encode( $merged ) );

			// 對每個新增的標籤觸發 fire_user_tagged
			foreach ( $new_tags as $tag ) {
				TriggerPointService::fire_user_tagged( $line_user_id, $tag );
			}

			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 200,
					'message' => '標籤新增成功',
				]
			);
		}

		// action === 'remove'：過濾掉指定標籤
		$filtered = \array_values( \array_diff( $existing_tags, $tags ) );
		\update_option( $option_key, \wp_json_encode( $filtered ) );

		return new WorkflowResultDTO(
			[
				'node_id' => $node->id,
				'code'    => 200,
				'message' => '標籤移除成功',
			]
		);
	}
}
