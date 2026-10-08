<?php
/**
 * Plugin Name: Bulk COGS Editor for WooCommerce
 * Plugin URI: https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce
 * Description: Allow bulk Cost of Goods Editing for products in WooCommerce.
 * Version: 1.0.0
 * Author: Dinesh Yadav
 * Author URI: https://dineshinaublog.wordpress.com/
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
 * Load plugin classes and initialize after all plugins are loaded.
 *
 * @return void
 */
function dkbce_load_plugin_files(): void {
	require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-cogs-service.php';
	require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-operation-store.php';
	require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-bulk-processor.php';
	require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-admin-functions.php';
	require_once DKBCE_PLUGIN_DIR . 'admin/class-dkbce-admin-hooks.php';
	DKBCE_Admin_Hooks::get_instance();
}
add_action( 'plugins_loaded', 'dkbce_load_plugin_files' );


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
