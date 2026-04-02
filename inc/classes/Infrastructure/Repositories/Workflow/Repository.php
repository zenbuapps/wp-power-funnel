<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;

/** Workflow CRUD  */
final class Repository {

	/**
	 * 建立因遞迴深度超過上限而失敗的 Workflow
	 *
	 * @param WorkflowRuleDTO      $workflow_rule_dto    工作流程規則
	 * @param array<string, mixed> $context_callable_set callable set array
	 * @param int                  $trigger_user_id      觸發此工作流的 WordPress 用戶 ID，0 代表系統自動觸發
	 * @return int workflow ID
	 */
	public static function create_failed_from_recursion_exceeded( WorkflowRuleDTO $workflow_rule_dto, array $context_callable_set = [], int $trigger_user_id = 0 ): int {
		$depth = \J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard::depth();
		\J7\PowerFunnel\Plugin::logger(
			"遞迴Guard：工作流建立遞迴深度超過上限（{$depth}），拒絕建立工作流規則 {$workflow_rule_dto->id}",
			'error',
			[
				'workflow_rule_id' => $workflow_rule_dto->id,
				'depth'            => $depth,
				'max_depth'        => \J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard::MAX_DEPTH,
			]
		);

		$args = [
			'post_name'   => 'workflow-failed-recursion-' . \time(),
			'post_status' => EWorkflowStatus::FAILED->value,
			'post_type'   => Register::post_type(),
			'post_author' => $trigger_user_id,
			'meta_input'  => [
				'workflow_rule_id'     => $workflow_rule_dto->id,
				'trigger_point'        => $workflow_rule_dto->trigger_point,
				'nodes'                => \array_map( static fn( $node ) => $node->to_array(), $workflow_rule_dto->nodes),
				'context_callable_set' => $context_callable_set,
				'results'              => [
					[
						'node_id' => 'recursion_guard',
						'code'    => 500,
						'message' => '工作流建立遞迴深度超過上限（最大 ' . \J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard::MAX_DEPTH . '）',
						'data'    => [ 'depth' => $depth ],
					],
				],
			],
		];

		/** @var int|\WP_Error $result */
		$result = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "建立遞迴失敗工作流程失敗: {$result->get_error_message()}" );
		}
		return $result;
	}

	/**
	 * 創建 workflow
	 * 執行 context_callable_set 裡面的 context_callable 並且帶入 context_callable_params 可以得到 WorkflowContextDTO
	 *
	 * @param WorkflowRuleDTO      $workflow_rule_dto    工作流程規則
	 * @param array<string, mixed> $context_callable_set callable set array
	 * @param int                  $trigger_user_id      觸發此工作流的 WordPress 用戶 ID，0 代表系統自動觸發
	 * @return int workflow ID
	 */
	public static function create_from( WorkflowRuleDTO $workflow_rule_dto, array $context_callable_set = [], int $trigger_user_id = 0 ): int {
		$args = [
			'post_name'   => 'workflow-' . \time(),
			'post_status' => EWorkflowStatus::RUNNING->value,
			'post_type'   => Register::post_type(),
			'post_author' => $trigger_user_id,
			'meta_input'  => [
				'workflow_rule_id'     => $workflow_rule_dto->id,
				'trigger_point'        => $workflow_rule_dto->trigger_point,
				'nodes'                => \array_map( static fn( $node ) => $node->to_array(), $workflow_rule_dto->nodes),
				'context_callable_set' => $context_callable_set,
				'results'              => [],
			],
		];

		/** @var int|\WP_Error $result */
		$result = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建工作流程失敗: {$result->get_error_message()}" );
		}
		return $result;
	}
}
