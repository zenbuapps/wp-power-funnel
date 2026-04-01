<?php

/**
 * LINE 群組事件枚舉存根觸發點測試。
 *
 * 驗證 ETriggerPoint enum 包含 4 個群組事件存根 case，
 * 並確認 TriggerPointService 不監聽群組 hooks。
 *
 * @group trigger-points
 * @group line-trigger
 * @group line-group-stub
 *
 * @see specs/line-trigger-expansion/features/trigger-point/register-line-group-stub-trigger-points.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * LINE 群組事件枚舉存根觸發點測試
 *
 * Feature: 註冊 LINE 群組事件枚舉存根觸發點
 */
class LineGroupStubTriggerTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	// ========== Rule: ETriggerPoint enum 包含群組事件存根 ==========

	/**
	 * Feature: 所有 4 個群組事件存根的 enum values 格式正確
	 * Example: ETriggerPoint enum 新增 LINE_JOIN 後可正確取得 hook 值和標籤
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint包含LINE_JOIN(): void {
		// When 系統讀取 ETriggerPoint::LINE_JOIN
		$trigger_point = ETriggerPoint::LINE_JOIN;

		// Then 該 enum case 的值應為 "pf/trigger/line_join"
		$this->assertSame( 'pf/trigger/line_join', $trigger_point->value, 'LINE_JOIN hook 值應正確' );

		// And 該 enum case 的 label 應為 "Bot 被加入群組後"
		$this->assertSame( 'Bot 被加入群組後', $trigger_point->label(), 'LINE_JOIN label 應正確' );
	}

	/**
	 * Example: ETriggerPoint enum 新增 LINE_LEAVE 後可正確取得 hook 值和標籤
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint包含LINE_LEAVE(): void {
		// When 系統讀取 ETriggerPoint::LINE_LEAVE
		$trigger_point = ETriggerPoint::LINE_LEAVE;

		// Then 該 enum case 的值應為 "pf/trigger/line_leave"
		$this->assertSame( 'pf/trigger/line_leave', $trigger_point->value, 'LINE_LEAVE hook 值應正確' );

		// And 該 enum case 的 label 應為 "Bot 被移出群組後"
		$this->assertSame( 'Bot 被移出群組後', $trigger_point->label(), 'LINE_LEAVE label 應正確' );
	}

	/**
	 * Example: ETriggerPoint enum 新增 LINE_MEMBER_JOINED 後可正確取得 hook 值和標籤
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint包含LINE_MEMBER_JOINED(): void {
		// When 系統讀取 ETriggerPoint::LINE_MEMBER_JOINED
		$trigger_point = ETriggerPoint::LINE_MEMBER_JOINED;

		// Then 該 enum case 的值應為 "pf/trigger/line_member_joined"
		$this->assertSame( 'pf/trigger/line_member_joined', $trigger_point->value, 'LINE_MEMBER_JOINED hook 值應正確' );

		// And 該 enum case 的 label 應為 "新成員加入群組後"
		$this->assertSame( '新成員加入群組後', $trigger_point->label(), 'LINE_MEMBER_JOINED label 應正確' );
	}

	/**
	 * Example: ETriggerPoint enum 新增 LINE_MEMBER_LEFT 後可正確取得 hook 值和標籤
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint包含LINE_MEMBER_LEFT(): void {
		// When 系統讀取 ETriggerPoint::LINE_MEMBER_LEFT
		$trigger_point = ETriggerPoint::LINE_MEMBER_LEFT;

		// Then 該 enum case 的值應為 "pf/trigger/line_member_left"
		$this->assertSame( 'pf/trigger/line_member_left', $trigger_point->value, 'LINE_MEMBER_LEFT hook 值應正確' );

		// And 該 enum case 的 label 應為 "成員離開群組後"
		$this->assertSame( '成員離開群組後', $trigger_point->label(), 'LINE_MEMBER_LEFT label 應正確' );
	}

	/**
	 * Feature: 所有 4 個群組事件存根的 enum values 格式正確
	 * Example: 系統列舉所有 ETriggerPoint cases 應包含 4 個群組事件存根
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint枚舉包含所有4個群組事件存根(): void {
		// When 系統列舉所有 ETriggerPoint cases
		$all_values = array_map( fn( ETriggerPoint $case ) => $case->value, ETriggerPoint::cases() );

		// Then 結果應包含以下觸發點
		$expected_cases = [
			'pf/trigger/line_join',
			'pf/trigger/line_leave',
			'pf/trigger/line_member_joined',
			'pf/trigger/line_member_left',
		];

		foreach ( $expected_cases as $expected_value ) {
			$this->assertContains( $expected_value, $all_values, "ETriggerPoint 應包含 {$expected_value}" );
		}
	}

	// ========== Rule: TriggerPointService 不監聽群組 hooks ==========

	/**
	 * Feature: TriggerPointService 不監聽 join/leave/memberJoined/memberLeft webhook
	 * Example: WebhookService dispatch "power_funnel/line/webhook/join" 時不觸發 pf/trigger/
	 *
	 * @group edge
	 */
	public function test_TriggerPointService不監聽join事件(): void {
		// Given TriggerPointService 已呼叫 register_hooks
		// (已在 configure_dependencies 中完成)

		// 監聽所有 pf/trigger/ hook 以確認沒有被觸發
		$pf_triggers_fired = [];
		foreach ( ETriggerPoint::cases() as $trigger_point ) {
			$hook = $trigger_point->value;
			\add_action( $hook, function () use ( $hook, &$pf_triggers_fired ): void {
				$pf_triggers_fired[] = $hook;
			}, 999 );
		}

		// When WebhookService dispatch "power_funnel/line/webhook/join" 事件
		\do_action( 'power_funnel/line/webhook/join', new \stdClass() );

		// Then TriggerPointService 不應觸發任何 pf/trigger/ hook
		$line_group_hooks = [
			'pf/trigger/line_join',
			'pf/trigger/line_leave',
			'pf/trigger/line_member_joined',
			'pf/trigger/line_member_left',
		];

		foreach ( $line_group_hooks as $group_hook ) {
			$this->assertNotContains( $group_hook, $pf_triggers_fired, "TriggerPointService 不應觸發 {$group_hook}" );
		}
	}

	/**
	 * Example: 群組事件存根觸發點應回傳空的 context keys
	 *
	 * @group happy
	 */
	public function test_群組事件存根的context_keys為空(): void {
		// When 管理員查詢群組事件存根的 context keys
		$group_hooks = [
			ETriggerPoint::LINE_JOIN->value,
			ETriggerPoint::LINE_LEAVE->value,
			ETriggerPoint::LINE_MEMBER_JOINED->value,
			ETriggerPoint::LINE_MEMBER_LEFT->value,
		];

		foreach ( $group_hooks as $hook ) {
			$context_keys = \apply_filters( 'power_funnel/trigger_point/context_keys', [], $hook );

			// Then 查詢結果應為空陣列
			$this->assertIsArray( $context_keys, "{$hook} 的 context keys 應為陣列" );
			$this->assertEmpty( $context_keys, "{$hook} 的 context keys 應為空陣列（枚舉存根）" );
		}
	}
}
