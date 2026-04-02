<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
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
	 * 執行回調
	 * 執行最後呼叫 $workflow->do_next()
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		$param_helper = new ParamHelper( $node, $workflow );
		// TODO
		$timestamp = $param_helper->try_get_param( 'timestamp');

		$status = EWorkflowStatus::RUNNING;

		$action_id = \as_schedule_single_action(
			$timestamp,
			"power_funnel/workflow/{$status->value}",
			[
				'workflow_id' => $workflow->id,
			]
			);

		return new WorkflowResultDTO(
			[
				'node_id'   => $node->id,
				'code'      => $action_id ? 200 : 500,
				'message'   => $action_id ? '等待中' : '等待排程失敗',
				'scheduled' => (bool) $action_id,
			]
			);
	}
}
