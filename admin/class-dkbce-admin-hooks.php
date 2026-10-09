<?php
/**
 * Admin hooks
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit; // Prevent direct file access.

/**
 * Admin hooks.
 */
class DKBCE_Admin_Hooks {
	/**
	 * Instance variable.
	 *
	 * @var $ins ;
	 */
	private static $ins = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$admin_functions = DKBCE_Admin_Functions::get_instance();
		add_action( 'admin_menu', array( $admin_functions, 'register_submenu_page' ), 20 );
		add_filter( 'plugin_action_links_' . DKBCE_PLUGIN_BASENAME, array( $admin_functions, 'plugin_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_functions, 'enqueue_assets' ) );
		add_action( 'wp_ajax_dkbce_get_products', array( $admin_functions, 'ajax_get_products' ) );
		add_action( 'wp_ajax_dkbce_preview', array( $admin_functions, 'ajax_preview' ) );
		add_action( 'wp_ajax_dkbce_preview_page', array( $admin_functions, 'ajax_preview_page' ) );
		add_action( 'wp_ajax_dkbce_apply', array( $admin_functions, 'ajax_apply' ) );
		add_action( 'wp_ajax_dkbce_progress', array( $admin_functions, 'ajax_progress' ) );
		add_action( 'wp_ajax_dkbce_cancel', array( $admin_functions, 'ajax_cancel' ) );
	}

	/**
	 * Creating an instance of this class.
	 *
	 * @return DKBCE_Admin_Hooks|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}
}
