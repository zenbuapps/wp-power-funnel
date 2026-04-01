<?php

/**
 * Workflow 非線性執行整合測試。
 *
 * 驗證 WorkflowDTO::try_execute() 能根據上一個節點結果的 next_node_id
 * 跳轉到指定節點，而非按線性順序執行。
 *
 * @group workflow
 * @group workflow-non-linear
 *
 * @see specs/order-trigger-and-branch-node/features/workflow/workflow-non-linear-execution.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * Workflow 非線性執行測試
 *
 * Feature: Workflow 非線性執行（支援 next_node_id 跳轉）
 */
class WorkflowNonLinearExecutionTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();

		// 移除 powerhouse EmailValidator 的 pre_wp_mail 過濾器
		\remove_all_filters('pre_wp_mail');
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 注入測試用 email 節點定義
		\add_filter(
			'power_funnel/workflow_rule/node_definitions',
			static function ( array $definitions ): array {
				$definitions['test_email'] = new class extends \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition {
					public string $id          = 'test_email';
					public string $name        = '測試 Email 節點';
					public string $description = '測試用';
					public \J7\PowerFunnel\Shared\Enums\ENodeType $type = \J7\PowerFunnel\Shared\Enums\ENodeType::SEND_MESSAGE;

					/**
					 * 執行節點
					 *
					 * @param \J7\PowerFunnel\Contracts\DTOs\NodeDTO     $node     節點
					 * @param \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow 工作流
					 * @return \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO
					 */
					public function execute(
						\J7\PowerFunnel\Contracts\DTOs\NodeDTO $node,
						\J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow
					): \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO {
						return new \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO(
							[
								'node_id' => $node->id,
								'code'    => 200,
								'message' => '測試發信成功',
							]
						);
					}
				};
				return $definitions;
			}
		);
	}

	/**
	 * 建立含有分支結構的 workflow post
	 *
	 * @param array<int, array<string, mixed>> $results 預設結果
	 * @return int workflow post ID
	 */
	private function create_branch_workflow_post(array $results = []): int {
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => [
					'condition_field'  => 'order_total',
					'operator'         => 'gt',
					'condition_value'  => '1000',
					'yes_next_node_id' => 'n2',
					'no_next_node_id'  => 'n3',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'VIP',
					'content_tpl' => 'VIP 歡迎',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '普通',
					'content_tpl' => '感謝訂購',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '非線性執行測試 Workflow',
				'meta_input'  => \wp_slash([
					'workflow_rule_id'     => '20',
					'trigger_point'        => 'pf/trigger/order_completed',
					'nodes'                => $nodes,
					'context_callable_set' => [],
					'results'              => $results,
				]),
			]
		);

		if ( ! is_int($post_id) || $post_id <= 0 ) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		$this->set_post_status_bypass_hooks($post_id, 'running');
		return $post_id;
	}

	// ========== Rule: 前一個 result 帶有 next_node_id 時應跳轉到對應節點 ==========

	/**
	 * n1 結果帶 next_node_id="n2"，下一個執行 n2（跳過線性順序）
	 *
	 * @group happy
	 */
	public function test_next_node_id指向n2時跳轉執行n2(): void {
		// Given Workflow 的 results 有 1 筆，next_node_id 為 n2
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件成立',
				'data'         => null,
				'next_node_id' => 'n2',
			],
		];
		$workflow_id = $this->create_branch_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統應執行節點 "n2"（而非線性的下一個）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		// 驗證第二筆 result 的 node_id 為 n2
		$this->assertGreaterThanOrEqual(2, count($updated_dto->results), '應有至少 2 筆 results');
		$second_result = $updated_dto->results[1] ?? null;
		$this->assertNotNull($second_result, '應有第二筆結果');
		$this->assertSame('n2', $second_result->node_id, '應跳轉執行 n2');
	}

	/**
	 * n1 結果帶 next_node_id="n3"，下一個執行 n3
	 *
	 * @group happy
	 */
	public function test_next_node_id指向n3時跳轉執行n3(): void {
		// Given Workflow 的 results 有 1 筆，next_node_id 為 n3
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件不成立',
				'data'         => null,
				'next_node_id' => 'n3',
			],
		];
		$workflow_id = $this->create_branch_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統應執行節點 "n3"
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertGreaterThanOrEqual(2, count($updated_dto->results), '應有至少 2 筆 results');
		$second_result = $updated_dto->results[1] ?? null;
		$this->assertNotNull($second_result, '應有第二筆結果');
		$this->assertSame('n3', $second_result->node_id, '應跳轉執行 n3');
	}

	// ========== Rule: 不帶 next_node_id 的結果應維持線性執行（向下相容） ==========

	/**
	 * 普通節點不帶 next_node_id，按線性順序執行
	 *
	 * @group happy
	 */
	public function test_不帶next_node_id時按線性順序執行(): void {
		// Given Workflow 的 results 有 1 筆，next_node_id 為空
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '成功',
				'data'         => null,
				'next_node_id' => '',
			],
		];
		$workflow_id = $this->create_branch_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統應執行 nodes[1]（線性下一個，即 n2）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertGreaterThanOrEqual(2, count($updated_dto->results), '應有至少 2 筆 results');
		$second_result = $updated_dto->results[1] ?? null;
		$this->assertNotNull($second_result, '應有第二筆結果');
		$this->assertSame('n2', $second_result->node_id, '不帶 next_node_id 時應按線性順序執行 n2');
	}

	// ========== Rule: next_node_id 對應的節點必須存在 ==========

	/**
	 * next_node_id 指向不存在的節點時 Workflow 標記為 failed
	 *
	 * @group edge
	 */
	public function test_next_node_id指向不存在節點時標記failed(): void {
		// Given Workflow 的 results 有 1 筆，next_node_id 為 nonexistent
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '成功',
				'data'         => null,
				'next_node_id' => 'nonexistent',
			],
		];
		$workflow_id = $this->create_branch_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then Workflow 的狀態應設為 "failed"
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::FAILED->value,
			$post_status,
			'next_node_id 指向不存在節點時應標記為 failed'
		);
	}

	// ========== Rule: 分支執行完成後 Workflow 應正確判定完成 ==========

	/**
	 * 分支節點執行後僅執行一個分支路徑即完成
	 *
	 * @group happy
	 */
	public function test_分支執行完成後Workflow標記completed(): void {
		// Given Workflow 的 results 有 2 筆（n1 → n2，已執行完一個分支路徑）
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件成立',
				'data'         => null,
				'next_node_id' => 'n2',
			],
			[
				'node_id'      => 'n2',
				'code'         => 200,
				'message'      => '測試發信成功',
				'data'         => null,
				'next_node_id' => '',
			],
		];
		$workflow_id = $this->create_branch_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then Workflow 的狀態應設為 "completed"
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::COMPLETED->value,
			$post_status,
			'分支路徑執行完成後工作流應標記為 completed'
		);
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * WorkflowResultDTO 的 next_node_id 欄位為可選
	 *
	 * @group happy
	 */
	public function test_WorkflowResultDTO的next_node_id為可選(): void {
		// Given 一個不帶 next_node_id 的 WorkflowResultDTO
		$result = new WorkflowResultDTO([
			'node_id' => 'n1',
			'code'    => 200,
			'message' => '成功',
		]);

		// Then next_node_id 應預設為空字串或 null
		$next_node_id = $result->next_node_id ?? '';
		$this->assertTrue(
			$next_node_id === '' || $next_node_id === null,
			'next_node_id 未設定時應預設為空字串或 null'
		);
	}
}
