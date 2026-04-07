<?php

/**
 * Plugin Name:       Power Funnel
 * Plugin URI:        https://github.com/p9-cloud/wp-power-funnel
 * Description:       自動抓取 Youtube 直播場次，讓用戶可以透過 LINE 報名
 * Version:           0.0.1
 * Requires at least: 5.7
 * Requires PHP:      8.1
 * Author:            J7
 * Author URI:        https://github.com/j7-dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       power_funnel
 * Domain Path:       /languages
 * Tags: youtube, funnel, line, marketing, webinar
 */

declare(strict_types=1);

namespace J7\PowerFunnel;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (\class_exists('J7\PowerFunnel\Plugin')) {
	return;
}

// if (\wp_get_environment_type() === 'local') {
require_once __DIR__ . '/vendor/autoload.php';
// } else {
// 	require_once __DIR__ . '/vendor-prefixed/autoload.php';
// }

/**
 * Class Plugin
 */
final class Plugin
{
	use \J7\WpUtils\Traits\PluginTrait;
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// self::$template_page_names = [ '404' ];

		$this->required_plugins = [
			// [
			// 'name'     => 'WooCommerce',
			// 'slug'     => 'woocommerce',
			// 'required' => true,
			// 'version'  => '7.6.0',
			// ],
			// [
			// 'name'     => 'Powerhouse',
			// 'slug'     => 'powerhouse',
			// 'source'   => ''https://github.com/p9-cloud/wp-powerhouse/releases/latest/download/powerhouse.zip',
			// 'version'  => '3.0.0',
			// 'required' => true,
			// ],
		];

		$this->init(
			[
				'app_name'    => 'Power Funnel',
				'github_repo' => 'https://github.com/p9-cloud/wp-power-funnel',
				'callback'    => [Bootstrap::class, 'register_hooks'],
				'lc'          => false,
			]
		);
	}

	/**
	 * 印出 WC Logger
	 *
	 * @param string               $message     訊息
	 * @param string               $level       等級
	 * @param array<string, mixed> $args        參數
	 * @param int                  $trace_limit 堆疊深度
	 */
	public static function logger(string $message, string $level, array $args = [], $trace_limit = 0): void
	{
		\J7\WpUtils\Classes\WC::logger($message, $level, $args, self::$kebab, $trace_limit);
	}
}

Plugin::instance();
