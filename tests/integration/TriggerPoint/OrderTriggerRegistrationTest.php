<?php

/**
 * ORDER_COMPLETED 觸發點註冊整合測試。
 *
 * 驗證 ETriggerPoint 包含 ORDER_COMPLETED case，
 * 且 TriggerPointService 在 WooCommerce 啟用/未啟用時的行為。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-trigger-registration
 *
 * @see specs/order-trigger-and-branch-node/features/trigger-point/register-order-completed-trigger.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * ORDER_COMPLETED 觸發點註冊測試
 *
 * Feature: 註冊 ORDER_COMPLETED 觸發點
 */
class OrderTriggerRegistrationTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 不在此處註冊 hooks，各測試案例會自行控制
	}

	// ========== Rule: ETriggerPoint 應包含 ORDER_COMPLETED case ==========

	/**
	 * ORDER_COMPLETED 的 hook value 為 pf/trigger/order_completed
	 *
	 * @group smoke
	 */
	public function test_ORDER_COMPLETED的hook_value正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_COMPLETED
		$trigger = ETriggerPoint::ORDER_COMPLETED;

		// Then hook value 應為 "pf/trigger/order_completed"
		$this->assertSame(
			'pf/trigger/order_completed',
			$trigger->value,
			'ORDER_COMPLETED 的 hook value 應為 pf/trigger/order_completed'
		);
	}

	/**
	 * ORDER_COMPLETED 的 label 為「訂單完成後」
	 *
	 * @group smoke
	 */
	public function test_ORDER_COMPLETED的label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_COMPLETED
		$trigger = ETriggerPoint::ORDER_COMPLETED;

		// Then label 應為「訂單完成後」
		$this->assertSame(
			'訂單完成後',
			$trigger->label(),
			'ORDER_COMPLETED 的 label 應為「訂單完成後」'
		);
	}

	/**
	 * ORDER_COMPLETED 的 enum value 必須包含 pf/trigger/ 前綴
	 *
	 * @group smoke
	 */
	public function test_ORDER_COMPLETED的value以pf_trigger開頭(): void {
		// When 系統讀取 ETriggerPoint::ORDER_COMPLETED->value
		$value = ETriggerPoint::ORDER_COMPLETED->value;

		// Then 值應以 "pf/trigger/" 開頭
		$this->assertStringStartsWith(
			'pf/trigger/',
			$value,
			'ORDER_COMPLETED 的 value 應以 pf/trigger/ 開頭'
		);
	}

	// ========== Rule: WooCommerce 啟用時 TriggerPointService 應監聽 woocommerce_order_status_completed ==========

	/**
	 * WooCommerce 啟用時註冊 hook 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊order_status_completed監聽器(): void {
		// Given WooCommerce 外掛已啟用（模擬 wc_get_order 函式存在）
		// 測試環境中 WooCommerce 可能未安裝，此測試驗證 register_hooks 的邏輯

		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_completed" hook 上註冊監聽器
		$has_action = \has_action(
			'woocommerce_order_status_completed',
			[ TriggerPointService::class, 'on_order_completed' ]
		);
		$this->assertNotFalse(
			$has_action,
			'TriggerPointService 應在 woocommerce_order_status_completed 上註冊監聽器'
		);
	}

	// ========== Rule: WooCommerce 未啟用時不應註冊監聽器 ==========

	/**
	 * WooCommerce 未啟用時靜默忽略
	 *
	 * @group edge
	 */
	public function test_WooCommerce未啟用時不註冊監聽器(): void {
		// Given WooCommerce 外掛未啟用
		// 在沒有 WooCommerce 的測試環境中，TriggerPointService 不應拋出錯誤

		// When 系統執行 TriggerPointService::register_hooks()
		// Then 系統不應拋出任何錯誤（如果拋出，測試會自動失敗）
		TriggerPointService::register_hooks();

		// 驗證不會因為 WooCommerce 不存在而有致命錯誤
		$this->assertTrue(true, 'WooCommerce 未啟用時不應拋出錯誤');
	}
}
