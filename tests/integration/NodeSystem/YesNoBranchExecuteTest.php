<?php

/**
 * YesNoBranchNode 條件分支執行整合測試。
 *
 * 驗證 YesNoBranchNode::execute() 能根據 context 中的欄位值
 * 正確判斷走 yes 或 no 分支，並回傳正確的 next_node_id。
 *
 * @group workflow
 * @group node-system
 * @group yes-no-branch
 * @group yes-no-branch-execute
 *
 * @see specs/order-trigger-and-branch-node/features/node-system/yes-no-branch-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * YesNoBranchNode 條件分支執行測試
 *
 * Feature: YesNoBranchNode 條件分支執行
 */
class YesNoBranchExecuteTest extends IntegrationTestCase {

	/** @var BaseNodeDefinition 分支節點定義 */
	private BaseNodeDefinition $branch_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		/** @var array<string, BaseNodeDefinition> $definitions */
		$definitions = \apply_filters('power_funnel/workflow_rule/node_definitions', []);
		$this->assertArrayHasKey('yes_no_branch', $definitions, 'yes_no_branch 節點定義應已註冊');
		$this->branch_node = $definitions['yes_no_branch'];
	}

	/**
	 * 建立含有分支節點的 workflow
	 *
	 * @param array<string, string> $context workflow context
	 * @param array<string, mixed>  $branch_params 分支節點參數
	 * @return int workflow post ID
	 */
	private function create_branch_workflow(
		array $context = [],
		array $branch_params = []
	): int {
		$default_context = [
			'order_id'      => '1001',
			'order_total'   => '2500',
			'billing_email' => 'alice@example.com',
			'customer_id'   => '42',
		];
		$context = \wp_parse_args($context, $default_context);

		// 將 context 設定到 TestCallable 靜態變數，讓 callable 可回傳
		TestCallable::$test_context = $context;

		$default_branch_params = [
			'condition_field'  => 'order_total',
			'operator'         => 'gt',
			'condition_value'  => '1000',
			'yes_next_node_id' => 'n2',
			'no_next_node_id'  => 'n3',
		];
		$branch_params = \wp_parse_args($branch_params, $default_branch_params);

		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => $branch_params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'email',
				'params'                => [
					'recipient'   => 'context',
					'subject_tpl' => 'VIP 歡迎',
					'content_tpl' => '感謝您的大額訂單',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'email',
				'params'                => [
					'recipient'   => 'context',
					'subject_tpl' => '感謝購買',
					'content_tpl' => '感謝您的訂單',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'YesNoBranch 測試 Workflow',
				'meta_input'  => \wp_slash([
					'workflow_rule_id'     => '20',
					'trigger_point'        => 'pf/trigger/order_completed',
					'nodes'                => $nodes,
					'context_callable_set' => [
						'callable' => [ TestCallable::class, 'return_test_context' ],
						'params'   => [],
					],
					'results'              => [],
				]),
			]
		);

		if ( ! is_int($post_id) || $post_id <= 0 ) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		$this->set_post_status_bypass_hooks($post_id, 'running');
		return $post_id;
	}

	/**
	 * 建立 NodeDTO 和 WorkflowDTO 用於直接呼叫 execute
	 *
	 * @param array<string, string> $context workflow context
	 * @param array<string, mixed>  $branch_params 分支節點參數
	 * @return array{node: NodeDTO, workflow: WorkflowDTO}
	 */
	private function make_node_and_workflow(
		array $context = [],
		array $branch_params = []
	): array {
		$default_branch_params = [
			'condition_field'  => 'order_total',
			'operator'         => 'gt',
			'condition_value'  => '1000',
			'yes_next_node_id' => 'n2',
			'no_next_node_id'  => 'n3',
		];
		$branch_params = \wp_parse_args($branch_params, $default_branch_params);

		$node = new NodeDTO([
			'id'                    => 'n1',
			'node_definition_id'    => 'yes_no_branch',
			'params'                => $branch_params,
			'match_callback'        => [ TestCallable::class, 'return_true' ],
			'match_callback_params' => [],
		]);

		$workflow_id = $this->create_branch_workflow($context, $branch_params);
		$workflow    = WorkflowDTO::of((string) $workflow_id);

		return [ 'node' => $node, 'workflow' => $workflow ];
	}

	// ========== Rule: 條件為 true 時應走 yes 分支 ==========

	/**
	 * order_total 2500 > 1000 為 true，走 yes_next_node_id "n2"
	 *
	 * @group happy
	 */
	public function test_條件成立時走yes分支(): void {
		// Given Workflow 100 的 context 中 order_total 為 "2500"
		$fixtures = $this->make_node_and_workflow(
			[ 'order_total' => '2500' ],
			[
				'condition_field'  => 'order_total',
				'operator'         => 'gt',
				'condition_value'  => '1000',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 code 應為 200
		$this->assertSame(200, $result->code, '條件成立時 code 應為 200');

		// And 結果的 next_node_id 應為 "n2"
		$this->assertSame('n2', $result->next_node_id, '條件成立時應走 yes 分支 n2');

		// And 結果的 message 應包含 "條件成立"
		$this->assertStringContainsString('條件成立', $result->message, 'message 應包含「條件成立」');
	}

	// ========== Rule: 條件為 false 時應走 no 分支 ==========

	/**
	 * order_total 500 > 1000 為 false，走 no_next_node_id "n3"
	 *
	 * @group happy
	 */
	public function test_條件不成立時走no分支(): void {
		// Given Workflow 100 的 context 中 order_total 為 "500"
		$fixtures = $this->make_node_and_workflow(
			[ 'order_total' => '500' ],
			[
				'condition_field'  => 'order_total',
				'operator'         => 'gt',
				'condition_value'  => '1000',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 code 應為 200
		$this->assertSame(200, $result->code, '條件不成立時 code 應為 200');

		// And 結果的 next_node_id 應為 "n3"
		$this->assertSame('n3', $result->next_node_id, '條件不成立時應走 no 分支 n3');

		// And 結果的 message 應包含 "條件不成立"
		$this->assertStringContainsString('條件不成立', $result->message, 'message 應包含「條件不成立」');
	}

	// ========== Rule: 各運算子應正確判斷 ==========

	/**
	 * equals 運算子比較字串
	 *
	 * @group happy
	 */
	public function test_equals運算子比較字串(): void {
		// Given billing_email 為 "alice@example.com"
		$fixtures = $this->make_node_and_workflow(
			[ 'billing_email' => 'alice@example.com' ],
			[
				'condition_field'  => 'billing_email',
				'operator'         => 'equals',
				'condition_value'  => 'alice@example.com',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 next_node_id 應為 "n2"（equals 成立）
		$this->assertSame('n2', $result->next_node_id, 'equals 字串比較成立時應走 yes 分支');
	}

	/**
	 * contains 運算子判斷子字串
	 *
	 * @group happy
	 */
	public function test_contains運算子判斷子字串(): void {
		// Given billing_email 為 "alice@gmail.com"
		$fixtures = $this->make_node_and_workflow(
			[ 'billing_email' => 'alice@gmail.com' ],
			[
				'condition_field'  => 'billing_email',
				'operator'         => 'contains',
				'condition_value'  => '@gmail.com',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 next_node_id 應為 "n2"
		$this->assertSame('n2', $result->next_node_id, 'contains 子字串匹配成立時應走 yes 分支');
	}

	/**
	 * is_empty 運算子判斷空值
	 *
	 * @group happy
	 */
	public function test_is_empty運算子判斷空值(): void {
		// Given billing_email 為 ""
		$fixtures = $this->make_node_and_workflow(
			[ 'billing_email' => '' ],
			[
				'condition_field'  => 'billing_email',
				'operator'         => 'is_empty',
				'condition_value'  => '',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 next_node_id 應為 "n2"
		$this->assertSame('n2', $result->next_node_id, 'is_empty 空值判斷成立時應走 yes 分支');
	}

	/**
	 * gte 運算子數值比較（邊界值）
	 *
	 * @group edge
	 */
	public function test_gte運算子邊界值比較(): void {
		// Given order_total 為 "1000"（等於 condition_value）
		$fixtures = $this->make_node_and_workflow(
			[ 'order_total' => '1000' ],
			[
				'condition_field'  => 'order_total',
				'operator'         => 'gte',
				'condition_value'  => '1000',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 next_node_id 應為 "n2"（1000 >= 1000 為 true）
		$this->assertSame('n2', $result->next_node_id, 'gte 邊界值（相等）時應走 yes 分支');
	}

	// ========== Rule: condition_field 在 context 中不存在時應走 no 分支 ==========

	/**
	 * context 中無 nonexistent_field 時走否分支
	 *
	 * @group edge
	 */
	public function test_context中無該欄位時走no分支(): void {
		// Given context 中不包含 key "nonexistent_field"
		$fixtures = $this->make_node_and_workflow(
			[ 'order_total' => '2500' ], // 不含 nonexistent_field
			[
				'condition_field'  => 'nonexistent_field',
				'operator'         => 'gt',
				'condition_value'  => '1000',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 code 應為 200
		$this->assertSame(200, $result->code, 'context 無該欄位時 code 應為 200');

		// And 結果的 next_node_id 應為 "n3"
		$this->assertSame('n3', $result->next_node_id, 'context 無該欄位時應走 no 分支');

		// And 結果的 message 應包含 "條件不成立"
		$this->assertStringContainsString('條件不成立', $result->message, 'message 應包含「條件不成立」');
	}

	// ========== Rule: 數值比較時應將字串轉為數值 ==========

	/**
	 * order_total 字串 "2500" 與 "1000" 的數值比較
	 *
	 * @group edge
	 */
	public function test_字串轉數值比較(): void {
		// Given order_total 為字串 "2500"
		$fixtures = $this->make_node_and_workflow(
			[ 'order_total' => '2500' ],
			[
				'condition_field'  => 'order_total',
				'operator'         => 'gt',
				'condition_value'  => '1000',
				'yes_next_node_id' => 'n2',
				'no_next_node_id'  => 'n3',
			]
		);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 系統應將兩邊都轉為數值後比較
		// And 結果的 next_node_id 應為 "n2"
		$this->assertSame('n2', $result->next_node_id, '字串數值比較應正確轉換後比較');
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * 缺少必要參數時節點執行失敗
	 *
	 * @group edge
	 * @dataProvider missing_params_provider
	 */
	public function test_缺少必要參數時執行失敗(string $missing_param): void {
		// Given 節點 "n1" 的 params 中某參數為空
		$params = [
			'condition_field'  => 'order_total',
			'operator'         => 'gt',
			'condition_value'  => '1000',
			'yes_next_node_id' => 'n2',
			'no_next_node_id'  => 'n3',
		];
		$params[ $missing_param ] = '';

		$fixtures = $this->make_node_and_workflow([], $params);

		// When 系統執行節點 "n1"（YesNoBranchNode）
		$result = $this->branch_node->execute($fixtures['node'], $fixtures['workflow']);

		// Then 結果的 code 應為 500
		$this->assertSame(500, $result->code, "缺少 {$missing_param} 時 code 應為 500");

		// And 結果的 message 應為「必要參數未提供」
		$this->assertStringContainsString(
			'必要參數未提供',
			$result->message,
			"缺少 {$missing_param} 時 message 應包含「必要參數未提供」"
		);
	}

	/**
	 * 缺少參數清單
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function missing_params_provider(): array {
		return [
			'condition_field'  => [ 'condition_field' ],
			'operator'         => [ 'operator' ],
			'yes_next_node_id' => [ 'yes_next_node_id' ],
			'no_next_node_id'  => [ 'no_next_node_id' ],
		];
	}
}
