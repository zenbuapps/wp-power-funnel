<?php
/**
 * SmsNode 傳送 SMS 簡訊整合測試。
 *
 * 驗證 SmsNode::execute() 透過 WordPress filter 委派 SMS 發送，
 * 並正確處理 filter 回傳的 success/failure 結果。
 *
 * @group node-system
 * @group sms-node
 *
 * @see specs/implement-node-definitions/features/nodes/sms-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * SmsNode 傳送 SMS 簡訊測試
 *
 * Feature: SmsNode 傳送 SMS 簡訊
 */
class SmsNodeExecuteTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 初始化 NodeDefinitions
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=true 時回傳 code 200
	 * Example: SMS 發送成功
	 *
	 * TODO: [事件風暴部位: Command - SmsNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.command 實作 When
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-given 實作 Given
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_SMS發送成功(): void {
		// Given power_funnel/sms/send filter 已掛載，回傳 {"success":true,"message":"SMS 發送成功"}

		// When 系統執行節點 "n1"（SmsNode）
		// （context: {"identity_id":"alice","billing_phone":"+886912345678"}，
		//   params: {"recipient":"{{billing_phone}}","content_tpl":"{{identity_id}} 您好"}）

		// Then 應呼叫 apply_filters('power_funnel/sms/send', ['success'=>false,'message'=>'SMS 發送失敗'], '+886912345678', 'alice 您好')
		// And 結果的 code 應為 200
		// And 結果的 message 應為 "SMS 發送成功"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=false 時回傳 code 500
	 * Example: SMS 發送失敗（無 filter 掛載，使用預設值）
	 *
	 * TODO: [事件風暴部位: Command - SmsNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_SMS發送失敗_無filter掛載使用預設值(): void {
		// Given power_funnel/sms/send filter 未掛載（回傳預設值）

		// When 系統執行節點 "n1"（SmsNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應為 "SMS 發送失敗"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=false 時回傳 code 500
	 * Example: SMS 服務回傳失敗
	 *
	 * TODO: [事件風暴部位: Command - SmsNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_SMS服務回傳失敗(): void {
		// Given power_funnel/sms/send filter 已掛載，回傳 {"success":false,"message":"餘額不足"}

		// When 系統執行節點 "n1"（SmsNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應為 "餘額不足"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 前置（參數）- recipient 必須提供
	 * Example: recipient 為空時失敗
	 *
	 * TODO: [事件風暴部位: Command - SmsNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 */
	public function test_recipient為空時失敗(): void {
		// Given 節點 "n1" 的 params 中 recipient 為 ""

		// When 系統執行節點 "n1"（SmsNode）

		// Then 結果的 code 應為 500
		// And 結果的 message 應包含 "recipient"

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換
	 * Example: recipient 也支援模板替換
	 *
	 * TODO: [事件風暴部位: Command - SmsNode::execute()]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.aggregate-then 實作 Then
	 */
	public function test_recipient也支援模板替換(): void {
		// Given Workflow 100 的 context 為 {"identity_id":"Bob","billing_phone":"+886999888777"}
		// And 節點 "n1" 的 recipient 為 "{{billing_phone}}"，content_tpl 為 "{{identity_id}} 提醒"

		// When 系統執行節點 "n1"（SmsNode）

		// Then apply_filters 的 recipient 參數應為 "+886999888777"
		// And apply_filters 的 content 參數應為 "Bob 提醒"

		$this->markTestIncomplete('尚未實作');
	}
}
