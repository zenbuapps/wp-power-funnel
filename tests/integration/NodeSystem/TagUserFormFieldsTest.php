<?php
/**
 * TagUserNode 表單欄位整合測試。
 *
 * 驗證 TagUserNode 的 form_fields 定義，
 * 確認 tags 欄位類型為 tags_input，action 欄位仍為 select。
 *
 * @group node-system
 * @group tag-user-node
 *
 * @see specs/implement-node-definitions/features/nodes/tag-user-form-fields.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * TagUserNode 表單欄位測試
 *
 * Feature: TagUserNode 表單欄位更新
 */
class TagUserFormFieldsTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 初始化 WorkflowRule NodeDefinitions
		// \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
	}

	/**
	 * Feature: TagUserNode 表單欄位更新
	 * Rule: 後置（狀態）- tags 欄位型別應為 tags_input
	 * Example: TagUserNode form_fields 定義
	 *
	 * TODO: [事件風暴部位: Query - TagUserNode::form_fields]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 */
	public function test_tags欄位型別應為tags_input(): void {
		// Given 系統已註冊 TagUserNode

		// When 檢查 TagUserNode 的 form_fields

		// Then tags 欄位的 type 應為 "tags_input"
		// And tags 欄位的 name 應為 "tags"
		// And tags 欄位的 label 應為 "標籤"
		// And tags 欄位的 required 應為 true
		// And tags 欄位不應有 options 屬性

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: TagUserNode 表單欄位更新
	 * Rule: 後置（狀態）- action 欄位應保持不變
	 * Example: action 欄位仍為 select
	 *
	 * TODO: [事件風暴部位: Query - TagUserNode::form_fields]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 */
	public function test_action欄位仍為select(): void {
		// Given 系統已註冊 TagUserNode

		// When 檢查 TagUserNode 的 form_fields

		// Then action 欄位的 type 應為 "select"
		// And action 欄位的 options 應包含 "add" 和 "remove"

		$this->markTestIncomplete('尚未實作');
	}
}
