<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\WpUtils\Classes\DTO;

/**
 * 儲存的 Node 節點資料，傳入節點定義內執行
 * 用戶挑選編輯完 NodeDefinitionDTO 後，儲存成 Node 節點資料
 * 多個節點 Node 組合成 Workflow
 *
 * @see https://www.figma.com/board/dB8yHondvpK2RRXEQaHqc5/Untitled?node-id=2054-345&t=Q72I2mv43LqTBKIW-1
 */
final class NodeDTO extends DTO {


	// region callback 調用時屬性

	/** @var string Node ID */
	public string $id;

	/** @var array<string, mixed> key-value */
	public array $params = [];

	/** @var string Node ID */
	public string $node_definition_id;

	/** @var string|array<int, string> match callback 滿足條件，才會執行 callback， */
	public string|array $match_callback = '__return_true';

	/** @var array<string, mixed> match_callback_params 接受的參數 */
	public array $match_callback_params = [];

	// endregion callback 調用時屬性



	/** 驗證參數 */
	protected function validate(): void {
		parent::validate();
		if (!\is_array( $this->match_callback)) {
			throw new \InvalidArgumentException('match_callback must be array');
		}
	}

	/**
	 * 執行當前 node
	 * 如果不滿足條件就執行下個 node
	 *
	 * @return void
	 */
	public function try_execute( WorkflowDTO $workflow_dto ): void {
		$index = $workflow_dto->get_index( $this->id );
		try {
			// 1. 檢查是否可以執行
			if ( !$this->can_execute( $workflow_dto ) ) {
				// 如果不符合執行條件就跳過，透過 AS 排程下一個
				$workflow_dto->add_result(
				$index,
				new WorkflowResultDTO(
				[
					'node_id'     => $this->id,
					'code'        => 301,
					'message'     => "workflow #{$workflow_dto->id} node #{$this->id} 不符合執行條件，跳過",
					'executed_at' => $this->get_current_timestamp(),
				]
				)
					);

				\as_schedule_single_action(
					\time(),
					'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
					[ 'workflow_id' => $workflow_dto->id ]
				);
				return;
			}

			$definition = Repository::get_node_definition( $this->node_definition_id );
			if (!$definition) {
				throw new \RuntimeException("找不到 {$this->node_definition_id} 節點定義");
			}

			// 2. 執行，並添加結果
			$result = $definition->execute($this, $workflow_dto);
			if (!$result->is_success()) {
				throw new \RuntimeException($result->message);
			}
			// 記錄節點執行時間戳（Success path）
			// 以建構子方式傳入，確保 DTO immutability 不被破壞，同時保留所有原始欄位（含 scheduled）
			$result_with_ts = new WorkflowResultDTO(
				[
					'node_id'      => $result->node_id,
					'code'         => $result->code,
					'message'      => $result->message,
					'data'         => $result->data,
					'next_node_id' => $result->next_node_id,
					'executed_at'  => $this->get_current_timestamp(),
					'scheduled'    => $result->scheduled,
				]
			);
			$workflow_dto->add_result( $index, $result_with_ts );

			// 若節點未自行排程（延遲節點會設 scheduled=true），由引擎統一排程下一節點
			if ( !$result_with_ts->scheduled ) {
				\as_schedule_single_action(
					\time(),
					'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
					[ 'workflow_id' => $workflow_dto->id ]
				);
			}
		} catch (\Throwable $e) {
			// 如果這個節點執行失敗，就拋出，中斷 workflow，並標註為失敗
			$workflow_dto->add_result(
				$index,
				new WorkflowResultDTO(
					[
						'node_id'     => $this->id,
						'code'        => 500,
						'message'     => $e->getMessage(),
						'executed_at' => $this->get_current_timestamp(),
					]
				)
			);
			throw $e;
		}
	}

	/** 是否可以執行 */
	private function can_execute( WorkflowDTO $workflow_dto ): bool {
		if (!\is_callable( $this->match_callback )) {
			return false;
		}
		return \call_user_func( $this->match_callback, $workflow_dto, $this->match_callback_params );
	}

	/** 取得參數 */
	public function try_get_param( string $key ): mixed {
		return $this->params[ $key ] ?? null;
	}

	/**
	 * 取得當前 ISO 8601 時間戳字串，優先使用 wp_date()，fallback 為 gmdate()
	 *
	 * @return string ISO 8601 格式的時間字串，例如 2026-04-01T10:00:00+08:00
	 */
	private function get_current_timestamp(): string {
		return \wp_date( 'c' ) ?: \gmdate( 'c' );
	}
}
