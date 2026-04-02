<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** LINE 訊息節點定義 */
final class LineNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'line';

	/** @var string Node 名稱 */
	public string $name = '傳送 LINE 訊息';

	/** @var string Node 描述 */
	public string $description = '傳送 LINE 訊息';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::SEND_MESSAGE;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'content_tpl' => new FormFieldDTO(
				[
					'name'     => 'content_tpl',
					'label'    => '內文',
					'type'     => 'template_editor',
					'required' => true,
					'sort'     => 0,
				]
			),
		];
	}

	/**
	 * 執行回調：透過 LINE Messaging API 發送文字訊息
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		$param_helper = new ParamHelper( $node, $workflow );

		// 從 workflow context 取得 line_user_id
		$line_user_id = (string) ( $workflow->context['line_user_id'] ?? '' );
		if ( $line_user_id === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'LineNode 執行失敗：缺少 line_user_id',
				]
			);
		}

		// 渲染 content_tpl 模板
		$content = $param_helper->replace( (string) ( $node->params['content_tpl'] ?? '' ) );

		// 呼叫 MessageService 發送訊息
		try {
			MessageService::instance()->send_text_message( $line_user_id, $content );
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 200,
					'message' => 'LINE 訊息發送成功',
				]
			);
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			if ( \str_contains( $message, 'Channel Access Token' ) ) {
				return new WorkflowResultDTO(
					[
						'node_id' => $node->id,
						'code'    => 500,
						'message' => 'LineNode 執行失敗：Channel Access Token 未設定',
					]
				);
			}
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => "LineNode 執行失敗：{$message}",
				]
			);
		}
	}
}
