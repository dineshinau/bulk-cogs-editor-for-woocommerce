<?php
/**
 * Plugin Name: Bulk COGS Editor for WooCommerce
 * Plugin URI: https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/
 * Description: Bulk edit WooCommerce product costs (COGS) with previews, background updates, CSV/XLSX import, history, and eligible rollback.
 * Version: 1.2.11
 * Author: Dinesh Yadav
 * Author URI: https://github.com/dineshinau/
 * Text Domain: bulk-cogs-editor-for-woocommerce
 * Domain Path: /languages
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 10.3
 * WC tested up to: 11.2
 * Requires Plugins: woocommerce
 *
 * Bulk COGS Editor for WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bulk COGS Editor for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Bulk COGS Editor for WooCommerce. If not, see <http://www.gnu.org/licenses/>.
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
define( 'DKBCE_VERSION', '1.2.1' );

/**
 * Plugin database schema version. Increase when table schemas change.
 */
define( 'DKBCE_DB_VERSION', '1.1.0' );

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

// Register the prefix-based loader before plugin classes are first referenced.
require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-autoloader.php';
DKBCE_Autoloader::register();

/**
 * Load plugin classes and initialize after all plugins are loaded.
 *
 * @return void
 */
function dkbce_load_plugin_files(): void {
	DKBCE_Admin_Hooks::get_instance();
}
add_action( 'plugins_loaded', 'dkbce_load_plugin_files' );

/**
 * Upgrade plugin database tables after a schema version change.
 *
 * @return void
 */
function dkbce_maybe_upgrade_database(): void {
	try {
		DKBCE_Database_Installer::maybe_upgrade();
	} catch ( Throwable $exception ) {
		dkbce_wc_log( 'Database schema upgrade failed: ' . $exception->getMessage() );
	}
}
add_action( 'admin_init', 'dkbce_maybe_upgrade_database' );


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

if ( ! function_exists( 'dkbce_wc_log' ) ) {
	/**
	 * Logger function for tracing and debugging.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	function dkbce_wc_log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$log_enabled = apply_filters( 'dkbce_is_log_enabled', true );

			if ( $log_enabled ) {
				$logger  = wc_get_logger();
				$done_by = wp_sprintf( ( /* translators: %s current user id */ esc_html__( 'Done by user ID: %s', 'bulk-cogs-editor-for-woocommerce' ) ), get_current_user_id() );
				$message = $done_by . ' -> ' . $message;

				$logger->log( 'info', $message, array( 'source' => 'dkbce' ) );
			}
		}
	}
}
