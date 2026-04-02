<?php
/**
 * 分組觸發條件列表查詢整合測試。
 *
 * 驗證 Repository::get_trigger_points() 和 GET /wp-json/power-funnel/trigger-points
 * 回傳分組結構的行為。
 *
 * @group smoke
 * @group workflow-rule
 * @group trigger-points
 * @group trigger-point-grouping
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\WorkflowRule;

use J7\PowerFunnel\Applications\TriggerPointApi;
use J7\PowerFunnel\Contracts\DTOs\TriggerPointGroupDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 分組觸發條件列表查詢測試
 *
 * Feature: 查詢分組觸發點清單
 */
class QueryGroupedTriggerPointsTest extends IntegrationTestCase {

	/** @var \WP_REST_Server REST 伺服器實例 */
	protected \WP_REST_Server $server;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointApi::register_hooks();
	}

	/** 每個測試前設置 REST Server */
	public function set_up(): void {
		parent::set_up();
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

	// ========== Repository::get_trigger_points() 測試 ==========

	/**
	 * Example: get_trigger_points() 回傳 TriggerPointGroupDTO 陣列
	 *
	 * @group happy
	 */
	public function test_get_trigger_points_回傳TriggerPointGroupDTO陣列(): void {
		$groups = Repository::get_trigger_points();

		$this->assertIsArray($groups, 'get_trigger_points() 應回傳陣列');
		$this->assertNotEmpty($groups, '回傳陣列不應為空');

		foreach ($groups as $group) {
			$this->assertInstanceOf(
				TriggerPointGroupDTO::class,
				$group,
				'每個元素應為 TriggerPointGroupDTO 實例'
			);
		}
	}

	/**
	 * Example: get_trigger_points() 回傳 7 個分組，固定順序
	 *
	 * @group happy
	 */
	public function test_get_trigger_points_回傳7個分組且順序固定(): void {
		$groups = Repository::get_trigger_points();

		$this->assertCount(7, $groups, '應有 7 個分組');

		$expected_order = [
			'registration',
			'line_interaction',
			'line_group',
			'workflow',
			'activity',
			'user_behavior',
			'woocommerce',
		];

		$actual_order = \array_map(fn( TriggerPointGroupDTO $g ) => $g->group, $groups);

		$this->assertSame(
			$expected_order,
			$actual_order,
			'分組順序應為固定順序'
		);
	}

	/**
	 * Example: get_trigger_points() 總共包含 20 個項目
	 *
	 * @group happy
	 */
	public function test_get_trigger_points_總共包含20個項目(): void {
		$groups = Repository::get_trigger_points();

		$total_items = 0;
		foreach ($groups as $group) {
			$total_items += \count($group->items);
		}

		$this->assertSame(20, $total_items, '總項目數應為 20（所有 case 減去 REGISTRATION_CREATED）');
	}

	/**
	 * Example: get_trigger_points() 不包含 REGISTRATION_CREATED
	 *
	 * @group happy
	 */
	public function test_get_trigger_points_不包含REGISTRATION_CREATED(): void {
		$groups = Repository::get_trigger_points();

		$all_hooks = [];
		foreach ($groups as $group) {
			foreach ($group->items as $item) {
				$all_hooks[] = $item->hook;
			}
		}

		$this->assertNotContains(
			ETriggerPoint::REGISTRATION_CREATED->value,
			$all_hooks,
			'回傳結果不應包含已棄用的 REGISTRATION_CREATED'
		);
	}

	/**
	 * Example: 分組的 group_label 正確
	 *
	 * @group happy
	 */
	public function test_分組的group_label正確(): void {
		$groups = Repository::get_trigger_points();

		$expected_labels = [
			'registration'    => '報名狀態',
			'line_interaction' => 'LINE 互動',
			'line_group'      => 'LINE 群組',
			'workflow'        => '工作流引擎',
			'activity'        => '活動時間',
			'user_behavior'   => '用戶行為',
			'woocommerce'     => 'WooCommerce',
		];

		foreach ($groups as $group) {
			$expected_label = $expected_labels[ $group->group ] ?? null;
			$this->assertNotNull($expected_label, "群組 '{$group->group}' 應有對應的 group_label");
			$this->assertSame(
				$expected_label,
				$group->group_label,
				"群組 '{$group->group}' 的 group_label 應為 '{$expected_label}'"
			);
		}
	}

	/**
	 * Example: 各分組的 items_count 正確
	 *
	 * @group happy
	 */
	public function test_各分組的items_count正確(): void {
		$groups = Repository::get_trigger_points();

		// 轉為 group => count 的 map 以便斷言
		$group_counts = [];
		foreach ($groups as $group) {
			$group_counts[ $group->group ] = \count($group->items);
		}

		$expected_counts = [
			'registration'    => 4,
			'line_interaction' => 4,
			'line_group'      => 4,
			'workflow'        => 2,
			'activity'        => 3,
			'user_behavior'   => 2,
			'woocommerce'     => 1,
		];

		foreach ($expected_counts as $group_key => $expected_count) {
			$this->assertSame(
				$expected_count,
				$group_counts[ $group_key ] ?? 0,
				"群組 '{$group_key}' 應有 {$expected_count} 個項目"
			);
		}
	}

	/**
	 * Example: 存根觸發點的 disabled = true 且 name 帶有「（即將推出）」後綴
	 *
	 * @group happy
	 */
	public function test_存根觸發點的disabled為true且name帶有即將推出後綴(): void {
		$groups = Repository::get_trigger_points();

		$stub_hooks = [
			ETriggerPoint::LINE_JOIN->value,
			ETriggerPoint::LINE_LEAVE->value,
			ETriggerPoint::LINE_MEMBER_JOINED->value,
			ETriggerPoint::LINE_MEMBER_LEFT->value,
			ETriggerPoint::ACTIVITY_ENDED->value,
			ETriggerPoint::PROMO_LINK_CLICKED->value,
		];

		$all_items = [];
		foreach ($groups as $group) {
			foreach ($group->items as $item) {
				$all_items[ $item->hook ] = $item;
			}
		}

		foreach ($stub_hooks as $stub_hook) {
			$this->assertArrayHasKey($stub_hook, $all_items, "存根 '{$stub_hook}' 應存在於結果中");
			$item = $all_items[ $stub_hook ];
			$this->assertTrue($item->disabled, "存根 '{$stub_hook}' 的 disabled 應為 true");
			$this->assertStringEndsWith(
				'（即將推出）',
				$item->name,
				"存根 '{$stub_hook}' 的 name 應以「（即將推出）」結尾，實際為 '{$item->name}'"
			);
		}
	}

	/**
	 * Example: 非存根觸發點的 disabled = false
	 *
	 * @group happy
	 */
	public function test_非存根觸發點的disabled為false(): void {
		$groups = Repository::get_trigger_points();

		$non_stub_hooks = [
			ETriggerPoint::REGISTRATION_APPROVED->value,
			ETriggerPoint::REGISTRATION_REJECTED->value,
			ETriggerPoint::REGISTRATION_CANCELLED->value,
			ETriggerPoint::REGISTRATION_FAILED->value,
			ETriggerPoint::LINE_FOLLOWED->value,
			ETriggerPoint::LINE_UNFOLLOWED->value,
			ETriggerPoint::LINE_MESSAGE_RECEIVED->value,
			ETriggerPoint::LINE_POSTBACK_RECEIVED->value,
			ETriggerPoint::WORKFLOW_COMPLETED->value,
			ETriggerPoint::WORKFLOW_FAILED->value,
			ETriggerPoint::ACTIVITY_STARTED->value,
			ETriggerPoint::ACTIVITY_BEFORE_START->value,
			ETriggerPoint::USER_TAGGED->value,
			ETriggerPoint::ORDER_COMPLETED->value,
		];

		$all_items = [];
		foreach ($groups as $group) {
			foreach ($group->items as $item) {
				$all_items[ $item->hook ] = $item;
			}
		}

		foreach ($non_stub_hooks as $hook) {
			$this->assertArrayHasKey($hook, $all_items, "觸發點 '{$hook}' 應存在於結果中");
			$item = $all_items[ $hook ];
			$this->assertFalse($item->disabled, "觸發點 '{$hook}' 的 disabled 應為 false");
		}
	}

	// ========== TriggerPointGroupDTO::to_array() 測試 ==========

	/**
	 * Example: TriggerPointGroupDTO::to_array() 產生正確的巢狀結構
	 *
	 * @group happy
	 */
	public function test_TriggerPointGroupDTO_to_array_產生正確巢狀結構(): void {
		$groups = Repository::get_trigger_points();
		$first_group = $groups[0];

		$array = $first_group->to_array();

		$this->assertIsArray($array, 'to_array() 應回傳陣列');
		$this->assertArrayHasKey('group', $array, '應有 group 欄位');
		$this->assertArrayHasKey('group_label', $array, '應有 group_label 欄位');
		$this->assertArrayHasKey('items', $array, '應有 items 欄位');
		$this->assertIsArray($array['items'], 'items 應為陣列');
		$this->assertNotEmpty($array['items'], 'items 不應為空');

		// 驗證 items 中的每個元素有正確的欄位
		foreach ($array['items'] as $item) {
			$this->assertIsArray($item, 'item 應為陣列');
			$this->assertArrayHasKey('hook', $item, 'item 應有 hook 欄位');
			$this->assertArrayHasKey('name', $item, 'item 應有 name 欄位');
			$this->assertArrayHasKey('disabled', $item, 'item 應有 disabled 欄位');
			$this->assertIsString($item['hook'], 'hook 應為字串');
			$this->assertIsString($item['name'], 'name 應為字串');
			$this->assertIsBool($item['disabled'], 'disabled 應為布林值');
		}
	}

	// ========== API 回應測試 ==========

	/**
	 * Example: API 回傳分組結構（7 個群組）
	 *
	 * @group happy
	 */
	public function test_API回傳分組結構(): void {
		$admin_id = $this->factory()->user->create([ 'role' => 'administrator' ]);
		\wp_set_current_user($admin_id);

		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		$this->assertSame(200, $response->get_status(), '應回傳 200');

		$data = $response->get_data();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('data', $data);
		$this->assertIsArray($data['data']);
		$this->assertCount(7, $data['data'], 'API 應回傳 7 個群組');

		foreach ($data['data'] as $group) {
			$this->assertIsArray($group, '每個群組應為陣列');
			$this->assertArrayHasKey('group', $group, '群組應有 group 欄位');
			$this->assertArrayHasKey('group_label', $group, '群組應有 group_label 欄位');
			$this->assertArrayHasKey('items', $group, '群組應有 items 欄位');
			$this->assertIsArray($group['items'], 'items 應為陣列');
		}
	}

	/**
	 * Example: API 回應不包含 REGISTRATION_CREATED
	 *
	 * @group happy
	 */
	public function test_API回應不包含REGISTRATION_CREATED(): void {
		$admin_id = $this->factory()->user->create([ 'role' => 'administrator' ]);
		\wp_set_current_user($admin_id);

		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		$this->assertSame(200, $response->get_status());

		$data = $response->get_data();
		$all_hooks = [];
		foreach ($data['data'] as $group) {
			foreach ($group['items'] as $item) {
				$all_hooks[] = $item['hook'];
			}
		}

		$this->assertNotContains(
			ETriggerPoint::REGISTRATION_CREATED->value,
			$all_hooks,
			'API 回應不應包含 REGISTRATION_CREATED'
		);
	}

	/**
	 * Example: API 回應分組順序固定
	 *
	 * @group happy
	 */
	public function test_API回應分組順序固定(): void {
		$admin_id = $this->factory()->user->create([ 'role' => 'administrator' ]);
		\wp_set_current_user($admin_id);

		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		$this->assertSame(200, $response->get_status());

		$data = $response->get_data();
		$actual_order = \array_column($data['data'], 'group');

		$expected_order = [
			'registration',
			'line_interaction',
			'line_group',
			'workflow',
			'activity',
			'user_behavior',
			'woocommerce',
		];

		$this->assertSame(
			$expected_order,
			$actual_order,
			'API 回應的分組順序應固定'
		);
	}
}
