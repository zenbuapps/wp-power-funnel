<?php

declare (strict_types = 1);

namespace J7\PowerFunnel;

use J7\PowerFunnel\Compatibility\Compatibility;
use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO;
use J7\PowerFunnel\Infrastructure\Youtube\Services\YoutubeService;
use J7\PowerFunnel\Shared\Constants\App;
use Kucrut\Vite;


/** Class Bootstrap */
final class Bootstrap {

	/** Register hooks */
	public static function register_hooks(): void {
		Compatibility::register_hooks();
		Domains\Admin\Entry::register_hooks();
		Infrastructure\Repositories\PromoLink\Register::register_hooks();
		Infrastructure\Repositories\Registration\Register::register_hooks();
		Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		Infrastructure\Line\Services\Register::register_hooks();
		YoutubeService::instance();
		ActivityService::instance();
		Applications\SendLine::register_hooks();
		Applications\RegisterActivityViaLine::register_hooks();
		Applications\ActivityApi::register_hooks();
		Applications\WorkflowApi::register_hooks();
		Applications\OptionApi::register_hooks();
		Applications\TriggerPointApi::register_hooks();
		Applications\NodeDefinitionApi::register_hooks();
		Applications\ContextKeysApi::register_hooks();
		Domains\Workflow\Services\TriggerPointService::register_hooks();
		Domains\Workflow\Services\ActivitySchedulerService::register_hooks();

		// 在 init priority=99 時掛載所有已發布 WorkflowRule 的 hook 監聽器
		// priority=99 確保 CPT 與 meta 已先完成註冊
		\add_action( 'init', [ Infrastructure\Repositories\WorkflowRule\Register::class, 'register_workflow_rules' ], 99 );

		\add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_script' ] );
	}

	/**
	 * Admin Enqueue script
	 * You can load the script on demand
	 *
	 * @param string $hook current page hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_script( $hook ): void { // phpcs:ignore
		self::enqueue_script();
	}

	/**
	 * Enqueue script
	 * You can load the script on demand
	 *
	 * @return void
	 */
	public static function enqueue_script(): void {

		Vite\enqueue_asset(
			Plugin::$dir . '/js/dist',
			'js/src/main.tsx',
			[
				'handle'    => Plugin::$kebab,
				'in-footer' => true,
			]
		);

		$post_id   = \get_the_ID();
		$permalink = $post_id ? ( \get_permalink( $post_id ) ?: '' ) : '';

		/** @var array<string> $active_plugins */
		$active_plugins = \get_option( 'active_plugins', [] );

		$env = [
			'SITE_URL'                => \untrailingslashit( \site_url() ),
			'API_URL'                 => \untrailingslashit( \esc_url_raw( \rest_url() ) ),
			'CURRENT_USER_ID'         => \get_current_user_id(),
			'CURRENT_POST_ID'         => $post_id,
			'PERMALINK'               => \untrailingslashit( $permalink ),
			'APP_NAME'                => Plugin::$app_name,
			'KEBAB'                   => Plugin::$kebab,
			'SNAKE'                   => Plugin::$snake,
			'NONCE'                   => \wp_create_nonce( 'wp_rest' ),
			'APP1_SELECTOR'           => App::APP1_SELECTOR,
			'APP2_SELECTOR'           => App::APP2_SELECTOR,
			'ELEMENTOR_ENABLED'       => \in_array( 'elementor/elementor.php', $active_plugins, true ), // 檢查 elementor 是否啟用
			'LIFF_ID'                 => SettingDTO::instance()->liff_id,
			'IS_LOCAL'                => \wp_get_environment_type() === 'local',
			'PROMO_LINK_POST_TYPE'    => Infrastructure\Repositories\PromoLink\Register::post_type(),
			'REGISTRATION_POST_TYPE'  => Infrastructure\Repositories\Registration\Register::post_type(),
			'WORKFLOW_POST_TYPE'      => Infrastructure\Repositories\Workflow\Register::post_type(),
			'WORKFLOW_RULE_POST_TYPE' => Infrastructure\Repositories\WorkflowRule\Register::post_type(),

		];

		\wp_localize_script(
			Plugin::$kebab,
			Plugin::$snake . '_data',
			[
				'env' => $env,
			]
		);
	}
}
