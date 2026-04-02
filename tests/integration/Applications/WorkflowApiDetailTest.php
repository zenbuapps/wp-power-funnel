<?php
/**
 * Workflow 執行詳情 API 整合測試。
 *
 * 驗證 GET /power-funnel/workflows/{id} 端點的詳情查詢行為，
 * 包含基本資訊、節點清單、resolved context、contextCallableSet 與耗時資訊。
 *
 * @group workflow-api
 * @group workflow-api-detail
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Applications;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * 查詢 Workflow 執行詳情測試
 *
 * Feature: 查詢 Workflow 執行詳情
 */
class WorkflowApiDetailTest extends IntegrationTestCase {

	/** @var \WP_REST_Server REST server 實例 */
	private \WP_REST_Server $rest_server;

	/** @var int 管理員 user ID */
	private int $admin_id;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 直接呼叫註冊方法（init 可能已觸發，不能只靠 add_action）
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_cpt();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_status();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();

		// 確保 WorkflowApi routes 已註冊
		\J7\PowerFunnel\Applications\WorkflowApi::register_hooks();

		// 初始化 REST server
		global $wp_rest_server;
		$wp_rest_server    = new \WP_REST_Server();
		$this->rest_server = $wp_rest_server;
		\do_action('rest_api_init', $wp_rest_server);

		// 建立管理員用戶
		$this->admin_id = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	/**
	 * 發送 REST API 請求的輔助方法
	 *
	 * @param string $method HTTP 方法
	 * @param string $path   請求路徑
	 * @return \WP_REST_Response
	 */
	private function do_rest_request( string $method, string $path ): \WP_REST_Response {
		$request  = new \WP_REST_Request($method, $path);
		$response = $this->rest_server->dispatch($request);
		return $response;
	}

	/**
	 * 建立測試用 Workflow post（帶完整 meta 資料）
	 *
	 * @param array<string, mixed> $meta   Meta 欄位
	 * @param string               $status Workflow 狀態
	 * @return int workflow post ID
	 */
	private function create_test_workflow_post( array $meta = [], string $status = 'completed' ): int {
		$default_meta = [
			'workflow_rule_id'    => '0',
			'trigger_point'       => 'pf/trigger/registration_approved',
			'nodes'               => [],
			'context_callable_set' => [],
			'results'             => [],
		];

		$merged_meta = \wp_parse_args($meta, $default_meta);

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '測試工作流',
				'meta_input'  => \wp_slash($merged_meta),
			]
		);

		if (!is_int($post_id) || $post_id <= 0) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		if ('draft' !== $status) {
			$this->set_post_status_bypass_hooks($post_id, $status);
		}

		return $post_id;
	}

	/**
	 * 建立測試用 WorkflowRule post
	 *
	 * @param string $title         規則標題
	 * @param string $trigger_point 觸發點
	 * @return int rule post ID
	 */
	private function create_test_rule( string $title, string $trigger_point ): int {
		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow_rule',
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => [
					'trigger_point' => $trigger_point,
					'nodes'         => [],
				],
			]
		);

		return (int) $post_id;
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 前置（狀態）- 呼叫者必須為已登入的管理員
	 * Example: 未登入時查詢 Workflow 詳情操作失敗
	 */
	public function test_未登入時查詢Workflow詳情操作失敗(): void {
		// Given 系統中有 Workflow 100
		$wf_id = $this->create_test_workflow_post([], 'completed');

		// And 未登入
		\wp_set_current_user(0);

		// When 未登入的訪客查詢 Workflow 詳情
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作失敗，HTTP 狀態碼為 401 或 403
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入訪客應收到 401 或 403 錯誤'
		);
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 前置（狀態）- 指定的 Workflow 必須存在
	 * Example: 查詢不存在的 Workflow 時操作失敗
	 */
	public function test_查詢不存在的Workflow時操作失敗(): void {
		// Given 管理員已登入
		\wp_set_current_user($this->admin_id);

		// When 管理員查詢不存在的 Workflow ID（999999）
		$response = $this->do_rest_request('GET', '/power-funnel/workflows/999999');

		// Then 操作失敗，HTTP 狀態碼為 404
		$this->assertSame(404, $response->get_status(), '不存在的 Workflow 應回傳 404');
		$data = $response->get_data();
		$this->assertStringContainsString('不存在', $data['message'] ?? '', '錯誤訊息應包含「不存在」');
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 後置（回應）- 詳情應包含 Workflow 基本資訊
	 * Example: 查詢成功後回傳 Workflow 基本資訊
	 */
	public function test_查詢成功後回傳Workflow基本資訊(): void {
		// Given 系統中有 WorkflowRule 10（報名通知工作流）
		$rule_id = $this->create_test_rule('報名通知工作流', 'pf/trigger/registration_approved');

		// And Workflow 100（completed, rule=rule_id）
		$wf_id = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
			],
			'completed'
		);

		// When 管理員查詢 Workflow 詳情
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作成功，HTTP 狀態碼為 200
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data = $response->get_data();
		$this->assertSame('operation_success', $data['code'] ?? '', 'code 應為 operation_success');
		$this->assertArrayHasKey('data', $data, '回應應有 data 鍵');

		$detail = $data['data'];

		// And 查詢結果的基本資訊正確
		$this->assertSame((string) $wf_id, $detail['workflowId'] ?? '', 'workflowId 應相符');
		$this->assertSame((string) $rule_id, $detail['workflowRuleId'] ?? '', 'workflowRuleId 應相符');
		$this->assertSame('報名通知工作流', $detail['workflowRuleTitle'] ?? '', 'workflowRuleTitle 應相符');
		$this->assertSame('pf/trigger/registration_approved', $detail['triggerPoint'] ?? '', 'triggerPoint 應相符');
		$this->assertSame('completed', $detail['status'] ?? '', 'status 應為 completed');
		$this->assertArrayHasKey('createdAt', $detail, '應有 createdAt 欄位');
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 後置（回應）- 詳情應包含每個節點的定義與執行結果
	 * Example: 查詢成功後回傳節點清單與結果
	 */
	public function test_查詢成功後回傳節點清單與結果(): void {
		// Given nodes 與 results 完整的 Workflow
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => [ 'recipient' => 'context', 'subject' => '歡迎' ],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'wait',
				'params'                => [ 'delay_seconds' => 60 ],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'line',
				'params'                => [ 'message_tpl' => '感謝報名' ],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$results = [
			[
				'node_id'     => 'n1',
				'code'        => 200,
				'message'     => '發信成功',
				'data'        => null,
				'executed_at' => '2026-04-01T09:00:05+00:00',
			],
			[
				'node_id'     => 'n2',
				'code'        => 200,
				'message'     => '等待完成',
				'data'        => null,
				'executed_at' => '2026-04-01T09:01:05+00:00',
			],
			[
				'node_id'     => 'n3',
				'code'        => 200,
				'message'     => 'LINE 發送成功',
				'data'        => null,
				'executed_at' => '2026-04-01T09:01:10+00:00',
			],
		];

		$wf_id = $this->create_test_workflow_post(
			[
				'nodes'   => $nodes,
				'results' => $results,
			],
			'completed'
		);

		// When 管理員查詢 Workflow 詳情
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data   = $response->get_data();
		$detail = $data['data'];

		// And 查詢結果的 nodes 陣列包含 3 個節點
		$this->assertArrayHasKey('nodes', $detail, '應有 nodes 鍵');
		$this->assertCount(3, $detail['nodes'], 'nodes 應有 3 個元素');

		// And 每個節點都有 nodeId、nodeDefinitionId、result（含 executedAt）
		$node_ids = array_column($detail['nodes'], 'nodeId');
		$this->assertContains('n1', $node_ids, '應包含節點 n1');
		$this->assertContains('n2', $node_ids, '應包含節點 n2');
		$this->assertContains('n3', $node_ids, '應包含節點 n3');

		// And n1 的 result 正確
		$n1 = null;
		foreach ($detail['nodes'] as $node) {
			if ('n1' === ($node['nodeId'] ?? '')) {
				$n1 = $node;
				break;
			}
		}
		$this->assertNotNull($n1, '應找到節點 n1');
		$this->assertSame('email', $n1['nodeDefinitionId'] ?? '', 'n1 nodeDefinitionId 應為 email');
		$this->assertArrayHasKey('result', $n1, 'n1 應有 result 鍵');
		$this->assertSame(200, $n1['result']['code'] ?? null, 'n1 result code 應為 200');
		$this->assertSame('發信成功', $n1['result']['message'] ?? '', 'n1 result message 應為 發信成功');
		$this->assertNotEmpty($n1['result']['executedAt'] ?? '', 'n1 result executedAt 應非空');

		// And n3 的 result executedAt 相符
		$n3 = null;
		foreach ($detail['nodes'] as $node) {
			if ('n3' === ($node['nodeId'] ?? '')) {
				$n3 = $node;
				break;
			}
		}
		$this->assertNotNull($n3, '應找到節點 n3');
		$this->assertSame('LINE 發送成功', $n3['result']['message'] ?? '', 'n3 result message 應為 LINE 發送成功');
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 後置（回應）- 詳情應包含 resolved context
	 * Example: 查詢成功後回傳 resolved context
	 */
	public function test_查詢成功後回傳resolved_context(): void {
		// Given context_callable_set 指向 TestCallable::return_test_context
		TestCallable::$test_context = [
			'user_email'     => 'alice@example.com',
			'user_name'      => 'Alice',
			'activity_title' => 'React 進階工作坊',
		];

		$wf_id = $this->create_test_workflow_post(
			[
				'context_callable_set' => [
					'callable' => [ TestCallable::class, 'return_test_context' ],
					'params'   => [],
				],
			],
			'completed'
		);

		// When 管理員查詢 Workflow 詳情
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data   = $response->get_data();
		$detail = $data['data'];

		// And 查詢結果的 context 包含 resolved key-value pairs
		$this->assertArrayHasKey('context', $detail, '應有 context 鍵');
		$context = $detail['context'];
		$this->assertSame('alice@example.com', $context['user_email'] ?? '', 'user_email 應相符');
		$this->assertSame('Alice', $context['user_name'] ?? '', 'user_name 應相符');
		$this->assertSame('React 進階工作坊', $context['activity_title'] ?? '', 'activity_title 應相符');
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 後置（回應）- 詳情應包含人類可讀的 context_callable_set
	 * Example: 查詢成功後回傳精簡的 context_callable_set
	 */
	public function test_查詢成功後回傳精簡的contextCallableSet(): void {
		// Given context_callable_set 使用 FQCN callable
		$wf_id = $this->create_test_workflow_post(
			[
				'context_callable_set' => [
					'callable' => [ TestCallable::class, 'return_test_context' ],
					'params'   => [ 123 ],
				],
			],
			'completed'
		);

		// When 管理員查詢 Workflow 詳情
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data   = $response->get_data();
		$detail = $data['data'];

		// And contextCallableSet 應包含人類可讀格式（短 class 名 + method）
		$this->assertArrayHasKey('contextCallableSet', $detail, '應有 contextCallableSet 鍵');
		$ccs = $detail['contextCallableSet'];
		$this->assertArrayHasKey('callable', $ccs, 'contextCallableSet 應有 callable 鍵');
		$this->assertArrayHasKey('params', $ccs, 'contextCallableSet 應有 params 鍵');

		// And callable 應為精簡格式（ClassName::method_name），不含 namespace
		$this->assertStringContainsString('::', $ccs['callable'], 'callable 應包含 ::');
		$this->assertStringNotContainsString('\\', $ccs['callable'], 'callable 不應包含 namespace 分隔符 \\');
		$this->assertStringContainsString('TestCallable', $ccs['callable'], 'callable 應包含 class 短名');
		$this->assertStringContainsString('return_test_context', $ccs['callable'], 'callable 應包含 method 名');

		// And params 應保留原始值
		$this->assertSame([ 123 ], $ccs['params'], 'params 應保留原始值');
	}

	/**
	 * Feature: 查詢 Workflow 執行詳情
	 * Rule: 後置（回應）- 詳情應包含計算出的耗時資訊
	 * Example: 查詢成功後回傳 startedAt、completedAt、duration
	 */
	public function test_查詢成功後回傳時間資訊(): void {
		// Given Workflow 有 3 個 results，第一筆在 09:00:05，最後一筆在 09:01:10
		$results = [
			[
				'node_id'     => 'n1',
				'code'        => 200,
				'message'     => '發信成功',
				'data'        => null,
				'executed_at' => '2026-04-01T09:00:05+00:00',
			],
			[
				'node_id'     => 'n2',
				'code'        => 200,
				'message'     => '等待完成',
				'data'        => null,
				'executed_at' => '2026-04-01T09:01:05+00:00',
			],
			[
				'node_id'     => 'n3',
				'code'        => 200,
				'message'     => 'LINE 發送成功',
				'data'        => null,
				'executed_at' => '2026-04-01T09:01:10+00:00',
			],
		];

		$wf_id = $this->create_test_workflow_post(
			[ 'results' => $results ],
			'completed'
		);

		// When 管理員查詢 Workflow 詳情
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request('GET', "/power-funnel/workflows/{$wf_id}");

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data   = $response->get_data();
		$detail = $data['data'];

		// And startedAt 為第一筆 result 的 executed_at
		$this->assertNotEmpty($detail['startedAt'] ?? '', 'startedAt 應非空');

		// And completedAt 為最後一筆 result 的 executed_at
		$this->assertNotEmpty($detail['completedAt'] ?? '', 'completedAt 應非空');

		// And duration 為時間差（65 秒）
		$this->assertSame('65s', $detail['duration'] ?? '', 'duration 應為 65s');
	}
}
