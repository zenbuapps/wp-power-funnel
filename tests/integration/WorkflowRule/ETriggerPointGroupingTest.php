<?php
/**
 * ETriggerPoint 分組方法測試。
 *
 * 驗證 ETriggerPoint enum 新增的 group()、group_label()、is_stub() 方法行為。
 *
 * @group smoke
 * @group workflow-rule
 * @group trigger-points
 * @group trigger-point-grouping
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\WorkflowRule;

use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * ETriggerPoint 分組測試
 *
 * Feature: ETriggerPoint enum 分組相關方法
 */
class ETriggerPointGroupingTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {}

	// ========== group() 方法測試 ==========

	/**
	 * Example: REGISTRATION_* 觸發點的 group() 應回傳 'registration'
	 *
	 * @group happy
	 */
	public function test_registration_觸發點的group回傳registration(): void {
		$registration_cases = [
			ETriggerPoint::REGISTRATION_CREATED,
			ETriggerPoint::REGISTRATION_APPROVED,
			ETriggerPoint::REGISTRATION_REJECTED,
			ETriggerPoint::REGISTRATION_CANCELLED,
			ETriggerPoint::REGISTRATION_FAILED,
		];

		foreach ($registration_cases as $case) {
			$this->assertSame(
				'registration',
				$case->group(),
				"{$case->value} 的 group() 應為 'registration'"
			);
		}
	}

	/**
	 * Example: LINE 互動觸發點的 group() 應回傳 'line_interaction'
	 *
	 * @group happy
	 */
	public function test_line_互動觸發點的group回傳line_interaction(): void {
		$line_interaction_cases = [
			ETriggerPoint::LINE_FOLLOWED,
			ETriggerPoint::LINE_UNFOLLOWED,
			ETriggerPoint::LINE_MESSAGE_RECEIVED,
			ETriggerPoint::LINE_POSTBACK_RECEIVED,
		];

		foreach ($line_interaction_cases as $case) {
			$this->assertSame(
				'line_interaction',
				$case->group(),
				"{$case->value} 的 group() 應為 'line_interaction'"
			);
		}
	}

	/**
	 * Example: LINE 群組觸發點的 group() 應回傳 'line_group'
	 *
	 * @group happy
	 */
	public function test_line_群組觸發點的group回傳line_group(): void {
		$line_group_cases = [
			ETriggerPoint::LINE_JOIN,
			ETriggerPoint::LINE_LEAVE,
			ETriggerPoint::LINE_MEMBER_JOINED,
			ETriggerPoint::LINE_MEMBER_LEFT,
		];

		foreach ($line_group_cases as $case) {
			$this->assertSame(
				'line_group',
				$case->group(),
				"{$case->value} 的 group() 應為 'line_group'"
			);
		}
	}

	/**
	 * Example: WORKFLOW_* 觸發點的 group() 應回傳 'workflow'
	 *
	 * @group happy
	 */
	public function test_workflow_觸發點的group回傳workflow(): void {
		$workflow_cases = [
			ETriggerPoint::WORKFLOW_COMPLETED,
			ETriggerPoint::WORKFLOW_FAILED,
		];

		foreach ($workflow_cases as $case) {
			$this->assertSame(
				'workflow',
				$case->group(),
				"{$case->value} 的 group() 應為 'workflow'"
			);
		}
	}

	/**
	 * Example: ACTIVITY_* 觸發點的 group() 應回傳 'activity'
	 *
	 * @group happy
	 */
	public function test_activity_觸發點的group回傳activity(): void {
		$activity_cases = [
			ETriggerPoint::ACTIVITY_STARTED,
			ETriggerPoint::ACTIVITY_BEFORE_START,
			ETriggerPoint::ACTIVITY_ENDED,
		];

		foreach ($activity_cases as $case) {
			$this->assertSame(
				'activity',
				$case->group(),
				"{$case->value} 的 group() 應為 'activity'"
			);
		}
	}

	/**
	 * Example: 用戶行為觸發點的 group() 應回傳 'user_behavior'
	 *
	 * @group happy
	 */
	public function test_用戶行為觸發點的group回傳user_behavior(): void {
		$user_behavior_cases = [
			ETriggerPoint::USER_TAGGED,
			ETriggerPoint::PROMO_LINK_CLICKED,
		];

		foreach ($user_behavior_cases as $case) {
			$this->assertSame(
				'user_behavior',
				$case->group(),
				"{$case->value} 的 group() 應為 'user_behavior'"
			);
		}
	}

	/**
	 * Example: ORDER_COMPLETED 觸發點的 group() 應回傳 'woocommerce'
	 *
	 * @group happy
	 */
	public function test_order_completed_觸發點的group回傳woocommerce(): void {
		$this->assertSame(
			'woocommerce',
			ETriggerPoint::ORDER_COMPLETED->group(),
			"ORDER_COMPLETED 的 group() 應為 'woocommerce'"
		);
	}

	/**
	 * Example: 所有 21 個 case 均有有效的 group 值
	 *
	 * @group happy
	 */
	public function test_所有case均有有效group值(): void {
		$valid_groups = [
			'registration',
			'line_interaction',
			'line_group',
			'workflow',
			'activity',
			'user_behavior',
			'woocommerce',
		];

		$cases = ETriggerPoint::cases();
		$this->assertCount(21, $cases, 'ETriggerPoint 應有 21 個 case');

		foreach ($cases as $case) {
			$group = $case->group();
			$this->assertIsString($group, "{$case->value} 的 group() 應為字串");
			$this->assertContains(
				$group,
				$valid_groups,
				"{$case->value} 的 group() 應為有效群組名稱，實際為 '{$group}'"
			);
		}
	}

	// ========== group_label() 方法測試 ==========

	/**
	 * Example: group_label() 回傳正確的中文標籤
	 *
	 * @group happy
	 */
	public function test_group_label_回傳正確中文標籤(): void {
		$expected_labels = [
			'registration'    => '報名狀態',
			'line_interaction' => 'LINE 互動',
			'line_group'      => 'LINE 群組',
			'workflow'        => '工作流引擎',
			'activity'        => '活動時間',
			'user_behavior'   => '用戶行為',
			'woocommerce'     => 'WooCommerce',
		];

		// 每個群組至少測試一個代表 case
		$representative_cases = [
			ETriggerPoint::REGISTRATION_APPROVED,
			ETriggerPoint::LINE_FOLLOWED,
			ETriggerPoint::LINE_JOIN,
			ETriggerPoint::WORKFLOW_COMPLETED,
			ETriggerPoint::ACTIVITY_STARTED,
			ETriggerPoint::USER_TAGGED,
			ETriggerPoint::ORDER_COMPLETED,
		];

		foreach ($representative_cases as $case) {
			$group       = $case->group();
			$group_label = $case->group_label();
			$expected    = $expected_labels[ $group ];

			$this->assertSame(
				$expected,
				$group_label,
				"{$case->value} 的 group_label() 應為 '{$expected}'，實際為 '{$group_label}'"
			);
		}
	}

	/**
	 * Example: 所有 case 的 group_label() 均為非空字串
	 *
	 * @group happy
	 */
	public function test_所有case的group_label均為非空字串(): void {
		foreach (ETriggerPoint::cases() as $case) {
			$label = $case->group_label();
			$this->assertIsString($label, "{$case->value} 的 group_label() 應為字串");
			$this->assertNotEmpty($label, "{$case->value} 的 group_label() 不應為空");
		}
	}

	// ========== is_stub() 方法測試 ==========

	/**
	 * Example: 6 個枚舉存根的 is_stub() 應回傳 true
	 *
	 * @group happy
	 */
	public function test_6個枚舉存根的is_stub回傳true(): void {
		$stub_cases = [
			ETriggerPoint::LINE_JOIN,
			ETriggerPoint::LINE_LEAVE,
			ETriggerPoint::LINE_MEMBER_JOINED,
			ETriggerPoint::LINE_MEMBER_LEFT,
			ETriggerPoint::ACTIVITY_ENDED,
			ETriggerPoint::PROMO_LINK_CLICKED,
		];

		$this->assertCount(6, $stub_cases, '應有 6 個枚舉存根');

		foreach ($stub_cases as $case) {
			$this->assertTrue(
				$case->is_stub(),
				"{$case->value} 的 is_stub() 應回傳 true"
			);
		}
	}

	/**
	 * Example: 非存根觸發點的 is_stub() 應回傳 false
	 *
	 * @group happy
	 */
	public function test_非存根觸發點的is_stub回傳false(): void {
		$non_stub_cases = [
			ETriggerPoint::REGISTRATION_CREATED,
			ETriggerPoint::REGISTRATION_APPROVED,
			ETriggerPoint::REGISTRATION_REJECTED,
			ETriggerPoint::REGISTRATION_CANCELLED,
			ETriggerPoint::REGISTRATION_FAILED,
			ETriggerPoint::LINE_FOLLOWED,
			ETriggerPoint::LINE_UNFOLLOWED,
			ETriggerPoint::LINE_MESSAGE_RECEIVED,
			ETriggerPoint::LINE_POSTBACK_RECEIVED,
			ETriggerPoint::WORKFLOW_COMPLETED,
			ETriggerPoint::WORKFLOW_FAILED,
			ETriggerPoint::ACTIVITY_STARTED,
			ETriggerPoint::ACTIVITY_BEFORE_START,
			ETriggerPoint::USER_TAGGED,
			ETriggerPoint::ORDER_COMPLETED,
		];

		$this->assertCount(15, $non_stub_cases, '應有 15 個非存根觸發點');

		foreach ($non_stub_cases as $case) {
			$this->assertFalse(
				$case->is_stub(),
				"{$case->value} 的 is_stub() 應回傳 false"
			);
		}
	}
}
