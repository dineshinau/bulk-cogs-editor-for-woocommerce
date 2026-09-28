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
