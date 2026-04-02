<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\TriggerPointDTO;
use J7\PowerFunnel\Contracts\DTOs\TriggerPointGroupDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Shared\Enums\EWorkflowRuleStatus;

/** WorkflowRule CRUD  */
final class Repository {

	/**
	 * 創建 workflow rule
	 *
	 * @param array<string, mixed> $args wp_insert_post 的參數
	 * @return int workflow rule ID
	 */
	public static function create( array $args = [] ): int {
		$default = [
			'post_status' => EWorkflowRuleStatus::DRAFT->value,
			'post_type'   => Register::post_type(),
		];
		$args    = \wp_parse_args($args, $default);
		/** @var int|\WP_Error $result */
		$result = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建工作流程規則失敗: {$result->get_error_message()}" );
		}
		return $result;
	}

	/**
	 * 查找已發佈的工作流程
	 *
	 * @param array<string, mixed> $args 查詢參數
	 * @return array<WorkflowRuleDTO> 工作流程規則
	 */
	public static function get_publish_workflow_rules( array $args = [] ): array {
		$default = [
			'posts_per_page' => -1,
			'post_status'    => EWorkflowRuleStatus::PUBLISH->value,
			'post_type'      => Register::post_type(),
			'fields'         => 'ids',
		];
		$args    = \wp_parse_args($args, $default);
		/** @var int[] $post_ids */
		$post_ids = \get_posts($args);
		return \array_map(static fn( $post_id ) => WorkflowRuleDTO::of( (string) $post_id ), $post_ids);
	}


	/**
	 * 查找已註冊的觸發時機點（分組結構）
	 *
	 * 流程：
	 * 1. 遍歷 ETriggerPoint::cases()，跳過已棄用的 REGISTRATION_CREATED
	 * 2. 枚舉存根設 disabled=true 並在 name 加上「（即將推出）」後綴
	 * 3. 以扁平格式（array<string, TriggerPointDTO>）通過 apply_filters 允許第三方擴充
	 * 4. filter 後再依 group() 分組，包裝成 TriggerPointGroupDTO[]
	 * 5. 依固定順序回傳：registration、line_interaction、line_group、workflow、activity、user_behavior、woocommerce
	 *
	 * 注意：apply_filters hook 簽名保持扁平格式，向後相容第三方 filter callback。
	 *
	 * @return TriggerPointGroupDTO[] 依固定順序排列的觸發點分組陣列
	 */
	public static function get_trigger_points(): array {
		/** @var array<string, TriggerPointDTO> $flat_dtos 扁平格式，供 filter 使用 */
		$flat_dtos = [];

		foreach ( ETriggerPoint::cases() as $enum ) {
			// 跳過已棄用的 REGISTRATION_CREATED
			if ( ETriggerPoint::REGISTRATION_CREATED === $enum ) {
				continue;
			}

			$trigger_point = $enum->value;
			$name          = $enum->label();
			$disabled      = $enum->is_stub();

			// 存根觸發點名稱加上「（即將推出）」後綴
			if ( $disabled ) {
				$name .= '（即將推出）';
			}

			$flat_dtos[ $trigger_point ] = new TriggerPointDTO(
				[
					'hook'     => $trigger_point,
					'name'     => $name,
					'disabled' => $disabled,
				]
			);
		}

		/**
		 * 允許第三方擴充觸發點清單（扁平格式，向後相容）
		 *
		 * @param array<string, TriggerPointDTO> $flat_dtos 觸發點 DTO 陣列，key 為 hook 值
		 */
		/** @var array<string, TriggerPointDTO> $filtered_dtos */
		$filtered_dtos = \apply_filters( 'power_funnel/workflow_rule/trigger_points', $flat_dtos );

		// 依 group() 將 DTO 分組
		/** @var array<string, TriggerPointDTO[]> $grouped */
		$grouped = [];
		foreach ( $filtered_dtos as $dto ) {
			// 僅對 ETriggerPoint 的值進行 group()，第三方擴充的觸發點可能無法呼叫 group()
			$case = ETriggerPoint::tryFrom( $dto->hook );
			if ( null !== $case && ETriggerPoint::REGISTRATION_CREATED !== $case ) {
				$group_key               = $case->group();
				$grouped[ $group_key ][] = $dto;
			} elseif ( ETriggerPoint::REGISTRATION_CREATED !== ETriggerPoint::tryFrom( $dto->hook ) ) {
				// 第三方擴充的觸發點，歸入 'woocommerce' 群組（或可根據需求調整）
				$grouped['woocommerce'][] = $dto;
			}
		}

		// 固定群組順序
		$group_order = [
			'registration',
			'line_interaction',
			'line_group',
			'workflow',
			'activity',
			'user_behavior',
			'woocommerce',
		];

		/** @var array<string, string> $group_label_map 群組 key => 中文標籤 */
		$group_label_map = [
			'registration'     => '報名狀態',
			'line_interaction' => 'LINE 互動',
			'line_group'       => 'LINE 群組',
			'workflow'         => '工作流引擎',
			'activity'         => '活動時間',
			'user_behavior'    => '用戶行為',
			'woocommerce'      => 'WooCommerce',
		];

		/** @var TriggerPointGroupDTO[] $result */
		$result = [];
		foreach ( $group_order as $group_key ) {
			$items = $grouped[ $group_key ] ?? [];
			if ( empty( $items ) ) {
				continue;
			}
			$result[] = new TriggerPointGroupDTO(
				[
					'group'       => $group_key,
					'group_label' => $group_label_map[ $group_key ],
					'items'       => $items,
				]
			);
		}

		return $result;
	}

	/**
	 * 查找已註冊的 node definitions
	 *
	 * @return array<string, BaseNodeDefinition> node definitions
	 */
	public static function get_node_definitions(): array {
		/** @var array<string, BaseNodeDefinition> $result */
		$result = \apply_filters( 'power_funnel/workflow_rule/node_definitions', []);
		return $result;
	}

	/**
	 * 查找指定的 node definition
	 *
	 * @param string $id node definition ID
	 * @return BaseNodeDefinition|null node definition
	 */
	public static function get_node_definition( string $id ): BaseNodeDefinition|null {
		$definitions = self::get_node_definitions();
		return $definitions[ $id ] ?? null;
	}
}
