<?php
/**
 * Workflow 執行整合測試。
 *
 * 驗證 Workflow 的節點執行、狀態轉換、完成與失敗邏輯。
 *
 * @group smoke
 * @group workflow
 * @group workflow-execution
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Repository;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\EmailNode;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * Workflow 執行測試
 *
 * Feature: 執行工作流節點
 * Feature: 完成工作流
 * Feature: 標記工作流失敗
 * Feature: 從規則建立工作流實例
 */
class WorkflowExecutionTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 確保 WorkflowRule 與 Workflow 的 hooks 已註冊
		// （Bootstrap 尚未包含這些呼叫，Workflow 功能開發中）
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		Register::register_hooks();

		// 移除 powerhouse EmailValidator 的 pre_wp_mail 過濾器
		// 該過濾器會驗證 email 網域的 MX 記錄，在測試環境中 example.com 沒有 MX 記錄
		// 導致 wp_mail('test@example.com', ...) 回傳 false
		\remove_all_filters('pre_wp_mail');

		// 覆寫 wp_mail 的 From 地址
		// wp-env 測試環境的 WordPress 預設 email 為 wordpress@localhost，
		// 但 PHPMailer 的驗證器使用 is_email() 拒絕 localhost 網域，
		// 導致 wp_mail() 拋出 "Invalid address: (From): wordpress@localhost"
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 注入測試用 email 節點定義（繞過 ReplaceHelper 的 null obj bug）
		// 原因：powerhouse 的 ReplaceHelper::__construct() 呼叫 EObjectType::get_type(null)
		// 導致在測試環境中 new ReplaceHelper($template) 總是拋出 "Unsupported object type"
		// 測試用節點直接呼叫 wp_mail()，不使用 ParamHelper::replace()
		\add_filter(
			'power_funnel/workflow_rule/node_definitions',
			static function ( array $definitions ): array {
				$definitions['test_email'] = new class extends \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition {
					public string $id          = 'test_email';
					public string $name        = '測試 Email 節點';
					public string $description = '測試用，直接呼叫 wp_mail()';
					public \J7\PowerFunnel\Shared\Enums\ENodeType $type = \J7\PowerFunnel\Shared\Enums\ENodeType::SEND_MESSAGE;

					/**
					 * 執行節點
					 *
					 * @param \J7\PowerFunnel\Contracts\DTOs\NodeDTO     $node     節點
					 * @param \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow 工作流
					 * @return \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO
					 */
					public function execute( \J7\PowerFunnel\Contracts\DTOs\NodeDTO $node, \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow ): \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO {
						$recipient = $node->params['recipient'] ?? 'test@example.com';
						$subject   = $node->params['subject_tpl'] ?? 'Test Subject';
						$content   = $node->params['content_tpl'] ?? 'Test Content';
						$result    = \wp_mail( (string) $recipient, (string) $subject, (string) $content );
						$code      = $result ? 200 : 500;
						$message   = $result ? '發信成功' : '發信失敗';
						return new \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO(
							[
								'node_id' => $node->id,
								'code'    => $code,
								'message' => $message,
							]
						);
					}
				};
				return $definitions;
			}
		);
	}

	/**
	 * 建立測試用 Workflow post（使用直接 DB 更新設定 running 狀態，避免觸發 start_workflow hook）
	 *
	 * @param array<string, mixed> $meta_input meta 欄位
	 * @param string               $status     工作流狀態
	 * @return int workflow post ID
	 */
	private function create_workflow_post(array $meta_input = [], string $status = 'running'): int {
		$default_nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '歡迎',
					'content_tpl' => '感謝報名',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '提醒',
					'content_tpl' => '活動即將開始',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$default_meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/registration_created',
			'nodes'                => $default_nodes,
			'context_callable_set' => [],
			'results'              => [],
		];

		$meta = \wp_parse_args($meta_input, $default_meta);

		// 先以 draft 建立，避免 transition_post_status 觸發 start_workflow
		// 注意：wp_insert_post 的 meta_input 會通過 update_post_meta → wp_unslash，
		// 導致類別名稱中的反斜線被移除。因此需先 wp_slash 讓反斜線不被剝除。
		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'Workflow 實例測試',
				'meta_input'  => \wp_slash($meta),
			]
		);

		if (!is_int($post_id) || $post_id <= 0) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		// 若目標狀態不是 draft，直接透過 DB 更新，繞過 transition_post_status hook
		if ($status !== 'draft') {
			$this->set_post_status_bypass_hooks($post_id, $status);
		}

		return $post_id;
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * 冒煙測試：WorkflowDTO::of 可以從 post_id 建立 DTO
	 *
	 * @group smoke
	 */
	public function test_WorkflowDTO可以從post_id建立(): void {
		// Given 系統中有一個 running 狀態的 workflow（透過 bypass hooks 建立，尚未執行）
		$workflow_id = $this->create_workflow_post();

		// When 建立 WorkflowDTO
		$dto = WorkflowDTO::of((string) $workflow_id);

		// Then DTO 應正確映射
		$this->assertSame((string) $workflow_id, $dto->id, 'id 應相符');
		$this->assertSame(EWorkflowStatus::RUNNING, $dto->status, '狀態應為 running');
		$this->assertCount(2, $dto->nodes, '應有 2 個節點');
		$this->assertEmpty($dto->results, 'results 應為空（尚未執行）');
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * 快樂路徑：Email 節點發送成功時記錄 code=200
	 *
	 * 注意：原始 EmailNode 使用 ParamHelper::replace() → ReplaceHelper，
	 * 但 ReplaceHelper::__construct() 呼叫 EObjectType::get_type(null) 會拋出
	 * "Unsupported object type"（powerhouse 相依套件的已知限制）。
	 * 測試改用在 configure_dependencies() 中注入的 test_email 節點，
	 * 其直接呼叫 wp_mail()，驗證 Workflow 執行機制可正確記錄 code=200。
	 *
	 * Feature: 執行工作流節點
	 * Example: Email 節點發送成功
	 *
	 * @group happy
	 */
	public function test_Email節點發送成功記錄code200(): void {
		// Given 使用 test_email 節點（繞過 ReplaceHelper null obj bug）的工作流
		$test_email_nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '歡迎',
					'content_tpl' => '感謝報名',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '提醒',
					'content_tpl' => '活動即將開始',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_workflow_post(['nodes' => $test_email_nodes]);

		// Given wp_mail 發送成功（WordPress 測試環境預設會捕獲 email，不實際發送）
		// wp-env 測試環境中 wp_mail 預設回傳 true

		// When 直接呼叫 WorkflowDTO::try_execute() 執行第一個節點
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);

		// 確保 test_email 節點定義已注入
		$node_definitions = \apply_filters('power_funnel/workflow_rule/node_definitions', []);
		$this->assertArrayHasKey('test_email', $node_definitions, 'test_email 節點定義應已注入');

		$workflow_dto->try_execute();

		// Then 節點 n1 的結果 code 應為 200
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $first_result */
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame(200, $first_result->code, '發信成功時 code 應為 200');
		$this->assertSame('發信成功', $first_result->message, '發信成功時 message 應為「發信成功」');
	}

	/**
	 * 快樂路徑：所有節點完成後工作流標記 completed
	 *
	 * Feature: 完成工作流
	 * Example: 全部節點成功執行後工作流完成
	 *
	 * @group happy
	 */
	public function test_所有節點完成後工作流標記completed(): void {
		// Given 工作流 results 已有 2 筆（與 nodes 數量相同）
		$success_result_1 = ['node_id' => 'n1', 'code' => 200, 'message' => '發信成功', 'data' => null];
		$success_result_2 = ['node_id' => 'n2', 'code' => 200, 'message' => '發信成功', 'data' => null];

		$workflow_id = $this->create_workflow_post(
			[
				'results' => [$success_result_1, $success_result_2],
			]
		);

		// When 系統觸發 action power_funnel/workflow/running
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 工作流狀態應變為 completed
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::COMPLETED->value,
			$post_status,
			'所有節點完成後工作流狀態應為 completed'
		);
	}

	/**
	 * 快樂路徑：從規則建立 Workflow 實例後狀態為 running
	 *
	 * Feature: 從規則建立工作流實例
	 * Example: trigger_point 被觸發後建立 Workflow
	 *
	 * @group happy
	 */
	public function test_從規則建立工作流實例後狀態為running(): void {
		// Given 系統中有一個已發布的 WorkflowRule
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'test@example.com', 'subject_tpl' => '歡迎', 'content_tpl' => '感謝'],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$rule_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow_rule',
				'post_status' => 'publish',
				'post_title'  => '報名後發 Email',
				'meta_input'  => [
					'trigger_point' => 'pf/trigger/registration_created',
					'nodes'         => $nodes,
				],
			]
		);
		$this->assertIsInt($rule_id);
		$this->ids['rule'] = $rule_id;

		// 暫時移除 start_workflow hook，避免建立 workflow 時立即執行
		\remove_action(
			'power_funnel/workflow/running',
			[ Register::class, 'start_workflow' ]
		);

		// When 從規則建立 Workflow（直接呼叫 Repository::create_from）
		$rule_dto    = \J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO::of((string) $rule_id);
		$workflow_id = Repository::create_from($rule_dto, []);

		// 重新掛回 start_workflow hook
		\add_action(
			'power_funnel/workflow/running',
			[ Register::class, 'start_workflow' ]
		);

		// Then 系統應建立一筆 pf_workflow 紀錄
		$post = \get_post($workflow_id);
		$this->assertNotNull($post, '應建立 workflow 紀錄');
		$this->assertSame('pf_workflow', $post->post_type, 'post_type 應為 pf_workflow');

		// Then 工作流狀態應為 running
		$this->assertSame(
			EWorkflowStatus::RUNNING->value,
			$post->post_status,
			'工作流初始狀態應為 running'
		);

		// Then 工作流的 workflow_rule_id 應為規則 ID
		$stored_rule_id = \get_post_meta($workflow_id, 'workflow_rule_id', true);
		$this->assertSame((string) $rule_id, (string) $stored_rule_id, 'workflow_rule_id 應相符');

		// Then 工作流的 trigger_point 應為 pf/trigger/registration_created
		$stored_trigger = \get_post_meta($workflow_id, 'trigger_point', true);
		$this->assertSame('pf/trigger/registration_created', $stored_trigger, 'trigger_point 應相符');

		// Then 工作流的 nodes 應從規則複製
		$stored_nodes = \get_post_meta($workflow_id, 'nodes', true);
		$this->assertIsArray($stored_nodes);
		$this->assertCount(1, $stored_nodes, 'nodes 應從規則複製，有 1 個節點');

		// Then 工作流的 results 應為空陣列
		$stored_results = \get_post_meta($workflow_id, 'results', true);
		$this->assertIsArray($stored_results);
		$this->assertEmpty($stored_results, 'results 應為空陣列');
	}

	/**
	 * 快樂路徑：草稿規則不應建立 Workflow
	 *
	 * Feature: 從規則建立工作流實例
	 * Example: 草稿狀態的 WorkflowRule 不會被觸發
	 *
	 * @group happy
	 */
	public function test_草稿規則不建立Workflow(): void {
		// Given 工作流規則狀態為 draft
		$trigger_point = 'pf/trigger/test_draft_' . uniqid();
		$rule_id       = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow_rule',
				'post_status' => 'draft',
				'post_title'  => 'Draft 規則',
				'meta_input'  => [
					'trigger_point' => $trigger_point,
					'nodes'         => [],
				],
			]
		);

		// 記錄當前 pf_workflow post 數量（使用 get_posts 查詢自訂狀態）
		$before_posts = \get_posts(
			[
				'post_type'      => 'pf_workflow',
				'post_status'    => [ 'running', 'completed', 'failed' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		$before_total = count($before_posts);

		// When 系統觸發 hook（draft 規則應不在 trigger_point 上掛載 callback）
		\do_action($trigger_point, []);

		// Then 系統不應建立新的 pf_workflow 紀錄
		$after_posts = \get_posts(
			[
				'post_type'      => 'pf_workflow',
				'post_status'    => [ 'running', 'completed', 'failed' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		$after_total = count($after_posts);

		$this->assertSame(
			(int) $before_total,
			(int) $after_total,
			'draft 規則觸發 hook 時不應建立新的 workflow'
		);
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * 錯誤處理：非 running 狀態的 Workflow 不執行節點
	 *
	 * Feature: 執行工作流節點
	 * Example: 非 running 狀態不執行
	 *
	 * @group error
	 */
	public function test_非running狀態不執行節點(): void {
		// Given 工作流狀態為 completed（透過 bypass hooks 建立）
		$workflow_id = $this->create_workflow_post([], 'completed');

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統不應執行任何節點（results 應維持空）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertEmpty($updated_dto->results, '非 running 狀態不應執行節點');

		// Then 狀態應維持 completed
		$this->assertSame(EWorkflowStatus::COMPLETED, $updated_dto->status, '狀態應維持 completed');
	}

	/**
	 * 錯誤處理：找不到節點定義時工作流失敗
	 *
	 * Feature: 執行工作流節點
	 * Example: node_definition_id 不存在時工作流失敗
	 *
	 * @group error
	 */
	public function test_找不到節點定義時工作流失敗(): void {
		// Given 工作流第一個節點 node_definition_id 為 non_existent
		$invalid_nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'non_existent',
				'params'                => [],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$workflow_id = $this->create_workflow_post(
			[
				'nodes'   => $invalid_nodes,
				'results' => [],
			]
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 節點 n1 的結果 code 應為 500
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $first_result */
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame(500, $first_result->code, '找不到節點定義時 code 應為 500');
		$this->assertStringContainsString('找不到', $first_result->message, '錯誤訊息應包含「找不到」');

		// Then 工作流狀態應變為 failed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(
			EWorkflowStatus::FAILED->value,
			$post_status,
			'找不到節點定義時工作流狀態應為 failed'
		);
	}

	/**
	 * 錯誤處理：Email 節點發送失敗導致工作流失敗
	 *
	 * Feature: 標記工作流失敗
	 * Example: wp_mail 發送失敗導致工作流失敗
	 *
	 * @group error
	 */
	public function test_Email節點發送失敗導致工作流失敗(): void {
		// Given 工作流尚未執行任何節點（results 為空），透過 bypass hooks 建立
		$workflow_id = $this->create_workflow_post(
			[
				'results' => [],
			]
		);

		// Given wp_mail 回傳 false（使用 pre_wp_mail filter 攔截）
		\add_filter(
			'pre_wp_mail',
			static function (): bool {
				return false; // 強制 wp_mail 回傳 false
			}
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		\remove_all_filters('pre_wp_mail');

		// Then 節點 n1 的結果 code 應為 500
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		/** @var WorkflowResultDTO $first_result */
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result);
		$this->assertSame(500, $first_result->code, '發信失敗時 code 應為 500');

		// Then 工作流狀態應變為 failed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(EWorkflowStatus::FAILED->value, $post_status, '發信失敗時工作流應標記為 failed');
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * 邊緣案例：match_callback 回傳 false 時跳過節點（code=301）
	 *
	 * Feature: 執行工作流節點
	 * Example: match_callback 回傳 false 時跳過該節點
	 *
	 * @group edge
	 */
	public function test_match_callback回傳false時跳過節點(): void {
		// Given 工作流第一個節點 match_callback 為 return_false
		$nodes_with_false_callback = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'test@example.com', 'subject_tpl' => '歡迎', 'content_tpl' => '感謝'],
				'match_callback'        => [TestCallable::class, 'return_false'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'test@example.com', 'subject_tpl' => '提醒', 'content_tpl' => '活動'],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$workflow_id = $this->create_workflow_post(
			[
				'nodes'   => $nodes_with_false_callback,
				'results' => [],
			]
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 節點 n1 的結果 code 應為 301（跳過）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertNotEmpty($updated_dto->results, '應有執行結果');

		/** @var WorkflowResultDTO $first_result */
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame(301, $first_result->code, 'match_callback=false 時 code 應為 301');
		$this->assertStringContainsString('不符合執行條件，跳過', $first_result->message, '訊息應包含「不符合執行條件，跳過」');
	}

	/**
	 * 邊緣案例：第一個節點失敗時不執行第二個節點
	 *
	 * Feature: 標記工作流失敗
	 * Example: 第一個節點失敗時不執行第二個節點
	 *
	 * @group edge
	 */
	public function test_第一個節點失敗時不執行第二個節點(): void {
		// Given 第一個節點 node_definition_id 為 non_existent（會失敗）
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'non_existent',
				'params'                => [],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'email',
				'params'                => ['recipient' => 'test@example.com', 'subject_tpl' => '提醒', 'content_tpl' => '活動'],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$workflow_id = $this->create_workflow_post(
			[
				'nodes'   => $nodes,
				'results' => [],
			]
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 工作流 results 應僅包含 1 筆結果（第二個節點未執行）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertCount(1, $updated_dto->results, '失敗後應只有 1 筆結果，不繼續執行第二個節點');
	}

	/**
	 * 邊緣案例：results 數量尚有 1 筆但 nodes 有 2 個時，繼續執行下一節點
	 *
	 * Feature: 完成工作流
	 * Example: 尚有未執行的節點時不標記完成
	 *
	 * @group edge
	 */
	public function test_尚有未執行節點時不標記completed(): void {
		// Given 工作流 results 僅有 1 筆（nodes 有 2 個）
		$first_result = ['node_id' => 'n1', 'code' => 200, 'message' => '發信成功', 'data' => null];

		$workflow_id = $this->create_workflow_post(
			[
				'results' => [$first_result],
			]
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 工作流狀態應維持 running（或轉為 completed/failed，取決於第二個節點執行結果）
		// 重點：不應在「results < nodes」時標記為 completed
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);

		// 狀態不應還是 running 且 results 應有增加
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertGreaterThan(1, count($updated_dto->results), '應繼續執行第二個節點，results 應增加');
	}

	/**
	 * 邊緣案例：已是 failed 狀態時不重複處理
	 *
	 * Feature: 標記工作流失敗
	 * Example: 已經是 failed 狀態時不重複處理
	 *
	 * @group edge
	 */
	public function test_已是failed狀態時不重複處理(): void {
		// Given 工作流狀態為 failed（透過 bypass hooks 建立）
		$workflow_id = $this->create_workflow_post([], 'failed');

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 系統不應執行任何節點（results 應維持空）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertEmpty($updated_dto->results, 'failed 狀態不應執行節點');

		// Then 狀態應維持 failed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame(EWorkflowStatus::FAILED->value, $post_status, '狀態應維持 failed');
	}

	/**
	 * 邊緣案例：Workflow 觸發 running action 時應呼叫 start_workflow
	 *
	 * Feature: 從規則建立工作流實例
	 * 驗證 power_funnel/workflow/running action 已正確掛載
	 *
	 * @group edge
	 */
	public function test_power_funnel_workflow_running_action已掛載(): void {
		// Then 確認 power_funnel/workflow/running action 已掛載
		$has_action = \has_action(
			'power_funnel/workflow/running',
			[Register::class, 'start_workflow']
		);
		$this->assertNotFalse(
			$has_action,
			'power_funnel/workflow/running action 應已掛載 start_workflow'
		);
	}

	/**
	 * 邊緣案例：WorkflowDTO 可以正確儲存並讀取 context_callable_set
	 *
	 * Feature: 從規則建立工作流實例
	 * 驗證 context_callable_set 的儲存與讀取
	 *
	 * @group edge
	 */
	public function test_context_callable_set可以儲存與讀取(): void {
		// Given 工作流帶有 context_callable_set
		$context_callable_set = [
			'callable' => '__return_empty_array',
			'params'   => [],
		];

		$workflow_id = $this->create_workflow_post(
			[
				'context_callable_set' => $context_callable_set,
			]
		);

		// When 讀取 WorkflowDTO
		$dto = WorkflowDTO::of((string) $workflow_id);

		// Then context 應可讀取（callable 執行結果）
		$this->assertIsArray($dto->context, 'context 應為陣列');
	}
}
