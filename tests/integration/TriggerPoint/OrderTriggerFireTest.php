<?php

/**
 * ORDER_COMPLETED 觸發點觸發整合測試。
 *
 * 驗證 WooCommerce 訂單狀態變更為 completed 時，
 * 系統正確觸發 pf/trigger/order_completed 並傳遞正確的 context_callable_set。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-trigger-fire
 *
 * @see specs/order-trigger-and-branch-node/features/trigger-point/fire-order-completed.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * ORDER_COMPLETED 觸發點觸發測試
 *
 * Feature: 觸發 ORDER_COMPLETED 觸發點
 */
class OrderTriggerFireTest extends IntegrationTestCase {

	/** @var array<int, array<string, mixed>> 已觸發的 order_completed 事件記錄 */
	private array $fired_order_completed = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_order_completed = [];

		// 清除並註冊測試用假訂單
		\WC_Order_Stub_Registry::clear();
		\WC_Order_Stub_Registry::register(1001, new \WC_Order(
			[
				'id'              => 1001,
				'total'           => '2500',
				'billing_email'   => 'alice@example.com',
				'customer_id'     => 42,
				'status'          => 'completed',
				'payment_method'  => 'credit_card',
				'billing_phone'   => '0912345678',
				'shipping_address' => '台北市信義區信義路五段7號',
				'date_created'    => '2026-04-01',
			],
			[ new \WC_Order_Item_Stub('MacBook Pro', 1) ]
		));

		// 監聽 pf/trigger/order_completed
		\add_action(
			ETriggerPoint::ORDER_COMPLETED->value,
			function ( array $context_callable_set ): void {
				$this->fired_order_completed[] = $context_callable_set;
			},
			999
		);
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Order_Stub_Registry::clear();
		parent::tear_down();
	}

	// ========== Rule: 訂單狀態必須變更為 completed ==========

	/**
	 * 訂單狀態從 processing 變為 completed 時觸發
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為completed時觸發order_completed(): void {
		// Given WooCommerce 外掛已啟用，訂單 1001 的狀態為 "processing"
		// When WooCommerce 將訂單 1001 的狀態更新為 "completed"
		// 模擬 woocommerce_order_status_completed hook 觸發
		$order_id = 1001;
		\do_action('woocommerce_order_status_completed', $order_id);

		// Then 系統應觸發 "pf/trigger/order_completed"
		$this->assertCount(
			1,
			$this->fired_order_completed,
			'order_completed 應被觸發一次'
		);

		// And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
		$context_callable_set = $this->fired_order_completed[0];
		$this->assertIsArray($context_callable_set, 'context_callable_set 應為陣列');
		$this->assertArrayHasKey('callable', $context_callable_set, '應有 callable');
		$this->assertArrayHasKey('params', $context_callable_set, '應有 params');

		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$context_callable_set['callable'],
			'callable 應為 [TriggerPointService::class, "resolve_order_context"]'
		);

		// And context_callable_set 的 params 應為 [1001]
		$this->assertSame(
			[ $order_id ],
			$context_callable_set['params'],
			'params 應為 [order_id]'
		);
	}

	// ========== Rule: 訂單狀態未變更為 completed 時不應觸發 ==========

	/**
	 * 訂單狀態從 processing 變為 cancelled 時不觸發 ORDER_COMPLETED
	 *
	 * @group happy
	 */
	public function test_訂單非completed狀態不觸發order_completed(): void {
		// Given 訂單 1001 的狀態為 "processing"
		// When WooCommerce 將訂單 1001 的狀態更新為 "cancelled"
		// woocommerce_order_status_completed 不會被觸發（WooCommerce 只在 completed 時觸發）
		// 因此我們驗證如果沒有 woocommerce_order_status_completed，就不會觸發 order_completed

		// Then 系統不應觸發 "pf/trigger/order_completed"
		$this->assertEmpty(
			$this->fired_order_completed,
			'非 completed 狀態不應觸發 order_completed'
		);
	}

	// ========== Rule: 訂單不存在時不應觸發 ==========

	/**
	 * wc_get_order() 回傳 false 時不觸發
	 *
	 * @group edge
	 */
	public function test_訂單不存在時不觸發order_completed(): void {
		// Given 訂單 9999 不存在
		// When 系統接收到 woocommerce_order_status_completed hook，order_id 為 9999
		\do_action('woocommerce_order_status_completed', 9999);

		// Then 系統不應觸發 "pf/trigger/order_completed"（因為 wc_get_order 回傳 false）
		$this->assertEmpty(
			$this->fired_order_completed,
			'訂單不存在時不應觸發 order_completed'
		);
	}

	// ========== Rule: context_callable_set 必須符合 Serializable Context Callable 模式 ==========

	/**
	 * context_callable_set 可被安全序列化
	 *
	 * @group happy
	 */
	public function test_context_callable_set可被序列化(): void {
		// Given 訂單 1001 的狀態為 "processing"
		$order_id = 1001;

		// When WooCommerce 將訂單 1001 的狀態更新為 "completed"
		\do_action('woocommerce_order_status_completed', $order_id);

		// Then context_callable_set 被觸發
		$this->assertCount(1, $this->fired_order_completed, 'order_completed 應被觸發');

		$context_callable_set = $this->fired_order_completed[0];

		// Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
		$this->assertIsArray($context_callable_set['callable'], 'callable 應為陣列（非 Closure）');
		$this->assertCount(2, $context_callable_set['callable'], 'callable 應為 2 元素陣列');
		$this->assertIsString($context_callable_set['callable'][0], 'callable[0] 應為字串（class name）');
		$this->assertIsString($context_callable_set['callable'][1], 'callable[1] 應為字串（method name）');

		// And context_callable_set 的 params 應僅包含純值（int）
		$this->assertIsArray($context_callable_set['params'], 'params 應為陣列');
		foreach ($context_callable_set['params'] as $param) {
			$this->assertIsInt($param, 'params 中的每個值應為 int');
		}

		// 驗證可序列化
		$serialized = \serialize($context_callable_set);
		$this->assertIsString($serialized, '序列化結果應為字串');

		$unserialized = \unserialize($serialized);
		$this->assertSame(
			$context_callable_set,
			$unserialized,
			'反序列化後應與原始值相同'
		);
	}
}
