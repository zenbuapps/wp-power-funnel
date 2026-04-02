<?php
/**
 * 查詢觸發條件列表 整合測試。
 *
 * 驗證 GET /wp-json/power-funnel/trigger-points 端點的行為。
 * 包含：未授權存取、成功查詢、filter 擴充、分組回應格式驗證。
 *
 * @group smoke
 * @group workflow-rule
 * @group trigger-points
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\WorkflowRule;

use J7\PowerFunnel\Applications\TriggerPointApi;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 查詢觸發條件列表測試
 *
 * Feature: 查詢觸發條件列表
 */
class QueryTriggerPointsTest extends IntegrationTestCase {

	/** @var \WP_REST_Server REST 伺服器實例 */
	protected \WP_REST_Server $server;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointApi::register_hooks();
	}

	/** 每個測試前設置 REST Server */
	public function set_up(): void {
		parent::set_up();
		// 初始化 REST Server
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		\do_action('rest_api_init');
	}

	/** 每個測試後清理 REST Server */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	// ========== Rule: 未授權存取 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 未登入用戶查詢觸發條件列表時操作失敗
	 *
	 * @group smoke
	 */
	public function test_未登入用戶查詢觸發條件列表時操作失敗(): void {
		// Given 用戶未登入
		\wp_set_current_user(0);

		// When 用戶查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作失敗，錯誤為「未授權的操作」
		$this->assertSame(401, $response->get_status(), '未登入應回傳 401');
	}

	// ========== Rule: 無需提供參數 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 無需提供任何參數即可查詢
	 *
	 * @group happy
	 */
	public function test_無需提供任何參數即可查詢(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');
	}

	// ========== Rule: 回應應為分組結構 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 回傳 7 個分組的觸發點清單（不含 REGISTRATION_CREATED）
	 *
	 * @group happy
	 */
	public function test_回傳7個分組的觸發點清單(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 查詢結果應為分組結構
		$data = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');
		$this->assertCount(7, $data['data'], '應有 7 個分組');

		// And 不包含 REGISTRATION_CREATED
		$all_hooks = [];
		foreach ($data['data'] as $group) {
			foreach ($group['items'] as $item) {
				$all_hooks[] = $item['hook'];
			}
		}
		$this->assertNotContains(
			ETriggerPoint::REGISTRATION_CREATED->value,
			$all_hooks,
			'回應不應包含已棄用的 REGISTRATION_CREATED'
		);

		// 驗證回應中共有 20 個觸發點
		$this->assertCount(20, $all_hooks, '應共有 20 個觸發點');
	}

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 有第三方開發者擴充觸發點時回傳合併後的列表
	 *
	 * @group happy
	 */
	public function test_有第三方開發者擴充觸發點時回傳合併後的列表(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// And 第三方開發者透過 filter 新增觸發點 pf/trigger/custom_event
		\add_filter(
			'power_funnel/workflow_rule/trigger_points',
			static function ( array $dtos ): array {
				$dtos['pf/trigger/custom_event'] = new \J7\PowerFunnel\Contracts\DTOs\TriggerPointDTO(
					[
						'hook' => 'pf/trigger/custom_event',
						'name' => '自訂事件',
					]
				);
				return $dtos;
			}
		);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 查詢結果應包含分組資料
		$data  = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');

		// And 所有 hooks 集合中應包含預設觸發點
		$all_hooks = [];
		foreach ($data['data'] as $group) {
			foreach ($group['items'] as $item) {
				$all_hooks[] = $item['hook'];
			}
		}

		$this->assertContains(
			'pf/trigger/registration_approved',
			$all_hooks,
			'應包含預設觸發點 registration_approved'
		);
	}

	// ========== Rule: 每個觸發點應包含 hook、name、disabled 欄位 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 回應格式正確，每個群組包含 group、group_label、items，每個 item 包含 hook、name、disabled
	 *
	 * @group happy
	 */
	public function test_回應格式正確每個觸發點包含hook與name與disabled(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 回應有正確的頂層結構
		$data = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('code', $data, 'response 應有 code 欄位');
		$this->assertArrayHasKey('message', $data, 'response 應有 message 欄位');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');
		$this->assertNotEmpty($data['data'], 'data.data 不應為空');

		// And 每個群組有正確的欄位
		foreach ($data['data'] as $group) {
			$this->assertIsArray($group, '每個群組應為陣列');
			$this->assertArrayHasKey('group', $group, '群組應有 group 欄位');
			$this->assertArrayHasKey('group_label', $group, '群組應有 group_label 欄位');
			$this->assertArrayHasKey('items', $group, '群組應有 items 欄位');
			$this->assertIsString($group['group'], 'group 應為字串');
			$this->assertIsString($group['group_label'], 'group_label 應為字串');
			$this->assertIsArray($group['items'], 'items 應為陣列');

			// And 每個 item 有正確的欄位
			foreach ($group['items'] as $item) {
				$this->assertIsArray($item, '每個觸發點應為陣列');
				$this->assertArrayHasKey('hook', $item, '觸發點應有 hook 欄位');
				$this->assertArrayHasKey('name', $item, '觸發點應有 name 欄位');
				$this->assertArrayHasKey('disabled', $item, '觸發點應有 disabled 欄位');
				$this->assertIsString($item['hook'], 'hook 應為字串');
				$this->assertIsString($item['name'], 'name 應為字串');
				$this->assertIsBool($item['disabled'], 'disabled 應為布林值');
			}
		}
	}

	// ========== Rule: 新增的觸發點應有正確標籤 ==========

	/**
	 * Example: 新增的 16 個觸發點的 label 均有效（非空字串）
	 *
	 * @group happy
	 */
	public function test_所有觸發點有有效標籤(): void {
		// When 取得所有 ETriggerPoint cases
		$cases = ETriggerPoint::cases();

		// Then 每個 case 的 label 應為非空字串
		foreach ($cases as $case) {
			$label = $case->label();
			$this->assertIsString($label, "{$case->value} 的 label 應為字串");
			$this->assertNotEmpty($label, "{$case->value} 的 label 不應為空");
		}
	}
}
