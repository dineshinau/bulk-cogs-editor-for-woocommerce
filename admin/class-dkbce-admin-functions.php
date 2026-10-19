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
		$links[] = '<a href="' . esc_url( 'https://youtu.be/R-kw_6g2JIg' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Video', 'bulk-cogs-editor-for-woocommerce' ) . '</a>';
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

		wp_localize_script(
			'dkbce-admin',
			'DKBCE',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE ),
				'cogsAvailable'  => $this->service->is_cogs_available(),
				'operationId'    => $operation ? $operation_id : '',
				'operationMode'  => $operation ? $operation['action'] : '',
				'preferences'    => $this->get_preferences(),
				'previewLimit'   => DKBCE_COGS_Service::PREVIEW_LIMIT,
				'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
				'decimals'       => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
				'csv'            => array(
					'selection'  => __( 'Only include products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ),
					'exportHelp' => __( 'Review the products, then download the selected CSV columns. Export does not change costs. Keep this page open until the download is ready.', 'bulk-cogs-editor-for-woocommerce' ),
					'export'     => __( 'Generate & download CSV', 'bulk-cogs-editor-for-woocommerce' ),
					'apply'      => __( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ),
					'file'       => __( 'Choose a CSV file no larger than 1 MB.', 'bulk-cogs-editor-for-woocommerce' ),
					'columns'    => __( 'Select at least one CSV column.', 'bulk-cogs-editor-for-woocommerce' ),
					'errors'     => __( 'Invalid rows are excluded from this preview and cannot be applied.', 'bulk-cogs-editor-for-woocommerce' ),
					'report'     => __( 'Download error report', 'bulk-cogs-editor-for-woocommerce' ),
					'row'        => __( 'Row', 'bulk-cogs-editor-for-woocommerce' ),
					'status'     => __( 'Status', 'bulk-cogs-editor-for-woocommerce' ),
					'valid'      => __( 'Ready', 'bulk-cogs-editor-for-woocommerce' ),
					'exporting'  => __( 'Preparing CSV… Keep this page open.', 'bulk-cogs-editor-for-woocommerce' ),
					'downloaded' => __( 'CSV download ready.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelled'  => __( 'Export cancelled. No file was downloaded.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancel'     => __( 'Cancel export', 'bulk-cogs-editor-for-woocommerce' ),
				),
				'i18n'           => array(
					/* translators: %d: number of products that will be changed. */
					'confirmText'        => __( 'You are about to modify COGS for %d products. This will change product cost data.', 'bulk-cogs-editor-for-woocommerce' ),
					'loading'            => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previewLoading'     => __( 'Calculating preview…', 'bulk-cogs-editor-for-woocommerce' ),
					'pageLoading'        => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previousPage'       => __( 'Previous', 'bulk-cogs-editor-for-woocommerce' ),
					'nextPage'           => __( 'Next', 'bulk-cogs-editor-for-woocommerce' ),
					'paginationLabel'    => __( 'Product preview pages', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: first product number, 2: last product number, 3: total products. */
					'pageSummary'        => __( 'Showing %1$d–%2$d of %3$d products.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: current page, 2: total pages. */
					'pageNumber'         => __( 'Page %1$d of %2$d', 'bulk-cogs-editor-for-woocommerce' ),
					'noProducts'         => __( 'No products match the selected filters.', 'bulk-cogs-editor-for-woocommerce' ),
					'previewRequired'    => __( 'Get products to calculate the changes before applying them.', 'bulk-cogs-editor-for-woocommerce' ),
					'fetchProductsFirst' => __( 'Fetch matching products in Step 1 before choosing a COGS action.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectRange'        => __( 'Select from all results', 'bulk-cogs-editor-for-woocommerce' ),
					'allResults'         => __( 'All', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of selected matching products. */
					'rangeSelected'      => __( '%d products selected across all pages.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectFirst'        => __( 'First', 'bulk-cogs-editor-for-woocommerce' ),
					'selectLast'         => __( 'Last', 'bulk-cogs-editor-for-woocommerce' ),
					'selectCount'        => __( 'Number of products to select', 'bulk-cogs-editor-for-woocommerce' ),
					'selectRows'         => __( 'Select', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelled'          => __( 'Operation cancelled. Products already processed remain changed.', 'bulk-cogs-editor-for-woocommerce' ),
					'columns'            => array( __( 'Select', 'bulk-cogs-editor-for-woocommerce' ), __( 'ID', 'bulk-cogs-editor-for-woocommerce' ), __( 'Product', 'bulk-cogs-editor-for-woocommerce' ), __( 'SKU', 'bulk-cogs-editor-for-woocommerce' ), __( 'Type', 'bulk-cogs-editor-for-woocommerce' ), __( 'Current COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'New COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'Change', 'bulk-cogs-editor-for-woocommerce' ) ),
					'productOne'         => __( 'product matches.', 'bulk-cogs-editor-for-woocommerce' ),
					'productMany'        => __( 'products match.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: total matches, 2: product count excluding variations, 3: variation count. */
					'matchCount'         => __( '%1$d matches: %2$d products and %3$d variations. Preview changes before applying.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: number of preview rows, 2: total matching products. */
					'showing'            => __( 'Showing first %1$d of %2$d matching products.', 'bulk-cogs-editor-for-woocommerce' ),
					'allShown'           => __( 'All matching products are shown.', 'bulk-cogs-editor-for-woocommerce' ),
					'noName'             => __( '(no name)', 'bulk-cogs-editor-for-woocommerce' ),
					'skipped'            => __( 'Skipped', 'bulk-cogs-editor-for-woocommerce' ),
					'updatedLabel'       => __( 'Products updated', 'bulk-cogs-editor-for-woocommerce' ),
					'skippedLabel'       => __( 'Products skipped', 'bulk-cogs-editor-for-woocommerce' ),
					'failedLabel'        => __( 'Products failed', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: formatted processed product count, 2: formatted total product count. */
					'progressSummary'    => __( '%1$s of %2$s products processed', 'bulk-cogs-editor-for-woocommerce' ),
					'processing'         => __( 'Processing COGS updates…', 'bulk-cogs-editor-for-woocommerce' ),
					'backgroundMessage'  => __( 'You can close this window or your browser. Updates will continue in the background. Return to this page later to view the results.', 'bulk-cogs-editor-for-woocommerce' ),
					'complete'           => __( 'COGS update completed.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: current operation status. */
					'operationStatus'    => __( 'COGS update %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: products processed, 2: total products, 3: percent complete, 4: successful updates, 5: skipped products, 6: failed products. */
					'processed'          => __( 'Processed %1$d of %2$d (%3$d%) · Success: %4$d · Skipped: %5$d · Failed: %6$d', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: elapsed duration in hours, minutes, and seconds. */
					'elapsedTime'        => __( 'Elapsed time: %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: approximate remaining duration or an estimating message. */
					'remainingTime'      => __( 'Approx. time remaining: %s', 'bulk-cogs-editor-for-woocommerce' ),
					'estimatingTime'     => __( 'Calculating…', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelOperation'    => __( 'Cancel operation', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelling'         => __( 'Cancellation requested…', 'bulk-cogs-editor-for-woocommerce' ),
					'starting'           => __( 'Starting…', 'bulk-cogs-editor-for-woocommerce' ),
					'noSelection'        => __( 'Select at least one product in the preview table.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectPageProducts' => __( 'Select all products on this page', 'bulk-cogs-editor-for-woocommerce' ),
					'badValue'           => __( 'Enter a valid non-negative value for this action.', 'bulk-cogs-editor-for-woocommerce' ),
					'onlySelected'       => __( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: product ID. */
					'ariaSelect'         => __( 'Select product %d', 'bulk-cogs-editor-for-woocommerce' ),
					'progressLabel'      => __( 'COGS update progress', 'bulk-cogs-editor-for-woocommerce' ),
					'snapshotting'       => __( 'Collecting matching products…', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %1$d: product ID, %2$s: safe error summary. */
					'productError'       => __( 'Product %1$d: %2$s', 'bulk-cogs-editor-for-woocommerce' ),
					'emptyValue'         => __( 'Empty', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: percentage change. */
					'deltaPercent'       => __( '(%s%)', 'bulk-cogs-editor-for-woocommerce' ),
					'clearedChange'      => __( 'COGS cleared', 'bulk-cogs-editor-for-woocommerce' ),
					'amountLabel'        => __( 'Amount', 'bulk-cogs-editor-for-woocommerce' ),
					'percentageLabel'    => __( 'Percentage', 'bulk-cogs-editor-for-woocommerce' ),
					'cogsLabel'          => __( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ),
					'noSku'              => __( '—', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersChanged'     => __( 'Filters changed. Get products again to refresh the matching set.', 'bulk-cogs-editor-for-woocommerce' ),
					'getProducts'        => __( 'Get products', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersReset'       => __( 'All filters reset.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectAll'          => __( 'Select all', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: positive formatted currency amount. */
					'deltaPositive'      => __( '+%s', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelledStatus'    => __( 'Operation cancelled.', 'bulk-cogs-editor-for-woocommerce' ),
					'clearCancelled'     => __( 'Clear cancellation state', 'bulk-cogs-editor-for-woocommerce' ),
					'failedStatus'       => __( 'Operation failed.', 'bulk-cogs-editor-for-woocommerce' ),
					'withErrorsStatus'   => __( 'Operation completed with errors.', 'bulk-cogs-editor-for-woocommerce' ),
					'completedStatus'    => __( 'Operation completed.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshing'         => __( 'COGS update completed.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshNow'         => __( 'Refresh now', 'bulk-cogs-editor-for-woocommerce' ),
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
		?>
		<div class="wrap dkbce-wrap">
			<header class="dkbce-hero">
				<span class="dkbce-brand-icon dashicons dashicons-database" aria-hidden="true"></span>
				<div><h1><?php esc_html_e( 'Bulk Cost of Goods Sold (COGS) Editor', 'bulk-cogs-editor-for-woocommerce' ); ?></h1><p><?php esc_html_e( 'Update cost of goods for your WooCommerce products quickly and safely.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div>
				<details class="dkbce-help"><summary><?php esc_html_e( 'Help & instructions', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><div class="dkbce-popover"><strong><?php esc_html_e( 'Update product costs in four steps', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><ol><li><?php esc_html_e( 'Use the filters to find the products you want to update, then click Get products. Leave a filter empty to include all its values.', 'bulk-cogs-editor-for-woocommerce' ); ?></li><li><?php esc_html_e( 'Set an exact cost, increase or decrease costs by an amount or percentage, or choose Clear COGS to remove costs. Enter a value unless you are clearing costs.', 'bulk-cogs-editor-for-woocommerce' ); ?></li><li><?php esc_html_e( 'Compare current and new costs in the preview. No changes are saved until you apply and confirm the update.', 'bulk-cogs-editor-for-woocommerce' ); ?></li><li><?php esc_html_e( 'By default, the update includes all matching products across all preview pages. To update specific products, check them in the preview and enable the option to apply only to checked products. Click Apply changes, then confirm.', 'bulk-cogs-editor-for-woocommerce' ); ?></li></ol>
					<p><?php esc_html_e( 'Need more guidance? Read the documentation or watch the video walkthrough.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
					<p><a href="<?php echo esc_url( 'https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Doc', 'bulk-cogs-editor-for-woocommerce' ); ?></a> | <a href="<?php echo esc_url( 'https://youtu.be/R-kw_6g2JIg' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Video', 'bulk-cogs-editor-for-woocommerce' ); ?></a></p>
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
			</div>
			<section id="dkbce-csv" class="dkbce-section" hidden aria-labelledby="dkbce-csv-title">
				<h2 id="dkbce-csv-title"><?php esc_html_e( 'CSV action', 'bulk-cogs-editor-for-woocommerce' ); ?></h2>
				<div class="dkbce-actions">
					<label for="dkbce-csv-action"><?php esc_html_e( 'Action', 'bulk-cogs-editor-for-woocommerce' ); ?></label>
					<select id="dkbce-csv-action"><option value="export"><?php esc_html_e( 'Export CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="import"><?php esc_html_e( 'Import CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select>
					<button type="button" class="button-link" id="dkbce-csv-template"><?php esc_html_e( 'Download sample CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></button>
				</div>
				<div id="dkbce-csv-export-options">
					<p><?php esc_html_e( 'Use the filters below to choose products. Product type controls whether variable parents and variations are included. Export includes all matching pages.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
					<fieldset class="dkbce-csv-columns"><legend><?php esc_html_e( 'CSV columns', 'bulk-cogs-editor-for-woocommerce' ); ?></legend>
					<?php foreach ( DKBCE_CSV_Service::COLUMNS as $column ) : ?>
						<label><input type="checkbox" name="dkbce-csv-column" value="<?php echo esc_attr( $column ); ?>" checked> <?php echo esc_html( $column ); ?></label>
					<?php endforeach; ?>
					</fieldset>
				</div>
				<div id="dkbce-csv-import-options" hidden>
					<p><?php esc_html_e( 'UTF-8 CSV, up to 1 MB and 1,000 rows. Required headers: cogs and id or sku. ID takes priority; if both are supplied they must agree. Use decimal points without currency symbols. Blank costs are skipped; zero follows WooCommerce empty-cost behavior.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
					<label id="dkbce-csv-drop" for="dkbce-csv-file"><?php esc_html_e( 'Drop a CSV file here or choose a file', 'bulk-cogs-editor-for-woocommerce' ); ?><input type="file" id="dkbce-csv-file" accept=".csv,text/csv"></label>
				</div>
				<p><button type="button" class="button button-primary" id="dkbce-csv-preview"><?php esc_html_e( 'Preview CSV', 'bulk-cogs-editor-for-woocommerce' ); ?></button></p>
				<div id="dkbce-csv-errors" hidden></div>
			</section>

			<section class="dkbce-section dkbce-filters-section" aria-labelledby="dkbce-filters-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-filter" aria-hidden="true"></i><span aria-hidden="true">1</span><div><h2 id="dkbce-filters-title"><?php esc_html_e( 'Select products', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Select one or more values per filter. Leave a filter empty to include all values.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div><div class="dkbce-actions dkbce-filter-actions"><button type="button" class="button button-primary" id="dkbce-get-products" <?php disabled( ! $available ); ?>><?php esc_html_e( 'Get products', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-reset"><?php esc_html_e( 'Reset filters', 'bulk-cogs-editor-for-woocommerce' ); ?></button><span class="dkbce-product-status"><span id="dkbce-products-loading" role="status"></span><span id="dkbce-products-spinner" class="spinner" aria-hidden="true"></span><span id="dkbce-product-count" aria-live="polite"></span></span></div></div>
				<div class="dkbce-grid dkbce-filter-grid">
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

			</section>

			<section class="dkbce-section dkbce-action-section" aria-labelledby="dkbce-action-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-admin-generic" aria-hidden="true"></i><span aria-hidden="true">2</span><div><h2 id="dkbce-action-title"><?php esc_html_e( 'Choose COGS update action', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Select how you want to update COGS for matching products.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<div class="dkbce-action-grid">
					<?php
					$action_labels = array(
						'set'              => __( 'Set exact COGS', 'bulk-cogs-editor-for-woocommerce' ),
						'increase_percent' => __( 'Increase by percentage', 'bulk-cogs-editor-for-woocommerce' ),
						'decrease_percent' => __( 'Decrease by percentage', 'bulk-cogs-editor-for-woocommerce' ),
						'increase_fixed'   => __( 'Increase by fixed amount', 'bulk-cogs-editor-for-woocommerce' ),
						'decrease_fixed'   => __( 'Decrease by fixed amount', 'bulk-cogs-editor-for-woocommerce' ),
						'clear'            => __( 'Clear COGS', 'bulk-cogs-editor-for-woocommerce' ),
					);
					?>
					<?php
					$action_icons = array(
						'set'              => 'edit',
						'increase_percent' => 'chart-line',
						'decrease_percent' => 'arrow-down-alt',
						'increase_fixed'   => 'plus-alt2',
						'decrease_fixed'   => 'minus',
						'clear'            => 'trash',
					);
					foreach ( $action_labels as $action => $label ) :
						?>
						<label class="dkbce-action-card dkbce-action-<?php echo esc_attr( $action ); ?>"><input type="radio" name="dkbce-action" value="<?php echo esc_attr( $action ); ?>" <?php checked( 'set', $action ); ?>><span><i class="dkbce-action-icon dashicons dashicons-<?php echo esc_attr( $action_icons[ $action ] ); ?>" aria-hidden="true"></i><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $this->get_action_description( $action ) ); ?></small></span></label><?php endforeach; ?>
				</div>
				<div class="dkbce-value-row"><p id="dkbce-value-wrap"><label for="dkbce-value" id="dkbce-value-label"><?php esc_html_e( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-value" type="number" min="0" step="any" inputmode="decimal"><span id="dkbce-value-suffix"></span></p>
				<p class="description"><?php esc_html_e( 'Variations are treated as separate products and their own COGS values are changed. WooCommerce stores a COGS value of zero as empty, so relative actions skip it.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
			</div>
			</section>

			<section class="dkbce-section dkbce-preview-section" aria-labelledby="dkbce-preview-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-list-view" aria-hidden="true"></i><span aria-hidden="true">3</span><div><h2 id="dkbce-preview-title"><?php esc_html_e( 'Review changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Review the products and calculated COGS changes before applying.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div><div class="dkbce-preview-controls"><span id="dkbce-preview-spinner" class="spinner" aria-hidden="true"></span><label for="dkbce-page-size"><?php esc_html_e( 'Products per page', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-page-size">
				<?php
				foreach ( DKBCE_COGS_Service::PREVIEW_PAGE_SIZES as $page_size ) :
					?>
					<option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( DKBCE_COGS_Service::PREVIEW_PAGE_SIZE, $page_size ); ?>><?php echo esc_html( $page_size ); ?></option><?php endforeach; ?></select><details class="dkbce-columns"><summary><?php esc_html_e( 'Manage columns', 'bulk-cogs-editor-for-woocommerce' ); ?></summary><div class="dkbce-popover"><strong><?php esc_html_e( 'Manage columns', 'bulk-cogs-editor-for-woocommerce' ); ?></strong><button type="button" class="dkbce-popover-close" aria-label="<?php esc_attr_e( 'Close column controls', 'bulk-cogs-editor-for-woocommerce' ); ?>">×</button><div id="dkbce-column-options"></div></div></details></div></div>
				<div id="dkbce-preview-content" hidden></div>
				<div class="dkbce-preview-empty"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><h3><?php esc_html_e( 'Your changes start here', 'bulk-cogs-editor-for-woocommerce' ); ?></h3><p><?php esc_html_e( 'Get your products, then choose an action and enter a value to see the new costs before applying.', 'bulk-cogs-editor-for-woocommerce' ); ?></p><span class="dkbce-empty-note"><?php esc_html_e( 'Preview first. Apply when you are ready.', 'bulk-cogs-editor-for-woocommerce' ); ?></span></div>
			</section>

			<section class="dkbce-section dkbce-apply-section" aria-labelledby="dkbce-apply-title">
				<div class="dkbce-section-heading"><i class="dkbce-step-icon dashicons dashicons-yes-alt" aria-hidden="true"></i><span aria-hidden="true">4</span><div><h2 id="dkbce-apply-title"><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Review your preview, choose which products to include, then confirm your update.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<p class="dkbce-selection-option"><label><input type="checkbox" id="dkbce-selected-only"> <?php esc_html_e( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ); ?></label></p>
				<p><span id="dkbce-apply-spinner" class="spinner" aria-hidden="true"></span><button type="button" class="button button-primary" id="dkbce-apply" disabled><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button></p>
			</section>

			<div id="dkbce-operation" class="dkbce-operation" hidden aria-live="polite"></div>

			</div>

			<div id="dkbce-confirm" class="dkbce-dialog" role="dialog" aria-modal="true" aria-labelledby="dkbce-confirm-title" aria-describedby="dkbce-confirm-text" hidden><div class="dkbce-dialog-panel"><h2 id="dkbce-confirm-title"><?php esc_html_e( 'Confirm COGS changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p id="dkbce-confirm-text"></p><div class="dkbce-actions"><button type="button" class="button" id="dkbce-confirm-cancel"><?php esc_html_e( 'Cancel', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button button-primary" id="dkbce-confirm-apply"><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button></div></div></div>
		</div>
		<?php
	}

	/**
	 * Get a short description for an action card.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	private function get_action_description( $action ) {
		$descriptions = array(
			'set'              => __( 'Set the same COGS value for matching products.', 'bulk-cogs-editor-for-woocommerce' ),
			'increase_percent' => __( 'Increase existing COGS by a percentage.', 'bulk-cogs-editor-for-woocommerce' ),
			'decrease_percent' => __( 'Decrease existing COGS by a percentage.', 'bulk-cogs-editor-for-woocommerce' ),
			'increase_fixed'   => __( 'Add a fixed amount to existing COGS.', 'bulk-cogs-editor-for-woocommerce' ),
			'decrease_fixed'   => __( 'Subtract a fixed amount, stopping at zero.', 'bulk-cogs-editor-for-woocommerce' ),
			'clear'            => __( 'Remove the stored COGS value.', 'bulk-cogs-editor-for-woocommerce' ),
		);
		return $descriptions[ $action ];
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
		$hidden = isset( $saved['hidden_columns'] ) && is_array( $saved['hidden_columns'] ) ? array_values( array_intersect( range( 0, 7 ), $saved['hidden_columns'] ) ) : array();
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The CSV parser validates each field; text sanitization would corrupt quoted records.
			$contents = isset( $_POST['csv'] ) && is_string( $_POST['csv'] ) ? wp_unslash( $_POST['csv'] ) : '';
			$result   = $csv->parse( $contents, $this->service );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}
			$preview['operation']['values'] = $result['values'];
			$preview['errors']              = $result['errors'];
			$preview['row_ids']             = array_keys( $result['values'] );
			$preview['total']               = count( $result['values'] );
		} else {
			$preview['total'] = $this->service->scan_matches( array_merge( $filters, array( 'count' => true ) ) );
		}
		$token              = wp_generate_uuid4();
		$result             = $this->csv_preview_page( $preview, $token, 1, $preview['page_size'] );
		$preview['row_ids'] = array_values( array_unique( array_merge( $preview['row_ids'], wp_list_pluck( $result['rows'], 'id' ) ) ) );
		set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
		$result['errors'] = $preview['errors'];
		$report           = $csv->line( array( 'row', 'id', 'sku', 'cogs', 'error' ) );
		foreach ( $preview['errors'] as $error ) {
			$report .= $csv->line( array( $error['line'], $error['id'], $error['sku'], $error['value'], $error['error'] ) );
		}
		$result['error_csv'] = $report;
		wp_send_json_success( $result );
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
		if ( 'csv_import' === $preview['operation']['action'] ) {
			$rows = array();
			foreach ( array_slice( array_keys( $preview['operation']['values'] ), ( $page - 1 ) * $size, $size ) as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$rows[] = $this->service->preview_row( $product, $preview['operation'] );
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
	 * Export a bounded read-only batch from an authorized preview.
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
		$after   = isset( $_POST['after'] ) && is_scalar( $_POST['after'] ) ? absint( wp_unslash( $_POST['after'] ) ) : 0;
		$filters = $preview['filters'];
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
		$filters['get_ids']      = true;
		$filters['page_size']    = 100;
		$filters['after_id']     = $after;
		$ids                     = $this->service->scan_matches( $filters );
		$csv                     = new DKBCE_CSV_Service();
		$output                  = 0 === $after ? $csv->line( $columns ) : '';
		$exported                = 0;
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$values = array(
				'id'            => $id,
				'sku'           => $product->get_sku(),
				'name'          => $product->get_name(),
				'cogs'          => $product->get_cogs_value(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'type'          => $product->get_type(),
			);
			$cells  = array();
			foreach ( $columns as $column ) {
				$cells[] = $values[ $column ];
			}
			$output .= $csv->line( $cells );
			++$exported;
		}
		set_transient( 'dkbce_preview_' . $token, $preview, 10 * MINUTE_IN_SECONDS );
		wp_send_json_success(
			array(
				'csv'   => $output,
				'after' => $ids ? max( $ids ) : $after,
				'done'  => count( $ids ) < 100,
				'count' => $exported,
			)
		);
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
		$filters['page_no']   = 1;
		$filters['page_size'] = $page_size;

		$result = $this->service->scan_matches( $filters );
		$token  = wp_generate_uuid4();
		set_transient(
			'dkbce_preview_' . $token,
			array(
				'user_id'   => get_current_user_id(),
				'filters'   => $request['filters'],
				'operation' => $request['operation'],
				'total'     => $result['count'],
				'page_size' => $result['page_size'],
				'row_ids'   => array_map( 'absint', wp_list_pluck( $result['rows'], 'id' ) ),
			),
			10 * MINUTE_IN_SECONDS
		);

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
							'total'     => count( $state['operation']['values'] ),
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
			$result             = $this->csv_preview_page( $preview, $token, $page_no, $page_size );
			$preview['row_ids'] = array_values( array_unique( array_merge( $preview['row_ids'], wp_list_pluck( $result['rows'], 'id' ) ) ) );
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

		$preview['page_size'] = $page_size;
		$preview['row_ids']   = array_values( array_unique( array_merge( $preview['row_ids'], array_map( 'absint', wp_list_pluck( $result['rows'], 'id' ) ) ) ) );
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
	 * Create an operation after validating the preview token and selected scope.
	 *
	 * @return void
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

		if ( ! add_option( 'dkbce_preview_claim_' . $token, get_current_user_id(), '', false ) ) {
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
		if ( $selected_only && $exclude_mode && ! $range_mode ) {
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
			if ( array_diff( $excluded_ids, $preview['row_ids'] ) || count( $excluded_ids ) >= (int) $preview['total'] ) {
				wp_send_json_error( array( 'message' => __( 'The selection is empty or no longer matches the preview.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
		}

		$csv_ids = null;
		if ( 'csv_import' === $preview['operation']['action'] ) {
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
			'errors'                => array(),
			'error_summary'         => '',
		);

		if ( $selected_only && $exclude_mode ) {
			$state['filters']['excluded_ids'] = $excluded_ids;
		}

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
	private function public_operation( $state ) {
		return array(
			'csv_report'       => $state['csv_report'] ?? '',
			'operation_id'     => $state['operation_id'],
			'status'           => $state['status'],
			'stage'            => $state['stage'],
			'total'            => (int) $state['total'],
			'processed'        => (int) $state['processed'],
			'succeeded'        => (int) $state['succeeded'],
			'skipped'          => (int) $state['skipped'],
			'failed'           => (int) $state['failed'],
			'elapsed_seconds'  => max(
				0,
				(int) ( ( ! empty( $state['completed_at'] ) ? $state['completed_at'] : time() ) - $state['created_at'] )
			),
			'errors'           => $state['errors'],
			'error_summary'    => $state['error_summary'],
			'cancel_requested' => (bool) $state['cancel_requested'],
			'cancelled_note'   => isset( $state['cancelled_note'] ) ? $state['cancelled_note'] : '',
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
		if ( isset( $query_vars['dkbce_after_id'] ) ) {
			$wp_query_args['dkbce_after_id'] = absint( $query_vars['dkbce_after_id'] );
		}
		if ( isset( $query_vars['dkbce_selection_range'] ) ) {
			$wp_query_args['dkbce_selection_range'] = array_map( 'absint', $query_vars['dkbce_selection_range'] );
		}
		if ( ! empty( $query_vars['dkbce_category_ids'] ) ) {
			$wp_query_args['dkbce_category_ids'] = array_map( 'absint', (array) $query_vars['dkbce_category_ids'] );
		}
		if ( ! empty( $query_vars['dkbce_meta_query'] ) ) {
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
