<?php
/**
 * Workflow 執行清單 API 整合測試。
 *
 * 驗證 GET /power-funnel/workflows 端點的查詢行為、篩選條件與分頁邏輯。
 *
 * @group workflow-api
 * @group workflow-api-list
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Applications;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 查詢 Workflow 執行清單測試
 *
 * Feature: 查詢 Workflow 執行清單
 */
class WorkflowApiListTest extends IntegrationTestCase {

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

		// 建立測試用戶 Alice（userId=5 的概念，實際 ID 由 WP 分配）
		$this->alice_id = $this->factory()->user->create(
			[
				'display_name' => 'Alice',
				'role'         => 'subscriber',
			]
		);

		// 建立待刪除的測試用戶
		$this->deleted_user_id = $this->factory()->user->create(
			[
				'display_name' => 'DeletedUser',
				'role'         => 'subscriber',
			]
		);
		// 立即刪除此用戶，模擬用戶已被刪除的情境
		\wp_delete_user($this->deleted_user_id);
	}

	/**
	 * 發送 REST API 請求的輔助方法
	 *
	 * @param string               $method HTTP 方法
	 * @param string               $path   請求路徑
	 * @param array<string, mixed> $params 查詢參數
	 * @return \WP_REST_Response
	 */
	private function do_rest_request( string $method, string $path, array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request($method, $path);
		if ('GET' === $method && !empty($params)) {
			$request->set_query_params($params);
		}
		$response = $this->rest_server->dispatch($request);
		return $response;
	}

	/** @var int 測試用戶 Alice 的 user ID */
	private int $alice_id;

	/** @var int 測試用戶（已被刪除）的 user ID */
	private int $deleted_user_id;

	/**
	 * 建立測試用 Workflow post（直接透過 DB 設定指定狀態）
	 *
	 * @param array<string, mixed> $meta            Meta 欄位
	 * @param string               $status          Workflow 狀態
	 * @param int                  $rule_id         WorkflowRule post ID
	 * @param int                  $post_author     post_author（觸發用戶 ID）
	 * @return int workflow post ID
	 */
	private function create_test_workflow_post( array $meta = [], string $status = 'running', int $rule_id = 0, int $post_author = 0 ): int {
		$default_meta = [
			'workflow_rule_id'     => (string) $rule_id,
			'trigger_point'        => 'pf/trigger/registration_approved',
			'nodes'                => [
				[
					'id'                    => 'n1',
					'node_definition_id'    => 'email',
					'params'                => [],
					'match_callback'        => [ \J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true' ],
					'match_callback_params' => [],
				],
				[
					'id'                    => 'n2',
					'node_definition_id'    => 'wait',
					'params'                => [],
					'match_callback'        => [ \J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true' ],
					'match_callback_params' => [],
				],
				[
					'id'                    => 'n3',
					'node_definition_id'    => 'line',
					'params'                => [],
					'match_callback'        => [ \J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true' ],
					'match_callback_params' => [],
				],
			],
			'context_callable_set' => [],
			'results'              => [],
		];

		$merged_meta = \wp_parse_args($meta, $default_meta);

		$insert_args = [
			'post_type'   => 'pf_workflow',
			'post_status' => 'draft',
			'post_title'  => '測試工作流',
			'meta_input'  => $merged_meta,
		];

		if ($post_author > 0) {
			$insert_args['post_author'] = $post_author;
		}

		$post_id = \wp_insert_post($insert_args);

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
	 * @param string $title        規則標題
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
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 前置（狀態）- 呼叫者必須為已登入的管理員
	 * Example: 未登入時查詢 Workflow 清單操作失敗
	 */
	public function test_未登入時查詢Workflow清單操作失敗(): void {
		// Given 未登入（不呼叫 wp_set_current_user）
		\wp_set_current_user(0);

		// When 未登入的訪客查詢 Workflow 執行清單
		$response = $this->do_rest_request('GET', '/power-funnel/workflows');

		// Then 操作失敗，HTTP 狀態碼為 401 或 403
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入訪客應收到 401 或 403 錯誤'
		);
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 前置（參數）- 必要參數必須提供
	 * Scenario Outline: 分頁參數無效時操作失敗 (per_page=0, page=1)
	 */
	public function test_per_page為0時操作失敗(): void {
		// Given 管理員已登入
		\wp_set_current_user($this->admin_id);

		// When 管理員查詢 Workflow 執行清單，per_page 為 0，page 為 1
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 0, 'page' => 1 ]
		);

		// Then 操作失敗，HTTP 狀態碼為 400，錯誤訊息包含「分頁參數無效」
		$this->assertSame(400, $response->get_status(), '無效 per_page 應回傳 400');
		$data = $response->get_data();
		$this->assertStringContainsString('分頁參數無效', $data['message'] ?? '', '錯誤訊息應包含「分頁參數無效」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 前置（參數）- 必要參數必須提供
	 * Scenario Outline: 分頁參數無效時操作失敗 (per_page=10, page=0)
	 */
	public function test_page為0時操作失敗(): void {
		// Given 管理員已登入
		\wp_set_current_user($this->admin_id);

		// When 管理員查詢 Workflow 執行清單，per_page 為 10，page 為 0
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 10, 'page' => 0 ]
		);

		// Then 操作失敗，HTTP 狀態碼為 400
		$this->assertSame(400, $response->get_status(), '無效 page 應回傳 400');
		$data = $response->get_data();
		$this->assertStringContainsString('分頁參數無效', $data['message'] ?? '', '錯誤訊息應包含「分頁參數無效」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 前置（參數）- 必要參數必須提供
	 * Scenario Outline: 分頁參數無效時操作失敗 (per_page=-1, page=1)
	 */
	public function test_per_page為負數時操作失敗(): void {
		// Given 管理員已登入
		\wp_set_current_user($this->admin_id);

		// When 管理員查詢 Workflow 執行清單，per_page 為 -1，page 為 1
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => -1, 'page' => 1 ]
		);

		// Then 操作失敗，HTTP 狀態碼為 400
		$this->assertSame(400, $response->get_status(), '負數 per_page 應回傳 400');
		$data = $response->get_data();
		$this->assertStringContainsString('分頁參數無效', $data['message'] ?? '', '錯誤訊息應包含「分頁參數無效」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 前置（參數）- status 篩選值必須為有效的 Workflow 狀態
	 * Example: 傳入無效 status 時操作失敗
	 */
	public function test_無效status篩選值時操作失敗(): void {
		// Given 管理員已登入
		\wp_set_current_user($this->admin_id);

		// When 管理員查詢 Workflow 執行清單，篩選 status 為 "invalid_status"
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'status' => 'invalid_status' ]
		);

		// Then 操作失敗，HTTP 狀態碼為 400，錯誤訊息包含「無效的 status 篩選值」
		$this->assertSame(400, $response->get_status(), '無效 status 應回傳 400');
		$data = $response->get_data();
		$this->assertStringContainsString('無效的 status 篩選值', $data['message'] ?? '', '錯誤訊息應包含「無效的 status 篩選值」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 清單應包含 Workflow 摘要資訊與分頁 meta
	 * Example: 查詢全部 Workflow 後回傳摘要清單
	 */
	public function test_查詢全部Workflow後回傳摘要清單(): void {
		// Given 系統中有 WorkflowRule 10（報名通知）與 WorkflowRule 20（活動提醒）
		$rule_10_id = $this->create_test_rule('報名通知工作流', 'pf/trigger/registration_approved');
		$rule_20_id = $this->create_test_rule('活動提醒工作流', 'pf/trigger/activity_before_start');

		// And Workflow 100（completed, rule=10, 3 nodes, results 3/3）
		$wf_100_results = [
			[
				'node_id'     => 'n1',
				'code'        => 200,
				'message'     => '發信成功',
				'executed_at' => '2026-04-01T09:00:05+00:00',
			],
			[
				'node_id'     => 'n2',
				'code'        => 200,
				'message'     => '等待完成',
				'executed_at' => '2026-04-01T09:01:05+00:00',
			],
			[
				'node_id'     => 'n3',
				'code'        => 200,
				'message'     => 'LINE 發送成功',
				'executed_at' => '2026-04-01T09:01:10+00:00',
			],
		];
		$this->ids['wf_100'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
				'results'          => $wf_100_results,
			],
			'completed',
			$rule_10_id
		);

		// And Workflow 101（failed, rule=10, 2/3 results）
		$wf_101_results = [
			[
				'node_id'     => 'n1',
				'code'        => 200,
				'message'     => '發信成功',
				'executed_at' => '2026-04-01T09:30:05+00:00',
			],
			[
				'node_id'     => 'n2',
				'code'        => 500,
				'message'     => 'LINE API token 過期',
				'executed_at' => '2026-04-01T09:30:10+00:00',
			],
		];
		$this->ids['wf_101'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
				'results'          => $wf_101_results,
			],
			'failed',
			$rule_10_id
		);

		// And Workflow 102（running, rule=20, 0/3 results）
		$this->ids['wf_102'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_20_id,
				'trigger_point'    => 'pf/trigger/activity_before_start',
				'results'          => [],
			],
			'running',
			$rule_20_id
		);

		// When 管理員查詢 Workflow 執行清單，per_page=10, page=1
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 10, 'page' => 1 ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data = $response->get_data();

		$this->assertSame('operation_success', $data['code'] ?? '', 'code 應為 operation_success');
		$this->assertArrayHasKey('data', $data, '應有 data 鍵');
		$this->assertArrayHasKey('items', $data['data'], '應有 items 鍵');
		$this->assertArrayHasKey('pagination', $data['data'], '應有 pagination 鍵');

		// And 查詢結果包含 3 筆
		$items = $data['data']['items'];
		$this->assertCount(3, $items, '應有 3 筆結果');

		// And 分頁資訊
		$pagination = $data['data']['pagination'];
		$this->assertSame(3, $pagination['total'], 'total 應為 3');
		$this->assertSame(1, $pagination['totalPages'], 'totalPages 應為 1');
		$this->assertSame(1, $pagination['currentPage'], 'currentPage 應為 1');
		$this->assertSame(10, $pagination['perPage'], 'perPage 應為 10');

		// And 每筆結果應包含必要欄位
		$workflow_ids = array_column($items, 'workflowId');
		$this->assertContains((string) $this->ids['wf_100'], $workflow_ids, '應包含 wf_100');
		$this->assertContains((string) $this->ids['wf_101'], $workflow_ids, '應包含 wf_101');
		$this->assertContains((string) $this->ids['wf_102'], $workflow_ids, '應包含 wf_102');

		// And 找到 wf_102（running）驗證 nodeProgress
		$wf_102_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_102'] === $item['workflowId']) {
				$wf_102_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_102_item, '應找到 wf_102');
		$this->assertSame('running', $wf_102_item['status'], 'wf_102 status 應為 running');
		$this->assertSame('0/3', $wf_102_item['nodeProgress'], 'wf_102 nodeProgress 應為 0/3');
		$this->assertSame('活動提醒工作流', $wf_102_item['workflowRuleTitle'], 'wf_102 workflowRuleTitle 應為 活動提醒工作流');

		// And 找到 wf_101（failed）驗證 nodeProgress 與 duration
		$wf_101_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_101'] === $item['workflowId']) {
				$wf_101_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_101_item, '應找到 wf_101');
		$this->assertSame('failed', $wf_101_item['status'], 'wf_101 status 應為 failed');
		$this->assertSame('2/3', $wf_101_item['nodeProgress'], 'wf_101 nodeProgress 應為 2/3');
		$this->assertNotEmpty($wf_101_item['duration'], 'wf_101 duration 應非空');

		// And 找到 wf_100（completed）驗證 nodeProgress 與 duration
		$wf_100_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_100'] === $item['workflowId']) {
				$wf_100_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_100_item, '應找到 wf_100');
		$this->assertSame('completed', $wf_100_item['status'], 'wf_100 status 應為 completed');
		$this->assertSame('3/3', $wf_100_item['nodeProgress'], 'wf_100 nodeProgress 應為 3/3');
		$this->assertNotEmpty($wf_100_item['duration'], 'wf_100 duration 應非空');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 按 status 篩選應僅回傳符合條件的 Workflow
	 * Example: 篩選 status 為 failed 後僅回傳失敗的 Workflow
	 */
	public function test_篩選status為failed後僅回傳失敗的Workflow(): void {
		// Given 系統中有 Workflow 100（completed）、101（failed）、102（running）
		$rule_id             = $this->create_test_rule('測試規則', 'pf/trigger/registration_approved');
		$this->ids['wf_100'] = $this->create_test_workflow_post([], 'completed', $rule_id);
		$this->ids['wf_101'] = $this->create_test_workflow_post([], 'failed', $rule_id);
		$this->ids['wf_102'] = $this->create_test_workflow_post([], 'running', $rule_id);

		// When 管理員查詢 Workflow 執行清單，篩選 status 為 "failed"
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'status' => 'failed' ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 查詢結果僅包含 failed 狀態的 Workflow
		$this->assertCount(1, $items, '篩選 failed 後應只有 1 筆');
		$this->assertSame((string) $this->ids['wf_101'], $items[0]['workflowId'], '應為 wf_101');
		$this->assertSame('failed', $items[0]['status'], 'status 應為 failed');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 按 workflow_rule_id 篩選應僅回傳指定規則的 Workflow
	 * Example: 篩選 workflow_rule_id 為 20 後僅回傳該規則的 Workflow
	 */
	public function test_篩選workflow_rule_id後僅回傳指定規則的Workflow(): void {
		// Given 系統中有 rule_10 和 rule_20
		$rule_10_id = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$rule_20_id = $this->create_test_rule('活動提醒', 'pf/trigger/activity_before_start');

		// And Workflow 100/101（rule=10）與 Workflow 102（rule=20）
		$this->ids['wf_100'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
			],
			'completed',
			$rule_10_id
		);
		$this->ids['wf_101'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
			],
			'failed',
			$rule_10_id
		);
		$this->ids['wf_102'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_20_id,
				'trigger_point'    => 'pf/trigger/activity_before_start',
			],
			'running',
			$rule_20_id
		);

		// When 管理員查詢 Workflow 執行清單，篩選 workflow_rule_id 為 rule_20_id
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'workflow_rule_id' => $rule_20_id ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 查詢結果僅包含 rule_20 的 Workflow
		$this->assertCount(1, $items, '篩選 workflow_rule_id=rule_20 後應只有 1 筆');
		$this->assertSame((string) $this->ids['wf_102'], $items[0]['workflowId'], '應為 wf_102');
		$this->assertSame((string) $rule_20_id, $items[0]['workflowRuleId'], 'workflowRuleId 應相符');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 按 trigger_point 篩選應僅回傳指定觸發點的 Workflow
	 * Example: 篩選 trigger_point 後僅回傳匹配的 Workflow
	 */
	public function test_篩選trigger_point後僅回傳匹配的Workflow(): void {
		// Given 系統中有不同 trigger_point 的 Workflow
		$rule_10_id = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$rule_20_id = $this->create_test_rule('活動提醒', 'pf/trigger/activity_before_start');

		$this->ids['wf_100'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
			],
			'completed',
			$rule_10_id
		);
		$this->ids['wf_101'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_10_id,
				'trigger_point'    => 'pf/trigger/registration_approved',
			],
			'failed',
			$rule_10_id
		);
		$this->ids['wf_102'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_20_id,
				'trigger_point'    => 'pf/trigger/activity_before_start',
			],
			'running',
			$rule_20_id
		);

		// When 管理員查詢 Workflow 執行清單，篩選 trigger_point 為 "pf/trigger/activity_before_start"
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'trigger_point' => 'pf/trigger/activity_before_start' ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 查詢結果僅包含 activity_before_start 觸發點的 Workflow
		$this->assertCount(1, $items, '篩選 trigger_point 後應只有 1 筆');
		$this->assertSame((string) $this->ids['wf_102'], $items[0]['workflowId'], '應為 wf_102');
		$this->assertSame('pf/trigger/activity_before_start', $items[0]['triggerPoint'], 'triggerPoint 應相符');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 關鍵字搜尋應模糊搜尋 results message
	 * Example: 搜尋 "token" 後回傳包含該關鍵字的 Workflow
	 */
	public function test_搜尋關鍵字後回傳包含該關鍵字的Workflow(): void {
		// Given 系統中有兩個 Workflow，其中 wf_101 的 results 包含 "LINE API token 過期"
		$rule_id             = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$this->ids['wf_100'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_id,
				'results'          => [
					[
						'node_id'     => 'n1',
						'code'        => 200,
						'message'     => '發信成功',
						'executed_at' => '2026-04-01T09:00:05+00:00',
					],
				],
			],
			'completed',
			$rule_id
		);
		$this->ids['wf_101'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_id,
				'results'          => [
					[
						'node_id'     => 'n1',
						'code'        => 200,
						'message'     => '發信成功',
						'executed_at' => '2026-04-01T09:30:05+00:00',
					],
					[
						'node_id'     => 'n2',
						'code'        => 500,
						'message'     => 'LINE API token 過期',
						'executed_at' => '2026-04-01T09:30:10+00:00',
					],
				],
			],
			'failed',
			$rule_id
		);

		// When 管理員查詢 Workflow 執行清單，搜尋關鍵字為 "token"
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'search' => 'token' ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 查詢結果僅包含含有 "token" 的 Workflow
		$this->assertCount(1, $items, '搜尋 token 後應只有 1 筆');
		$this->assertSame((string) $this->ids['wf_101'], $items[0]['workflowId'], '應為 wf_101');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 觸發用戶為已登入用戶時應顯示 display_name
	 * Example: 已登入用戶觸發的 Workflow 應顯示用戶名稱
	 */
	public function test_已登入用戶觸發的Workflow應顯示用戶名稱(): void {
		// Given 系統中有 Alice（已登入用戶）觸發的 Workflow
		$rule_id            = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$this->ids['wf_100'] = $this->create_test_workflow_post(
			[ 'workflow_rule_id' => (string) $rule_id ],
			'completed',
			$rule_id,
			$this->alice_id
		);

		// When 管理員查詢 Workflow 執行清單
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 10, 'page' => 1 ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 找到 wf_100，驗證 userId 與 userDisplayName
		$wf_100_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_100'] === $item['workflowId']) {
				$wf_100_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_100_item, '應找到 wf_100');
		$this->assertSame($this->alice_id, $wf_100_item['userId'], 'userId 應為 Alice 的 user ID');
		$this->assertSame('Alice', $wf_100_item['userDisplayName'], 'userDisplayName 應為 Alice');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- post_author 為 0 時應顯示「訪客」
	 * Example: Action Scheduler 自動觸發的 Workflow 應顯示訪客
	 */
	public function test_post_author為0時顯示訪客(): void {
		// Given 系統中有 post_author=0 的 Workflow（Action Scheduler 自動觸發）
		$rule_id            = $this->create_test_rule('活動提醒', 'pf/trigger/activity_before_start');
		$this->ids['wf_102'] = $this->create_test_workflow_post(
			[
				'workflow_rule_id' => (string) $rule_id,
				'trigger_point'    => 'pf/trigger/activity_before_start',
			],
			'running',
			$rule_id,
			0
		);

		// When 管理員查詢 Workflow 執行清單
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 10, 'page' => 1 ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 找到 wf_102，驗證 userId=0 時 userDisplayName 為「訪客」
		$wf_102_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_102'] === $item['workflowId']) {
				$wf_102_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_102_item, '應找到 wf_102');
		$this->assertSame(0, $wf_102_item['userId'], 'userId 應為 0');
		$this->assertSame('訪客', $wf_102_item['userDisplayName'], 'post_author=0 時 userDisplayName 應為「訪客」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 觸發用戶已被刪除時應顯示「訪客」
	 * Example: 觸發用戶已被刪除的 Workflow 應顯示訪客
	 */
	public function test_觸發用戶已被刪除時顯示訪客(): void {
		// Given 系統中有已被刪除用戶觸發的 Workflow
		$rule_id            = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$this->ids['wf_101'] = $this->create_test_workflow_post(
			[ 'workflow_rule_id' => (string) $rule_id ],
			'failed',
			$rule_id,
			$this->deleted_user_id
		);

		// When 管理員查詢 Workflow 執行清單
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'per_page' => 10, 'page' => 1 ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 找到 wf_101，驗證已刪除用戶的 userDisplayName 為「訪客」
		$wf_101_item = null;
		foreach ($items as $item) {
			if ((string) $this->ids['wf_101'] === $item['workflowId']) {
				$wf_101_item = $item;
				break;
			}
		}
		$this->assertNotNull($wf_101_item, '應找到 wf_101');
		$this->assertSame($this->deleted_user_id, $wf_101_item['userId'], 'userId 應為已刪除用戶的原始 ID');
		$this->assertSame('訪客', $wf_101_item['userDisplayName'], '用戶已刪除時 userDisplayName 應為「訪客」');
	}

	/**
	 * Feature: 查詢 Workflow 執行清單
	 * Rule: 後置（回應）- 無符合條件的 Workflow 時應回傳空陣列
	 * Example: 篩選條件無匹配結果時回傳空清單
	 */
	public function test_篩選條件無匹配結果時回傳空清單(): void {
		// Given 系統中有若干 Workflow（但 workflow_rule_id=999 的不存在）
		$rule_id = $this->create_test_rule('報名通知', 'pf/trigger/registration_approved');
		$this->create_test_workflow_post([], 'completed', $rule_id);

		// When 管理員查詢 Workflow 執行清單，篩選 workflow_rule_id 為 999999（不存在）
		\wp_set_current_user($this->admin_id);
		$response = $this->do_rest_request(
			'GET',
			'/power-funnel/workflows',
			[ 'workflow_rule_id' => 999999 ]
		);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
		$data  = $response->get_data();
		$items = $data['data']['items'] ?? [];

		// And 查詢結果為空陣列
		$this->assertEmpty($items, '無匹配結果時應回傳空陣列');
	}
}
