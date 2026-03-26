<?php

declare ( strict_types = 1 );

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\EmailNode;

/** Class Register */
final class Register {

	private const POST_TYPE = 'pf_workflow_rule';

	/** 註冊 hooks */
	public static function register_hooks(): void {
		\add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		\add_action( 'init', [ __CLASS__, 'register_meta_fields' ] );
		\add_filter('power_funnel/workflow_rule/node_definitions', [ __CLASS__, 'register_default_node_definitions' ]);
	}

	/** Register cpt */
	public static function register_cpt(): void {

		$args = [
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		// @phpstan-ignore-next-line
		\register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * 註冊 meta 欄位，暴露至 REST API
	 */
	public static function register_meta_fields(): void {
		\register_post_meta(
			self::POST_TYPE,
			'trigger_point',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn() => \current_user_can( 'edit_posts' ),
			]
		);

		\register_post_meta(
			self::POST_TYPE,
			'nodes',
			[
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'node_module'    => [ 'type' => 'string' ],
								'node_type'      => [ 'type' => 'string' ],
								'sort'           => [ 'type' => 'integer' ],
								'args'           => [
									'type'                 => 'object',
									'additionalProperties' => true,
								],
								'match_callback' => [ 'type' => 'string' ],
							],
						],
					],
				],
				'auth_callback' => static fn() => \current_user_can( 'edit_posts' ),
			]
		);
	}

	/** 取得 post_type */
	public static function post_type(): string {
		return self::POST_TYPE;
	}


	/** @return bool 是否為活動報名 post */
	public static function match( \WP_Post $post ): bool {
		return $post->post_type === self::POST_TYPE;
	}

	/** 註冊所有已發布的 WorkflowRule */
	public static function register_workflow_rules(): void {
		/** @var array<WorkflowRuleDTO> $workflow_rules */
		$workflow_rules = Repository::get_publish_workflow_rules();
		foreach ($workflow_rules as $workflow_rule) {
			$workflow_rule->register();
		}
	}

	/**
	 * 註冊預設的 node definitions
	 *
	 * @param array<string, BaseNodeDefinition> $node_definitions 現有的 node definitions
	 * @return array<string, BaseNodeDefinition> 新增後的 node definitions
	 */
	public static function register_default_node_definitions( array $node_definitions ): array {
		$definitions = [
			new EmailNode(),
		];
		foreach ($definitions as $definition) {
			$node_definitions[ $definition->id ] = $definition;
		}
		return $node_definitions;
	}
}
