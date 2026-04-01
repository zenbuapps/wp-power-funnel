<?php

/**
 * YesNoBranchNode 表單欄位定義整合測試。
 *
 * 驗證 YesNoBranchNode 的 form_fields 包含正確的欄位定義，
 * 包含 condition_field、operator、condition_value、yes_next_node_id、no_next_node_id。
 *
 * @group workflow
 * @group node-system
 * @group yes-no-branch
 * @group yes-no-branch-form
 *
 * @see specs/order-trigger-and-branch-node/features/node-system/yes-no-branch-form-fields.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * YesNoBranchNode 表單欄位測試
 *
 * Feature: YesNoBranchNode 表單欄位定義
 */
class YesNoBranchFormFieldsTest extends IntegrationTestCase {

	/** @var BaseNodeDefinition|null */
	private ?BaseNodeDefinition $node_definition = null;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
	}

	/**
	 * 取得 yes_no_branch 節點定義（透過 filter 取得，避免直接 new 觸發 FormFieldDTO 載入失敗）
	 *
	 * @return BaseNodeDefinition
	 */
	private function get_node_definition(): BaseNodeDefinition {
		if ($this->node_definition !== null) {
			return $this->node_definition;
		}

		/** @var array<string, BaseNodeDefinition> $definitions */
		$definitions = \apply_filters('power_funnel/workflow_rule/node_definitions', []);
		$this->assertArrayHasKey('yes_no_branch', $definitions, 'yes_no_branch 節點定義應已註冊');
		$this->node_definition = $definitions['yes_no_branch'];
		return $this->node_definition;
	}

	// ========== Rule: form_fields 應包含 5 個欄位 ==========

	/**
	 * YesNoBranchNode 的 form_fields 包含 5 個欄位
	 *
	 * @group smoke
	 */
	public function test_form_fields包含5個欄位(): void {
		$definition = $this->get_node_definition();

		// When 系統讀取 YesNoBranchNode 的 form_fields
		$form_fields = $definition->form_fields;

		// Then form_fields 應包含 5 個欄位
		$this->assertCount(
			5,
			$form_fields,
			'YesNoBranchNode 的 form_fields 應包含 5 個欄位'
		);

		// 驗證所有必要欄位存在
		$expected_fields = [
			'condition_field'  => [ 'label' => '條件欄位', 'type' => 'select', 'required' => true ],
			'operator'         => [ 'label' => '運算子', 'type' => 'select', 'required' => true ],
			'condition_value'  => [ 'label' => '條件值', 'type' => 'text', 'required' => true ],
			'yes_next_node_id' => [ 'label' => '是分支目標節點', 'type' => 'text', 'required' => true ],
			'no_next_node_id'  => [ 'label' => '否分支目標節點', 'type' => 'text', 'required' => true ],
		];

		foreach ($expected_fields as $name => $expected) {
			$this->assertArrayHasKey(
				$name,
				$form_fields,
				"form_fields 應包含欄位: {$name}"
			);

			$field = $form_fields[ $name ];
			$this->assertSame(
				$expected['label'],
				$field->label,
				"欄位 {$name} 的 label 應為「{$expected['label']}」"
			);
			$this->assertSame(
				$expected['type'],
				$field->type,
				"欄位 {$name} 的 type 應為 {$expected['type']}"
			);
			$this->assertTrue(
				$field->required,
				"欄位 {$name} 應為必填"
			);
		}
	}

	// ========== Rule: condition_field 的選項應從觸發點 context keys API 動態帶入 ==========

	/**
	 * condition_field 為 select 類型
	 *
	 * @group happy
	 */
	public function test_condition_field為select類型(): void {
		$definition = $this->get_node_definition();
		$field      = $definition->form_fields['condition_field'] ?? null;

		// Then type 應為 "select"
		$this->assertNotNull($field, 'condition_field 欄位應存在');
		$this->assertSame(
			'select',
			$field->type,
			'condition_field 的 type 應為 select'
		);
	}

	// ========== Rule: operator 選項應包含所有支援的運算子 ==========

	/**
	 * operator 的 options 包含 10 種運算子
	 *
	 * @group happy
	 */
	public function test_operator包含10種運算子(): void {
		$definition = $this->get_node_definition();
		$field      = $definition->form_fields['operator'] ?? null;
		$this->assertNotNull($field, 'operator 欄位應存在');

		// Then options 應包含 10 種運算子
		$options = $field->options ?? [];
		$this->assertIsArray($options, 'options 應為陣列');

		$expected_operators = [
			'gt'           => '大於',
			'gte'          => '大於等於',
			'lt'           => '小於',
			'lte'          => '小於等於',
			'equals'       => '等於',
			'not_equals'   => '不等於',
			'contains'     => '包含',
			'not_contains' => '不包含',
			'is_empty'     => '為空',
			'is_not_empty' => '不為空',
		];

		$this->assertCount(
			10,
			$options,
			'operator 應有 10 種運算子選項'
		);

		// 驗證每個運算子都存在
		$option_values = \array_column($options, 'value');
		foreach ($expected_operators as $value => $label) {
			$this->assertContains(
				$value,
				$option_values,
				"operator options 應包含: {$value}（{$label}）"
			);
		}
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * 所有 5 個欄位的 required 都為 true
	 *
	 * @group happy
	 * @dataProvider required_fields_provider
	 */
	public function test_必填欄位驗證(string $field_name): void {
		$definition = $this->get_node_definition();
		$field      = $definition->form_fields[ $field_name ] ?? null;

		// Then 該欄位的 required 應為 true
		$this->assertNotNull($field, "欄位 {$field_name} 應存在");
		$this->assertTrue(
			$field->required,
			"欄位 {$field_name} 的 required 應為 true"
		);
	}

	/**
	 * 必填欄位清單
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function required_fields_provider(): array {
		return [
			'condition_field'  => [ 'condition_field' ],
			'operator'         => [ 'operator' ],
			'condition_value'  => [ 'condition_value' ],
			'yes_next_node_id' => [ 'yes_next_node_id' ],
			'no_next_node_id'  => [ 'no_next_node_id' ],
		];
	}
}
