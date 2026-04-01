<?php

/**
 * 查詢觸發點可用 Context Keys 整合測試。
 *
 * 驗證系統能根據觸發點名稱回傳該觸發點可用的 context keys 清單，
 * 包含 ORDER_COMPLETED 的 9 個 keys。
 *
 * @group trigger-points
 * @group order-trigger
 * @group context-keys
 *
 * @see specs/order-trigger-and-branch-node/features/trigger-point/query-trigger-point-context-keys.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 觸發點 Context Keys 查詢測試
 *
 * Feature: 查詢觸發點可用 Context Keys
 */
class TriggerPointContextKeysTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// Context keys 查詢功能可能透過 filter 或 service 方法提供
	}

	// ========== Rule: 指定觸發點時應回傳該觸發點的可用 context keys ==========

	/**
	 * 查詢 ORDER_COMPLETED 觸發點的 context keys
	 *
	 * @group happy
	 */
	public function test_查詢ORDER_COMPLETED的context_keys(): void {
		// When 管理員查詢觸發點 "pf/trigger/order_completed" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::ORDER_COMPLETED->value
		);

		// Then 回傳結果應包含 9 個 order 相關 keys
		$expected_keys = [
			'order_id'           => '訂單 ID',
			'order_total'        => '訂單金額',
			'billing_email'      => '帳單 Email',
			'customer_id'        => '客戶 ID',
			'line_items_summary' => '商品清單摘要',
			'shipping_address'   => '配送地址',
			'payment_method'     => '付款方式',
			'order_date'         => '訂單日期',
			'billing_phone'      => '帳單電話',
		];

		$this->assertIsArray($context_keys, 'context keys 應為陣列');

		foreach ($expected_keys as $key => $label) {
			$found = false;
			foreach ($context_keys as $context_key) {
				if (isset($context_key['key']) && $context_key['key'] === $key) {
					$found = true;
					$this->assertSame(
						$label,
						$context_key['label'],
						"key '{$key}' 的 label 應為「{$label}」"
					);
					break;
				}
			}
			$this->assertTrue($found, "context keys 應包含 key: {$key}");
		}
	}

	/**
	 * 查詢 REGISTRATION_APPROVED 觸發點的 context keys
	 *
	 * @group happy
	 */
	public function test_查詢REGISTRATION_APPROVED的context_keys(): void {
		// When 管理員查詢觸發點 "pf/trigger/registration_approved" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::REGISTRATION_APPROVED->value
		);

		// Then 回傳結果應包含報名相關 keys
		$expected_keys = [
			'registration_id',
			'identity_id',
			'identity_provider',
			'activity_id',
			'promo_link_id',
		];

		$this->assertIsArray($context_keys, 'context keys 應為陣列');

		foreach ($expected_keys as $key) {
			$found = false;
			foreach ($context_keys as $context_key) {
				if (isset($context_key['key']) && $context_key['key'] === $key) {
					$found = true;
					break;
				}
			}
			$this->assertTrue($found, "context keys 應包含 key: {$key}");
		}
	}

	// ========== Rule: 觸發點不存在時應回傳空陣列 ==========

	/**
	 * 查詢不存在的觸發點時回傳空陣列
	 *
	 * @group edge
	 */
	public function test_查詢不存在的觸發點時回傳空陣列(): void {
		// When 管理員查詢觸發點 "pf/trigger/nonexistent" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			'pf/trigger/nonexistent'
		);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context_keys, 'context keys 應為陣列');
		$this->assertEmpty($context_keys, '不存在的觸發點應回傳空陣列');
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * 未提供觸發點名稱時操作失敗
	 *
	 * @group edge
	 */
	public function test_未提供觸發點名稱時回傳空陣列(): void {
		// When 管理員查詢觸發點的 context keys，但未提供觸發點名稱
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			''
		);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context_keys, 'context keys 應為陣列');
		$this->assertEmpty($context_keys, '未提供觸發點名稱時應回傳空陣列');
	}
}
