<?php
/**
 * Action Scheduler 統一節點排程整合測試。
 *
 * 驗證 NodeDTO::try_execute() 在成功路徑呼叫 as_schedule_single_action()，
 * 延遲節點（scheduled=true）不二次排程，失敗時不排程。
 *
 * @group workflow
 * @group action-scheduler-chaining
 *
 * @see specs/implement-node-definitions/features/engine/action-scheduler-chaining.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * Action Scheduler 統一節點排程測試
 *
 * Feature: Action Scheduler 統一節點排程
 */
class ActionSchedulerChainingTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 初始化 WorkflowRule 與 Workflow Register hooks
		// \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		// \J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 非延遲節點成功後引擎應排程立即執行下一節點
	 * Example: EmailNode 成功後引擎排程 as_schedule_single_action(time(), ...)
	 *
	 * TODO: [事件風暴部位: Command - NodeDTO::try_execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_EmailNode成功後引擎排程as_schedule_single_action(): void {
		// Given Workflow 100 有以下節點：
		// | id | node_definition_id | params |
		// | n1 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
		// | n2 | line               | {"content_tpl":"Follow up"} |
		// And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false)

		// When NodeDTO::try_execute() 處理成功結果

		// Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
		// And Workflow 100 的 results 應包含節點 "n1" 的結果

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 延遲節點成功後引擎不應二次排程
	 * Example: WaitNode 回傳 scheduled=true 時引擎不排程
	 *
	 * TODO: [事件風暴部位: Command - NodeDTO::try_execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_WaitNode回傳scheduled_true時引擎不排程(): void {
		// Given Workflow 100 有以下節點：
		// | id | node_definition_id | params |
		// | n1 | wait               | {"duration":"30","unit":"minutes"} |
		// | n2 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"After wait"} |
		// And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=true)

		// When NodeDTO::try_execute() 處理成功結果

		// Then 不應呼叫 as_schedule_single_action
		// And Workflow 100 的 results 應包含節點 "n1" 的結果

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 節點執行失敗時不應排程下一節點
	 * Example: 節點回傳 code=500 時不排程
	 *
	 * TODO: [事件風暴部位: Command - NodeDTO::try_execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_節點回傳code_500時不排程(): void {
		// Given Workflow 100 有以下節點：
		// | id | node_definition_id | params |
		// | n1 | email              | {"recipient":"","subject_tpl":"Hi","content_tpl":"Hello"} |
		// | n2 | line               | {"content_tpl":"Follow up"} |
		// And 節點 "n1" 執行失敗（拋出 RuntimeException）

		// When NodeDTO::try_execute() 捕獲例外

		// Then 不應呼叫 as_schedule_single_action
		// And Workflow 100 的 status 應為 "failed"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 最後一個節點成功後引擎排程使 workflow 進入 completed
	 * Example: 最後一個節點成功後排程觸發 try_execute 進入 completed
	 *
	 * TODO: [事件風暴部位: Command - NodeDTO::try_execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_最後一個節點成功後排程觸發completed(): void {
		// Given Workflow 100 有以下節點：
		// | id | node_definition_id | params |
		// | n1 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
		// And Workflow 100 的 results 為空（即將執行第一個也是最後一個節點）
		// And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false)

		// When NodeDTO::try_execute() 處理成功結果

		// Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
		// And 下次 try_execute 被觸發時 get_current_index() 回傳 null
		// And Workflow 100 的 status 應設為 "completed"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- WorkflowResultDTO 新增 scheduled 欄位
	 * Example: WorkflowResultDTO 預設 scheduled 為 false
	 *
	 * TODO: [事件風暴部位: Query - WorkflowResultDTO 屬性]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 */
	public function test_WorkflowResultDTO預設scheduled為false(): void {
		// When 建立 WorkflowResultDTO(node_id='n1', code=200, message='OK')

		// Then scheduled 欄位應為 false

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- WorkflowResultDTO 新增 scheduled 欄位
	 * Example: WorkflowResultDTO 可設定 scheduled 為 true
	 *
	 * TODO: [事件風暴部位: Query - WorkflowResultDTO 屬性]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 */
	public function test_WorkflowResultDTO可設定scheduled為true(): void {
		// When 建立 WorkflowResultDTO(node_id='n1', code=200, message='等待中', scheduled=true)

		// Then scheduled 欄位應為 true

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 帶有 next_node_id 的分支節點也應排程
	 * Example: YesNoBranchNode 回傳 next_node_id 時引擎仍排程下一步
	 *
	 * TODO: [事件風暴部位: Command - NodeDTO::try_execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_YesNoBranchNode回傳next_node_id時引擎仍排程(): void {
		// Given Workflow 100 有以下節點：
		// | id | node_definition_id | params |
		// | n1 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
		// | n2 | email              | {"recipient":"test@example.com","subject_tpl":"VIP","content_tpl":"Hi VIP"} |
		// | n3 | email              | {"recipient":"test@example.com","subject_tpl":"Thanks","content_tpl":"Hi"} |
		// And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false, next_node_id='n2')

		// When NodeDTO::try_execute() 處理成功結果

		// Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])

		$this->markTestIncomplete('尚未實作');
	}
}
