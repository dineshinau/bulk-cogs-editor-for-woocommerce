<?php
/**
 * Plugin Name:       Bulk COGS Editor for WooCommerce
 * Plugin URI:        https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce
 * Description:       Allow bulk Cost of Goods Editing for products in WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Dinesh Yadav
 * Author URI:        https://dineshinaublog.wordpress.com/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       bulk-cogs-editor-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 *
 * @package BulkCOGSEditor
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version constant.
 */
define( 'DKBCE_VERSION', '1.0.0' );

/**
 * Plugin file path constant.
 */
define( 'DKBCE_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path constant (with trailing slash).
 */
define( 'DKBCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL constant (with trailing slash).
 */
define( 'DKBCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename constant.
 */
define( 'DKBCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum WooCommerce version required.
 */
define( 'DKBCE_MIN_WC_VERSION', '7.0' );

/**
 * Display an admin notice when WooCommerce is not active or the version is too low.
 *
 * @return void
 */
function dkbce_woocommerce_missing_notice(): void {
	$message = sprintf(
		/* translators: 1: Plugin name, 2: WooCommerce, 3: Minimum WooCommerce version. */
		esc_html__(
			'%1$s requires %2$s version %3$s or higher to be installed and active.',
			'bulk-cogs-editor-for-woocommerce'
		),
		'<strong>Bulk COGS Editor for WooCommerce</strong>',
		'<strong>WooCommerce</strong>',
		DKBCE_MIN_WC_VERSION
	);

	printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
}

/**
 * Check plugin requirements and initialise.
 *
 * Hooked on `plugins_loaded` so WooCommerce (if active) is already available.
 *
 * @return void
 */
function dkbce_init(): void {

	// Verify WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'dkbce_woocommerce_missing_notice' );
		return;
	}

	// Verify minimum WooCommerce version.
	if ( version_compare( WC_VERSION, DKBCE_MIN_WC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'dkbce_woocommerce_missing_notice' );
		return;
	}

	// Load plugin text domain for translations.
	load_plugin_textdomain(
		'bulk-cogs-editor-for-woocommerce',
		false,
		dirname( DKBCE_PLUGIN_BASENAME ) . '/languages'
	);

	// TODO: Load core plugin classes and hooks here.
}
add_action( 'plugins_loaded', 'dkbce_init' );

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 *
 * @return void
 */
function dkbce_declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			DKBCE_PLUGIN_FILE,
			true
		);
	}
}
add_action( 'before_woocommerce_init', 'dkbce_declare_hpos_compatibility' );

/**
 * Run on plugin activation.
 *
 * @return void
 */
function dkbce_activate(): void {
	// TODO: Add activation tasks (e.g. default options, capability grants).
}
register_activation_hook( DKBCE_PLUGIN_FILE, 'dkbce_activate' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function dkbce_deactivate(): void {
	// TODO: Add deactivation cleanup tasks.
}
register_deactivation_hook( DKBCE_PLUGIN_FILE, 'dkbce_deactivate' );
