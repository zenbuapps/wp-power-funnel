<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** Wait 節點定義 */
final class WaitNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'wait';

	/** @var string Node 名稱 */
	public string $name = '等待';

	/** @var string Node 描述 */
	public string $description = '等待';

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
			'duration' => new FormFieldDTO(
				[
					'name'        => 'duration',
					'label'       => '等待時間',
					'type'        => 'number',
					'required'    => true,
					'placeholder' => '1',
					'sort'        => 0,
					'validation'  => [
						[
							'rule'    => 'min',
							'value'   => 1,
							'message' => '等待時間必須大於 0',
						],
					],
				]
			),
			'unit'     => new FormFieldDTO(
				[
					'name'     => 'unit',
					'label'    => '時間單位',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'minutes',
							'label' => '分鐘',
						],
						[
							'value' => 'hours',
							'label' => '小時',
						],
						[
							'value' => 'days',
							'label' => '天',
						],
					],
				]
			),
		];
	}

	/**
	 * 執行回調：依 duration + unit 計算 Unix timestamp，並透過 Action Scheduler 排程延遲
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		// 支援的時間單位（秒數）對照表
		$unit_seconds = [
			'minutes' => 60,
			'hours'   => 3600,
			'days'    => 86400,
		];

		// 支援的時間單位（中文顯示名稱）對照表
		$unit_labels = [
			'minutes' => '分鐘',
			'hours'   => '小時',
			'days'    => '天',
		];

		// 驗證 duration 參數：必須存在且轉型後大於 0
		$duration_raw = (string) ( $node->params['duration'] ?? '' );
		$duration     = (int) $duration_raw;
		if ( $duration_raw === '' || $duration <= 0 ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitNode 執行失敗：duration 必須為大於 0 的整數',
				]
			);
		}

		// 驗證 unit 參數：必須存在且在支援清單中
		$unit = (string) ( $node->params['unit'] ?? '' );
		if ( $unit === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitNode 執行失敗：缺少 unit，支援 minutes / hours / days',
				]
			);
		}
		if ( ! \array_key_exists( $unit, $unit_seconds ) ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => "WaitNode 執行失敗：不支援的 unit「{$unit}」，支援 minutes / hours / days",
				]
			);
		}

		// 計算目標 Unix timestamp（相對於當前時間）
		$timestamp = \time() + $duration * $unit_seconds[ $unit ];

		// 透過 Action Scheduler 排程，到期後重新觸發 workflow 繼續執行
		$action_id = \as_schedule_single_action(
			$timestamp,
			'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
			[ 'workflow_id' => $workflow->id ]
		);

		// action_id 為 0 代表排程失敗（例如 AS 去重機制）
		if ( ! $action_id ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'WaitNode 執行失敗：排程失敗',
				]
			);
		}

		return new WorkflowResultDTO(
			[
				'node_id'   => $node->id,
				'code'      => 200,
				'message'   => "等待 {$duration} {$unit_labels[ $unit ]}",
				'scheduled' => true,
			]
		);
	}
}
