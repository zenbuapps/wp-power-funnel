<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\Workflow\Services;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository as WorkflowRuleRepository;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;

/**
 * 活動排程觸發點服務
 *
 * 負責整合 Action Scheduler 以支援時間型觸發點：
 * - ACTIVITY_STARTED：活動開始時觸發
 * - ACTIVITY_BEFORE_START：活動開始前 N 分鐘觸發（可在 WorkflowRule 參數中設定）
 */
final class ActivitySchedulerService {

	/** @var string Action Scheduler hook：活動開始觸發 */
	public const HOOK_ACTIVITY_STARTED = 'power_funnel/activity_trigger/started';

	/** @var string Action Scheduler hook：活動開始前觸發 */
	public const HOOK_ACTIVITY_BEFORE_START = 'power_funnel/activity_trigger/before_start';

	/** @var int 預設的活動開始前分鐘數 */
	public const DEFAULT_BEFORE_MINUTES = 30;

	/**
	 * 註冊 hooks
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		// 監聽 Action Scheduler 的觸發
		\add_action(self::HOOK_ACTIVITY_STARTED, [ __CLASS__, 'on_activity_started' ], 10, 1);
		\add_action(self::HOOK_ACTIVITY_BEFORE_START, [ __CLASS__, 'on_activity_before_start' ], 10, 2);
	}

	/**
	 * 為指定活動排程觸發點
	 *
	 * 當活動資料同步時（如從 YouTube 同步）呼叫此方法。
	 * 若活動已有排程，先取消再重新排程（處理時間更新的情況）。
	 *
	 * @param ActivityDTO $activity 活動 DTO
	 * @return void
	 */
	public static function schedule_activity( ActivityDTO $activity ): void {
		$activity_id     = $activity->id;
		$start_time      = $activity->scheduled_start_time;
		$start_timestamp = $start_time->getTimestamp();

		if ($start_timestamp <= 0) {
			Plugin::logger("ActivitySchedulerService：活動 {$activity_id} 無有效的開始時間，跳過排程", 'info');
			return;
		}

		// 取消舊有的 ACTIVITY_STARTED 排程
		if (\function_exists('as_unschedule_all_actions')) {
			\as_unschedule_all_actions(self::HOOK_ACTIVITY_STARTED, [ $activity_id ], 'power_funnel');
			\as_unschedule_all_actions(self::HOOK_ACTIVITY_BEFORE_START, [ $activity_id ], 'power_funnel');
		}

		// 排程 ACTIVITY_STARTED
		$scheduled_id = \as_schedule_single_action(
			$start_timestamp,
			self::HOOK_ACTIVITY_STARTED,
			[ $activity_id ],
			'power_funnel',
			true
		);

		if ($scheduled_id === 0) {
			Plugin::logger("ActivitySchedulerService：活動 {$activity_id} 排程 ACTIVITY_STARTED 失敗", 'error');
		}

		// 為每個有 activity_before_start 觸發點的 WorkflowRule 排程 ACTIVITY_BEFORE_START
		self::schedule_before_start_for_activity($activity_id, $start_timestamp);
	}

	/**
	 * 為所有設定了 ACTIVITY_BEFORE_START 觸發點的 WorkflowRule 排程
	 *
	 * @param string $activity_id      活動 ID
	 * @param int    $start_timestamp  活動開始 Unix 時間戳記
	 * @return void
	 */
	private static function schedule_before_start_for_activity( string $activity_id, int $start_timestamp ): void {
		$rules = WorkflowRuleRepository::get_publish_workflow_rules();

		foreach ($rules as $rule) {
			if ($rule->get_trigger_hook() !== ETriggerPoint::ACTIVITY_BEFORE_START->value) {
				continue;
			}

			$params         = $rule->get_trigger_params();
			$before_minutes = isset($params['before_minutes']) ? (int) $params['before_minutes'] : self::DEFAULT_BEFORE_MINUTES;

			if ($before_minutes <= 0) {
				Plugin::logger(
					"ActivitySchedulerService：WorkflowRule {$rule->id} 的 before_minutes ({$before_minutes}) 無效，跳過排程",
					'warning'
				);
				continue;
			}

			$before_timestamp = $start_timestamp - ( $before_minutes * 60 );
			$scheduled_id     = \as_schedule_single_action(
				$before_timestamp,
				self::HOOK_ACTIVITY_BEFORE_START,
				[ $activity_id, $rule->id ],
				'power_funnel',
				true
			);

			if ($scheduled_id === 0) {
				Plugin::logger(
					"ActivitySchedulerService：活動 {$activity_id} / 規則 {$rule->id} 排程 ACTIVITY_BEFORE_START 失敗",
					'error'
				);
			}
		}
	}

	/**
	 * Action Scheduler 觸發：活動已開始
	 *
	 * @param string $activity_id 活動 ID
	 * @return void
	 */
	public static function on_activity_started( string $activity_id ): void {
		$context_callable_set = [
			'callable' => [ self::class, 'resolve_activity_started_context' ],
			'params'   => [ $activity_id ],
		];
		\do_action(ETriggerPoint::ACTIVITY_STARTED->value, $context_callable_set);
	}

	/**
	 * 解析活動開始 context（Serializable Context Callable 目標方法）
	 *
	 * @param string $activity_id 活動 ID
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_activity_started_context( string $activity_id ): array {
		return [
			'activity_id' => $activity_id,
			'event_type'  => 'activity_started',
		];
	}

	/**
	 * Action Scheduler 觸發：活動開始前
	 *
	 * @param string $activity_id     活動 ID
	 * @param string $workflow_rule_id WorkflowRule ID
	 * @return void
	 */
	public static function on_activity_before_start( string $activity_id, string $workflow_rule_id ): void {
		$context_callable_set = [
			'callable' => [ self::class, 'resolve_activity_before_start_context' ],
			'params'   => [ $activity_id, $workflow_rule_id ],
		];
		\do_action(ETriggerPoint::ACTIVITY_BEFORE_START->value, $context_callable_set);
	}

	/**
	 * 解析活動開始前 context（Serializable Context Callable 目標方法）
	 *
	 * @param string $activity_id      活動 ID
	 * @param string $workflow_rule_id WorkflowRule ID
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_activity_before_start_context( string $activity_id, string $workflow_rule_id ): array {
		return [
			'activity_id'      => $activity_id,
			'workflow_rule_id' => $workflow_rule_id,
			'event_type'       => 'activity_before_start',
		];
	}
}
