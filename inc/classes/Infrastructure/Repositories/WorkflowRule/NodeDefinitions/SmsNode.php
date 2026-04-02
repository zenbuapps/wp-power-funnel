<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** SMS 節點定義 */
final class SmsNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'sms';

	/** @var string Node 名稱 */
	public string $name = '傳送 SMS';

	/** @var string Node 描述 */
	public string $description = '傳送 SMS';

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
			'recipient'   => new FormFieldDTO(
				[
					'name'        => 'recipient',
					'label'       => '收件人',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '手機號碼',
					'description' => '輸入收件人的手機號碼',
					'sort'        => 0,
				]
			),
			'content_tpl' => new FormFieldDTO(
				[
					'name'     => 'content_tpl',
					'label'    => '內文',
					'type'     => 'textarea',
					'required' => true,
					'sort'     => 1,
				]
			),
		];
	}

	/**
	 * 執行回調：透過 WordPress filter 委派 SMS 發送
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		$param_helper = new ParamHelper( $node, $workflow );

		// 取得並渲染收件人（支援 {{variable}} 模板替換）
		$recipient = $param_helper->replace( (string) ( $node->params['recipient'] ?? '' ) );
		if ( $recipient === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'SmsNode 執行失敗：缺少 recipient',
				]
			);
		}

		// 渲染訊息內容
		$content = $param_helper->replace( (string) ( $node->params['content_tpl'] ?? '' ) );

		// 透過 WordPress filter 委派 SMS 發送
		$raw_result = \apply_filters(
			'power_funnel/sms/send',
			[
				'success' => false,
				'message' => 'SMS 發送失敗',
			],
			$recipient,
			$content
		);

		$result  = \is_array( $raw_result ) ? $raw_result : [
			'success' => false,
			'message' => 'SMS 發送失敗',
		];
		$success = (bool) ( $result['success'] ?? false );
		$message = (string) ( $result['message'] ?? '' );

		return new WorkflowResultDTO(
			[
				'node_id' => $node->id,
				'code'    => $success ? 200 : 500,
				'message' => $message,
			]
		);
	}
}
