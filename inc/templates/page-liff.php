<?php
/**
 * LIFF Page Template
 * 此模板可在子主題中覆寫，只需在主題目錄建立 page-liff.php 即可
 *
 * @package PowerFunnel
 */

declare(strict_types=1);

// 防止直接訪問
if (!defined('ABSPATH')) {
	exit;
}

$liff_style = \J7\PowerFunnel\Plugin::$url . '/js/dist/css/style.css?ver=' . \J7\PowerFunnel\Plugin::$version;

?>
<!DOCTYPE html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="<?php \bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<link rel='stylesheet' id='liff-css' href='<?php echo $liff_style; ?>' media='all' />
	<title><?php echo \bloginfo('name'); ?></title>
</head>
<body>
	<div id="power_funnel_liff_app"></div>
	
	<?php
	/**
	 * Prints any scripts and data queued for the footer.
	 *
	 * @since 2.8.0
	 */
	\do_action('wp_print_footer_scripts');

	?>
</body>
</html>

