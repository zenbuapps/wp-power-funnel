<?php

/**
 * ParamHelper 訂單變數替換整合測試。
 *
 * 驗證 ParamHelper::replace() 能正確處理 order 相關 {{variable}} 替換，
 * 以及 WooCommerce 未啟用或訂單不存在時的降級行為。
 *
 * @group workflow
 * @group order-trigger
 * @group param-helper
 *
 * @see specs/order-trigger-and-branch-node/features/trigger-point/order-param-helper.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * ParamHelper 訂單變數替換測試
 *
 * Feature: ParamHelper 訂單變數替換
 */
class OrderParamHelperTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/**
	 * 建立含有 order context 的 workflow
	 *
	 * @param array<string, string> $context workflow context
	 * @param array<string, mixed>  $node_params 節點參數
	 * @return int workflow post ID
	 */
	private function create_workflow_with_order_context(
		array $context = [],
		array $node_params = []
	): int {
		$default_context = [
			'order_id'    => '1001',
			'order_total' => '2500',
			'billing_email' => 'alice@example.com',
			'customer_id' => '42',
		];
		$context = \wp_parse_args($context, $default_context);

		$default_node_params = [
			'recipient'   => 'context',
			'subject_tpl' => '訂單 {{order_id}} 確認',
			'content_tpl' => '感謝您的購買，訂單金額為 {{order_total}} 元',
		];
		$node_params = \wp_parse_args($node_params, $default_node_params);

		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'email',
				'params'                => $node_params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		// 使用 TestCallable::return_test_context 作為可序列化的 context callable
		TestCallable::$test_context = $context;

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'ParamHelper 測試 Workflow',
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

	// ========== Rule: ParamHelper::replace() 應支援 order 相關 {{variable}} 替換 ==========

	/**
	 * 模板中的 {{order_id}} 被替換為實際訂單 ID
	 *
	 * @group happy
	 */
	public function test_order_id變數替換(): void {
		// Given context 中 order_id 為 "1001"
		$workflow_id = $this->create_workflow_with_order_context(
			[ 'order_id' => '1001' ],
			[ 'subject_tpl' => '訂單 {{order_id}} 確認' ]
		);
		$workflow = WorkflowDTO::of((string) $workflow_id);
		$node     = $workflow->nodes[0];
		$helper   = new ParamHelper($node, $workflow);

		// When 系統以 ParamHelper::replace() 處理 subject_tpl
		$result = $helper->replace('訂單 {{order_id}} 確認');

		// Then 結果應為 "訂單 1001 確認"
		$this->assertSame(
			'訂單 1001 確認',
			$result,
			'{{order_id}} 應被替換為實際訂單 ID'
		);
	}

	/**
	 * 模板中的 {{order_total}} 被替換為實際金額
	 *
	 * @group happy
	 */
	public function test_order_total變數替換(): void {
		// Given context 中 order_id 為 "1001"
		$workflow_id = $this->create_workflow_with_order_context(
			[ 'order_id' => '1001', 'order_total' => '2500' ],
			[ 'content_tpl' => '感謝您的購買，訂單金額為 {{order_total}} 元' ]
		);
		$workflow = WorkflowDTO::of((string) $workflow_id);
		$node     = $workflow->nodes[0];
		$helper   = new ParamHelper($node, $workflow);

		// When 系統以 ParamHelper::replace() 處理 content_tpl
		$result = $helper->replace('感謝您的購買，訂單金額為 {{order_total}} 元');

		// Then 結果應為 "感謝您的購買，訂單金額為 2500 元"
		$this->assertSame(
			'感謝您的購買，訂單金額為 2500 元',
			$result,
			'{{order_total}} 應被替換為實際金額'
		);
	}

	// ========== Rule: WC_Order 物件不存在時 context 變數仍可替換 ==========

	/**
	 * 訂單已刪除但 context 中有 order_id 時，{{order_id}} 仍被 context 替換
	 *
	 * 注意：context 變數取代（{{order_id}}）不依賴 WC_Order 物件是否存在。
	 * WC_Order 物件不存在只影響 ReplaceHelper 的物件取代（如 {{order.billing_first_name}}），
	 * 不影響 context 層級的 {{variable}} 取代。
	 *
	 * @group edge
	 */
	public function test_訂單不存在時context變數仍會被替換(): void {
		// Given context 中 order_id 為 "9999"（不存在的訂單）
		$workflow_id = $this->create_workflow_with_order_context(
			[ 'order_id' => '9999' ],
			[ 'subject_tpl' => '訂單 {{order_id}} 資訊' ]
		);
		$workflow = WorkflowDTO::of((string) $workflow_id);
		$node     = $workflow->nodes[0];
		$helper   = new ParamHelper($node, $workflow);

		// When 系統以 ParamHelper::replace() 處理模板
		$result = $helper->replace('訂單 {{order_id}} 資訊');

		// Then context 中有 order_id，即使訂單不存在也會被 context 變數替換
		$this->assertSame(
			'訂單 9999 資訊',
			$result,
			'context 中存在 order_id 時 {{order_id}} 應被替換為 order_id 值'
		);
	}

	// ========== Rule: context 中不存在的變數不應被替換 ==========

	/**
	 * 未知變數保留原樣
	 *
	 * @group edge
	 */
	public function test_未知變數保留原樣(): void {
		// Given 標準 context
		$workflow_id = $this->create_workflow_with_order_context();
		$workflow    = WorkflowDTO::of((string) $workflow_id);
		$node        = $workflow->nodes[0];
		$helper      = new ParamHelper($node, $workflow);

		// When 系統以 ParamHelper::replace() 處理含未知變數的模板
		$result = $helper->replace('訂單 {{unknown_field}} 資訊');

		// Then 結果應為 "訂單 {{unknown_field}} 資訊"
		$this->assertSame(
			'訂單 {{unknown_field}} 資訊',
			$result,
			'未知變數應保留原樣'
		);
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * recipient 為 "context" 時從 workflow.context 取值
	 *
	 * @group happy
	 */
	public function test_recipient為context時從workflow_context取值(): void {
		// Given recipient 為 "context"
		$workflow_id = $this->create_workflow_with_order_context(
			[ 'recipient' => 'alice@example.com' ],
			[ 'recipient' => 'context' ]
		);
		$workflow = WorkflowDTO::of((string) $workflow_id);
		$node     = $workflow->nodes[0];
		$helper   = new ParamHelper($node, $workflow);

		// When 系統呼叫 ParamHelper::try_get_param("recipient")
		$result = $helper->try_get_param('recipient');

		// Then 結果應從 workflow.context 中取得對應值
		$this->assertSame(
			'alice@example.com',
			$result,
			'recipient 為 context 時應從 workflow.context 取值'
		);
	}
}
