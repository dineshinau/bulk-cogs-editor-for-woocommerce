<?php
/**
 * Native preset management list.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays the authenticated user's presets and management actions.
 */
class DKBCE_Preset_List_Table extends WP_List_Table {
	/**
	 * Current page supplied by the AJAX request.
	 *
	 * @var int
	 */
	private $preset_page = 1;

	/**
	 * Return the requested page.
	 *
	 * @return int
	 */
	public function get_pagenum() {
		return $this->preset_page;
	}

	/**
	 * Initialize the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'dkbce-preset',
				'plural'   => 'dkbce-presets',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column labels.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'bulk-cogs-editor-for-woocommerce' ),
			'created_at' => __( 'Created date', 'bulk-cogs-editor-for-woocommerce' ),
		);
	}

	/**
	 * Available bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'bulk-cogs-editor-for-woocommerce' ) );
	}

	/**
	 * Selection checkbox.
	 *
	 * @param array $item Preset row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		/* translators: %s: preset name. */
		return '<input type="checkbox" name="preset_ids[]" value="' . absint( $item['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'bulk-cogs-editor-for-woocommerce' ), $item['name'] ) ) . '" />';
	}

	/**
	 * Name and row actions.
	 *
	 * @param array $item Preset row.
	 * @return string
	 */
	protected function column_name( $item ) {
		$edit   = '<button type="button" class="button-link" data-preset-edit="' . absint( $item['id'] ) . '">' . esc_html__( 'Edit', 'bulk-cogs-editor-for-woocommerce' ) . '</button>';
		$delete = '<button type="button" class="button-link" data-preset-delete="' . absint( $item['id'] ) . '">' . esc_html__( 'Delete', 'bulk-cogs-editor-for-woocommerce' ) . '</button>';
		return '<strong>' . esc_html( $item['name'] ) . '</strong>' . $this->row_actions(
			array(
				'edit'   => $edit,
				'delete' => $delete,
			)
		);
	}

	/**
	 * Render the created date using the site timezone.
	 *
	 * @param array  $item Preset row.
	 * @param string $column Column name.
	 * @return string
	 */
	protected function column_default( $item, $column ) {
		return 'created_at' === $column ? esc_html( get_date_from_gmt( $item['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '';
	}

	/**
	 * Prepare owned rows for display; the endpoint supplies the page number.
	 *
	 * @param array $rows Owned preset rows.
	 * @param int   $page Requested page.
	 * @return void
	 */
	public function set_rows( $rows, $page ) {
		$this->preset_page     = min( max( 1, $page ), max( 1, (int) ceil( count( $rows ) / 20 ) ) );
		$page                  = $this->preset_page;
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'name' );
		$this->items           = array_slice( $rows, ( max( 1, $page ) - 1 ) * 20, 20 );
		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => 20,
			)
		);
	}
}
