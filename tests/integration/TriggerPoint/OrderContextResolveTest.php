<?php

/**
 * 訂單 Context 解析（延遲求值）整合測試。
 *
 * 驗證 TriggerPointService::resolve_order_context() 能正確從 WC_Order 取得訂單資料，
 * 並在訂單不存在時回傳安全預設值。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-context-resolve
 *
 * @see specs/order-trigger-and-branch-node/features/trigger-point/resolve-order-context.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 訂單 Context 解析測試
 *
 * Feature: 解析訂單 Context（延遲求值）
 */
class OrderContextResolveTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// resolve_order_context 是靜態方法，不需要特別的依賴注入
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();

		// 註冊測試用假訂單
		\WC_Order_Stub_Registry::clear();
		\WC_Order_Stub_Registry::register(1001, new \WC_Order(
			[
				'id'               => 1001,
				'total'            => '2500',
				'billing_email'    => 'alice@example.com',
				'customer_id'      => 42,
				'payment_method'   => 'credit_card',
				'billing_phone'    => '0912345678',
				'shipping_address' => '台北市信義區信義路五段7號',
				'date_created'     => '2026-04-01',
			],
			[ new \WC_Order_Item_Stub('MacBook Pro', 1) ]
		));
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Order_Stub_Registry::clear();
		parent::tear_down();
	}

	// ========== Rule: resolve_order_context 應回傳 9 個訂單關鍵欄位 ==========

	/**
	 * 訂單存在時回傳完整 context（9 個 keys）
	 *
	 * @group happy
	 */
	public function test_訂單存在時回傳完整context(): void {
		// Given WooCommerce 外掛已啟用（stub），訂單 1001 存在

		// When 系統呼叫 resolve_order_context(1001)
		$context = TriggerPointService::resolve_order_context(1001);

		// Then 回傳結果應包含 9 個 keys
		$expected_keys = [
			'order_id',
			'order_total',
			'billing_email',
			'customer_id',
			'line_items_summary',
			'shipping_address',
			'payment_method',
			'order_date',
			'billing_phone',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey(
				$key,
				$context,
				"context 應包含 key: {$key}"
			);
		}

		$this->assertCount(
			9,
			$context,
			'context 應恰好包含 9 個 keys'
		);
	}

	// ========== Rule: 訂單已刪除時應回傳安全預設值 ==========

	/**
	 * 訂單不存在時回傳空陣列
	 *
	 * @group edge
	 */
	public function test_訂單不存在時回傳空陣列(): void {
		// Given 訂單 9999 已被刪除
		// When 系統呼叫 resolve_order_context(9999)
		$context = TriggerPointService::resolve_order_context(9999);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, '訂單不存在時應回傳空陣列');
	}

	// ========== Rule: WaitNode 延遲後應取得最新訂單資料 ==========

	/**
	 * 訂單金額在 WaitNode 等待期間被修改後取得最新值
	 *
	 * 此測試驗證延遲求值機制：resolve_order_context 不快照資料，
	 * 而是每次呼叫時從 DB 讀取最新值。
	 *
	 * @group happy
	 */
	public function test_WaitNode延遲後取得最新訂單資料(): void {
		// Given 訂單 1001 的 order_total 為 "2500"
		$context_before = TriggerPointService::resolve_order_context(1001);
		$this->assertSame('2500', $context_before['order_total'], '初始金額應為 2500');

		// And WaitNode 等待後，訂單 1001 的 order_total 被修改為 "3000"
		\WC_Order_Stub_Registry::register(1001, new \WC_Order(
			[
				'id'               => 1001,
				'total'            => '3000',
				'billing_email'    => 'alice@example.com',
				'customer_id'      => 42,
				'payment_method'   => 'credit_card',
				'billing_phone'    => '0912345678',
				'shipping_address' => '台北市信義區信義路五段7號',
				'date_created'     => '2026-04-01',
			],
			[ new \WC_Order_Item_Stub('MacBook Pro', 1) ]
		));

		// When 系統呼叫 resolve_order_context(1001)（第二次）
		$context_after = TriggerPointService::resolve_order_context(1001);

		// Then 回傳結果的 order_total 應為 "3000"（延遲求值，非快照）
		$this->assertSame('3000', $context_after['order_total'], '延遲求值應取得最新金額 3000');
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * 缺少必要參數時操作失敗：orderId = 0
	 *
	 * @group edge
	 */
	public function test_orderId為0時回傳空陣列(): void {
		// When 系統呼叫 resolve_order_context(0)
		$context = TriggerPointService::resolve_order_context(0);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, 'orderId 為 0 時應回傳空陣列');
	}
}
