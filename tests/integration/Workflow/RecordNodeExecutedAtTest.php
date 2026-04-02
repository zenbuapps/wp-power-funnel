<?php
/**
 * 記錄節點執行時間戳整合測試。
 *
 * 驗證 NodeDTO::try_execute() 執行後，WorkflowResultDTO 包含正確的 executed_at 時間戳。
 *
 * @group workflow
 * @group record-node-executed-at
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;
use J7\PowerFunnel\Tests\Integration\Workflow\Stubs\TestSuccessNodeDefinition;
use J7\PowerFunnel\Tests\Integration\Workflow\Stubs\TestFailNodeDefinition;

/**
 * 記錄節點執行時間戳測試
 *
 * Feature: 記錄節點執行時間戳
 */
class RecordNodeExecutedAtTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 只需要 Workflow\Register（負責 start_workflow、status hooks）
		// 不呼叫 WorkflowRule\Register::register_hooks()，因為它會載入 EmailNode 等節點定義，
		// 而 EmailNode 依賴 J7\Powerhouse\Contracts\DTOs\FormFieldDTO，在測試環境中找不到
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();

		// 移除 pre_wp_mail 過濾器，避免 MX 記錄驗證失敗
		\remove_all_filters('pre_wp_mail');

		// 覆寫 wp_mail From 地址，避免 localhost 網域驗證失敗
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 移除所有 node_definitions filter（包含 WorkflowRule\Register 的 register_default_node_definitions，
		// 後者嘗試建立 EmailNode，而 EmailNode 依賴 FormFieldDTO 在測試環境中找不到）
		\remove_all_filters('power_funnel/workflow_rule/node_definitions');

		// 注入測試用節點定義（不繼承 BaseNodeDefinition，避免 FormFieldDTO 找不到的問題）
		\add_filter(
			'power_funnel/workflow_rule/node_definitions',
			static function ( array $definitions ): array {
				$definitions['test_success_node'] = new TestSuccessNodeDefinition();
				$definitions['test_fail_node']    = new TestFailNodeDefinition();
				return $definitions;
			}
		);
	}

	/**
	 * 建立測試用 Workflow post
	 *
	 * @param array<string, mixed> $nodes 節點陣列
	 * @return int workflow post ID
	 */
	private function create_test_workflow( array $nodes ): int {
		$meta = [
			'workflow_rule_id'     => '10',
			'trigger_point'        => 'pf/trigger/registration_approved',
			'nodes'                => $nodes,
			'context_callable_set' => [],
			'results'              => [],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'executed_at 測試工作流',
				'meta_input'  => \wp_slash($meta),
			]
		);

		if (!is_int($post_id) || $post_id <= 0) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		// 直接透過 DB 設定 running 狀態，繞過 transition_post_status hook
		$this->set_post_status_bypass_hooks($post_id, 'running');

		return $post_id;
	}

	/**
	 * Feature: 記錄節點執行時間戳
	 * Rule: 後置（狀態）- 節點執行成功時 WorkflowResultDTO 應包含 executed_at 時間戳
	 * Example: 節點執行成功後結果包含 executed_at
	 */
	public function test_節點執行成功後結果包含executed_at(): void {
		// Given 系統中有一個 running 狀態的 Workflow，節點 n1 使用 test_success_node（永遠成功）
		$nodes       = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_success_node',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_test_workflow($nodes);

		// When 系統執行 Workflow 的節點 n1（結果為成功 code=200）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then Workflow 的 results 應包含 nodeId=n1, code=200, executedAt 非空
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $result */
		$result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($result, '應有第一個節點結果');
		$this->assertSame(200, $result->code, '節點執行成功時 code 應為 200');
		$this->assertNotEmpty($result->executed_at, 'executed_at 應為非空字串');
		// 驗證是合法的 ISO 8601 日期格式
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result->executed_at, 'executed_at 應為 ISO 8601 格式');
	}

	/**
	 * Feature: 記錄節點執行時間戳
	 * Rule: 後置（狀態）- 節點被跳過時 WorkflowResultDTO 應包含 executed_at 時間戳
	 * Example: 節點被跳過後結果包含 executed_at
	 */
	public function test_節點被跳過後結果包含executed_at(): void {
		// Given 系統中有一個 running 狀態的 Workflow，節點 n1 的 match_callback 永遠回傳 false（跳過）
		$nodes       = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_success_node',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_false' ],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_test_workflow($nodes);

		// When 系統執行 Workflow 的節點 n1（match_callback 不滿足，code=301）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then Workflow 的 results 應包含 nodeId=n1, code=301, executedAt 非空
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $result */
		$result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($result, '應有第一個節點結果');
		$this->assertSame(301, $result->code, '節點被跳過時 code 應為 301');
		$this->assertNotEmpty($result->executed_at, 'executed_at 應為非空字串（跳過路徑）');
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result->executed_at, 'executed_at 應為 ISO 8601 格式');
	}

	/**
	 * Feature: 記錄節點執行時間戳
	 * Rule: 後置（狀態）- 節點執行失敗時 WorkflowResultDTO 應包含 executed_at 時間戳
	 * Example: 節點執行失敗後結果包含 executed_at
	 */
	public function test_節點執行失敗後結果包含executed_at(): void {
		// Given 系統中有一個 running 狀態的 Workflow，節點 n1 使用 test_fail_node（永遠拋出例外）
		$nodes       = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_fail_node',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_test_workflow($nodes);

		// When 系統執行 Workflow 的節點 n1（執行過程中拋出例外，code=500）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then Workflow 的 results 應包含 nodeId=n1, code=500, executedAt 非空
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $result */
		$result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($result, '應有第一個節點結果');
		$this->assertSame(500, $result->code, '節點執行失敗時 code 應為 500');
		$this->assertNotEmpty($result->executed_at, 'executed_at 應為非空字串（失敗路徑）');
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result->executed_at, 'executed_at 應為 ISO 8601 格式');
	}

	/**
	 * Feature: 記錄節點執行時間戳
	 * Rule: 前置（參數）- 必要參數必須提供
	 * Example: 缺少 node_id 時記錄失敗
	 */
	public function test_缺少node_id時記錄失敗(): void {
		// Given / When 嘗試建立不含 node_id 的 WorkflowResultDTO
		try {
			$result          = new WorkflowResultDTO( [] ); // node_id 為必填
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
		}

		// Then 操作失敗，錯誤訊息應包含「node_id」相關提示
		$this->assert_operation_failed();
	}
}
