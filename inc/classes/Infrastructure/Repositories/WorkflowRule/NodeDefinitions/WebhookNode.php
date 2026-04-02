<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** Webhook 節點定義 */
final class WebhookNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'webhook';

	/** @var string Node 名稱 */
	public string $name = '發送 Webhook 通知';

	/** @var string Node 描述 */
	public string $description = '發送 Webhook 通知';

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
			'url'      => new FormFieldDTO(
				[
					'name'        => 'url',
					'label'       => 'URL',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'https://example.com/webhook',
					'description' => 'Webhook 接收端 URL',
					'sort'        => 0,
				]
			),
			'method'   => new FormFieldDTO(
				[
					'name'     => 'method',
					'label'    => 'HTTP 方法',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'GET',
							'label' => 'GET',
						],
						[
							'value' => 'POST',
							'label' => 'POST',
						],
						[
							'value' => 'PUT',
							'label' => 'PUT',
						],
						[
							'value' => 'DELETE',
							'label' => 'DELETE',
						],
					],
				]
			),
			'headers'  => new FormFieldDTO(
				[
					'name'        => 'headers',
					'label'       => '標頭',
					'type'        => 'json',
					'required'    => false,
					'placeholder' => '{"Content-Type": "application/json"}',
					'description' => 'HTTP 請求標頭（JSON 格式）',
					'sort'        => 2,
				]
			),
			'body_tpl' => new FormFieldDTO(
				[
					'name'        => 'body_tpl',
					'label'       => '內文',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => '請求內文',
					'sort'        => 3,
				]
			),
		];
	}

	/**
	 * 執行回調：使用 wp_remote_request() 發送 HTTP Webhook 請求
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		$param_helper = new ParamHelper( $node, $workflow );

		// 取得 URL（必填）
		$url = (string) ( $node->params['url'] ?? '' );
		if ( $url === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WebhookNode 執行失敗：缺少 url',
				]
			);
		}

		// 取得 HTTP 方法（預設 POST）
		$method = (string) ( $node->params['method'] ?? 'POST' );
		if ( $method === '' ) {
			$method = 'POST';
		}

		// 解析 headers（JSON 格式，允許空字串）
		$headers_raw = (string) ( $node->params['headers'] ?? '' );
		$headers     = [];
		if ( $headers_raw !== '' ) {
			$decoded = \json_decode( $headers_raw, true );
			if ( !\is_array( $decoded ) ) {
				return new WorkflowResultDTO(
					[
						'node_id' => $node->id,
						'code'    => 500,
						'message' => 'WebhookNode 執行失敗：headers 不是合法 JSON',
					]
				);
			}
			$headers = $decoded;
		}

		// 渲染 body（支援 {{variable}} 模板替換）
		$body = $param_helper->replace( (string) ( $node->params['body_tpl'] ?? '' ) );

		// 發送 HTTP 請求
		$response = \wp_remote_request(
			$url,
			[
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
			]
		);

		// 處理 WP_Error
		if ( \is_wp_error( $response ) ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WebhookNode 執行失敗：' . $response->get_error_message(),
				]
			);
		}

		// 判斷 HTTP status code
		$status_code = (int) \wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 200 && $status_code < 300 ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 200,
					'message' => 'Webhook 發送成功',
				]
			);
		}

		return new WorkflowResultDTO(
			[
				'node_id' => $node->id,
				'code'    => 500,
				'message' => "WebhookNode 執行失敗：HTTP {$status_code}",
			]
		);
	}
}
