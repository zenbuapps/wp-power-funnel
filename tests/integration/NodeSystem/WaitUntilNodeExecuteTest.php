<?php
/**
 * WaitUntilNode 等待至指定時間點整合測試。
 *
 * 驗證 WaitUntilNode::execute() 使用 Action Scheduler 排程至指定時間，
 * 過去時間立即排程，並正確處理無效 datetime 和排程失敗。
 *
 * @group node-system
 * @group wait-until-node
 *
 * @see specs/implement-node-definitions/features/nodes/wait-until-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * WaitUntilNode 等待至指定時間點測試
 *
 * Feature: WaitUntilNode 等待至指定時間點
 */
class WaitUntilNodeExecuteTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 初始化 NodeDefinitions
	}

	/**
	 * Feature: WaitUntilNode 等待至指定時間點
	 * Rule: 後置（狀態）- 未來時間應排程至該時間點
	 * Example: datetime 為未來時間
	 *
	 * TODO: [事件風暴部位: Command - WaitUntilNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_datetime為未來時間(): void {
		// Given 當前時間為 2026-04-01T09:00:00
		// And 節點 "n1" 的 datetime 為 "2026-04-15T10:00:00"

		// When 系統執行節點 "n1"（WaitUntilNode）

		// Then 應呼叫 as_schedule_single_action(timestamp_of("2026-04-15T10:00:00"), 'power_funnel/workflow/running', ['workflow_id' => '100'])
		// And 結果的 code 應為 200
		// And 結果的 message 應包含 "等待至"
		// And 結果的 scheduled 應為 true

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: WaitUntilNode 等待至指定時間點
	 * Rule: 後置（狀態）- 過去時間應立即排程
	 * Example: datetime 已過期
	 *
	 * TODO: [事件風暴部位: Command - WaitUntilNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_datetime已過期(): void {
		// Given 當前時間為 2026-04-15T12:00:00
		// And 節點 "n1" 的 datetime 為 "2026-04-10T10:00:00"

		// When 系統執行節點 "n1"（WaitUntilNode）

		// Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
		// And 結果的 code 應為 200
		// And 結果的 scheduled 應為 true

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: WaitUntilNode 等待至指定時間點
	 * Rule: 後置（狀態）- 排程失敗時回傳 code 500
	 * Example: as_schedule_single_action 回傳 0
	 *
	 * TODO: [事件風暴部位: Command - WaitUntilNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_as_schedule_single_action回傳0(): void {
		// Given as_schedule_single_action() 回傳 0（排程失敗）

		// When 系統執行節點 "n1"（WaitUntilNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "排程失敗"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: WaitUntilNode 等待至指定時間點
	 * Rule: 前置（參數）- datetime 必須提供且格式正確
	 * Example: datetime 為空時失敗
	 *
	 * TODO: [事件風暴部位: Command - WaitUntilNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_datetime為空時失敗(): void {
		// Given 節點 "n1" 的 params 中 datetime 為 ""

		// When 系統執行節點 "n1"（WaitUntilNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "datetime"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: WaitUntilNode 等待至指定時間點
	 * Rule: 前置（參數）- datetime 必須提供且格式正確
	 * Example: datetime 格式無法解析時失敗
	 *
	 * TODO: [事件風暴部位: Command - WaitUntilNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_datetime格式無法解析時失敗(): void {
		// Given 節點 "n1" 的 params 中 datetime 為 "not-a-date"

		// When 系統執行節點 "n1"（WaitUntilNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "datetime"

		$this->markTestIncomplete('尚未實作');
	}
}
