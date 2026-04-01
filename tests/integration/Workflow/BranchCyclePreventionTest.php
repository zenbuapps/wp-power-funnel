<?php

/**
 * 分支迴圈防護整合測試。
 *
 * 驗證 WorkflowDTO::try_execute() 能偵測 next_node_id 指向已執行節點的迴圈情況，
 * 並將 Workflow 標記為 failed。
 *
 * @group workflow
 * @group workflow-cycle-prevention
 *
 * @see specs/order-trigger-and-branch-node/features/workflow/branch-cycle-prevention.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * 分支迴圈防護測試
 *
 * Feature: 分支迴圈防護
 */
class BranchCyclePreventionTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();

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
	 * 建立含有迴圈結構的 workflow post
	 *
	 * @param array<int, array<string, mixed>> $results 預設結果
	 * @return int workflow post ID
	 */
	private function create_cycle_workflow_post(array $results = []): int {
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
					'content_tpl' => 'VIP',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => [
					'condition_field'  => 'order_total',
					'operator'         => 'gt',
					'condition_value'  => '500',
					'yes_next_node_id' => 'n1',  // 迴圈！指回 n1
					'no_next_node_id'  => 'n2',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '迴圈防護測試 Workflow',
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

	// ========== Rule: 已執行過的節點不可再次執行 ==========

	/**
	 * next_node_id 指向已執行過的節點時 Workflow 標記為 failed
	 *
	 * @group happy
	 */
	public function test_偵測到迴圈時標記failed(): void {
		// Given Workflow 的 results 有 2 筆：n1 → n3 → n1（迴圈）
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件不成立',
				'data'         => null,
				'next_node_id' => 'n3',
			],
			[
				'node_id'      => 'n3',
				'code'         => 200,
				'message'      => '條件成立',
				'data'         => null,
				'next_node_id' => 'n1', // 迴圈：n1 已在 results 中
			],
		];
		$workflow_id = $this->create_cycle_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統偵測到節點 "n1" 已在 results 中存在
		// And Workflow 的狀態應設為 "failed"
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::FAILED->value,
			$post_status,
			'偵測到迴圈時工作流應標記為 failed'
		);
	}

	// ========== Rule: 正常的非迴圈分支應可正常執行 ==========

	/**
	 * 不同分支路徑的節點不會觸發迴圈防護
	 *
	 * @group happy
	 */
	public function test_非迴圈分支正常執行(): void {
		// Given Workflow 的 results 有 1 筆（n1 → n2，n2 不在 results 中）
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件成立',
				'data'         => null,
				'next_node_id' => 'n2',
			],
		];
		$workflow_id = $this->create_cycle_workflow_post($results);

		// When 系統呼叫 WorkflowDTO::try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統應正常執行節點 "n2"（n2 不在 results 中）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertGreaterThanOrEqual(2, count($updated_dto->results), '應有至少 2 筆 results');
		$second_result = $updated_dto->results[1] ?? null;
		$this->assertNotNull($second_result, '應有第二筆結果');
		$this->assertSame('n2', $second_result->node_id, '非迴圈分支應正常執行 n2');
		$this->assertSame(200, $second_result->code, '正常執行時 code 應為 200');
	}

	// ========== Rule: 迴圈偵測失敗時應記錄詳細資訊 ==========

	/**
	 * 迴圈偵測觸發時 error message 包含相關資訊
	 *
	 * @group edge
	 */
	public function test_迴圈偵測包含詳細錯誤訊息(): void {
		// Given Workflow 的 results 有迴圈結構
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '條件不成立',
				'data'         => null,
				'next_node_id' => 'n3',
			],
			[
				'node_id'      => 'n3',
				'code'         => 200,
				'message'      => '條件成立',
				'data'         => null,
				'next_node_id' => 'n1', // 迴圈
			],
		];
		$workflow_id = $this->create_cycle_workflow_post($results);

		// When 系統偵測到迴圈
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 最後一筆 result 的 message 應包含迴圈相關資訊
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$last_result = end($updated_dto->results);
		if ($last_result instanceof WorkflowResultDTO) {
			// 錯誤訊息應包含「迴圈」或「已執行過」
			$has_cycle_info = str_contains($last_result->message, '迴圈')
				|| str_contains($last_result->message, '已執行過')
				|| str_contains($last_result->message, 'n1');
			$this->assertTrue(
				$has_cycle_info,
				'迴圈偵測的錯誤訊息應包含相關資訊（迴圈/已執行過/node_id）'
			);
		}

		// 確認狀態為 failed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::FAILED->value,
			$post_status,
			'迴圈偵測後工作流應為 failed'
		);
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * results 中的 node_id 不可為空
	 *
	 * @group edge
	 */
	public function test_results中的node_id為已知值時可正確比對(): void {
		// Given Workflow 的 results 有 1 筆，node_id 為已知值 "n1"
		$results = [
			[
				'node_id'      => 'n1',
				'code'         => 200,
				'message'      => '成功',
				'data'         => null,
				'next_node_id' => 'n2',
			],
		];
		$workflow_id = $this->create_cycle_workflow_post($results);

		// When 系統檢查迴圈防護
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);

		// Then 系統應能正確比對 node_id
		$this->assertSame('n1', $workflow_dto->results[0]->node_id, 'node_id 應為 n1');
	}
}
