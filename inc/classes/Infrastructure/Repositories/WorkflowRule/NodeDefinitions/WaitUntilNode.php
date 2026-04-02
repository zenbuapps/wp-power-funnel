<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 等待至節點定義 */
final class WaitUntilNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'wait_until';

	/** @var string Node 名稱 */
	public string $name = '等待至';

	/** @var string Node 描述 */
	public string $description = '等待至';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::ACTION;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'datetime' => new FormFieldDTO(
				[
					'name'        => 'datetime',
					'label'       => '目標日期時間',
					'type'        => 'date',
					'required'    => true,
					'description' => '等待至指定的日期時間',
					'sort'        => 0,
				]
			),
		];
	}

	/**
	 * 執行回調：等待至指定日期時間後繼續執行
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		// 取得目標日期時間字串（必填）
		$datetime_str = (string) ( $node->params['datetime'] ?? '' );
		if ( $datetime_str === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitUntilNode 執行失敗：缺少 datetime',
				]
			);
		}

		// 解析目標時間戳
		$timestamp = \strtotime( $datetime_str );
		if ( $timestamp === false ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitUntilNode 執行失敗：datetime 格式無法解析',
				]
			);
		}

		// 若指定時間已過，立即排程（使用當前時間）
		if ( $timestamp < \time() ) {
			$timestamp = \time();
		}

		// 使用 Action Scheduler 排程
		$action_id = \as_schedule_single_action(
			$timestamp,
			'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
			[ 'workflow_id' => $workflow->id ]
		);

		if ( ! $action_id ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitUntilNode 執行失敗：排程失敗',
				]
			);
		}

		return new WorkflowResultDTO(
			[
				'node_id'   => $node->id,
				'code'      => 200,
				'message'   => "等待至 {$datetime_str}",
				'scheduled' => true,
			]
		);
	}
}
