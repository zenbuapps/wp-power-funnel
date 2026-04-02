<?php
/**
 * LineNode 傳送 LINE 文字訊息整合測試。
 *
 * 驗證 LineNode::execute() 能正確從 context 取得 line_user_id，
 * 透過 ParamHelper 渲染 content_tpl，並呼叫 MessageService 發送訊息。
 *
 * @group node-system
 * @group line-node
 *
 * @see specs/implement-node-definitions/features/nodes/line-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * LineNode 傳送 LINE 文字訊息測試
 *
 * Feature: LineNode 傳送 LINE 文字訊息
 */
class LineNodeExecuteTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 初始化 WorkflowRule 與 Workflow Register hooks（僅需 NodeDefinitions）
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- 成功發送 LINE 訊息時回傳 code 200
	 * Example: content_tpl 模板替換後發送成功
	 *
	 * TODO: [事件風暴部位: Command - LineNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_content_tpl模板替換後發送成功(): void {
		// Given MessageService Channel Access Token 已設定
		// And MessageService::send_text_message() 模擬回傳成功

		// When 系統執行節點 "n1"（LineNode）
		// （context: {"line_user_id":"U1234567890abcdef","identity_id":"alice"}，content_tpl: "{{identity_id}} 您好！歡迎加入"）

		// Then 應呼叫 MessageService::getInstance()->send_text_message("U1234567890abcdef", "alice 您好！歡迎加入")
		// And 結果的 code 應為 200
		// And 結果的 message 應包含 "LINE 訊息發送成功"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 前置（參數）- context 中缺少 line_user_id 時應失敗
	 * Example: workflow context 中無 line_user_id
	 *
	 * TODO: [事件風暴部位: Command - LineNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_workflow_context中無line_user_id(): void {
		// Given Workflow 100 的 context 為 {"identity_id":"alice"}（無 line_user_id）

		// When 系統執行節點 "n1"（LineNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "line_user_id"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 前置（依賴）- Channel Access Token 未設定時應失敗
	 * Example: MessageService 建構失敗
	 *
	 * TODO: [事件風暴部位: Command - LineNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_MessageService建構失敗(): void {
		// Given MessageService Channel Access Token 未設定
		// And MessageService::getInstance() 拋出 Exception

		// When 系統執行節點 "n1"（LineNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "Channel Access Token"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- MessageService 拋出例外時回傳 code 500
	 * Example: LINE API 回傳錯誤
	 *
	 * TODO: [事件風暴部位: Command - LineNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_LINE_API回傳錯誤(): void {
		// Given MessageService Channel Access Token 已設定
		// And MessageService::send_text_message() 拋出 Exception("LINE API error")

		// When 系統執行節點 "n1"（LineNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "LINE API error"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換
	 * Example: 多個模板變數替換
	 *
	 * TODO: [事件風暴部位: Command - LineNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_多個模板變數替換(): void {
		// Given Workflow 100 的 context 為 {"line_user_id":"U123","identity_id":"Bob","activity_id":"A99"}
		// And 節點 "n1" 的 content_tpl 為 "{{identity_id}} 報名活動 {{activity_id}} 成功"

		// When 系統執行節點 "n1"（LineNode）

		// Then 發送的訊息內容應為 "Bob 報名活動 A99 成功"

		$this->markTestIncomplete('尚未實作');
	}
}
