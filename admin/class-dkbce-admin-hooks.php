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
		$cogs_service    = new DKBCE_COGS_Service();
		add_filter( 'posts_where', array( $cogs_service, 'filter_category_where' ), 10, 2 );
		add_action( 'admin_menu', array( $admin_functions, 'register_submenu_page' ), 20 );
		add_filter( 'plugin_action_links_' . DKBCE_PLUGIN_BASENAME, array( $admin_functions, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $admin_functions, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $admin_functions, 'enqueue_assets' ) );
		add_action( 'wp_ajax_dkbce_get_products', array( $admin_functions, 'ajax_get_products' ) );
		add_action( 'wp_ajax_dkbce_import_status', array( $admin_functions, 'ajax_import_status' ) );
		add_action( 'wp_ajax_dkbce_import_report', array( $admin_functions, 'ajax_import_report' ) );
		add_action( 'wp_ajax_dkbce_download_status', array( $admin_functions, 'ajax_download_status' ) );
		add_action( 'wp_ajax_dkbce_download_cancel', array( $admin_functions, 'ajax_download_cancel' ) );
		add_action( 'wp_ajax_dkbce_download_file', array( $admin_functions, 'ajax_download_file' ) );
		add_action( 'wp_ajax_dkbce_csv_preview', array( $admin_functions, 'ajax_csv_preview' ) );
		add_action( 'wp_ajax_dkbce_csv_export', array( $admin_functions, 'ajax_csv_export' ) );
		add_action( 'wp_ajax_dkbce_preview', array( $admin_functions, 'ajax_preview' ) );
		add_action( 'wp_ajax_dkbce_preview_page', array( $admin_functions, 'ajax_preview_page' ) );
		add_action( 'wp_ajax_dkbce_select_range', array( $admin_functions, 'ajax_select_range' ) );
		add_action( 'wp_ajax_dkbce_preferences', array( $admin_functions, 'ajax_preferences' ) );
		add_action( 'wp_ajax_dkbce_apply', array( $admin_functions, 'ajax_apply' ) );
		add_action( 'wp_ajax_dkbce_progress', array( $admin_functions, 'ajax_progress' ) );
		add_action( 'wp_ajax_dkbce_cancel', array( $admin_functions, 'ajax_cancel' ) );
		add_action( 'wp_ajax_dkbce_clear_cancelled', array( $admin_functions, 'ajax_clear_cancelled' ) );
		add_action( 'wp_ajax_dkbce_clear_operation', array( $admin_functions, 'ajax_clear_cancelled' ) );
		add_action( 'woocommerce_product_data_store_cpt_get_products_query', array( $admin_functions, 'extend_product_query' ), 10, 2 );
		$downloads = new DKBCE_Download_Processor();
		add_action( DKBCE_Download_Processor::HOOK, array( $downloads, 'process' ) );
		add_action( DKBCE_Download_Processor::CLEANUP_HOOK, array( $downloads, 'cleanup' ) );
		new DKBCE_Retention_Processor();
		new DKBCE_History_Controller();
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
