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
	const NONCE = 'dkbce_bulk_cogs';

	/** COGS filtering and calculations.
	 *
	 * @var DKBCE_COGS_Service
	 */
	private $service;

	/** Operation state persistence.
	 *
	 * @var DKBCE_Operation_Store
	 */
	private $store;

	/** Action Scheduler batch processor.
	 *
	 * @var DKBCE_Bulk_Processor
	 */
	private $processor;

	/** Admin page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';
	/**
	 * Instance variable.
	 *
	 * @var $ins ;
	 */
	private static $ins = null;

	/**
	 * Register submenu page under Products.
	 *
	 * @return void
	 */
	public function register_submenu_page(): void {
		$this->page_hook = add_submenu_page(
			'edit.php?post_type=product',
			__( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ),
			__( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ),
			'edit_others_products',
			'bulk-cogs-editor',
			array( $this, 'render_bulk_cogs_editor_page' ),
			2
		);
	}

	/**
	 * Configure shared services and asynchronous processing.
	 *
	 * @param DKBCE_COGS_Service    $service COGS service.
	 * @param DKBCE_Operation_Store $store Operation state store.
	 * @param DKBCE_Bulk_Processor  $processor Action Scheduler processor.
	 */
	public function __construct( $service, $store, $processor ) {
		$this->service   = $service;
		$this->store     = $store;
		$this->processor = $processor;
	}

	/**
	 * Add the settings link to the plugin action row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=bulk-cogs-editor' ) ) . '">' . __( 'Settings', 'bulk-cogs-editor-for-woocommerce' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add documentation and video links to this plugin's row metadata.
	 *
	 * @param array  $links Existing metadata links.
	 * @param string $file Plugin basename.
	 * @return array
	 */
	public function plugin_row_meta( array $links, string $file ): array {
		if ( DKBCE_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( 'https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Doc', 'bulk-cogs-editor-for-woocommerce' ) . '</a>';
		$links[] = '<a href="' . esc_url( 'https://youtu.be/sOEOg0xiqYs' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Video', 'bulk-cogs-editor-for-woocommerce' ) . '</a>';
		return $links;
	}

	/**
	 * Load assets only on this plugin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		$script_asset_path   = DKBCE_PLUGIN_DIR . 'assets/bulk-cogs-editor.asset.php';
		$script_asset        = file_exists( $script_asset_path ) ? require $script_asset_path : array();
		$script_version      = isset( $script_asset['version'] ) ? $script_asset['version'] : DKBCE_VERSION;
		$script_dependencies = isset( $script_asset['dependencies'] ) ? $script_asset['dependencies'] : array();

		wp_enqueue_style( 'dkbce-admin', DKBCE_PLUGIN_URL . 'assets/bulk-cogs-editor.css', array(), $script_version );
		wp_enqueue_script( 'dkbce-admin', DKBCE_PLUGIN_URL . 'assets/bulk-cogs-editor.js', $script_dependencies, $script_version, true );

		$operation_id = sanitize_key( get_user_meta( get_current_user_id(), 'dkbce_latest_operation', true ) );
		$operation    = $operation_id ? $this->store->get( $operation_id ) : false;
		if ( $operation && get_current_user_id() !== (int) $operation['user_id'] ) {
			$operation = false;
		}

		$import_id  = sanitize_key( get_user_meta( get_current_user_id(), 'dkbce_latest_import', true ) );
		$import_job = $import_id ? $this->store->get( $import_id ) : false;
		if ( ! $import_job || get_current_user_id() !== (int) $import_job['user_id'] || ! in_array( $import_job['status'], array( 'pending', 'processing', 'ready' ), true ) || ! empty( $import_job['applied_operation'] ) || get_option( 'dkbce_preview_claim_' . $import_id ) ) {
			$import_id = '';
		}

		wp_localize_script(
			'dkbce-admin',
			'DKBCE',
			array(
				'history'        => DKBCE_History_Controller::labels(),
				'importId'       => $import_id,
				'maxUploadSize'  => wp_max_upload_size(),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE ),
				'cogsAvailable'  => $this->service->is_cogs_available(),
				'operationId'    => $operation ? $operation_id : '',
				'operationMode'  => $operation ? $operation['action'] : '',
				'preferences'    => $this->get_preferences(),
				'previewLimit'   => DKBCE_COGS_Service::PREVIEW_LIMIT,
				'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
				'decimals'       => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
				'ui'             => array(
					'bulkTitle'       => __( 'Review cost changes', 'bulk-cogs-editor-for-woocommerce' ),
					'exportTitle'     => __( 'Preview export', 'bulk-cogs-editor-for-woocommerce' ),
					'importTitle'     => __( 'Review import', 'bulk-cogs-editor-for-woocommerce' ),
					'goToPage'        => __( 'Go to page', 'bulk-cogs-editor-for-woocommerce' ),
					'go'              => __( 'Go', 'bulk-cogs-editor-for-woocommerce' ),
					'clearWarning'    => __( 'This removes the stored cost from every selected product.', 'bulk-cogs-editor-for-woocommerce' ),
					'searchOptions'   => __( 'Search options', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of selected filter options. */
					'optionsSelected' => __( '%d selected', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: filter label and value. */
					'removeFilter'    => __( 'Remove filter: %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: original sample cost, 2: resulting sample cost. */
					'example'         => __( 'Example: %1$s becomes %2$s.', 'bulk-cogs-editor-for-woocommerce' ),
					'bulkEmpty'       => __( 'Find products, then choose a cost update. Compare current and new costs here before you confirm.', 'bulk-cogs-editor-for-woocommerce' ),
					'exportEmpty'     => __( 'Choose products to preview your export.', 'bulk-cogs-editor-for-woocommerce' ),
					'importEmpty'     => __( 'Upload a file to preview your cost changes.', 'bulk-cogs-editor-for-woocommerce' ),
					'emptyTitle'      => __( 'A clear view of every cost change', 'bulk-cogs-editor-for-woocommerce' ),
					'review'          => __( 'Review changes', 'bulk-cogs-editor-for-woocommerce' ),
					'createCSV'       => __( 'Create CSV', 'bulk-cogs-editor-for-woocommerce' ),
					'downloadCSV'     => __( 'Download CSV', 'bulk-cogs-editor-for-woocommerce' ),
					'operation'       => __( 'Operation', 'bulk-cogs-editor-for-woocommerce' ),
					'scope'           => __( 'Selection', 'bulk-cogs-editor-for-woocommerce' ),
					'coverage'        => __( 'Review coverage', 'bulk-cogs-editor-for-woocommerce' ),
					'persistence'     => __( 'Saved changes remain if you stop remaining updates.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'reviewed'        => __( '%d selected products reviewed; other selected products are calculated during processing.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'updateCount'     => __( 'Update %d products', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'reviewCount'     => __( 'Review %d changes', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'selected'        => __( '%d selected across all pages', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'pageSelected'    => __( '%d selected on this page', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of matching products. */
					'all'             => __( 'Select all %d matching products', 'bulk-cogs-editor-for-woocommerce' ),
					'allReviewed'     => __( 'All selected products have been reviewed.', 'bulk-cogs-editor-for-woocommerce' ),
					'exportTask'      => __( 'File export', 'bulk-cogs-editor-for-woocommerce' ),
					'clear'           => __( 'Clear selection', 'bulk-cogs-editor-for-woocommerce' ),
					'narrowed'        => __( 'Selection includes previous filter constraints.', 'bulk-cogs-editor-for-woocommerce' ),
					'reused'          => __( 'Using the completed operation’s selection with current costs. Edit filters to narrow it, or use Reset filters to start a new selection.', 'bulk-cogs-editor-for-woocommerce' ),
					'set'             => __( 'Set cost', 'bulk-cogs-editor-for-woocommerce' ),
					'clearCost'       => __( 'Clear cost', 'bulk-cogs-editor-for-woocommerce' ),
					'increase'        => __( 'Increase', 'bulk-cogs-editor-for-woocommerce' ),
					'decrease'        => __( 'Decrease', 'bulk-cogs-editor-for-woocommerce' ),
					'recent'          => __( 'Recent activity', 'bulk-cogs-editor-for-woocommerce' ),
					'details'         => __( 'View details', 'bulk-cogs-editor-for-woocommerce' ),
					'bulkTask'        => __( 'Bulk cost update', 'bulk-cogs-editor-for-woocommerce' ),
					'importTask'      => __( 'File import', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products or selected options. */
					'completed'       => __( '%d products updated', 'bulk-cogs-editor-for-woocommerce' ),
					'stale'           => __( 'Preview needs refreshing', 'bulk-cogs-editor-for-woocommerce' ),
					'staleHelp'       => __( 'These are previous results. Refresh the preview before selecting products or confirming.', 'bulk-cogs-editor-for-woocommerce' ),
					'manualLimit'     => __( 'Select up to 500 individual products, or use all matching products or a range.', 'bulk-cogs-editor-for-woocommerce' ),
					'fileColumns'     => __( 'CSV columns', 'bulk-cogs-editor-for-woocommerce' ),
					'format'          => __( 'CSV with a blank new_cogs column', 'bulk-cogs-editor-for-woocommerce' ),
					'page'            => __( 'Page', 'bulk-cogs-editor-for-woocommerce' ),
					'downloadReady'   => __( 'Your CSV is ready. Download it below.', 'bulk-cogs-editor-for-woocommerce' ),
				),
				'csv'            => array(
					'requestFailed'   => __( 'Request failed. Check the server upload limit or reload the page and try again.', 'bulk-cogs-editor-for-woocommerce' ),
					'preparing'       => __( 'Preparing import preview in the background. You can return to this page later.', 'bulk-cogs-editor-for-woocommerce' ),
					'readingStrings'  => __( 'Reading spreadsheet text…', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: rows checked, 2: estimated total rows. */
					'progressRows'    => __( 'Rows checked: %1$s of approximately %2$s.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: rows checked. */
					'progressUnknown' => __( 'Rows checked: %s. Total is being calculated.', 'bulk-cogs-editor-for-woocommerce' ),
					'progressLabel'   => __( 'Import preview progress', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: invalid row count. */
					'invalidCount'    => __( 'Invalid rows: %d. Showing the first 50; download the report for all errors.', 'bulk-cogs-editor-for-woocommerce' ),
					'retrying'        => __( 'Retrying status shortly. Reload this page if your session has expired.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelling'      => __( 'Cancelling import preparation…', 'bulk-cogs-editor-for-woocommerce' ),
					'importStopped'   => __( 'Import preparation stopped or this preview was already applied. Upload again to prepare a new preview.', 'bulk-cogs-editor-for-woocommerce' ),
					'selection'       => __( 'Only include products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ),
					'exportHelp'      => __( 'Review the products, then download the selected CSV columns. Export does not change costs. Keep this page open until the download is ready.', 'bulk-cogs-editor-for-woocommerce' ),
					'export'          => __( 'Create CSV', 'bulk-cogs-editor-for-woocommerce' ),
					'previewImport'   => __( 'Validate file', 'bulk-cogs-editor-for-woocommerce' ),
					'apply'           => __( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ),
					'file'            => __( 'Choose a CSV or XLSX file within the WordPress upload limit.', 'bulk-cogs-editor-for-woocommerce' ),
					'columns'         => __( 'Select at least one CSV column.', 'bulk-cogs-editor-for-woocommerce' ),
					'errors'          => __( 'Invalid rows are excluded from this preview and cannot be applied.', 'bulk-cogs-editor-for-woocommerce' ),
					'report'          => __( 'Download error report', 'bulk-cogs-editor-for-woocommerce' ),
					'row'             => __( 'Row', 'bulk-cogs-editor-for-woocommerce' ),
					'status'          => __( 'Status', 'bulk-cogs-editor-for-woocommerce' ),
					'valid'           => __( 'Ready', 'bulk-cogs-editor-for-woocommerce' ),
					'exporting'       => __( 'Preparing CSV… Keep this page open.', 'bulk-cogs-editor-for-woocommerce' ),
					'downloaded'      => __( 'CSV download ready.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelled'       => __( 'Export cancelled. No file was downloaded.', 'bulk-cogs-editor-for-woocommerce' ),
					'downloadFailed'  => __( 'The download failed. Please try again.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancel'          => __( 'Cancel export', 'bulk-cogs-editor-for-woocommerce' ),
				),
				'i18n'           => array(
					'confirmTitle'        => __( 'Confirm COGS changes', 'bulk-cogs-editor-for-woocommerce' ),
					'confirmButton'       => __( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ),
					'confirmTextOne'      => __( 'You are about to modify COGS for 1 product. This will change product cost data.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products that will be changed. */
					'confirmText'         => __( 'You are about to modify COGS for %d products. This will change product cost data.', 'bulk-cogs-editor-for-woocommerce' ),
					'confirmExportOne'    => __( 'The CSV will contain 1 selected product. Generating this file will not change product costs.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products selected for export. */
					'confirmExportText'   => __( 'The CSV will contain %d selected products. Generating this file will not change product costs.', 'bulk-cogs-editor-for-woocommerce' ),
					'confirmExportTitle'  => __( 'Confirm CSV export', 'bulk-cogs-editor-for-woocommerce' ),
					'loading'             => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previewLoading'      => __( 'Calculating preview…', 'bulk-cogs-editor-for-woocommerce' ),
					'pageLoading'         => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previousPage'        => __( 'Previous', 'bulk-cogs-editor-for-woocommerce' ),
					'nextPage'            => __( 'Next', 'bulk-cogs-editor-for-woocommerce' ),
					'paginationLabel'     => __( 'Product preview pages', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: first product number, 2: last product number, 3: total products. */
					'pageSummary'         => __( 'Showing %1$d–%2$d of %3$d products.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: current page, 2: total pages. */
					'pageNumber'          => __( 'Page %1$d of %2$d', 'bulk-cogs-editor-for-woocommerce' ),
					'noProducts'          => __( 'No products match the selected filters.', 'bulk-cogs-editor-for-woocommerce' ),
					'previewRequired'     => __( 'Get products to calculate the changes before applying them.', 'bulk-cogs-editor-for-woocommerce' ),
					'fetchProductsFirst'  => __( 'Fetch matching products in Step 1 before choosing a COGS action.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectRange'         => __( 'Select from all results', 'bulk-cogs-editor-for-woocommerce' ),
					'allResults'          => __( 'All', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of selected matching products. */
					'rangeSelected'       => __( '%d products selected across all pages.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectFirst'         => __( 'First', 'bulk-cogs-editor-for-woocommerce' ),
					'selectLast'          => __( 'Last', 'bulk-cogs-editor-for-woocommerce' ),
					'selectCount'         => __( 'Number of products to select', 'bulk-cogs-editor-for-woocommerce' ),
					'selectRows'          => __( 'Select', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelled'           => __( 'Operation cancelled. Products already processed remain changed.', 'bulk-cogs-editor-for-woocommerce' ),
					'columns'             => array( __( 'Select', 'bulk-cogs-editor-for-woocommerce' ), __( 'ID', 'bulk-cogs-editor-for-woocommerce' ), __( 'Product', 'bulk-cogs-editor-for-woocommerce' ), __( 'SKU', 'bulk-cogs-editor-for-woocommerce' ), __( 'Type', 'bulk-cogs-editor-for-woocommerce' ), __( 'Current COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'New COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'Change', 'bulk-cogs-editor-for-woocommerce' ) ),
					'filterHelp'          => __( 'Preview the products that will be included in the CSV export.', 'bulk-cogs-editor-for-woocommerce' ),
					'previewHelp'         => __( 'Review the products and selected CSV columns before generating the export.', 'bulk-cogs-editor-for-woocommerce' ),
					'productOne'          => __( 'product matches.', 'bulk-cogs-editor-for-woocommerce' ),
					'productMany'         => __( 'products match.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: total matches, 2: product count excluding variations, 3: variation count. */
					'matchCount'          => __( '%1$d matches: %2$d products and %3$d variations. Preview changes before applying.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: number of preview rows, 2: total matching products. */
					'showing'             => __( 'Showing first %1$d of %2$d matching products.', 'bulk-cogs-editor-for-woocommerce' ),
					'allShown'            => __( 'All matching products are shown.', 'bulk-cogs-editor-for-woocommerce' ),
					'noName'              => __( '(no name)', 'bulk-cogs-editor-for-woocommerce' ),
					'skipped'             => __( 'Skipped', 'bulk-cogs-editor-for-woocommerce' ),
					'updatedLabel'        => __( 'Products updated', 'bulk-cogs-editor-for-woocommerce' ),
					'skippedLabel'        => __( 'Products skipped', 'bulk-cogs-editor-for-woocommerce' ),
					'failedLabel'         => __( 'Products failed', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: formatted processed product count, 2: formatted total product count. */
					'progressSummary'     => __( '%1$s of %2$s products processed', 'bulk-cogs-editor-for-woocommerce' ),
					'queued'              => __( 'Waiting for a background worker…', 'bulk-cogs-editor-for-woocommerce' ),
					'queueDelayed'        => __( 'No progress has been reported for at least two minutes. Refresh status or cancel this operation. If it remains delayed, ask an administrator to check WooCommerce → Status → Scheduled Actions and the site cron runner.', 'bulk-cogs-editor-for-woocommerce' ),
					'queueUnavailable'    => __( 'Background processing is unavailable. Ask an administrator to restore Action Scheduler, then refresh status or cancel the operation.', 'bulk-cogs-editor-for-woocommerce' ),
					'progressUnavailable' => __( 'Status could not be refreshed. Background changes may still continue. Retry status or reload this page to reconnect.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshStatus'       => __( 'Refresh status', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshPreview'      => __( 'Refresh preview', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: local date and time of the latest progress checkpoint. */
					'lastProgress'        => __( 'Last progress reported: %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of product IDs collected for the snapshot. */
					'snapshotCount'       => __( '%d matching products collected. Product costs have not been changed yet.', 'bulk-cogs-editor-for-woocommerce' ),
					'processing'          => __( 'Processing COGS updates…', 'bulk-cogs-editor-for-woocommerce' ),
					'backgroundMessage'   => __( 'You can close this window or your browser. Updates will continue in the background. Return to this page later to view the results.', 'bulk-cogs-editor-for-woocommerce' ),
					'complete'            => __( 'COGS update completed.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: current operation status. */
					'operationStatus'     => __( 'COGS update %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: products processed, 2: total products, 3: percent complete, 4: successful updates, 5: skipped products, 6: failed products. */
					'processed'           => __( 'Processed %1$d of %2$d (%3$d%) · Success: %4$d · Skipped: %5$d · Failed: %6$d', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: elapsed duration in hours, minutes, and seconds. */
					'elapsedTime'         => __( 'Elapsed time: %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: approximate remaining duration or an estimating message. */
					'remainingTime'       => __( 'Approx. time remaining: %s', 'bulk-cogs-editor-for-woocommerce' ),
					'estimatingTime'      => __( 'Calculating…', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelOperation'     => __( 'Stop remaining updates', 'bulk-cogs-editor-for-woocommerce' ),
					'confirmCancel'       => __( 'Any changes already made will remain.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelling'          => __( 'Cancellation requested…', 'bulk-cogs-editor-for-woocommerce' ),
					'starting'            => __( 'Starting…', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: selected products remaining after a filter refresh. */
					'selectionPreserved'  => __( 'Previous selection narrowed to the current filters: %d products selected. Exclusions are preserved. Use Reset filters to start a new selection.', 'bulk-cogs-editor-for-woocommerce' ),
					'noSelection'         => __( 'Select at least one product in the preview table.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectPageProducts'  => __( 'Select all products on this page', 'bulk-cogs-editor-for-woocommerce' ),
					'badValue'            => __( 'Enter a valid non-negative value for this action.', 'bulk-cogs-editor-for-woocommerce' ),
					'onlySelected'        => __( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: product ID. */
					'ariaSelect'          => __( 'Select product %d', 'bulk-cogs-editor-for-woocommerce' ),
					'progressLabel'       => __( 'COGS update progress', 'bulk-cogs-editor-for-woocommerce' ),
					'snapshotting'        => __( 'Collecting matching products…', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %1$d: product ID, %2$s: safe error summary. */
					'productError'        => __( 'Product %1$d: %2$s', 'bulk-cogs-editor-for-woocommerce' ),
					'previewFreshness'    => __( 'Values shown on reviewed pages are checked again before starting and before each update. Unvisited products are calculated when processed. Changed reviewed products require a fresh preview and confirmation.', 'bulk-cogs-editor-for-woocommerce' ),
					'zeroClears'          => __( 'WooCommerce stores zero as Empty. Any selected cost calculated as zero will be cleared.', 'bulk-cogs-editor-for-woocommerce' ),
					'emptyValue'          => __( 'Empty', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: percentage change. */
					'deltaPercent'        => __( '(%s%)', 'bulk-cogs-editor-for-woocommerce' ),
					'clearedChange'       => __( 'COGS cleared', 'bulk-cogs-editor-for-woocommerce' ),
					'amountLabel'         => __( 'Amount', 'bulk-cogs-editor-for-woocommerce' ),
					'percentageLabel'     => __( 'Percentage', 'bulk-cogs-editor-for-woocommerce' ),
					'cogsLabel'           => __( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ),
					'noSku'               => __( '—', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersChanged'      => __( 'Filters changed. Get products again to refresh the matching set.', 'bulk-cogs-editor-for-woocommerce' ),
					'getProducts'         => __( 'Find products', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersReset'        => __( 'All filters reset.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectAll'           => __( 'Select all', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: positive formatted currency amount. */
					'deltaPositive'       => __( '+%s', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelledStatus'     => __( 'Operation cancelled.', 'bulk-cogs-editor-for-woocommerce' ),
					'clearCancelled'      => __( 'Clear cancellation state', 'bulk-cogs-editor-for-woocommerce' ),
					'failedStatus'        => __( 'Operation failed.', 'bulk-cogs-editor-for-woocommerce' ),
					'withErrorsStatus'    => __( 'Operation completed with errors.', 'bulk-cogs-editor-for-woocommerce' ),
					'completedStatus'     => __( 'Operation completed.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshing'          => __( 'COGS update completed.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshNow'          => __( 'Refresh now', 'bulk-cogs-editor-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render the Bulk COGS Editor page.
	 *
	 * @return void
	 */
	public function render_bulk_cogs_editor_page(): void {
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage product costs.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		$available      = $this->service->is_cogs_available();
		$categories     = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$brand_taxonomy = $this->service->get_brand_taxonomy();
		$brands         = $brand_taxonomy ? get_terms(
			array(
				'taxonomy'   => $brand_taxonomy->name,
				'hide_empty' => false,
			)
		) : array();
		$types          = $this->service->get_product_types();
		$column_labels  = array(
			'id'            => __( 'Product ID', 'bulk-cogs-editor-for-woocommerce' ),
			'sku'           => __( 'SKU', 'bulk-cogs-editor-for-woocommerce' ),
			'name'          => __( 'Product name', 'bulk-cogs-editor-for-woocommerce' ),
			'cogs'          => __( 'Current cost', 'bulk-cogs-editor-for-woocommerce' ),
			'regular_price' => __( 'Regular price', 'bulk-cogs-editor-for-woocommerce' ),
			'sale_price'    => __( 'Sale price', 'bulk-cogs-editor-for-woocommerce' ),
			'type'          => __( 'Product type', 'bulk-cogs-editor-for-woocommerce' ),
		);
		?>
		<div class="wrap dkbce-wrap">
			<header class="dkbce-hero">
				<span class="dkbce-brand-icon dashicons dashicons-database" aria-hidden="true"></span>
				<div><h1><?php esc_html_e( 'Bulk COGS (Cost of Goods Sold) Editor', 'bulk-cogs-editor-for-woocommerce' ); ?></h1><p><?php esc_html_e( 'Review and update product costs, or transfer costs with CSV and Excel files.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div>
				<details class="dkbce-help"><summary><?php esc_html_e( 'Help', 'bulk-cogs-editor-for-woocommerce' ); ?> (<span data-dkbce-help-title><?php esc_html_e( 'Bulk edit', 'bulk-cogs-editor-for-woocommerce' ); ?></span>)</summary><div class="dkbce-popover">
					<div data-dkbce-help="bulk">
						<strong><?php esc_html_e( 'Update product costs in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Use the filters to choose products, then click Find products. Leave a filter empty to include all its values.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Choose Set exact cost, Adjust existing cost, or Clear cost. For adjustments, choose a direction and a percentage or fixed amount. Enter a value unless you are clearing costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Compare current and new costs in the preview. All matching products across all pages are selected initially. Use the checkboxes or selection controls to choose which products to include.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Use the Review button to check the selected count and update details, then confirm the update. No costs are saved before confirmation.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
						</ol>
					</div>
					<div data-dkbce-help="export" hidden>
						<strong><?php esc_html_e( 'Export product costs in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Choose Export CSV and select the columns to include. A blank new_cogs column is always added for entering updated costs later.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Use the filters to choose products, then click Find products. Use Product type to include variable products, variations, or both.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Review the export preview. All matching products across all pages are selected initially. Use the checkboxes or selection controls to adjust the export selection.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Click Create CSV, confirm the export, and keep this page open until the download is ready. Exporting does not change product costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
						</ol>
					</div>
					<div data-dkbce-help="import" hidden>
						<strong><?php esc_html_e( 'Import product costs in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Choose Import CSV. Download the template or use an exported file. Enter updated costs in new_cogs and identify each product with id or sku.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Choose a CSV or XLSX file, then click Validate file. Use decimal points without currency symbols. Blank costs are skipped; zero clears the stored cost. Open File requirements for more details.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Wait for validation, then review the current and new costs and any reported issues. Use the preview checkboxes to choose which products to update.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Use the Review button to check the selected count and import details, then confirm the update. Validation alone does not save costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
						</ol>
					</div>
					<div data-dkbce-help="history" hidden>
						<strong><?php esc_html_e( 'Review history and roll back costs in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Use status, operation type, user, date, or search filters to find an operation, then click Filter. Search accepts an operation ID, note, or product ID.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Click View details to inspect the operation summary, filters, timing, and recorded product cost changes.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'For an eligible operation, click Review rollback and compare Current COGS (now) with Rollback to (original). The rollback policy in Settings determines whether later cost changes are overwritten or skipped.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Read the warning, select the confirmation checkbox, and click Confirm rollback. Follow the background progress in History; the completed rollback is recorded as a separate operation.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
						</ol>
					</div>
					<div data-dkbce-help="settings" hidden>
						<strong><?php esc_html_e( 'Configure history and rollback settings in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Choose how long to retain completed operation history and original product costs. Select 30 days, 90 days, or Custom days.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Remember that expired history is deleted automatically and cannot be rolled back. Reducing retention also affects existing history.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Choose whether new rollbacks overwrite current costs with recorded original costs or skip products whose costs changed after the original operation.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Click Save settings. The selected rollback behavior appears during review and remains fixed after a rollback starts.', 'bulk-cogs-editor-for-woocommerce' ); ?></li>
						</ol>
					</div>
					<p><?php esc_html_e( 'Need more guidance? Read the documentation or watch the video walkthrough.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
					<p><a href="<?php echo esc_url( 'https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Doc', 'bulk-cogs-editor-for-woocommerce' ); ?></a> | <a href="<?php echo esc_url( 'https://youtu.be/sOEOg0xiqYs' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Video', 'bulk-cogs-editor-for-woocommerce' ); ?></a></p>
				</div></details>
			</header>
			<hr class="wp-header-end">
			<?php if ( ! $available ) : ?>
				<div class="notice notice-error"><p>
				<?php
					printf(
						wp_kses(
							/* translators: %1$s: URL to the WooCommerce Features settings page. */
							__( 'WooCommerce Cost of Goods Sold is unavailable or disabled. Update WooCommerce to a version with the supported COGS product API and enable Cost of Goods Sold in <a href="%1$s">WooCommerce Features settings</a> before using this editor.', 'bulk-cogs-editor-for-woocommerce' ),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						),
						esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) )
					);
				?>
				</p></div>
			<?php endif; ?>
			<div id="dkbce-alert" class="notice" hidden role="status"><p></p></div>

			<div class="dkbce-workspace">
			<div class="dkbce-mode-switch" role="group" aria-label="<?php esc_attr_e( 'Editor mode', 'bulk-cogs-editor-for-woocommerce' ); ?>">
				<button type="button" class="button button-primary" data-dkbce-mode="bulk" aria-pressed="true"><?php esc_html_e( 'Bulk edit', 'bulk-cogs-editor-for-woocommerce' ); ?></button>
				<button type="button" class="button" data-dkbce-mode="csv" aria-pressed="false"><?php esc_html_e( 'CSV import / export', 'bulk-cogs-editor-for-woocommerce' ); ?></button>
				<button type="button" class="button" data-dkbce-mode="history" aria-pressed="false"><?php esc_html_e( 'History', 'bulk-cogs-editor-for-woocommerce' ); ?></button>
				<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
				<button type="button" class="button" data-dkbce-mode="settings" aria-pressed="false"><?php esc_html_e( 'Settings', 'bulk-cogs-editor-for-woocommerce' ); ?></button>
				<?php endif; ?>
			</div>

			<ol class="dkbce-workflow" aria-label="<?php esc_attr_e( 'Bulk edit progress', 'bulk-cogs-editor-for-woocommerce' ); ?>">
				<li aria-current="step"><span aria-hidden="true">1</span><div><strong><?php esc_html_e( 'Select products', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><small><?php esc_html_e( 'Find your selection', 'bulk-cogs-editor-for-woocommerce' ); ?></small></div></li>
				<li><span aria-hidden="true">2</span><div><strong><?php esc_html_e( 'Set cost update', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><small><?php esc_html_e( 'Choose an action and value', 'bulk-cogs-editor-for-woocommerce' ); ?></small></div></li>
				<li><span aria-hidden="true">3</span><div><strong><?php esc_html_e( 'Review & confirm', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><small><?php esc_html_e( 'Preview before applying', 'bulk-cogs-editor-for-woocommerce' ); ?></small></div></li>
			</ol>
			<div id="dkbce-history" hidden></div>
			<div id="dkbce-settings" hidden></div>
			<section id="dkbce-csv" class="dkbce-section dkbce-csv-section" hidden aria-labelledby="dkbce-csv-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-media-spreadsheet" aria-hidden="true"></i><span class="dkbce-step-number" aria-hidden="true">1</span><div><h2 id="dkbce-csv-title"><?php esc_html_e( 'File transfer', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Choose whether to export matching products or import updated costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<div class="dkbce-actions">
					<fieldset class="dkbce-csv-action">
						<legend class="screen-reader-text"><?php esc_html_e( 'CSV action', 'bulk-cogs-editor-for-woocommerce' ); ?></legend>
						<label><input type="radio" name="dkbce-csv-action" value="export" checked> <?php esc_html_e( 'Export CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></label>
						<label><input type="radio" name="dkbce-csv-action" value="import"> <?php esc_html_e( 'Import CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></label>
					</fieldset>
				</div>
				<div id="dkbce-csv-export-options">

					<p><?php esc_html_e( 'Use the filters below to choose products. Product type controls whether variable parents and variations are included. Export includes all matching pages. A blank new_cogs column is always added last. Enter your new costs there before importing.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
					<fieldset class="dkbce-csv-columns"><legend><?php esc_html_e( 'CSV columns', 'bulk-cogs-editor-for-woocommerce' ); ?></legend>
					<?php foreach ( DKBCE_CSV_Service::COLUMNS as $column ) : ?>
						<label><input type="checkbox" name="dkbce-csv-column" value="<?php echo esc_attr( $column ); ?>" checked> <?php echo esc_html( $column_labels[ $column ] ); ?></label>
					<?php endforeach; ?>
					</fieldset>
				</div>
				<div id="dkbce-csv-import-options" hidden>
					<p><button type="button" class="button-link" id="dkbce-csv-template"><?php esc_html_e( 'Download template', 'bulk-cogs-editor-for-woocommerce' ); ?></button></p>
					<p><?php esc_html_e( 'CSV or XLSX. Include new_cogs and either id or sku. Validate your file before changing costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></p><details class="dkbce-file-requirements"><summary><?php esc_html_e( 'File requirements', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><p><?php esc_html_e( 'UTF-8 CSV or XLSX (first worksheet, formulas replaced with values). No plugin row limit. Preview preparation runs in the background; review the paged results before applying. Required headers: new_cogs and id or sku. The cogs column shows current costs; only new_cogs is imported when present. ID takes priority; if both are supplied they must agree. Use decimal points without currency symbols. Blank costs are skipped; zero follows WooCommerce empty-cost behavior.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></details>
					<p>
					<?php
					/* translators: %s: site maximum upload size. */
					printf( esc_html__( 'Maximum upload size: %s (WordPress setting).', 'bulk-cogs-editor-for-woocommerce' ), esc_html( size_format( wp_max_upload_size() ) ) );
					?>
					</p>
					<div id="dkbce-import-progress" class="dkbce-operation" hidden><p id="dkbce-import-status" role="status" aria-live="polite"></p><p id="dkbce-import-count" class="dkbce-progress-count"></p><div id="dkbce-import-progress-track" class="dkbce-progress-track is-active"><progress id="dkbce-import-progress-bar" max="100" value="0" aria-label="<?php esc_attr_e( 'Import preview progress', 'bulk-cogs-editor-for-woocommerce' ); ?>"></progress><span class="dkbce-progress-highlight" aria-hidden="true"></span><span id="dkbce-import-percentage" class="dkbce-progress-percentage" aria-hidden="true">0%</span></div><div class="dkbce-operation-metrics"><p id="dkbce-import-time" class="dkbce-operation-time" aria-live="off"></p></div><button type="button" class="button"><?php esc_html_e( 'Cancel preparation', 'bulk-cogs-editor-for-woocommerce' ); ?></button></div>
					<label id="dkbce-csv-drop" for="dkbce-csv-file"><?php esc_html_e( 'Drop a CSV or XLSX file here or choose a file', 'bulk-cogs-editor-for-woocommerce' ); ?><input type="file" id="dkbce-csv-file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"></label><div id="dkbce-file-details" hidden><span></span><button type="button" class="button-link" id="dkbce-file-replace"><?php esc_html_e( 'Replace file', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-file-remove"><?php esc_html_e( 'Remove file', 'bulk-cogs-editor-for-woocommerce' ); ?></button></div>
				</div>
				<p id="dkbce-csv-preview-wrap"><button type="button" class="button button-primary" id="dkbce-csv-preview"><?php esc_html_e( 'Validate file', 'bulk-cogs-editor-for-woocommerce' ); ?></button></p>
				<div id="dkbce-csv-errors" hidden></div>
			</section>

			<section class="dkbce-section dkbce-filters-section" aria-labelledby="dkbce-filters-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-filter" aria-hidden="true"></i><span class="dkbce-step-number" aria-hidden="true">1</span><div><h2 id="dkbce-filters-title"><?php esc_html_e( 'Select products', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p id="dkbce-filter-help"><?php esc_html_e( 'Select one or more values per filter. Leave a filter empty to include all values.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div><div class="dkbce-actions dkbce-filter-actions"><button type="button" class="button button-primary" id="dkbce-get-products" <?php disabled( ! $available ); ?>><?php esc_html_e( 'Find products', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-clear-filters"><?php esc_html_e( 'Clear filters', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-reset"><?php esc_html_e( 'Start a new selection', 'bulk-cogs-editor-for-woocommerce' ); ?></button><span class="dkbce-product-status"><span id="dkbce-products-loading" role="status"></span><span id="dkbce-products-spinner" class="spinner" aria-hidden="true"></span><span id="dkbce-product-count" aria-live="polite"></span></span></div></div>
				<details id="dkbce-filter-panel" open><summary><?php esc_html_e( 'Product filters', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><div class="dkbce-grid dkbce-filter-grid">
					<p><label for="dkbce-search"><?php esc_html_e( 'Search', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-search" type="search" maxlength="100" aria-describedby="dkbce-search-help" placeholder="<?php esc_attr_e( 'Name, SKU or product identifier', 'bulk-cogs-editor-for-woocommerce' ); ?>"><span id="dkbce-search-help" class="description"><?php esc_html_e( 'Search by name, SKU, GTIN, ISBN, EAN or UPC.', 'bulk-cogs-editor-for-woocommerce' ); ?></span></p>
					<p><label for="dkbce-category"><?php esc_html_e( 'Product category', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-category" class="dkbce-multi-select" multiple size="4"><option value="0"><?php esc_html_e( 'All categories', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
					<?php
					if ( ! is_wp_error( $categories ) ) :
						foreach ( $categories as $category ) :
							?>
						<option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name . ' (' . number_format_i18n( $category->count ) . ')' ); ?></option>
							<?php
						endforeach;
					endif;
					?>
					</select></p>
					<p><label for="dkbce-type"><?php esc_html_e( 'Product type', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-type" class="dkbce-multi-select" multiple size="4"><option value="any"><?php esc_html_e( 'All product types', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
					<?php
					foreach ( $types as $type => $label ) :
						?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
				</div><details class="dkbce-more-filters"><summary><?php esc_html_e( 'More filters', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><div class="dkbce-grid dkbce-filter-grid">
					<p><label for="dkbce-stock"><?php esc_html_e( 'Stock status', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-stock" class="dkbce-multi-select" multiple size="4"><option value="any"><?php esc_html_e( 'Any stock status', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="instock"><?php esc_html_e( 'In stock', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="outofstock"><?php esc_html_e( 'Out of stock', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="onbackorder"><?php esc_html_e( 'On backorder', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select></p>
					<?php
					if ( $brand_taxonomy ) :
						?>
						<p><label for="dkbce-brand"><?php echo esc_html( $brand_taxonomy->labels->singular_name ); ?></label><select id="dkbce-brand" class="dkbce-multi-select" multiple size="4"><option value="0"><?php esc_html_e( 'All brands', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
						<?php
						if ( ! is_wp_error( $brands ) ) :
							foreach ( $brands as $brand ) :
								?>
								<option value="<?php echo esc_attr( $brand->term_id ); ?>"><?php echo esc_html( $brand->name ); ?></option>
								<?php
							endforeach;
						endif;
						?>
				</select></p><?php endif; ?>
					<fieldset class="dkbce-range"><legend><?php esc_html_e( 'Price range (current price)', 'bulk-cogs-editor-for-woocommerce' ); ?></legend><label class="screen-reader-text" for="dkbce-price-min"><?php esc_html_e( 'Minimum price', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-price-min" type="number" min="0" step="any" placeholder="<?php esc_attr_e( 'Min price', 'bulk-cogs-editor-for-woocommerce' ); ?>"><span aria-hidden="true">–</span><label class="screen-reader-text" for="dkbce-price-max"><?php esc_html_e( 'Maximum price', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-price-max" type="number" min="0" step="any" placeholder="<?php esc_attr_e( 'Max price', 'bulk-cogs-editor-for-woocommerce' ); ?>"></fieldset>
					<fieldset class="dkbce-range"><legend><?php esc_html_e( 'Current COGS range', 'bulk-cogs-editor-for-woocommerce' ); ?></legend><label class="screen-reader-text" for="dkbce-cogs-min"><?php esc_html_e( 'Minimum COGS', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-cogs-min" type="number" min="0" step="any" placeholder="<?php esc_attr_e( 'Min COGS', 'bulk-cogs-editor-for-woocommerce' ); ?>"><span aria-hidden="true">–</span><label class="screen-reader-text" for="dkbce-cogs-max"><?php esc_html_e( 'Maximum COGS', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-cogs-max" type="number" min="0" step="any" placeholder="<?php esc_attr_e( 'Max COGS', 'bulk-cogs-editor-for-woocommerce' ); ?>"></fieldset>
				</div>
</details></details><div id="dkbce-filter-chips" aria-live="polite"></div>
			</section>

			<section class="dkbce-section dkbce-action-section" aria-labelledby="dkbce-action-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-admin-generic" aria-hidden="true"></i><span aria-hidden="true">2</span><div><h2 id="dkbce-action-title"><?php esc_html_e( 'Set your cost update', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Select how you want to update COGS for matching products.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>

				<p id="dkbce-action-prerequisite" class="description" role="status"><?php esc_html_e( 'Start with your products. Cost update options unlock when products are found.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
				<div class="dkbce-action-fields"><p><label for="dkbce-action-kind"><?php esc_html_e( 'Update action', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-action-kind" aria-describedby="dkbce-action-prerequisite"><option value="set"><?php esc_html_e( 'Set exact cost', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="adjust"><?php esc_html_e( 'Adjust existing cost', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="clear"><?php esc_html_e( 'Clear cost', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select></p><p><label for="dkbce-action-direction"><?php esc_html_e( 'Direction', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-action-direction"><option value="increase"><?php esc_html_e( 'Increase', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="decrease"><?php esc_html_e( 'Decrease', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select></p><p><label for="dkbce-action-unit"><?php esc_html_e( 'Change by', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-action-unit"><option value="percent"><?php esc_html_e( 'Percentage', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="fixed"><?php esc_html_e( 'Fixed amount', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select></p></div>
				<div class="dkbce-value-row"><p id="dkbce-value-wrap"><label for="dkbce-value" id="dkbce-value-label"><?php esc_html_e( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-value" aria-describedby="dkbce-action-example" type="number" min="0" step="any" inputmode="decimal"><span id="dkbce-value-suffix"></span></p>
				<p id="dkbce-action-example" aria-live="polite"></p><p class="description"><?php esc_html_e( 'Variations are treated as separate products and their own COGS values are changed. WooCommerce stores a COGS value of zero as empty, so relative actions skip it.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
			</div>
			</section>

			<section class="dkbce-section dkbce-preview-section" aria-labelledby="dkbce-preview-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-list-view" aria-hidden="true"></i><span aria-hidden="true">3</span><div><h2 id="dkbce-preview-title"><?php esc_html_e( 'Review changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p id="dkbce-preview-help"><?php esc_html_e( 'Review the products and calculated COGS changes before applying.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div><div class="dkbce-preview-controls"><span id="dkbce-preview-spinner" class="spinner" aria-hidden="true"></span><label for="dkbce-page-size"><?php esc_html_e( 'Products per page', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-page-size">
				<?php
				foreach ( DKBCE_COGS_Service::PREVIEW_PAGE_SIZES as $page_size ) :
					?>
					<option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( DKBCE_COGS_Service::PREVIEW_PAGE_SIZE, $page_size ); ?>><?php echo esc_html( $page_size ); ?></option><?php endforeach; ?></select><details class="dkbce-columns"><summary><?php esc_html_e( 'Manage columns', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><div class="dkbce-popover"><strong><?php esc_html_e( 'Manage columns', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><button type="button" class="dkbce-popover-close" aria-label="<?php esc_attr_e( 'Close column controls', 'bulk-cogs-editor-for-woocommerce' ); ?>">×</button><div id="dkbce-column-options"></div></div></details></div></div>
				<div id="dkbce-preview-state" class="dkbce-preview-state" hidden><span role="status"></span><button type="button" class="button" id="dkbce-refresh-preview"><?php esc_html_e( 'Refresh preview', 'bulk-cogs-editor-for-woocommerce' ); ?></button></div>
				<div id="dkbce-selection-toolbar" hidden><strong id="dkbce-selection-count" aria-live="polite"></strong><span id="dkbce-page-selection-count"></span><button type="button" class="button-link" id="dkbce-select-all"><?php esc_html_e( 'Select all matching products', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-select-none"><?php esc_html_e( 'Clear selection', 'bulk-cogs-editor-for-woocommerce' ); ?></button><details id="dkbce-range-menu"><summary><?php esc_html_e( 'Select a range…', 'bulk-cogs-editor-for-woocommerce' ); ?></summary></details></div><p id="dkbce-selection-context" hidden></p><div id="dkbce-preview-content" hidden></div>
				<div class="dkbce-preview-empty"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><h3><?php esc_html_e( 'Your changes start here', 'bulk-cogs-editor-for-woocommerce' ); ?></h3><p><?php esc_html_e( 'Get your products, then choose an action and enter a value to see the new costs before applying.', 'bulk-cogs-editor-for-woocommerce' ); ?></p><span class="dkbce-empty-note"><?php esc_html_e( 'Preview first. Apply when you are ready.', 'bulk-cogs-editor-for-woocommerce' ); ?></span></div>
			</section>

			<section class="dkbce-section dkbce-apply-section" aria-labelledby="dkbce-apply-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-yes-alt" aria-hidden="true"></i><span aria-hidden="true">4</span><div><h2 id="dkbce-apply-title"><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Review your preview, choose which products to include, then confirm your update.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<p class="dkbce-selection-option" hidden><label><input type="checkbox" id="dkbce-selected-only" checked> <?php esc_html_e( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ); ?></label></p>
				<p id="dkbce-action-summary" aria-live="polite"></p><p><span id="dkbce-apply-spinner" class="spinner" aria-hidden="true"></span><button type="button" class="button button-primary" id="dkbce-apply" disabled><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button></p>
			</section>

			<div id="dkbce-operation" class="dkbce-operation" hidden aria-live="polite"></div>

			</div>

			<div id="dkbce-confirm" class="dkbce-dialog" role="dialog" aria-modal="true" aria-labelledby="dkbce-confirm-title" aria-describedby="dkbce-confirm-text" hidden><div class="dkbce-dialog-panel"><h2 id="dkbce-confirm-title"><?php esc_html_e( 'Confirm COGS changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p id="dkbce-confirm-text"></p><dl id="dkbce-confirm-summary"></dl><p id="dkbce-confirm-warning"></p><div class="dkbce-actions"><button type="button" class="button" id="dkbce-confirm-cancel"><?php esc_html_e( 'Cancel', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button button-primary" id="dkbce-confirm-apply"><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button></div></div></div>
		</div>
		<?php
	}

	/**
	 * Get the current user's validated display preferences.
	 *
	 * @return array
	 */
	private function get_preferences() {
		$saved  = get_user_meta( get_current_user_id(), 'dkbce_display_preferences', true );
		$saved  = is_array( $saved ) ? $saved : array();
		$size   = isset( $saved['page_size'] ) ? absint( $saved['page_size'] ) : 0;
		$hidden = isset( $saved['hidden_columns'] ) && is_array( $saved['hidden_columns'] ) ? array_values( array_intersect( range( 0, 7 ), $saved['hidden_columns'] ) ) : array( 1, 3, 4 );
		return array(
			'page_size'      => in_array( $size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ? $size : DKBCE_COGS_Service::PREVIEW_PAGE_SIZE,
			'hidden_columns' => count( $hidden ) < 8 ? $hidden : array(),
		);
	}

	/**
	 * Save display preferences for the authenticated administrator.
	 *
	 * @return void
	 */
	public function ajax_preferences() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$size   = isset( $_POST['page_size'] ) && is_scalar( $_POST['page_size'] ) ? absint( wp_unslash( $_POST['page_size'] ) ) : 0;
		$hidden = isset( $_POST['hidden_columns'] ) && is_array( $_POST['hidden_columns'] ) ? wp_unslash( $_POST['hidden_columns'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each column is strictly validated below.
		if ( ! in_array( $size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) || count( $hidden ) > 7 ) {
			wp_send_json_error( array( 'message' => __( 'The display preferences are invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		foreach ( $hidden as $column ) {
			if ( ! is_scalar( $column ) || ! preg_match( '/\A[0-7]\z/', (string) $column ) ) {
				wp_send_json_error( array( 'message' => __( 'The display preferences are invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}
		$preferences = array(
			'page_size'      => $size,
			'hidden_columns' => array_values( array_unique( array_map( 'absint', $hidden ) ) ),
		);
		$user_id     = get_current_user_id();
		if ( ! update_user_meta( $user_id, 'dkbce_display_preferences', $preferences ) && get_user_meta( $user_id, 'dkbce_display_preferences', true ) !== $preferences ) {
			wp_send_json_error( array( 'message' => __( 'Could not save display preferences. Please try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 500 );
		}
		wp_send_json_success( $preferences );
	}

	/**
	 * Validate nonce, capability, and COGS availability for privileged AJAX requests.
	 *
	 * @return void
	 */
	private function authorize_ajax() {
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage product COGS.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		if ( ! $this->service->is_cogs_available() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce Cost of Goods Sold is unavailable or disabled.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
	}

	/**
	 * Return a safe JSON response when an AJAX nonce is invalid.
	 *
	 * @return void
	 */
	private function send_nonce_error() {
		wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
	}

	/**
	 * Get validated filter and action data from the request.
	 *
	 * @param mixed $raw_filters Raw product filters.
	 * @param mixed $action Raw COGS action.
	 * @param mixed $value Raw action value.
	 * @return array|WP_Error
	 */
	private function get_validated_request( $raw_filters, $action, $value ) {
		$filters = $this->service->validate_filters( $raw_filters );
		if ( is_wp_error( $filters ) ) {
			return $filters;
		}

		$operation = $this->service->validate_action( $action, $value );
		if ( is_wp_error( $operation ) ) {
			return $operation;
		}

		return array(
			'filters'   => $filters,
			'operation' => $operation,
		);
	}

	/**
	 * Return the current number of products matching filters.
	 *
	 * @return void
	 */
	public function ajax_get_products() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$raw_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? wc_clean( wp_unslash( $_POST['filters'] ) ) : array();
		$filters     = $this->service->validate_filters( $raw_filters );

		if ( is_wp_error( $filters ) ) {
			wp_send_json_error( array( 'message' => $filters->get_error_message() ), 400 );
		}

		$filters['count']           = true;
		$filters['count_breakdown'] = true;

		$products_started_at = microtime( true );
		$counts              = $this->service->scan_matches( $filters );
		$product_count       = $counts['count'];
		dkbce_wc_log(
			sprintf(
				'Get Products completed in %.4f seconds ( Limit: %d, Filters: %s, Product count: %s).',
				microtime( true ) - $products_started_at,
				DKBCE_COGS_Service::PREVIEW_LIMIT,
				wp_json_encode( $filters ),
				$product_count
			)
		);
		wp_send_json_success(
			array(
				'count'           => $product_count,
				'product_count'   => $counts['product_count'],
				'variation_count' => $counts['variation_count'],
				'limit'           => DKBCE_COGS_Service::PREVIEW_LIMIT,
			)
		);
	}

	/**
	 * Build an authorized, read-only CSV preview.
	 *
	 * @return void
	 */
	public function ajax_csv_preview() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $mode, array( 'import', 'export' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose import or export.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$raw_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? wc_clean( wp_unslash( $_POST['filters'] ) ) : array();
		$filters     = $this->service->validate_filters( $raw_filters );
		if ( is_wp_error( $filters ) ) {
			wp_send_json_error( array( 'message' => $filters->get_error_message() ), 400 );
		}
		if ( 'export' === $mode && ! empty( $filters['search'] ) ) {
			// Freeze matches for this authorized preview token; a new preview resolves a fresh set.
			$filters['search_ids'] = $this->service->search_product_ids( $filters['search'] );
		}
		$preview = array(
			'user_id'   => get_current_user_id(),
			'filters'   => $filters,
			'operation' => array(
				'action' => 'csv_' . $mode,
				'value'  => '',
			),
			'page_size' => DKBCE_COGS_Service::PREVIEW_PAGE_SIZE,
			'row_ids'   => array(),
			'errors'    => array(),
		);
		$csv     = new DKBCE_CSV_Service();
		if ( 'import' === $mode ) {
			if ( ! isset( $_FILES['import_file']['name'], $_FILES['import_file']['tmp_name'], $_FILES['import_file']['error'] ) || ! is_string( $_FILES['import_file']['name'] ) || ! is_string( $_FILES['import_file']['tmp_name'] ) || ! is_int( $_FILES['import_file']['error'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose a CSV or XLSX file within the WordPress upload limit.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			try {
				$imports = new DKBCE_Import_Processor();
				$upload  = array(
					'name'     => sanitize_file_name( $_FILES['import_file']['name'] ),
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- PHP upload path is checked with is_uploaded_file before moving to a generated private path.
					'tmp_name' => $_FILES['import_file']['tmp_name'],
					'error'    => (int) $_FILES['import_file']['error'],
				);
				$job     = $imports->upload( $upload );
				wp_send_json_success(
					array(
						'import_id' => $job['operation_id'],
						'status'    => $job['status'],
					)
				);
			} catch ( Throwable $exception ) {
				wp_send_json_error( array( 'message' => $exception->getMessage() ), 400 );
			}
		}
		$preview['total'] = $this->service->scan_matches( array_merge( $filters, array( 'count' => true ) ) );
		$token            = wp_generate_uuid4();
		$result           = $this->csv_preview_page( $preview, $token, 1, $preview['page_size'] );
		if ( empty( $preview['operation']['source_id'] ) ) {
			$preview['row_ids'] = array_values( array_unique( array_merge( $preview['row_ids'], wp_list_pluck( $result['rows'], 'id' ) ) ) );
		}
		$preview['reviewed_rows'] = $this->reviewed_rows( $result['rows'], $preview['reviewed_rows'] ?? array() );
		set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
		$result['errors'] = $preview['errors'];
		$report           = $csv->line( array( 'row', 'id', 'sku', 'new_cogs', 'error' ) );
		foreach ( $preview['errors'] as $error ) {
			$report .= $csv->line( array( $error['line'], $error['id'], $error['sku'], $error['value'], $error['error'] ) );
		}
		$result['error_csv'] = $report;
		wp_send_json_success( $result );
	}

	/**
	 * Poll or cancel the current user's read-only import preparation.
	 *
	 * @return void
	 */
	public function ajax_import_status() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$id  = isset( $_POST['import_id'] ) && is_string( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
		$job = preg_match( '/\A[0-9a-f-]{36}\z/i', $id ) ? $this->store->get( $id ) : false;
		if ( ! $job || 'import_preview' !== $job['action'] || get_current_user_id() !== (int) $job['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'This import preview is unavailable. Upload the file again.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		if ( isset( $_POST['cancel'] ) && '1' === $_POST['cancel'] && empty( $job['cancel_requested'] ) && in_array( $job['status'], array( 'pending', 'processing' ), true ) ) {
			$this->store->request_cancellation( $id );
			$this->processor->enqueue( $id );
		}
		$this->processor->recover_if_stale( $job );
		$result = array(
			'import_id'       => $id,
			'status'          => $job['status'],
			'stage'           => $job['stage'],
			'processed'       => (int) $job['processed'],
			'message'         => $job['error_summary'],
			'sheet'           => $job['sheet_name'] ?? '',
			'elapsed_seconds' => max( 0, time() - (int) $job['created_at'] ),
			'progress_ratio'  => null,
			'total_rows'      => 0,
		);
		if ( 'csv' === $job['format'] && ! empty( $job['file_size'] ) ) {
			$result['progress_ratio'] = min( 1, max( 0, (float) $job['offset'] / (int) $job['file_size'] ) );
			if ( $result['processed'] > 0 && $result['progress_ratio'] > 0 ) {
				$result['total_rows'] = max( $result['processed'], (int) ceil( $result['processed'] / $result['progress_ratio'] ) );
			}
		} elseif ( ! empty( $job['estimated_total'] ) ) {
			$result['total_rows']     = (int) $job['estimated_total'];
			$result['progress_ratio'] = min( 1, (float) $result['processed'] / $result['total_rows'] );
		}
		if ( 'ready' === $job['status'] ) {
			$result['progress_ratio'] = 1;
			$result['total_rows']     = (int) ( $job['counts']['total'] ?? $job['processed'] );
			if ( get_option( 'dkbce_preview_claim_' . $id ) ) {
				$result['status'] = 'applied';
			} else {
				$preview                  = array(
					'user_id'   => $job['user_id'],
					'filters'   => array(),
					'operation' => array(
						'action'    => 'csv_import',
						'value'     => '',
						'source_id' => $id,
					),
					'total'     => $job['total'],
					'page_size' => DKBCE_COGS_Service::PREVIEW_PAGE_SIZE,
					'row_ids'   => array(),
				);
				$result['preview']        = $this->csv_preview_page( $preview, $id, 1, $preview['page_size'] );
				$preview['reviewed_rows'] = $this->reviewed_rows( $result['preview']['rows'] );
				set_transient( 'dkbce_preview_' . $id, $preview, 10 * MINUTE_IN_SECONDS );
				$imports                            = new DKBCE_Import_Store();
				$result['preview']['errors']        = $imports->rows( $id, 50, 0, 0, true );
				$result['preview']['invalid_count'] = $job['counts']['invalid'];
				$result['preview']['report_source'] = $id;
			}
		}
		wp_send_json_success( $result );
	}

	/**
	 * Start an asynchronous validation or final import report download.
	 *
	 * @return void
	 */
	public function ajax_import_report() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$id  = isset( $_POST['import_id'] ) && is_string( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
		$job = preg_match( '/\A[0-9a-f-]{36}\z/i', $id ) ? $this->store->get( $id ) : false;
		if ( ! $job || 'import_preview' !== $job['action'] || get_current_user_id() !== (int) $job['user_id'] || 'ready' !== $job['status'] ) {
			wp_send_json_error( array( 'message' => __( 'This import report has expired or is not ready.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$after        = isset( $_POST['after'] ) && is_scalar( $_POST['after'] ) ? absint( wp_unslash( $_POST['after'] ) ) : 0;
		$results      = isset( $_POST['results'] ) && '1' === $_POST['results'];
		$operation_id = '';
		if ( $results ) {
			$operation = empty( $job['applied_operation'] ) ? false : $this->store->get( $job['applied_operation'] );
			if ( ! $operation || ! in_array( $operation['status'], array( 'completed', 'completed_with_errors', 'cancelled', 'failed' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Wait for the import to finish before downloading its results.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
			}
			$operation_id = $job['applied_operation'];
		}
		try {
			$download = ( new DKBCE_Download_Processor() )->start(
				array(
					'user_id'             => get_current_user_id(),
					'mode'                => 'report',
					'source_id'           => $id,
					'source_operation_id' => $operation_id,
					'results'             => $results,
				)
			);
			wp_send_json_success(
				array(
					'download_id' => $download['operation_id'],
					'status'      => $download['status'],
				)
			);
		} catch ( Throwable $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 503 );
		}
	}

	/**
	 * Poll an authorized private download job.
	 *
	 * @return void
	 */
	public function ajax_download_status() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage product COGS.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		$id    = isset( $_POST['download_id'] ) && is_string( $_POST['download_id'] ) ? sanitize_text_field( wp_unslash( $_POST['download_id'] ) ) : '';
		$state = preg_match( '/\\A[0-9a-f-]{36}\\z/i', $id ) ? $this->store->get( $id ) : false;
		if ( ! $state || 'server_download' !== $state['action'] || get_current_user_id() !== (int) $state['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'This download has expired or is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		if ( time() - (int) $state['created_at'] > DKBCE_Download_Processor::RETENTION ) {
			( new DKBCE_Download_Processor() )->cleanup( $id );
			wp_send_json_error( array( 'message' => __( 'This download has expired.', 'bulk-cogs-editor-for-woocommerce' ) ), 410 );
		}
		$response = array(
			'status' => $state['status'],
			'rows'   => (int) $state['rows'],
		);
		if ( 'completed' === $state['status'] && is_readable( $state['file'] ) ) {
			$response['url'] = add_query_arg(
				array(
					'action'      => 'dkbce_download_file',
					'download_id' => $id,
					'nonce'       => wp_create_nonce( self::NONCE ),
				),
				admin_url( 'admin-ajax.php' )
			);
		}
		if ( 'failed' === $state['status'] ) {
			$response['message'] = $state['error_summary'] ?? __( 'The download failed.', 'bulk-cogs-editor-for-woocommerce' );
		} elseif ( 'cancelled' === $state['status'] ) {
			$response['message'] = __( 'The download was cancelled.', 'bulk-cogs-editor-for-woocommerce' );
		}
		wp_send_json_success( $response );
	}

	/**
	 * Cancel an owned private download job.
	 *
	 * @return void
	 */
	public function ajax_download_cancel() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage product COGS.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		$id    = isset( $_POST['download_id'] ) && is_string( $_POST['download_id'] ) ? sanitize_text_field( wp_unslash( $_POST['download_id'] ) ) : '';
		$state = preg_match( '/\\A[0-9a-f-]{36}\\z/i', $id ) ? $this->store->get( $id ) : false;
		if ( ! $state || 'server_download' !== $state['action'] || get_current_user_id() !== (int) $state['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'This download is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$this->store->request_cancellation( $id );
		wp_send_json_success();
	}

	/**
	 * Stream a completed private download to its owner.
	 *
	 * @return void
	 */
	public function ajax_download_file() {
		$id    = isset( $_GET['download_id'] ) && is_string( $_GET['download_id'] ) ? sanitize_text_field( wp_unslash( $_GET['download_id'] ) ) : '';
		$nonce = isset( $_GET['nonce'] ) && is_string( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		$state = preg_match( '/\\A[0-9a-f-]{36}\\z/i', $id ) ? $this->store->get( $id ) : false;
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_others_products' ) || ! $state || 'server_download' !== $state['action'] || 'completed' !== $state['status'] || get_current_user_id() !== (int) $state['user_id'] || time() - (int) $state['created_at'] > DKBCE_Download_Processor::RETENTION || ! is_readable( $state['file'] ) ) {
			wp_die( esc_html__( 'This download is unavailable.', 'bulk-cogs-editor-for-woocommerce' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . ( 'export' === $state['mode'] ? 'product-cogs.csv' : ( $state['results'] ? 'cogs-import-results.csv' : 'cogs-import-errors.csv' ) ) . '"' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream an authorized private temporary file.
		readfile( $state['file'] );
		exit;
	}

	/**
	 * Render a page from trusted CSV preview data.
	 *
	 * @param array  $preview Stored preview.
	 * @param string $token Preview ID.
	 * @param int    $page Page number.
	 * @param int    $size Page size.
	 * @return array
	 */
	private function csv_preview_page( $preview, $token, $page, $size ) {
		$operation = $preview['operation'];
		if ( 'csv_import' === $preview['operation']['action'] ) {
			$rows = array();
			if ( ! empty( $preview['operation']['source_id'] ) ) {
				$source = $this->store->get( $preview['operation']['source_id'] );
				if ( ! $source || 'ready' !== $source['status'] ) {
					wp_send_json_error( array( 'message' => __( 'The import preview has expired. Upload the file again.', 'bulk-cogs-editor-for-woocommerce' ) ), 410 );
				}
				$imports             = new DKBCE_Import_Store();
				$staged_rows         = $imports->rows( $preview['operation']['source_id'], $size, ( $page - 1 ) * $size );
				$ids                 = array_map( 'intval', wp_list_pluck( $staged_rows, 'product_id' ) );
				$operation['values'] = array();
				foreach ( $staged_rows as $staged_row ) {
					$operation['values'][ (int) $staged_row['product_id'] ] = $staged_row['value'];
				}
				unset( $operation['source_id'] );
			} else {
				$ids = array_slice( array_keys( $preview['operation']['values'] ), ( $page - 1 ) * $size, $size );
			}
			_prime_post_caches( $ids, true, true );
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$rows[] = $this->service->preview_row( $product, $operation );
				}
			}
			$result = array(
				'rows'        => $rows,
				'count'       => $preview['total'],
				'page'        => $page,
				'page_size'   => $size,
				'total_pages' => max( 1, (int) ceil( $preview['total'] / $size ) ),
			);
		} else {
			$result = $this->service->scan_matches(
				array_merge(
					$preview['filters'],
					array(
						'operation' => $preview['operation'],
						'page_no'   => $page,
						'page_size' => $size,
					)
				)
			);
		}
		$result['preview_id'] = $token;
		return $result;
	}

	/**
	 * Validate a bounded explicit selection against rows in an owned preview.
	 *
	 * @param array $preview Owned preview.
	 * @return array Validated product IDs.
	 */
	private function explicit_preview_ids( $preview ) {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$raw     = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] ) ? wp_unslash( $_POST['selected_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict decimal IDs are validated against owned preview rows below.
		$allowed = array_merge( $preview['row_ids'], array_map( 'absint', array_keys( $preview['reviewed_rows'] ?? array() ) ) );
		$ids     = array();
		if ( count( $raw ) > DKBCE_COGS_Service::MAX_SELECTED_PRODUCTS ) {
			wp_send_json_error( array( 'message' => __( 'Select up to 500 individual products, or use all matching products or a range.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		foreach ( $raw as $id ) {
			if ( ! is_scalar( $id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $id ) || ! in_array( absint( $id ), $allowed, true ) ) {
				wp_send_json_error( array( 'message' => __( 'The selected products do not match the preview. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$ids[] = absint( $id );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Start an asynchronous export from an authorized preview.
	 *
	 * @return void
	 */
	public function ajax_csv_export() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$token   = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$preview = get_transient( 'dkbce_preview_' . $token );
		if ( ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] || 'csv_export' !== $preview['operation']['action'] ) {
			wp_send_json_error( array( 'message' => __( 'Export preview expired. Preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$columns = isset( $_POST['columns'] ) && is_array( $_POST['columns'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['columns'] ) ) : array();
		if ( ! $columns || array_diff( $columns, DKBCE_CSV_Service::COLUMNS ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose valid CSV columns.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$filters     = $preview['filters'];
		$range_token = isset( $_POST['range_token'] ) && is_string( $_POST['range_token'] ) ? sanitize_text_field( wp_unslash( $_POST['range_token'] ) ) : '';
		if ( '' !== $range_token ) {
			if ( empty( $preview['selection_range']['token'] ) || ! hash_equals( $preview['selection_range']['token'], $range_token ) ) {
				wp_send_json_error( array( 'message' => __( 'Select the product range again before exporting.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$filters['selection_range'] = $preview['selection_range'];
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON IDs are validated against the authorized preview below.
		$excluded = isset( $_POST['excluded_ids'] ) && is_string( $_POST['excluded_ids'] ) ? json_decode( wp_unslash( $_POST['excluded_ids'] ), true ) : array();
		if ( ! is_array( $excluded ) || count( $excluded ) > count( $preview['row_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid export selection.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		foreach ( $excluded as $id ) {
			if ( ! is_scalar( $id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $id ) || ! in_array( absint( $id ), $preview['row_ids'], true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid export selection.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}
		$filters['excluded_ids'] = array_map( 'absint', $excluded );
		if ( isset( $_POST['selection_mode'] ) && 'explicit' === $_POST['selection_mode'] ) {
			$ids = $this->explicit_preview_ids( $preview );
			if ( ! $ids ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one product.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$filters['selected_ids'] = $ids;
		}
		try {
			$download = ( new DKBCE_Download_Processor() )->start(
				array(
					'user_id' => get_current_user_id(),
					'mode'    => 'export',
					'filters' => $filters,
					'columns' => $columns,
				)
			);
			wp_send_json_success(
				array(
					'download_id' => $download['operation_id'],
					'status'      => $download['status'],
				)
			);
		} catch ( Throwable $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 503 );
		}
	}

	/**
	 * Build a no-write preview and issue a short-lived one-use preview token.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$raw_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? wc_clean( wp_unslash( $_POST['filters'] ) ) : array();
		$action      = isset( $_POST['action_type'] ) ? wc_clean( wp_unslash( $_POST['action_type'] ) ) : '';
		$value       = isset( $_POST['action_value'] ) ? wc_clean( wp_unslash( $_POST['action_value'] ) ) : '';
		$page_size   = isset( $_POST['page_size'] ) && is_scalar( $_POST['page_size'] ) ? absint( wp_unslash( $_POST['page_size'] ) ) : DKBCE_COGS_Service::PREVIEW_PAGE_SIZE;

		if ( ! in_array( $page_size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid number of products per page.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$request = $this->get_validated_request( $raw_filters, $action, $value );

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( array( 'message' => $request->get_error_message() ), 400 );
		}

		$filters              = $request['filters'] ?? array();
		$filters['operation'] = $request['operation'] ?? array();
		if ( ! empty( $filters['search'] ) ) {
			// Preview pages reuse matches for this token; starting a new preview refreshes them.
			$filters['search_ids'] = $this->service->search_product_ids( $filters['search'] );
		}
		$selection            = $this->refresh_preview_selection( $filters );
		$filters['page_no']   = 1;
		$filters['page_size'] = $page_size;

		$result                           = $this->service->scan_matches( $filters );
		$selected_filters                 = $filters;
		$selected_filters['count']        = true;
		$selected_filters['excluded_ids'] = $selection['excluded_ids'];
		if ( $selection['range'] ) {
			$selected_filters['selection_range'] = $selection['range'];
		}
		$selected_ids = null;
		if ( isset( $_POST['selection_mode'] ) && 'explicit' === $_POST['selection_mode'] && ! empty( $selection['previous'] ) ) {
			$selected_filters['selected_ids'] = $this->explicit_preview_ids( $selection['previous'] );
			$selected_ids                     = $this->service->scan_matches(
				array_merge(
					$selected_filters,
					array(
						'count'     => false,
						'get_ids'   => true,
						'page_size' => DKBCE_COGS_Service::MAX_SELECTED_PRODUCTS,
					)
				)
			);
		}
		$selected_count = null !== $selected_ids ? count( $selected_ids ) : $this->service->scan_matches( $selected_filters );
		if ( $selection['range'] ) {
			$selection['range']['count'] = $selected_count;
		}

		$token = wp_generate_uuid4();
		set_transient(
			'dkbce_preview_' . $token,
			array(
				'user_id'         => get_current_user_id(),
				'filters'         => $filters,
				'operation'       => $request['operation'],
				'total'           => $result['count'],
				'page_size'       => $result['page_size'],
				'row_ids'         => array_values( array_unique( array_merge( $selection['row_ids'], array_map( 'absint', wp_list_pluck( $result['rows'], 'id' ) ) ) ) ),
				'reviewed_rows'   => $this->reviewed_rows( $result['rows'] ),
				'selection_range' => $selection['range'],
			),
			10 * MINUTE_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'selected_count'   => $selected_count,
				'reused_selection' => ! empty( $filters['selection_operation'] ),
				'selected_ids'     => $selected_ids,
				'excluded_ids'     => $selection['excluded_ids'],
				'selection_range'  => $selection['range'],
				'preview_id'       => $token,
				'count'            => $result['count'],
				'page'             => $result['page'],
				'page_size'        => $result['page_size'],
				'total_pages'      => $result['total_pages'],
				'rows'             => $result['rows'],
			)
		);
	}

	/**
	 * Carry an owned preview's selection into its replacement.
	 *
	 * Filter refreshes intersect prior matching constraints without loading all IDs.
	 *
	 * @param array $filters Validated new filters, updated by reference.
	 * @return array
	 */
	private function refresh_preview_selection( &$filters ) {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$selection    = array(
			'row_ids'      => array(),
			'excluded_ids' => array(),
			'range'        => null,
		);
		$token        = isset( $_POST['previous_preview_id'] ) && is_string( $_POST['previous_preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['previous_preview_id'] ) ) : '';
		$operation_id = isset( $_POST['previous_operation_id'] ) && is_string( $_POST['previous_operation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['previous_operation_id'] ) ) : '';
		if ( '' === $token && '' !== $operation_id ) {
			$operation = preg_match( '/\A[0-9a-f-]{36}\z/i', $operation_id ) ? $this->store->get( $operation_id ) : false;
			if ( ! $operation || get_current_user_id() !== (int) $operation['user_id'] || empty( $operation['reusable_selection'] ) || ! in_array( $operation['status'], array( 'completed', 'completed_with_errors' ), true ) || ! empty( $operation['history_cleanup_started'] ) ) {
				wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
			}
			$filters['selection_operation'] = $operation_id;
			$filters['selection_baseline']  = array_intersect_key( $operation['filters'], $this->service->validate_filters( array() ) );
			return $selection;
		}
		if ( '' === $token ) {
			return $selection;
		}
		$previous = get_transient( 'dkbce_preview_' . $token );
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $previous ) || get_current_user_id() !== (int) $previous['user_id'] || in_array( $previous['operation']['action'], array( 'csv_import', 'csv_export' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'The previous selection expired. Use Reset filters to start a new selection.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every decoded ID is validated against the owned preview below.
		$excluded = isset( $_POST['excluded_ids'] ) && is_string( $_POST['excluded_ids'] ) ? json_decode( wp_unslash( $_POST['excluded_ids'] ), true ) : null;
		if ( ! is_array( $excluded ) || count( $excluded ) > count( $previous['row_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The excluded product list is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		foreach ( $excluded as $id ) {
			if ( ! is_scalar( $id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $id ) || ! in_array( absint( $id ), $previous['row_ids'], true ) ) {
				wp_send_json_error( array( 'message' => __( 'The excluded product list is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}
		$selection['previous'] = $previous;
		if ( ! empty( $previous['filters']['selection_operation'] ) ) {
			$filters['selection_operation'] = $previous['filters']['selection_operation'];
			$filters['selection_baseline']  = $previous['filters']['selection_baseline'];
		}
		$selection['row_ids']      = $previous['row_ids'];
		$selection['excluded_ids'] = array_values( array_unique( array_map( 'absint', $excluded ) ) );
		$range_token               = isset( $_POST['range_token'] ) && is_string( $_POST['range_token'] ) ? sanitize_text_field( wp_unslash( $_POST['range_token'] ) ) : '';
		if ( '' !== $range_token ) {
			if ( empty( $previous['selection_range']['token'] ) || ! hash_equals( $previous['selection_range']['token'], $range_token ) ) {
				wp_send_json_error( array( 'message' => __( 'Select the product range again before applying.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$selection['range'] = $previous['selection_range'];
		}
		$prior       = array_intersect_key( $previous['filters'], $this->service->validate_filters( array() ) );
		$current     = array_intersect_key( $filters, $prior );
		$constraints = $previous['filters']['selection_filters'] ?? array();
		if ( $current !== $prior ) {
			if ( isset( $previous['filters']['search_ids'] ) ) {
				$prior['search_ids'] = $previous['filters']['search_ids'];
			}
			if ( ! in_array( $prior, $constraints, true ) ) {
				$constraints[] = $prior;
			}
		}
		if ( count( $constraints ) > 12 ) {
			wp_send_json_error( array( 'message' => __( 'Use Reset filters to start a new selection before changing filters again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$filters['selection_filters'] = $constraints;
		return $selection;
	}

	/**
	 * Load one page of rows for an authorized preview.
	 *
	 * @return void
	 */
	public function ajax_preview_page() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();

		$token        = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$page_no      = isset( $_POST['page'] ) && is_scalar( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 0;
		$page_size    = isset( $_POST['page_size'] ) && is_scalar( $_POST['page_size'] ) ? absint( wp_unslash( $_POST['page_size'] ) ) : 0;
		$operation_id = isset( $_POST['operation_id'] ) && is_string( $_POST['operation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( $operation_id ) {
			$state = preg_match( '/\A[0-9a-f-]{36}\z/i', $operation_id ) ? $this->store->get( $operation_id ) : false;
			if ( ! $this->can_view_operation( $state ) ) {
				wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
			}
			if ( $page_no < 1 || ! in_array( $page_size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose a valid number of products per page.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}

			if ( 'csv_import' === $state['operation']['action'] ) {
				wp_send_json_success(
					$this->csv_preview_page(
						array(
							'operation' => $state['operation'],
							'total'     => ! empty( $state['operation']['source_id'] ) ? (int) ( $this->store->get( $state['operation']['source_id'] )['selected_total'] ?? $state['total'] ) : count( $state['operation']['values'] ),
						),
						$token,
						$page_no,
						$page_size
					)
				);
			}

			// The apply token is consumed. Browsing an operation is read-only and
			// uses its trusted filters, which may match fewer products as costs change.
			$filters              = $state['filters'];
			$filters['operation'] = $state['operation'];
			$filters['page_no']   = $page_no;
			$filters['page_size'] = $page_size;
			$result               = $this->service->scan_matches( $filters );
			wp_send_json_success(
				array(
					'preview_id'  => $token,
					'count'       => $result['count'],
					'page'        => $result['page'],
					'page_size'   => $result['page_size'],
					'total_pages' => $result['total_pages'],
					'rows'        => $result['rows'],
				)
			);
		}
		$preview = get_transient( 'dkbce_preview_' . $token );
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Your preview has expired. Please update a filter or action in Step 1 or Step 2 to refresh the preview before applying your changes', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		if ( ! $page_size ) {
			$page_size = (int) $preview['page_size'];
		}
		if ( ! in_array( $page_size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid number of products per page.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		if ( in_array( $preview['operation']['action'], array( 'csv_import', 'csv_export' ), true ) ) {
			if ( $page_no < 1 || $page_no > max( 1, (int) ceil( $preview['total'] / $page_size ) ) ) {
				wp_send_json_error( array( 'message' => __( 'That CSV page is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$result = $this->csv_preview_page( $preview, $token, $page_no, $page_size );
			if ( empty( $preview['operation']['source_id'] ) ) {
				$preview['row_ids'] = array_values( array_unique( array_merge( $preview['row_ids'], wp_list_pluck( $result['rows'], 'id' ) ) ) );
			}
			$preview['reviewed_rows'] = $this->reviewed_rows( $result['rows'], $preview['reviewed_rows'] ?? array() );
			set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
			wp_send_json_success( $result );
		}

		$total_pages = max( 1, (int) ceil( $preview['total'] / $page_size ) );
		if ( $page_no < 1 || $page_no > $total_pages ) {
			wp_send_json_error( array( 'message' => __( 'That preview page is unavailable. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$filters              = $preview['filters'] ?? array();
		$filters['operation'] = $preview['operation'] ?? array();
		$filters['page_no']   = $page_no;
		$filters['page_size'] = $page_size;

		$result = $this->service->scan_matches( $filters );
		if ( $result['count'] !== (int) $preview['total'] ) {
			delete_transient( 'dkbce_preview_' . $token );
			wp_send_json_error( array( 'message' => __( 'Products changed after this preview. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}

		$preview['page_size']     = $page_size;
		$preview['row_ids']       = array_values( array_unique( array_merge( $preview['row_ids'], array_map( 'absint', wp_list_pluck( $result['rows'], 'id' ) ) ) ) );
		$preview['reviewed_rows'] = $this->reviewed_rows( $result['rows'], $preview['reviewed_rows'] ?? array() );
		set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
		wp_send_json_success(
			array(
				'preview_id'  => $token,
				'count'       => $result['count'],
				'page'        => $result['page'],
				'page_size'   => $result['page_size'],
				'total_pages' => $result['total_pages'],
				'rows'        => $result['rows'],
			)
		);
	}

	/**
	 * Merge calculations shown to the user into a preview.
	 *
	 * @param array $rows Preview rows.
	 * @param array $reviewed Existing signatures keyed by product ID.
	 * @return array
	 */
	private function reviewed_rows( $rows, $reviewed = array() ) {
		foreach ( $rows as $row ) {
			$reviewed[ $row['id'] ] = $this->service->preview_signature( $row );
		}
		return $reviewed;
	}

	/**
	 * Reject stale reviewed values before claiming a preview or queuing work.
	 *
	 * @param array  $preview Owned preview.
	 * @param string $token Owned preview token.
	 * @param float  $requested_at Original Apply start time, restored on continuation.
	 * @return int|null Selected count, or null for import and legacy explicit selections.
	 */
	private function revalidate_reviewed_rows( $preview, $token, &$requested_at ) {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$selected_only = isset( $_POST['selected_only'] ) && '1' === $_POST['selected_only'];
		$mode          = isset( $_POST['selection_mode'] ) && is_string( $_POST['selection_mode'] ) ? sanitize_key( wp_unslash( $_POST['selection_mode'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only to omit excluded rows from preflight; apply validates every ID and the worker guards reviewed values again.
		$excluded = isset( $_POST['excluded_ids'] ) && is_string( $_POST['excluded_ids'] ) ? json_decode( wp_unslash( $_POST['excluded_ids'] ), true ) : array();
		$excluded = is_array( $excluded ) ? array_filter( $excluded, 'is_scalar' ) : array();
		$excluded = array_map( 'absint', $excluded );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Legacy IDs only narrow preflight here and are strictly validated by ajax_apply.
		$selected        = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] ) ? array_map( 'absint', array_filter( wp_unslash( $_POST['selected_ids'] ), 'is_scalar' ) ) : array();
		$range           = $preview['selection_range'] ?? null;
		$selected_lookup = array_fill_keys( $selected, true );
		$excluded_lookup = array_fill_keys( $excluded, true );
		$reviewed        = array();
		foreach ( $preview['reviewed_rows'] ?? array() as $id => $signature ) {
			if ( $selected_only && ! in_array( $mode, array( 'range', 'all_except' ), true ) && ! isset( $selected_lookup[ (int) $id ] ) ) {
				continue;
			}
			if ( $selected_only && 'all_except' === $mode && isset( $excluded_lookup[ (int) $id ] ) ) {
				continue;
			}
			if ( $selected_only && 'range' === $mode && $range && ( $id < $range['min'] || $id > $range['max'] ) ) {
				continue;
			}
			$reviewed[ $id ] = $signature;
		}
		$fingerprint  = hash( 'sha256', maybe_serialize( array( $token, $preview, $selected_only, $mode, $excluded, $selected ) ) );
		$preflight_id = isset( $_POST['preflight_id'] ) && is_string( $_POST['preflight_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preflight_id'] ) ) : '';
		$offset       = 0;
		if ( '' !== $preflight_id ) {
			$checkpoint = preg_match( '/\A[0-9a-f-]{36}\z/i', $preflight_id ) ? get_transient( 'dkbce_preflight_' . $preflight_id ) : false;
			if ( ! is_array( $checkpoint ) || ! isset( $checkpoint['fingerprint'], $checkpoint['offset'], $checkpoint['requested_at'] ) || ! is_string( $checkpoint['fingerprint'] ) || ! hash_equals( $checkpoint['fingerprint'], $fingerprint ) || ! is_int( $checkpoint['offset'] ) || $checkpoint['offset'] < 0 || $checkpoint['offset'] > count( $reviewed ) || ! is_numeric( $checkpoint['requested_at'] ) ) {
				wp_send_json_error(
					array(
						'message'         => __( 'The selection check expired or the preview changed. Refresh the preview and apply again.', 'bulk-cogs-editor-for-woocommerce' ),
						'refresh_preview' => true,
					),
					409
				);
			}
			$offset       = $checkpoint['offset'];
			$requested_at = $checkpoint['requested_at'];
		}
		$offset += $this->revalidate_reviewed_batch( array_slice( $reviewed, $offset, 250, true ), $preview['operation'] );
		if ( $offset < count( $reviewed ) ) {
			$preflight_id = $preflight_id ? $preflight_id : wp_generate_uuid4();
			$checkpoint   = array(
				'fingerprint'  => $fingerprint,
				'offset'       => $offset,
				'requested_at' => $requested_at,
			);
			if ( ! set_transient( 'dkbce_preflight_' . $preflight_id, $checkpoint, 5 * MINUTE_IN_SECONDS ) ) {
				wp_send_json_error( array( 'message' => __( 'The selection check could not be saved. Try applying again.', 'bulk-cogs-editor-for-woocommerce' ) ), 503 );
			}
			wp_send_json_success(
				array(
					'preflight_pending' => true,
					'preflight_id'      => $preflight_id,
				)
			);
		}
		if ( $preflight_id ) {
			delete_transient( 'dkbce_preflight_' . $preflight_id );
		}
		if ( 'csv_import' === $preview['operation']['action'] || ( $selected_only && ! in_array( $mode, array( 'range', 'all_except' ), true ) ) ) {
			return null;
		}
		$filters          = $preview['filters'];
		$filters['count'] = true;
		$matches          = $this->service->scan_matches( $filters );
		if ( $selected_only ) {
			$filters['excluded_ids'] = 'all_except' === $mode ? $excluded : array();
			if ( 'range' === $mode && $range ) {
				$filters['selection_range'] = $range;
			}
		}
		$total     = $selected_only ? $this->service->scan_matches( $filters ) : $matches;
		$confirmed = isset( $_POST['confirmed_count'] ) && is_scalar( $_POST['confirmed_count'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmed_count'] ) ) : null;
		if ( ! $total || (int) $preview['total'] !== $matches || ( null !== $confirmed && ( ! ctype_digit( $confirmed ) || (int) $confirmed !== $total ) ) ) {
			wp_send_json_error(
				array(
					'message'         => __( 'The selected product scope changed. Refresh the preview, review the selection, and confirm again. No update was started.', 'bulk-cogs-editor-for-woocommerce' ),
					'refresh_preview' => true,
				),
				409
			);
		}
		return $total;
	}

	/**
	 * Check a bounded group of reviewed costs with batched reads.
	 *
	 * @param array $reviewed Product signatures, at most 250 per request.
	 * @param array $operation Preview operation.
	 * @return int Number of signatures checked before the cooperative deadline.
	 */
	private function revalidate_reviewed_batch( $reviewed, $operation ) {
		$deadline = microtime( true ) + 5;
		$checked  = 0;
		foreach ( array_chunk( $reviewed, 50, true ) as $batch ) {
			if ( $checked && microtime( true ) >= $deadline ) {
				break;
			}
			$ids = array_keys( $batch );
			$this->service->prime_products( $ids );
			$batch_operation = $operation;
			if ( 'csv_import' === $operation['action'] && ! empty( $operation['source_id'] ) ) {
				$imports                   = new DKBCE_Import_Store();
				$batch_operation['values'] = $imports->values( $operation['source_id'], $ids );
				unset( $batch_operation['source_id'] );
			}
			foreach ( $batch as $id => $signature ) {
				// Finish at least one row so expensive cache priming cannot stall progress.
				if ( $checked && microtime( true ) >= $deadline ) {
					break 2;
				}
				$product = wc_get_product( $id );
				$result  = $product ? $this->service->calculate( $product, $batch_operation ) : null;
				if ( $product && is_wp_error( $result ) ) {
					$result = array(
						'current' => $product->get_cogs_value(),
						'new'     => null,
						'status'  => 'failed',
					);
				}
				if ( ! is_array( $result ) || ! hash_equals( $signature, $this->service->preview_signature( $result ) ) ) {
					wp_send_json_error(
						array(
							'message'         => __( 'A reviewed product changed after this preview. Refresh the preview, review the new values, and confirm again. No update was started.', 'bulk-cogs-editor-for-woocommerce' ),
							'refresh_preview' => true,
						),
						409
					);
				}
				++$checked;
			}
		}
		return $checked;
	}

	/**
	 * Create an operation after validating the preview token and selected scope.
	 *
	 * @return void
	 * @throws RuntimeException Internally caught and returned as an invalid import selection.
	 */
	public function ajax_apply() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}

		$this->authorize_ajax();
		$requested_at = microtime( true );

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Background processing is unavailable. No products were changed.', 'bulk-cogs-editor-for-woocommerce' ) ), 503 );
		}

		$token   = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$preview = get_transient( 'dkbce_preview_' . $token );

		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Your preview has expired. Please update a filter or action in Step 1 or Step 2 to refresh the preview before applying your changes', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		if ( 'csv_export' === $preview['operation']['action'] ) {
			wp_send_json_error( array( 'message' => __( 'Export cannot update products.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$import_job = false;
		if ( ! empty( $preview['operation']['source_id'] ) ) {
			$import_job = $this->store->get( $preview['operation']['source_id'] );
			if ( ! $import_job || 'ready' !== $import_job['status'] || get_current_user_id() !== (int) $import_job['user_id'] ) {
				wp_send_json_error( array( 'message' => __( 'The import preview is not ready or has expired.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}

		$expected_total = $this->revalidate_reviewed_rows( $preview, $token, $requested_at );

		if ( ! add_option(
			'dkbce_preview_claim_' . $token,
			array(
				'user_id'    => get_current_user_id(),
				'created_at' => time(),
			),
			'',
			false
		) ) {
			wp_send_json_error( array( 'message' => __( 'This preview has already been used. Run a new preview before applying.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}

		delete_transient( 'dkbce_preview_' . $token );

		$operation_id = wp_generate_uuid4();

		if ( isset( $_POST['selected_only'] ) && ! is_string( $_POST['selected_only'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected product scope is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$selected_only_value = isset( $_POST['selected_only'] ) && is_string( $_POST['selected_only'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_only'] ) ) : '';

		if ( '' !== $selected_only_value && ! in_array( $selected_only_value, array( '0', '1' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected product scope is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$selected_only = '1' === $selected_only_value;
		$exclude_mode  = isset( $_POST['selection_mode'] ) && 'all_except' === $_POST['selection_mode'];
		$range_mode    = $selected_only && isset( $_POST['selection_mode'] ) && 'range' === $_POST['selection_mode'];
		if ( $range_mode ) {
			$range_token = isset( $_POST['range_token'] ) && is_string( $_POST['range_token'] ) ? sanitize_text_field( wp_unslash( $_POST['range_token'] ) ) : '';
			if ( empty( $preview['selection_range'] ) || ! hash_equals( $preview['selection_range']['token'], $range_token ) ) {
				wp_send_json_error( array( 'message' => __( 'Select the product range again before applying.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$preview['filters']['selection_range'] = $preview['selection_range'];
			$exclude_mode                          = true;
		}
		$excluded_ids = array();
		if ( $selected_only && $exclude_mode && ! $range_mode && ! $import_job ) {
			$raw_excluded = isset( $_POST['excluded_ids'] ) && is_string( $_POST['excluded_ids'] ) ? json_decode( wp_unslash( $_POST['excluded_ids'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON IDs are strictly validated below.
			if ( ! is_array( $raw_excluded ) || count( $raw_excluded ) > count( $preview['row_ids'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The excluded product list is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			foreach ( $raw_excluded as $raw_id ) {
				if ( ! is_scalar( $raw_id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $raw_id ) ) {
					wp_send_json_error( array( 'message' => __( 'The excluded product list is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
				}
				$excluded_ids[] = absint( $raw_id );
			}
			$excluded_ids = array_values( array_unique( $excluded_ids ) );
			if ( array_diff( $excluded_ids, $preview['row_ids'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The selection is empty or no longer matches the preview.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}

		if ( $import_job ) {
			try {
				$imports = new DKBCE_Import_Store();
				if ( $selected_only ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every JSON ID is checked against private staged rows by exclude().
					$excluded = isset( $_POST['excluded_ids'] ) && is_string( $_POST['excluded_ids'] ) ? json_decode( wp_unslash( $_POST['excluded_ids'] ), true ) : null;
					if ( isset( $_POST['selection_mode'] ) && 'explicit' === $_POST['selection_mode'] ) {
						$imports->select_only( $import_job['operation_id'], $this->explicit_preview_ids( $preview ) );
					} else {
						if ( ! $exclude_mode || $range_mode || ! is_array( $excluded ) ) {
							throw new RuntimeException( esc_html__( 'Choose import products using the preview checkboxes.', 'bulk-cogs-editor-for-woocommerce' ) );
						}
						$imports->exclude( $import_job['operation_id'], array_unique( $excluded, SORT_REGULAR ) );
					}
				}
				$counts = $imports->counts( $import_job['operation_id'] );
				if ( ! $counts['valid'] ) {
					throw new RuntimeException( esc_html__( 'Select at least one valid product to import.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
			} catch ( Throwable $exception ) {
				wp_send_json_error( array( 'message' => $exception->getMessage() ), 400 );
			}
			$selected_only = false;
			$exclude_mode  = false;
		}

		$csv_ids = null;
		if ( 'csv_import' === $preview['operation']['action'] && ! $import_job ) {
			$csv_ids = array_keys( $preview['operation']['values'] );
			if ( $selected_only ) {
				if ( ! $exclude_mode || $range_mode ) {
					wp_send_json_error( array( 'message' => __( 'Choose CSV products using the preview checkboxes.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
				}
				$csv_ids = array_values( array_diff( $csv_ids, $excluded_ids ) );
			}
			if ( ! $csv_ids ) {
				wp_send_json_error( array( 'message' => __( 'There are no valid CSV products to import.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$preview['operation']['values'] = array_intersect_key( $preview['operation']['values'], array_flip( $csv_ids ) );
			$selected_only                  = true;
			$exclude_mode                   = false;
		}

		$state = array(
			'operation_id'          => $operation_id,
			'user_id'               => get_current_user_id(),
			'created_at'            => time(),
			'requested_at'          => $requested_at,
			'started_at'            => 0,
			'completed_at'          => 0,
			'total'                 => 0,
			'processed'             => 0,
			'succeeded'             => 0,
			'skipped'               => 0,
			'failed'                => 0,
			'action'                => $preview['operation']['action'],
			'note'                  => isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['note'] ) ), 0, 500 ) : '',
			'value'                 => $preview['operation']['value'],
			'operation'             => $preview['operation'],
			'filters'               => $preview['filters'],
			'status'                => 'pending',
			'stage'                 => $selected_only && ! $exclude_mode ? 'processing' : 'snapshot',
			'type_index'            => 0,
			'page'                  => 1,
			'snapshot_chunks'       => 0,
			'processed_chunks'      => 0,
			'counted_chunk_results' => 0,
			'batch_size'            => $this->processor->get_batch_size(),
			'cancel_requested'      => false,
			'preview_claim_token'   => $token,
			'reusable_selection'    => ! in_array( $preview['operation']['action'], array( 'csv_import', 'csv_export', 'rollback' ), true ),
			'reviewed_rows'         => $preview['reviewed_rows'] ?? array(),
			'expected_total'        => $expected_total,
			'errors'                => array(),
			'error_summary'         => '',
		);

		if ( $selected_only && $exclude_mode ) {
			$state['filters']['excluded_ids'] = $excluded_ids;
		}
		// The worker persists its snapshot search IDs separately from operation progress.
		unset( $state['filters']['search_ids'] );

		if ( $selected_only && ! $exclude_mode ) {
			$raw_selected_ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] ) ? wp_unslash( $_POST['selected_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each selected ID is validated as digits and converted with absint below.
			if ( null !== $csv_ids ) {
				$raw_selected_ids = $csv_ids;
			}
			if ( null === $csv_ids && count( $raw_selected_ids ) > DKBCE_COGS_Service::MAX_SELECTED_PRODUCTS ) {
				wp_send_json_error( array( 'message' => __( 'The selected product list is too large. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$selected_ids = array();
			foreach ( $raw_selected_ids as $raw_id ) {
				if ( ! is_scalar( $raw_id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $raw_id ) ) {
					wp_send_json_error( array( 'message' => __( 'The selected product list is invalid.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
				}
				$selected_ids[] = absint( $raw_id );
			}
			$selected_ids = array_values( array_unique( $selected_ids ) );
			if ( ! $selected_ids ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one product in the preview table.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			if ( array_diff( $selected_ids, $preview['row_ids'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The selected products do not match the preview. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}

			$state['total'] = count( $selected_ids );
			foreach ( array_chunk( $selected_ids, $state['batch_size'] ) as $ids ) {
				++$state['snapshot_chunks'];
				$this->store->save_chunk( $operation_id, $state['snapshot_chunks'], $ids );
			}
		}

		$this->store->save( $state );
		$claim_key = 'dkbce_preview_claim_' . $token;
		$claim     = get_option( $claim_key, false );
		if ( is_array( $claim ) ) {
			$claim['operation_id'] = $operation_id;
			update_option( $claim_key, $claim, false );
		}
		if ( isset( $preview['filters']['search_ids'] ) ) {
			$this->store->save_search_ids( $operation_id, $preview['filters']['search_ids'] );
		}
		if ( $import_job ) {
			$import_job['applied_operation'] = $operation_id;
			$import_job['selected_total']    = $counts['valid'];
			$this->store->save( $import_job );
			delete_user_meta( get_current_user_id(), 'dkbce_latest_import', $import_job['operation_id'] );
		}
		update_user_meta( get_current_user_id(), 'dkbce_latest_operation', $operation_id );

		if ( ! $this->processor->enqueue( $operation_id ) ) {
			$state['status']        = 'failed';
			$state['completed_at']  = time();
			$state['error_summary'] = __( 'Background processing could not be started. No products were changed.', 'bulk-cogs-editor-for-woocommerce' );
			$this->store->save( $state );
			wp_send_json_error( array( 'message' => $state['error_summary'] ), 503 );
		}

		wp_send_json_success(
			array(
				'operation_id' => $operation_id,
				'state'        => $this->public_operation( $state ),
			)
		);
	}

	/**
	 * Return authorized operation progress.
	 *
	 * @return void
	 */
	public function ajax_progress() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$operation_id = isset( $_POST['operation_id'] ) && is_string( $_POST['operation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $operation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$state = $this->store->get( $operation_id );
		if ( ! $this->can_view_operation( $state ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$this->processor->recover_if_stale( $state );
		wp_send_json_success( array( 'state' => $this->public_operation( $state ) ) );
	}

	/**
	 * Request cooperative cancellation of an operation.
	 *
	 * @return void
	 */
	public function ajax_cancel() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$operation_id = isset( $_POST['operation_id'] ) && is_string( $_POST['operation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $operation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$state = $this->store->get( $operation_id );
		if ( ! $this->can_view_operation( $state ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		if ( ! in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) {
			$this->store->request_cancellation( $operation_id );
			$state['cancel_requested'] = true;
			$this->processor->enqueue( $operation_id );
		}
		wp_send_json_success( array( 'state' => $this->public_operation( $state ) ) );
	}

	/**
	 * Clear the current user's finished operation reference.
	 *
	 * @return void
	 */
	public function ajax_clear_cancelled() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$operation_id = isset( $_POST['operation_id'] ) && is_string( $_POST['operation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $operation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		$state = $this->store->get( $operation_id );
		if ( ! $state || get_current_user_id() !== (int) $state['user_id'] || ! in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This finished operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		delete_user_meta( get_current_user_id(), 'dkbce_latest_operation', $operation_id );
		if ( get_user_meta( get_current_user_id(), 'dkbce_latest_operation', true ) === $operation_id ) {
			wp_send_json_error( array( 'message' => __( 'The operation could not be cleared. Please try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}
		wp_send_json_success();
	}

	/**
	 * Check operation ownership or manager access.
	 *
	 * @param array|false $state Operation state.
	 * @return bool
	 */
	private function can_view_operation( $state ) {
		return $state && ( get_current_user_id() === (int) $state['user_id'] || current_user_can( 'manage_woocommerce' ) );
	}

	/**
	 * Resolve a selection range against the complete authorized preview.
	 *
	 * @return void
	 */
	public function ajax_select_range() {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			$this->send_nonce_error();
		}
		$this->authorize_ajax();
		$token   = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$preview = get_transient( 'dkbce_preview_' . $token );
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Your preview has expired. Please update a filter or action in Step 1 or Step 2 to refresh the preview before applying your changes', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		if ( ! empty( $preview['operation']['source_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose import products using the preview checkboxes.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$direction = isset( $_POST['direction'] ) && is_string( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '';
		$count     = isset( $_POST['count'] ) && is_string( $_POST['count'] ) ? sanitize_text_field( wp_unslash( $_POST['count'] ) ) : '';
		if ( ! in_array( $direction, array( 'first', 'last' ), true ) || ! ctype_digit( $count ) || (int) $count < 1 || (int) $count > (int) $preview['total'] ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid number of matching products.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$count            = (int) $count;
		$filters          = $preview['filters'];
		$filters['count'] = true;
		if ( (int) $this->service->scan_matches( $filters ) !== (int) $preview['total'] ) {
			wp_send_json_error( array( 'message' => __( 'Products changed after this preview. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}
		$filters['count']     = false;
		$filters['get_ids']   = true;
		$filters['page_size'] = 1;
		$filters['page_no']   = 'first' === $direction ? 1 : (int) $preview['total'] - $count + 1;
		$first                = $this->service->scan_matches( $filters );
		$filters['page_no']  += $count - 1;
		$last                 = $this->service->scan_matches( $filters );
		if ( ! $first || ! $last ) {
			wp_send_json_error( array( 'message' => __( 'Products changed after this preview. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}
		$range                      = array(
			'min'   => absint( $first[0] ),
			'max'   => absint( $last[0] ),
			'count' => $count,
			'token' => wp_generate_uuid4(),
		);
		$preview['selection_range'] = $range;
		set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'range' => $range ) );
	}

	/**
	 * Limit operation data returned to the browser.
	 *
	 * @param array $state Operation state.
	 * @return array
	 */
	public function public_operation( $state ) {
		return array(
			'action'             => $state['action'],
			'completed_at'       => (int) ( $state['completed_at'] ?? 0 ),
			'last_progress_at'   => (int) ( $state['updated_at'] ?? $state['created_at'] ),
			'progress_age'       => max( 0, time() - (int) ( $state['updated_at'] ?? $state['created_at'] ) ),
			'queue_available'    => function_exists( 'as_enqueue_async_action' ),
			'csv_report'         => $state['csv_report'] ?? '',
			'import_source'      => $state['operation']['source_id'] ?? '',
			'operation_id'       => $state['operation_id'],
			'reusable_selection' => ! empty( $state['reusable_selection'] ),
			'status'             => $state['status'],
			'stage'              => $state['stage'],
			'total'              => (int) $state['total'],
			'processed'          => (int) $state['processed'],
			'succeeded'          => (int) $state['succeeded'],
			'skipped'            => (int) $state['skipped'],
			'failed'             => (int) $state['failed'],
			'elapsed_seconds'    => max(
				0,
				(int) ( ( ! empty( $state['completed_at'] ) ? $state['completed_at'] : time() ) - $state['created_at'] )
			),
			'errors'             => $state['errors'],
			'error_summary'      => $state['error_summary'],
			'cancel_requested'   => (bool) $state['cancel_requested'],
			'cancelled_note'     => isset( $state['cancelled_note'] ) ? $state['cancelled_note'] : '',
		);
	}

	/**
	 * Extend product query.
	 *
	 * @param array $wp_query_args Query args.
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function extend_product_query( $wp_query_args, $query_vars ) {
		if ( ! empty( $query_vars['dkbce_selection_operation'] ) ) {
			$wp_query_args['dkbce_selection_operation'] = sanitize_key( $query_vars['dkbce_selection_operation'] );
		}
		if ( isset( $query_vars['dkbce_after_id'] ) ) {
			$wp_query_args['dkbce_after_id'] = absint( $query_vars['dkbce_after_id'] );
		}
		if ( isset( $query_vars['dkbce_selection_range'] ) ) {
			$wp_query_args['dkbce_selection_range'] = array_map( 'absint', $query_vars['dkbce_selection_range'] );
		}
		if ( ! empty( $query_vars['dkbce_category_ids'] ) ) {
			$wp_query_args['dkbce_category_ids'] = array_map( 'absint', (array) $query_vars['dkbce_category_ids'] );
		}
		if ( isset( $query_vars['dkbce_category_groups'] ) ) {
			$wp_query_args['dkbce_category_groups'] = array_map(
				static function ( $ids ) {
					return array_map( 'absint', $ids );
				},
				$query_vars['dkbce_category_groups']
			);
		}
		if ( ! empty( $query_vars['dkbce_meta_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- WooCommerce COGS filters require product metadata conditions supplied by the validated query service.
			$wp_query_args['meta_query'] = array_merge(
				isset( $wp_query_args['meta_query'] ) ? $wp_query_args['meta_query'] : array(),
				$query_vars['dkbce_meta_query']
			);
		}
		return $wp_query_args;
	}

	/**
	 * Creating an instance of this class.
	 *
	 * @return DKBCE_Admin_Functions|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			$service   = new DKBCE_COGS_Service();
			$store     = new DKBCE_Operation_Store();
			$processor = new DKBCE_Bulk_Processor( $store, $service );
			self::$ins = new self( $service, $store, $processor );
		}

		return self::$ins;
	}
}
