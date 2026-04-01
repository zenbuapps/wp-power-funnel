<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/**
 * 是/否分支節點定義
 *
 * 根據 workflow.context 中的欄位值進行條件判斷，
 * 條件成立時走 yes 分支，不成立時走 no 分支。
 * 透過 WorkflowResultDTO.next_node_id 指定下一個要執行的節點。
 */
final class YesNoBranchNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'yes_no_branch';

	/** @var string Node 名稱 */
	public string $name = '是/否分支';

	/** @var string Node 描述 */
	public string $description = '根據條件判斷走是或否分支';

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
			'condition_field'  => new FormFieldDTO(
				[
					'name'        => 'condition_field',
					'label'       => '條件欄位',
					'type'        => 'select',
					'required'    => true,
					'description' => '選擇要比較的欄位（選項從觸發點 Context Keys API 動態載入）',
					'sort'        => 0,
				]
			),
			'operator'         => new FormFieldDTO(
				[
					'name'     => 'operator',
					'label'    => '運算子',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'gt',
							'label' => '大於',
						],
						[
							'value' => 'gte',
							'label' => '大於等於',
						],
						[
							'value' => 'lt',
							'label' => '小於',
						],
						[
							'value' => 'lte',
							'label' => '小於等於',
						],
						[
							'value' => 'equals',
							'label' => '等於',
						],
						[
							'value' => 'not_equals',
							'label' => '不等於',
						],
						[
							'value' => 'contains',
							'label' => '包含',
						],
						[
							'value' => 'not_contains',
							'label' => '不包含',
						],
						[
							'value' => 'is_empty',
							'label' => '為空',
						],
						[
							'value' => 'is_not_empty',
							'label' => '不為空',
						],
					],
				]
			),
			'condition_value'  => new FormFieldDTO(
				[
					'name'        => 'condition_value',
					'label'       => '條件值',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '比較值',
					'sort'        => 2,
				]
			),
			'yes_next_node_id' => new FormFieldDTO(
				[
					'name'     => 'yes_next_node_id',
					'label'    => '是分支目標節點',
					'type'     => 'text',
					'required' => true,
					'sort'     => 3,
				]
			),
			'no_next_node_id'  => new FormFieldDTO(
				[
					'name'     => 'no_next_node_id',
					'label'    => '否分支目標節點',
					'type'     => 'text',
					'required' => true,
					'sort'     => 4,
				]
			),
		];
	}

	/**
	 * 執行條件分支判斷
	 *
	 * 1. 驗證必要參數
	 * 2. 從 context 取得欄位值
	 * 3. 以指定運算子比較
	 * 4. 回傳 WorkflowResultDTO，帶有 next_node_id 指向 yes 或 no 分支
	 *
	 * @param NodeDTO     $node     節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		// 取得參數
		$condition_field  = (string) ( $node->params['condition_field'] ?? '' );
		$operator         = (string) ( $node->params['operator'] ?? '' );
		$condition_value  = (string) ( $node->params['condition_value'] ?? '' );
		$yes_next_node_id = (string) ( $node->params['yes_next_node_id'] ?? '' );
		$no_next_node_id  = (string) ( $node->params['no_next_node_id'] ?? '' );

		// 驗證必要參數
		if ($condition_field === '' || $operator === '' || $yes_next_node_id === '' || $no_next_node_id === '') {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => '必要參數未提供',
				]
			);
		}

		// 從 context 取得實際值（欄位不存在時預設為空字串）
		$actual_value = (string) ( $workflow->context[ $condition_field ] ?? '' );

		// 條件判斷
		$result       = self::evaluate_condition($actual_value, $operator, $condition_value);
		$next_node_id = $result ? $yes_next_node_id : $no_next_node_id;
		$message      = $result ? '條件成立' : '條件不成立';

		return new WorkflowResultDTO(
			[
				'node_id'      => $node->id,
				'code'         => 200,
				'next_node_id' => $next_node_id,
				'message'      => $message,
			]
		);
	}

	/**
	 * 條件評估
	 *
	 * 數值運算子（gt, gte, lt, lte）會將字串轉為 float 後比較。
	 * 字串運算子（equals, not_equals, contains, not_contains）直接比較字串。
	 * 空值運算子（is_empty, is_not_empty）檢查是否為空字串。
	 *
	 * @param string $actual_value    實際值
	 * @param string $operator        運算子
	 * @param string $condition_value 條件值
	 * @return bool 條件是否成立
	 */
	private static function evaluate_condition( string $actual_value, string $operator, string $condition_value ): bool {
		return match ($operator) {
			'gt'           => (float) $actual_value > (float) $condition_value,
			'gte'          => (float) $actual_value >= (float) $condition_value,
			'lt'           => (float) $actual_value < (float) $condition_value,
			'lte'          => (float) $actual_value <= (float) $condition_value,
			'equals'       => $actual_value === $condition_value,
			'not_equals'   => $actual_value !== $condition_value,
			'contains'     => \str_contains($actual_value, $condition_value),
			'not_contains' => !\str_contains($actual_value, $condition_value),
			'is_empty'     => $actual_value === '',
			'is_not_empty' => $actual_value !== '',
			default        => false,
		};
	}
}
