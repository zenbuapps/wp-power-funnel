<?php

/**
 * LINE Postback trigger_params 匹配測試。
 *
 * 驗證 WorkflowRuleDTO 的 trigger_params.postback_action 過濾機制：
 * - 設定 postback_action 時只匹配對應的 Postback
 * - 空的 postback_action 或無 trigger_params 時匹配所有 Postback
 *
 * @group trigger-points
 * @group line-trigger
 * @group line-postback
 * @group params-matching
 *
 * @see specs/line-trigger-expansion/features/trigger-point/fire-line-postback-received.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * LINE Postback trigger_params 匹配測試
 *
 * Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點（postback_action 過濾匹配）
 */
class LinePostbackParamsMatchingTest extends IntegrationTestCase {

	/** @var array<string, int> 工作流被觸發的計數 */
	private array $workflow_created_count = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/**
	 * 建立帶有 postback_action 過濾的 WorkflowRule post
	 *
	 * @param string $postback_action 要過濾的 postback action（空字串表示不過濾）
	 * @return int post ID
	 */
	private function create_postback_workflow_rule( string $postback_action = '' ): int {
		$trigger_params = $postback_action !== '' ? [ 'postback_action' => $postback_action ] : [];
		$trigger_meta   = [
			'hook'   => ETriggerPoint::LINE_POSTBACK_RECEIVED->value,
			'params' => $trigger_params,
		];

		$post_id = $this->factory()->post->create( [
			'post_type'   => 'pf_workflow_rule',
			'post_status' => 'publish',
			'post_title'  => "測試 Postback WorkflowRule ({$postback_action})",
		] );

		\update_post_meta( $post_id, 'trigger_point', $trigger_meta );
		\update_post_meta( $post_id, 'nodes', [] );

		return (int) $post_id;
	}

	/**
	 * 建立帶有 postback data 的 LINE Postback 事件
	 *
	 * @param string $postback_data postback data 字串
	 * @param string $user_id       LINE User ID
	 * @return \LINE\Webhook\Model\PostbackEvent
	 */
	private function make_postback_event( string $postback_data = '{"action":"register"}', string $user_id = 'U1234567890' ): \LINE\Webhook\Model\PostbackEvent {
		return \LINE\Webhook\Model\PostbackEvent::fromAssocArray( [
			'type'            => 'postback',
			'mode'            => 'active',
			'timestamp'       => (int) ( \microtime( true ) * 1000 ),
			'webhookEventId'  => 'test_event_' . \uniqid(),
			'replyToken'      => 'test_reply_token',
			'source'          => [ 'type' => 'user', 'userId' => $user_id ],
			'deliveryContext' => [ 'isRedelivery' => false ],
			'postback'        => [
				'data'   => $postback_data,
				'params' => [],
			],
		] );
	}

	// ========== Rule: postback_action 過濾匹配時應觸發對應 WorkflowRule ==========

	/**
	 * Feature: postback_action=register 匹配 register postback
	 * Example: WorkflowRule 設定 postback_action 過濾為 register 時僅匹配 register 的 Postback 觸發
	 *
	 * @group happy
	 */
	public function test_postback_action_register匹配register的postback(): void {
		// Given 一個 WorkflowRule，trigger_params.postback_action 為 "register"
		$rule_id = $this->create_postback_workflow_rule( 'register' );

		// 監聽 pf_workflow 建立
		$workflow_created = 0;
		\add_action( 'save_post_pf_workflow', function () use ( &$workflow_created ): void {
			$workflow_created++;
		} );

		// And 一個 WorkflowRuleDTO 已註冊到 hook
		$workflow_rule_dto = WorkflowRuleDTO::of( (string) $rule_id );
		$workflow_rule_dto->register();

		// When 系統收到 postback data 為 action=register 的事件
		$event = $this->make_postback_event( '{"action":"register","activity_id":"99"}' );
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then WorkflowRule 應被匹配並建立 Workflow 實例
		$this->assertGreaterThan( 0, $workflow_created, '應建立 Workflow 實例' );
	}

	/**
	 * Feature: postback_action=confirm 不匹配 register postback
	 * Example: WorkflowRule 設定 postback_action 過濾為 confirm 時不匹配 register 的 Postback
	 *
	 * @group edge
	 */
	public function test_postback_action_confirm不匹配register的postback(): void {
		// Given 一個 WorkflowRule，trigger_params.postback_action 為 "confirm"
		$rule_id = $this->create_postback_workflow_rule( 'confirm' );

		// 監聽 pf_workflow 建立
		$workflow_created = 0;
		\add_action( 'save_post_pf_workflow', function () use ( &$workflow_created ): void {
			$workflow_created++;
		} );

		// And 一個 WorkflowRuleDTO 已註冊到 hook
		$workflow_rule_dto = WorkflowRuleDTO::of( (string) $rule_id );
		$workflow_rule_dto->register();

		// When 系統收到 postback data 為 action=register 的事件
		$event = $this->make_postback_event( '{"action":"register","activity_id":"99"}' );
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then WorkflowRule 不應被匹配（不建立 Workflow）
		$this->assertSame( 0, $workflow_created, 'confirm 過濾不應匹配 register postback' );
	}

	// ========== Rule: postback_action 過濾為空時應觸發所有 Postback 事件 ==========

	/**
	 * Feature: 空的 postback_action 匹配所有 postback
	 * Example: WorkflowRule 未設定 postback_action 過濾時所有 Postback 都觸發
	 *
	 * @group happy
	 */
	public function test_空的trigger_params匹配所有postback(): void {
		// Given 一個 WorkflowRule，trigger_params 為空（不過濾 postback_action）
		$rule_id = $this->create_postback_workflow_rule( '' );

		// 監聽 pf_workflow 建立
		$workflow_created = 0;
		\add_action( 'save_post_pf_workflow', function () use ( &$workflow_created ): void {
			$workflow_created++;
		} );

		// And 一個 WorkflowRuleDTO 已註冊到 hook
		$workflow_rule_dto = WorkflowRuleDTO::of( (string) $rule_id );
		$workflow_rule_dto->register();

		// When 系統收到任意 postback 事件
		$event = $this->make_postback_event( '{"action":"any_action"}' );
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then WorkflowRule 應被匹配並建立 Workflow 實例
		$this->assertGreaterThan( 0, $workflow_created, '空 trigger_params 應匹配所有 postback' );
	}

	/**
	 * Feature: 無 trigger_params 匹配所有 postback
	 * Example: WorkflowRule 完全無 trigger_params 時所有 Postback 都觸發
	 *
	 * @group happy
	 */
	public function test_無trigger_params匹配所有postback(): void {
		// Given 一個 WorkflowRule，使用舊版純字串格式（無 trigger_params）
		$post_id = $this->factory()->post->create( [
			'post_type'   => 'pf_workflow_rule',
			'post_status' => 'publish',
			'post_title'  => '測試 Postback WorkflowRule (舊版格式)',
		] );

		\update_post_meta( $post_id, 'trigger_point', ETriggerPoint::LINE_POSTBACK_RECEIVED->value );
		\update_post_meta( $post_id, 'nodes', [] );

		// 監聽 pf_workflow 建立
		$workflow_created = 0;
		\add_action( 'save_post_pf_workflow', function () use ( &$workflow_created ): void {
			$workflow_created++;
		} );

		// And 一個 WorkflowRuleDTO 已註冊到 hook
		$workflow_rule_dto = WorkflowRuleDTO::of( (string) $post_id );
		$workflow_rule_dto->register();

		// When 系統收到任意 postback 事件
		$event = $this->make_postback_event( '{"action":"any_action"}' );
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then WorkflowRule 應被匹配並建立 Workflow 實例
		$this->assertGreaterThan( 0, $workflow_created, '無 trigger_params 應匹配所有 postback（向下相容）' );
	}
}
