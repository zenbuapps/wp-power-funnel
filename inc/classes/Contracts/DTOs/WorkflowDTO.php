<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\WpUtils\Classes\DTO;

/**
 * 多個 Node 會組合成一個 workflow
 */
final class WorkflowDTO extends DTO {

	/** @var string workflow ID */
	public string $id;

	/** @var string workflow 名稱 */
	public string $name;

	/** @var EWorkflowStatus workflow 狀態 */
	public EWorkflowStatus $status;

	/** @var string workflow rule ID 這個 workflow 是用哪個 rule 開出來的 */
	public string $workflow_rule_id;

	/** @var string 這個 workflow 應該掛在哪裡的 hook name */
	public string $trigger_point;

	/** @var array<int, NodeDTO> $nodes 這個 workflow 的節點 */
	public array $nodes;

	/** @var array<string, mixed> $context */
	public array $context = [];


	/** @var array<int, WorkflowResultDTO> 結果 index => WorkflowResultDTO */
	public array $results = [];

	/**
	 * 取得 context
	 *
	 * @return array<string, mixed> context array
	 */
	private static function get_context( string $post_id ): array {
		$context_callable_set = \get_post_meta( (int) $post_id, 'context_callable_set', true);
		$context_callable_set = \is_array($context_callable_set) ? $context_callable_set : [];
		$callable             = $context_callable_set['callable'] ?? null;
		$params               = $context_callable_set['params'] ?? [];
		if (\is_callable($callable) && \is_array($params)) {
			$result = \call_user_func_array($callable, $params);
			return \is_array($result) ? $result : [];
		}
		return [];
	}

	/** 取得實例 */
	public static function of( string $post_id ): self {
		$int_post_id   = (int) $post_id;
		$nodes_array   = \get_post_meta($int_post_id, 'nodes', true);
		$nodes_array   = \is_array($nodes_array) ? $nodes_array : [];
		$results_array = \get_post_meta($int_post_id, 'results', true);
		$results_array = \is_array($results_array) ? $results_array : [];

		$post_status = \get_post_status($int_post_id);
		$args        =[
			'id'               => $post_id,
			'name'             => \get_the_title($int_post_id),
			'status'           => EWorkflowStatus::from(\is_string($post_status) ? $post_status : ''),
			'workflow_rule_id' => (string) \get_post_meta($int_post_id, 'workflow_rule_id', true),
			'trigger_point'    => (string) \get_post_meta($int_post_id, 'trigger_point', true),
			'nodes'            => \array_values( NodeDTO::parse_array( $nodes_array )),
			'context'          => self::get_context($post_id),
			'results'          => \array_values( WorkflowResultDTO::parse_array( $results_array )),
		];
		return new self($args);
	}

	/**
	 * 檢查當前 workflow 要執行哪個 node
	 * 如果無須執行就設定狀態、返回
	 *
	 * 支援非線性執行：若上一個 result 帶有 next_node_id，
	 * 則跳轉到對應節點而非按線性順序執行。
	 *
	 * @return void
	 */
	public function try_execute(): void {
		if (EWorkflowStatus::RUNNING !== $this->status) {
			return;
		}

		$current_index = $this->get_current_index();
		if ($current_index === null) {
			// get_current_index 可能已將狀態設為 FAILED（迴圈偵測 / 目標節點不存在）
			if ($this->status === EWorkflowStatus::RUNNING) {
				$this->set_status( EWorkflowStatus::COMPLETED);
			}
			return;
		}
		try {
			$current_node = $this->get_node($current_index);
			$current_node->try_execute($this);
		} catch (\Throwable $e) {
			$this->set_status( EWorkflowStatus::FAILED);
		}
	}

	/** @return NodeDTO 取得 Node */
	public function get_node( int $index ): NodeDTO {
		return $this->nodes[ $index ] ?? throw new \Exception("workflow #{$this->id} 找不到節點 {$index}");
	}

	/** 查找 index */
	public function get_index( string $node_id ): int {
		foreach ($this->nodes as $index => $node) {
			if ($node->id === $node_id) {
				return $index;
			}
		}
		throw new \Exception("找不到節點 {$node_id}");
	}

	/**
	 * 決定下一個要執行的節點 index
	 *
	 * 支援非線性跳轉：
	 * 1. results 為空 → 執行第 0 個節點
	 * 2. 上一個 result 帶有 next_node_id → 跳轉到對應節點
	 *    - 迴圈偵測：若 next_node_id 已在 results 中出現過 → FAILED
	 *    - 目標不存在：找不到 next_node_id 對應的節點 → FAILED
	 * 3. 上一個 result 不帶 next_node_id：
	 *    - 若之前有分支（任一 result 帶 next_node_id）→ 分支結束，return null（completed）
	 *    - 否則按線性順序（向下相容）
	 *
	 * @return int|null 下一個要執行的節點 index，null 表示已完成或已失敗
	 */
	private function get_current_index(): int|null {
		$results_count = \count($this->results);

		// 尚無結果，從第 0 個開始
		if ($results_count === 0) {
			return \count($this->nodes) > 0 ? 0 : null;
		}

		/** @var WorkflowResultDTO $last_result */
		$last_result = \end($this->results);

		// 非線性跳轉：last result 帶有 next_node_id
		if ($last_result->next_node_id !== '') {
			$target_node_id = $last_result->next_node_id;

			// 迴圈偵測：檢查 target_node_id 是否已在 results 中出現過
			foreach ($this->results as $result) {
				if ($result->node_id === $target_node_id) {
					\J7\PowerFunnel\Plugin::logger(
						"WorkflowDTO #{$this->id}：偵測到節點迴圈：節點 {$target_node_id} 已執行過",
						'warning'
					);
					$this->add_result(
						$results_count,
						new WorkflowResultDTO(
							[
								'node_id' => $target_node_id,
								'code'    => 500,
								'message' => "偵測到節點迴圈：節點 {$target_node_id} 已執行過",
							]
						)
					);
					$this->set_status(EWorkflowStatus::FAILED);
					return null;
				}
			}

			// 目標節點查找
			try {
				return $this->get_index($target_node_id);
			} catch (\Throwable $e) {
				$this->add_result(
					$results_count,
					new WorkflowResultDTO(
						[
							'node_id' => $target_node_id,
							'code'    => 500,
							'message' => "找不到目標節點 {$target_node_id}",
						]
					)
				);
				$this->set_status(EWorkflowStatus::FAILED);
				return null;
			}
		}

		// 線性模式：last result 不帶 next_node_id
		// 檢查之前是否有分支（任一 result 帶 next_node_id）
		$has_branching = false;
		foreach ($this->results as $result) {
			if ($result->next_node_id !== '') {
				$has_branching = true;
				break;
			}
		}

		if ($has_branching) {
			// 分支模式中遇到不帶 next_node_id 的結果 → 分支路徑結束
			return null;
		}

		// 純線性模式（向下相容）
		$nodes_count = \count($this->nodes);
		if ($results_count >= $nodes_count) {
			return null;
		}
		return $results_count;
	}

	/** 添加結果，儲存進 db */
	public function add_result( int $index, WorkflowResultDTO $result ): void {
		$this->results[ $index ] = $result;
		$results_array           = \array_map( static fn( $r ) => $r->to_array(), $this->results );
		\update_post_meta( (int) $this->id, 'results', $results_array );
	}

	/** 設定狀態 */
	private function set_status( EWorkflowStatus $status ): void {
		\wp_update_post(
			[
				'ID'          => (int) $this->id,
				'post_status' => $status->value,
			]
			);
		$this->status = $status;
	}

	/** 執行下一個 */
	public function do_next(): void {
		$status = EWorkflowStatus::RUNNING;
		\do_action("power_funnel/workflow/{$status->value}", $this->id);
	}
}
