<?php

/**
 * 查詢觸發點可用 Context Keys 整合測試。
 *
 * 驗證系統能根據觸發點名稱回傳該觸發點可用的 context keys 清單。
 *
 * @group trigger-points
 * @group context-keys
 *
 * @see specs/line-trigger-expansion/features/trigger-point/query-line-expanded-trigger-points.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 觸發點 Context Keys 查詢測試
 *
 * Feature: 查詢觸發點可用 Context Keys
 * Feature: 查詢擴展後的 LINE 觸發點列表
 */
class TriggerPointContextKeysTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	// ========== Rule: 指定觸發點時應回傳該觸發點的可用 context keys ==========

	/**
	 * 查詢 LINE_POSTBACK_RECEIVED 觸發點的 context keys
	 *
	 * Feature: 查詢擴展後的 LINE 觸發點列表
	 * Example: 查詢 LINE_POSTBACK_RECEIVED 的 context keys 後回傳 4 個欄位
	 *
	 * @group happy
	 */
	public function test_查詢LINE_POSTBACK_RECEIVED的context_keys(): void {
		// When 管理員查詢觸發點 "pf/trigger/line_postback_received" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::LINE_POSTBACK_RECEIVED->value
		);

		// Then 回傳結果應包含 4 個 postback 相關 keys
		$expected_keys = [
			'line_user_id'    => 'LINE 用戶 ID',
			'event_type'      => '事件類型',
			'postback_data'   => 'Postback 原始資料',
			'postback_action' => 'Postback Action',
		];

		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertCount( 4, $context_keys, 'LINE_POSTBACK_RECEIVED 應有 4 個 context keys' );

		foreach ( $expected_keys as $key => $label ) {
			$found = false;
			foreach ( $context_keys as $context_key ) {
				if ( isset( $context_key['key'] ) && $context_key['key'] === $key ) {
					$found = true;
					$this->assertSame(
						$label,
						$context_key['label'],
						"key '{$key}' 的 label 應為「{$label}」"
					);
					break;
				}
			}
			$this->assertTrue( $found, "context keys 應包含 key: {$key}" );
		}
	}

	/**
	 * 查詢群組事件存根觸發點的 context keys 應回傳空陣列
	 *
	 * Feature: 查詢擴展後的 LINE 觸發點列表
	 * Example: 查詢 LINE_JOIN 的 context keys 後回傳空陣列
	 *
	 * @group happy
	 */
	public function test_查詢LINE_JOIN的context_keys回傳空陣列(): void {
		// When 管理員查詢觸發點 "pf/trigger/line_join" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::LINE_JOIN->value
		);

		// Then 查詢結果應為空陣列（群組事件存根無 context keys）
		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, 'LINE_JOIN 的 context keys 應為空陣列' );
	}

	/**
	 * 查詢 LINE_LEAVE 的 context keys 應回傳空陣列
	 *
	 * @group happy
	 */
	public function test_查詢LINE_LEAVE的context_keys回傳空陣列(): void {
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::LINE_LEAVE->value
		);

		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, 'LINE_LEAVE 的 context keys 應為空陣列' );
	}

	/**
	 * 查詢 LINE_MEMBER_JOINED 的 context keys 應回傳空陣列
	 *
	 * @group happy
	 */
	public function test_查詢LINE_MEMBER_JOINED的context_keys回傳空陣列(): void {
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::LINE_MEMBER_JOINED->value
		);

		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, 'LINE_MEMBER_JOINED 的 context keys 應為空陣列' );
	}

	/**
	 * 查詢 LINE_MEMBER_LEFT 的 context keys 應回傳空陣列
	 *
	 * @group happy
	 */
	public function test_查詢LINE_MEMBER_LEFT的context_keys回傳空陣列(): void {
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::LINE_MEMBER_LEFT->value
		);

		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, 'LINE_MEMBER_LEFT 的 context keys 應為空陣列' );
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
		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, '不存在的觸發點應回傳空陣列' );
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
		$this->assertIsArray( $context_keys, 'context keys 應為陣列' );
		$this->assertEmpty( $context_keys, '未提供觸發點名稱時應回傳空陣列' );
	}
}
