<?php
/**
 * WorkflowRule Repository 整合測試。
 *
 * 驗證 pf_workflow_rule CPT 的建立、查詢、發布邏輯。
 *
 * @group smoke
 * @group workflow
 * @group workflow-rule
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\PowerFunnel\Shared\Enums\EWorkflowRuleStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * WorkflowRule Repository 測試
 *
 * Feature: 建立工作流規則
 * Feature: 發布工作流規則
 */
class WorkflowRuleRepositoryTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 確保 WorkflowRule hooks 已註冊（Bootstrap 尚未包含此呼叫，Workflow 功能開發中）
		Register::register_hooks();
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * 冒煙測試：Repository::create 可以建立 pf_workflow_rule CPT
	 *
	 * @group smoke
	 */
	public function test_可以建立工作流規則(): void {
		// When 系統建立工作流規則
		try {
			$rule_id         = Repository::create(
				[
					'post_title'  => '冒煙測試規則',
					'meta_input'  => [
						'trigger_point' => 'pf/trigger/registration_created',
						'nodes'         => [],
					],
				]
			);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
			$rule_id         = 0;
		}

		// Then 操作應成功
		$this->assert_operation_succeeded();
		$this->assertGreaterThan(0, $rule_id);

		// Then post_type 應為 pf_workflow_rule
		$post = \get_post($rule_id);
		$this->assertNotNull($post);
		$this->assertSame('pf_workflow_rule', $post->post_type);
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * 快樂路徑：建立工作流規則後預設狀態為 draft
	 *
	 * Feature: 建立工作流規則
	 * Example: 建立包含 Email 節點的工作流規則
	 *
	 * @group happy
	 */
	public function test_建立工作流規則後狀態為draft(): void {
		// Given 節點序列
		$nodes = [
			[
				'id'                 => 'n1',
				'node_definition_id' => 'email',
				'params'             => [
					'recipient'    => 'context',
					'subject_tpl'  => '歡迎',
					'content_tpl'  => '感謝報名',
				],
				'match_callback'        => [\J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		// When 管理員建立工作流規則
		$rule_id = Repository::create(
			[
				'post_title'  => '報名後發 Email',
				'meta_input'  => [
					'trigger_point' => 'pf/trigger/registration_created',
					'nodes'         => $nodes,
				],
			]
		);

		// Then 系統應建立一筆 pf_workflow_rule 紀錄
		$post = \get_post($rule_id);
		$this->assertNotNull($post, '應建立工作流規則紀錄');
		$this->assertSame('pf_workflow_rule', $post->post_type, 'post_type 應為 pf_workflow_rule');

		// Then 規則的狀態應為 draft
		$this->assertSame(
			EWorkflowRuleStatus::DRAFT->value,
			$post->post_status,
			'工作流規則預設狀態應為 draft'
		);

		// Then 規則的 trigger_point 應為 pf/trigger/registration_created
		$stored_trigger = \get_post_meta($rule_id, 'trigger_point', true);
		$this->assertSame(
			'pf/trigger/registration_created',
			$stored_trigger,
			'trigger_point 應正確儲存'
		);

		// Then 規則的 nodes 應包含 1 個節點
		$stored_nodes = \get_post_meta($rule_id, 'nodes', true);
		$this->assertIsArray($stored_nodes, 'nodes 應為陣列');
		$this->assertCount(1, $stored_nodes, 'nodes 應包含 1 個節點');
	}

	/**
	 * 快樂路徑：get_publish_workflow_rules 只回傳已發布的規則
	 *
	 * Feature: 發布工作流規則
	 * Example: 發布工作流規則
	 *
	 * @group happy
	 */
	public function test_get_publish_workflow_rules只回傳已發布規則(): void {
		// Given 系統中有一個 draft 規則
		Repository::create(
			[
				'post_title' => 'Draft 規則',
				'meta_input' => ['trigger_point' => 'pf/trigger/registration_created', 'nodes' => []],
			]
		);

		// Given 系統中有一個 publish 規則
		$published_rule_id = Repository::create(
			[
				'post_title'  => 'Published 規則',
				'post_status' => EWorkflowRuleStatus::PUBLISH->value,
				'meta_input'  => ['trigger_point' => 'pf/trigger/registration_created', 'nodes' => []],
			]
		);

		// When 查詢已發布的工作流規則
		$rules = Repository::get_publish_workflow_rules();

		// Then 只應回傳已發布的規則
		$this->assertNotEmpty($rules, '應有已發布的工作流規則');
		$rule_ids = array_map(static fn(WorkflowRuleDTO $r) => (int) $r->id, $rules);
		$this->assertContains($published_rule_id, $rule_ids, '已發布的規則應在結果中');
	}

	/**
	 * 快樂路徑：發布工作流規則後調用 register 應在 trigger_point 上掛載 callback
	 *
	 * Feature: 發布工作流規則
	 * Example: 發布後註冊 hook 監聽器
	 *
	 * @group happy
	 */
	public function test_發布工作流規則後掛載hook監聽器(): void {
		// Given 系統中有一個已發布的工作流規則
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'context', 'subject_tpl' => '歡迎', 'content_tpl' => '感謝報名'],
				'match_callback'        => [\J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$rule_id = Repository::create(
			[
				'post_title'  => '報名後發 Email',
				'post_status' => EWorkflowRuleStatus::PUBLISH->value,
				'meta_input'  => [
					'trigger_point' => 'test_hook_' . uniqid(),
					'nodes'         => $nodes,
				],
			]
		);

		$trigger_point = \get_post_meta($rule_id, 'trigger_point', true);
		$this->assertIsString($trigger_point);

		// When 系統執行 register_workflow_rules()
		// 建立 WorkflowRuleDTO 並呼叫 register()
		$rule_dto = WorkflowRuleDTO::of((string) $rule_id);
		$rule_dto->register();

		// Then 系統應在 trigger_point 上掛載 callback
		$hook_count = \has_action($trigger_point);
		$this->assertNotFalse($hook_count, "應在 {$trigger_point} 上掛載 callback");
	}

	/**
	 * 快樂路徑：get_trigger_points 回傳分組結構的觸發點清單
	 *
	 * @group happy
	 */
	public function test_get_trigger_points回傳所有觸發點(): void {
		// When 取得所有觸發點
		$trigger_point_groups = Repository::get_trigger_points();

		// Then 應回傳非空陣列
		$this->assertIsArray($trigger_point_groups, '應回傳陣列');
		$this->assertNotEmpty($trigger_point_groups, '應有至少一個分組');

		// Then 每個元素應為 TriggerPointGroupDTO
		foreach ($trigger_point_groups as $group) {
			$this->assertInstanceOf(
				\J7\PowerFunnel\Contracts\DTOs\TriggerPointGroupDTO::class,
				$group,
				'每個元素應為 TriggerPointGroupDTO'
			);
			$this->assertIsString($group->group, 'group 應為字串');
			$this->assertNotEmpty($group->group, 'group 不可為空');
		}
	}

	/**
	 * 快樂路徑：get_node_definitions 回傳已註冊的 EmailNode
	 *
	 * @group happy
	 */
	public function test_get_node_definitions包含EmailNode(): void {
		// When 取得所有節點定義
		$node_definitions = Repository::get_node_definitions();

		// Then 應包含 email 節點定義
		$this->assertArrayHasKey('email', $node_definitions, '應有 email 節點定義');
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * 錯誤處理：Repository::create 回傳值應為正整數（驗證建立成功路徑）
	 *
	 * Feature: 建立工作流規則
	 * 說明：wp_insert_post 在沒有 $wp_error=true 參數時，失敗只回傳 0（不是 WP_Error），
	 * 因此 Repository::create 的 is_wp_error 檢查只在特殊情況下觸發。
	 * 此測試驗證正常建立流程確實回傳正整數。
	 *
	 * @group error
	 */
	public function test_建立工作流規則成功時回傳正整數(): void {
		// Given 正常的工作流規則參數
		$args = [
			'post_title' => '正常工作流規則',
			'meta_input' => [
				'trigger_point' => 'pf/trigger/registration_created',
				'nodes'         => [],
			],
		];

		// When 管理員建立工作流規則
		$rule_id = Repository::create($args);

		// Then 應回傳正整數 post ID
		$this->assertIsInt($rule_id, '應回傳整數 ID');
		$this->assertGreaterThan(0, $rule_id, '應回傳正整數 ID');

		// Then 應可查詢到建立的紀錄
		$post = \get_post($rule_id);
		$this->assertNotNull($post, '應可查詢到建立的規則');
		$this->assertSame('pf_workflow_rule', $post->post_type, 'post_type 應為 pf_workflow_rule');
	}

	/**
	 * 錯誤處理：草稿狀態的 WorkflowRule 不會被 get_publish_workflow_rules 回傳
	 *
	 * Feature: 從規則建立工作流實例
	 * Example: 草稿狀態的 WorkflowRule 不會被觸發
	 *
	 * @group error
	 */
	public function test_草稿工作流規則不回傳在publish查詢中(): void {
		// Given 工作流規則狀態為 draft
		$draft_rule_id = Repository::create(
			[
				'post_title' => 'Draft 規則',
				'meta_input' => ['trigger_point' => 'pf/trigger/test', 'nodes' => []],
			]
		);

		// When 查詢已發布的工作流規則
		$rules  = Repository::get_publish_workflow_rules();
		$ids    = array_map(static fn(WorkflowRuleDTO $r) => (int) $r->id, $rules);

		// Then draft 規則不應出現在結果中
		$this->assertNotContains($draft_rule_id, $ids, 'draft 規則不應出現在已發布規則清單中');
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * 邊緣案例：nodes 為空陣列時應可建立工作流規則
	 *
	 * @group edge
	 */
	public function test_空nodes時應可建立工作流規則(): void {
		// When 建立空 nodes 的工作流規則
		$rule_id = Repository::create(
			[
				'post_title' => '空節點規則',
				'meta_input' => [
					'trigger_point' => 'pf/trigger/registration_created',
					'nodes'         => [],
				],
			]
		);

		// Then 應成功建立
		$this->assertGreaterThan(0, $rule_id);

		$stored_nodes = \get_post_meta($rule_id, 'nodes', true);
		$this->assertIsArray($stored_nodes);
		$this->assertEmpty($stored_nodes, '空 nodes 應正確儲存');
	}

	/**
	 * 邊緣案例：trigger_point 包含特殊字元時應可建立
	 *
	 * @group edge
	 */
	public function test_trigger_point包含特殊字元時可建立(): void {
		// Given trigger_point 包含特殊字元
		$special_hook = 'pf/trigger/special_hook_!@#$%';

		// When 建立工作流規則
		$rule_id = Repository::create(
			[
				'post_title' => '特殊 hook 規則',
				'meta_input' => ['trigger_point' => $special_hook, 'nodes' => []],
			]
		);

		// Then 應成功建立且 trigger_point 原樣儲存
		$this->assertGreaterThan(0, $rule_id);
		$stored_trigger = \get_post_meta($rule_id, 'trigger_point', true);
		$this->assertSame($special_hook, $stored_trigger, 'trigger_point 應原樣儲存');
	}

	/**
	 * 邊緣案例：WorkflowRuleDTO::of 可以正確從 post 建立 DTO
	 *
	 * @group edge
	 */
	public function test_WorkflowRuleDTO可以從post_id建立(): void {
		// Given 系統中有一個工作流規則
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'test@example.com', 'subject_tpl' => '歡迎', 'content_tpl' => '感謝'],
				'match_callback'        => [\J7\PowerFunnel\Tests\Integration\TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$rule_id = Repository::create(
			[
				'post_title' => '測試規則',
				'meta_input' => [
					'trigger_point' => 'pf/trigger/registration_created',
					'nodes'         => $nodes,
				],
			]
		);

		// When 建立 WorkflowRuleDTO
		$dto = WorkflowRuleDTO::of((string) $rule_id);

		// Then DTO 應正確映射
		$this->assertSame((string) $rule_id, $dto->id, 'DTO id 應相符');
		$this->assertSame('pf/trigger/registration_created', $dto->trigger_point, 'trigger_point 應相符');
		$this->assertCount(1, $dto->nodes, 'nodes 應有 1 個節點');
		$this->assertSame('n1', $dto->nodes[0]->id, '節點 id 應相符');
		$this->assertSame('email', $dto->nodes[0]->node_definition_id, '節點 node_definition_id 應相符');
	}
}
