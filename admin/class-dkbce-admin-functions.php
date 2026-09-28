<?php
/**
 * Admin functions
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit; // Prevent direct file access.

/**
 * Admin functions.
 */
class DKBCE_Admin_Functions {
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
	}

	/**
	 * Register submenu page under Products.
	 *
	 * @return void
	 */
	public function register_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ),
			__( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ),
			'manage_woocommerce',
			'bulk-cogs-editor',
			array( $this, 'render_bulk_cogs_editor_page' ),
			2
		);
	}

	/**
	 * Render the Bulk COGS Editor page.
	 *
	 * @return void
	 */
	public function render_bulk_cogs_editor_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ) . '</h1></div>';
	}

	/**
	 * Creating an instance of this class.
	 *
	 * @return DKBCE_Admin_Functions|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}
}
