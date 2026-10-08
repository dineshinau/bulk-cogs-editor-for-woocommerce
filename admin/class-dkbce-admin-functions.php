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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_dkbce_get_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_dkbce_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_dkbce_preview_page', array( $this, 'ajax_preview_page' ) );
		add_action( 'wp_ajax_dkbce_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_dkbce_progress', array( $this, 'ajax_progress' ) );
		add_action( 'wp_ajax_dkbce_cancel', array( $this, 'ajax_cancel' ) );
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

		wp_enqueue_style( 'dkbce-admin', DKBCE_PLUGIN_URL . 'assets/bulk-cogs-editor.css', array(), DKBCE_VERSION );
		wp_enqueue_script( 'dkbce-admin', DKBCE_PLUGIN_URL . 'assets/bulk-cogs-editor.js', $script_dependencies, $script_version, true );

		$operation_id = sanitize_key( get_user_meta( get_current_user_id(), 'dkbce_latest_operation', true ) );
		$operation    = $operation_id ? $this->store->get( $operation_id ) : false;
		$completed_statuses = array( 'completed', 'completed_with_errors' );
		if ( $operation && ( get_current_user_id() !== (int) $operation['user_id'] || in_array( $operation['status'], $completed_statuses, true ) ) ) {
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
				'previewLimit'   => DKBCE_COGS_Service::PREVIEW_LIMIT,
				'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
				'decimals'       => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
				'i18n'           => array(
					/* translators: %d: number of products that will be changed. */
					'confirmText'      => __( 'You are about to modify COGS for %d products. This will change product cost data.', 'bulk-cogs-editor-for-woocommerce' ),
					'loading'          => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previewLoading'   => __( 'Calculating preview…', 'bulk-cogs-editor-for-woocommerce' ),
					'pageLoading'      => __( 'Loading products…', 'bulk-cogs-editor-for-woocommerce' ),
					'previousPage'     => __( 'Previous', 'bulk-cogs-editor-for-woocommerce' ),
					'nextPage'         => __( 'Next', 'bulk-cogs-editor-for-woocommerce' ),
					'paginationLabel'  => __( 'Product preview pages', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: first product number, 2: last product number, 3: total products. */
					'pageSummary'      => __( 'Showing %1$d–%2$d of %3$d products.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: current page, 2: total pages. */
					'pageNumber'       => __( 'Page %1$d of %2$d', 'bulk-cogs-editor-for-woocommerce' ),
					'noProducts'       => __( 'No products match the selected filters.', 'bulk-cogs-editor-for-woocommerce' ),
					'previewRequired'  => __( 'Get products and preview the changes before applying them.', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelled'        => __( 'Operation cancelled. Products already processed remain changed.', 'bulk-cogs-editor-for-woocommerce' ),
					'columns'          => array( __( 'Select', 'bulk-cogs-editor-for-woocommerce' ), __( 'ID', 'bulk-cogs-editor-for-woocommerce' ), __( 'Product', 'bulk-cogs-editor-for-woocommerce' ), __( 'SKU', 'bulk-cogs-editor-for-woocommerce' ), __( 'Type', 'bulk-cogs-editor-for-woocommerce' ), __( 'Current COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'New COGS', 'bulk-cogs-editor-for-woocommerce' ), __( 'Change', 'bulk-cogs-editor-for-woocommerce' ) ),
					'productOne'       => __( 'product matches.', 'bulk-cogs-editor-for-woocommerce' ),
					'productMany'      => __( 'products match.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: number of products. */
					'matchCount'       => __( '%d matching products. Preview changes before applying.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: number of preview rows, 2: total matching products. */
					'showing'          => __( 'Showing first %1$d of %2$d matching products.', 'bulk-cogs-editor-for-woocommerce' ),
					'allShown'         => __( 'All matching products are shown.', 'bulk-cogs-editor-for-woocommerce' ),
					'noName'           => __( '(no name)', 'bulk-cogs-editor-for-woocommerce' ),
					'skipped'          => __( 'Skipped', 'bulk-cogs-editor-for-woocommerce' ),
					'processing'       => __( 'Processing COGS updates…', 'bulk-cogs-editor-for-woocommerce' ),
					'complete'         => __( 'COGS update completed.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: current operation status. */
					'operationStatus'  => __( 'COGS update %s', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: 1: products processed, 2: total products, 3: percent complete, 4: successful updates, 5: skipped products, 6: failed products. */
					'processed'        => __( 'Processed %1$d of %2$d (%3$d%) · Success: %4$d · Skipped: %5$d · Failed: %6$d', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelOperation'  => __( 'Cancel operation', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelling'       => __( 'Cancellation requested…', 'bulk-cogs-editor-for-woocommerce' ),
					'starting'         => __( 'Starting…', 'bulk-cogs-editor-for-woocommerce' ),
					'noSelection'      => __( 'Select at least one product in the preview table.', 'bulk-cogs-editor-for-woocommerce' ),
					'selectPageProducts' => __( 'Select all products on this page', 'bulk-cogs-editor-for-woocommerce' ),
					'badValue'         => __( 'Enter a valid non-negative value for this action.', 'bulk-cogs-editor-for-woocommerce' ),
					'onlySelected'     => __( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %d: product ID. */
					'ariaSelect'       => __( 'Select product %d', 'bulk-cogs-editor-for-woocommerce' ),
					'progressLabel'    => __( 'COGS update progress', 'bulk-cogs-editor-for-woocommerce' ),
					'snapshotting'     => __( 'Collecting matching products…', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %1$d: product ID, %2$s: safe error summary. */
					'productError'     => __( 'Product %1$d: %2$s', 'bulk-cogs-editor-for-woocommerce' ),
					'emptyValue'       => __( 'Empty', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: percentage change. */
					'deltaPercent'     => __( '(%s%)', 'bulk-cogs-editor-for-woocommerce' ),
					'clearedChange'    => __( 'COGS cleared', 'bulk-cogs-editor-for-woocommerce' ),
					'amountLabel'      => __( 'Amount', 'bulk-cogs-editor-for-woocommerce' ),
					'percentageLabel'  => __( 'Percentage', 'bulk-cogs-editor-for-woocommerce' ),
					'cogsLabel'        => __( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ),
					'noSku'            => __( '—', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersChanged'   => __( 'Filters changed. Get products again to refresh the matching set.', 'bulk-cogs-editor-for-woocommerce' ),
					'getProducts'      => __( 'Get products', 'bulk-cogs-editor-for-woocommerce' ),
					'previewChanges'   => __( 'Preview changes', 'bulk-cogs-editor-for-woocommerce' ),
					'filtersReset'     => __( 'All filters reset.', 'bulk-cogs-editor-for-woocommerce' ),
					/* translators: %s: positive formatted currency amount. */
					'deltaPositive'    => __( '+%s', 'bulk-cogs-editor-for-woocommerce' ),
					'cancelledStatus'  => __( 'Operation cancelled.', 'bulk-cogs-editor-for-woocommerce' ),
					'failedStatus'     => __( 'Operation failed.', 'bulk-cogs-editor-for-woocommerce' ),
					'withErrorsStatus' => __( 'Operation completed with errors.', 'bulk-cogs-editor-for-woocommerce' ),
					'completedStatus'  => __( 'Operation completed.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshing'       => __( 'COGS update completed. This page will refresh in 10 seconds.', 'bulk-cogs-editor-for-woocommerce' ),
					'refreshNow'       => __( 'Refresh now', 'bulk-cogs-editor-for-woocommerce' ),
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
			<h1><?php esc_html_e( 'Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ); ?> <span class="dkbce-version">v<?php echo esc_html( DKBCE_VERSION ); ?></span></h1>
			<?php if ( ! $available ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'WooCommerce Cost of Goods Sold is unavailable or disabled. Update WooCommerce to a version with the supported COGS product API and enable Cost of Goods Sold in WooCommerce settings before using this editor.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div>
			<?php endif; ?>
			<div id="dkbce-alert" class="notice" hidden role="status"><p></p></div>

			<section class="dkbce-section" aria-labelledby="dkbce-filters-title">
				<div class="dkbce-section-heading"><span aria-hidden="true">1</span><div><h2 id="dkbce-filters-title"><?php esc_html_e( 'Select products (filters)', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Choose the products you want to update. All filters are combined.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<div class="dkbce-grid dkbce-filter-grid">
					<p><label for="dkbce-search"><?php esc_html_e( 'Search', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-search" type="search" maxlength="100" placeholder="<?php esc_attr_e( 'Product name or SKU', 'bulk-cogs-editor-for-woocommerce' ); ?>"></p>
					<p><label for="dkbce-category"><?php esc_html_e( 'Product category', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-category"><option value="0"><?php esc_html_e( 'All categories', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
					<?php
					if ( ! is_wp_error( $categories ) ) :
						foreach ( $categories as $category ) :
							?>
						<option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
							<?php
endforeach;
endif;
					?>
					</select></p>
					<p><label for="dkbce-type"><?php esc_html_e( 'Product type', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-type"><option value="any"><?php esc_html_e( 'All product types', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
					<?php
					foreach ( $types as $type => $label ) :
						?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
					<p><label for="dkbce-stock"><?php esc_html_e( 'Stock status', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-stock"><option value="any"><?php esc_html_e( 'Any stock status', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="instock"><?php esc_html_e( 'In stock', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="outofstock"><?php esc_html_e( 'Out of stock', 'bulk-cogs-editor-for-woocommerce' ); ?></option><option value="onbackorder"><?php esc_html_e( 'On backorder', 'bulk-cogs-editor-for-woocommerce' ); ?></option></select></p>
					<?php
					if ( $brand_taxonomy ) :
						?>
						<p><label for="dkbce-brand"><?php echo esc_html( $brand_taxonomy->labels->singular_name ); ?></label><select id="dkbce-brand"><option value="0"><?php esc_html_e( 'All brands', 'bulk-cogs-editor-for-woocommerce' ); ?></option>
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
				<div class="dkbce-actions"><button type="button" class="button button-primary" id="dkbce-get-products" <?php disabled( ! $available ); ?>><?php esc_html_e( 'Get products', 'bulk-cogs-editor-for-woocommerce' ); ?></button><button type="button" class="button-link" id="dkbce-reset"><?php esc_html_e( 'Reset filters', 'bulk-cogs-editor-for-woocommerce' ); ?></button><span id="dkbce-product-count" aria-live="polite"></span></div>
			</section>

			<section class="dkbce-section" aria-labelledby="dkbce-action-title">
				<div class="dkbce-section-heading"><span aria-hidden="true">2</span><div><h2 id="dkbce-action-title"><?php esc_html_e( 'Choose COGS update action', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Select how you want to update COGS for matching products.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
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
					foreach ( $action_labels as $action => $label ) :
						?>
						<label class="dkbce-action-card"><input type="radio" name="dkbce-action" value="<?php echo esc_attr( $action ); ?>" <?php checked( 'set', $action ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $this->get_action_description( $action ) ); ?></small></span></label><?php endforeach; ?>
				</div>
				<p id="dkbce-value-wrap"><label for="dkbce-value" id="dkbce-value-label"><?php esc_html_e( 'COGS value', 'bulk-cogs-editor-for-woocommerce' ); ?></label><input id="dkbce-value" type="number" min="0" step="any" inputmode="decimal"><span id="dkbce-value-suffix"></span></p>
				<p class="description"><?php esc_html_e( 'Variations are treated as separate products and their own COGS values are changed. WooCommerce stores a COGS value of zero as empty, so relative actions skip it.', 'bulk-cogs-editor-for-woocommerce' ); ?></p>
			</section>

			<section class="dkbce-section" aria-labelledby="dkbce-preview-title">
				<div class="dkbce-section-heading"><span aria-hidden="true">3</span><div><h2 id="dkbce-preview-title"><?php esc_html_e( 'Preview changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Review the products and calculated COGS changes before applying.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div><div class="dkbce-preview-controls"><button type="button" class="button button-primary" id="dkbce-preview" disabled><?php esc_html_e( 'Preview changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button><label for="dkbce-page-size"><?php esc_html_e( 'Products per page', 'bulk-cogs-editor-for-woocommerce' ); ?></label><select id="dkbce-page-size"><?php foreach ( DKBCE_COGS_Service::PREVIEW_PAGE_SIZES as $page_size ) : ?><option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( DKBCE_COGS_Service::PREVIEW_PAGE_SIZE, $page_size ); ?>><?php echo esc_html( $page_size ); ?></option><?php endforeach; ?></select></div></div>
				<div id="dkbce-preview-content" hidden></div>
			</section>

			<section class="dkbce-section" aria-labelledby="dkbce-apply-title">
				<div class="dkbce-section-heading"><span aria-hidden="true">4</span><div><h2 id="dkbce-apply-title"><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Apply the changes after reviewing the preview.', 'bulk-cogs-editor-for-woocommerce' ); ?></p></div></div>
				<p><label><input type="checkbox" id="dkbce-selected-only"> <?php esc_html_e( 'Only apply to products checked in the preview table', 'bulk-cogs-editor-for-woocommerce' ); ?></label></p>
				<p><button type="button" class="button button-primary" id="dkbce-apply" disabled><?php esc_html_e( 'Apply changes', 'bulk-cogs-editor-for-woocommerce' ); ?></button><span id="dkbce-apply-spinner" class="spinner" aria-hidden="true"></span></p>
				<div id="dkbce-operation" class="dkbce-operation" hidden aria-live="polite"></div>
			</section>

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
		$raw_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? $_POST['filters'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The service unslashes, validates, and sanitizes each field.
		$filters     = $this->service->validate_filters( $raw_filters );
		if ( is_wp_error( $filters ) ) {
			wp_send_json_error( array( 'message' => $filters->get_error_message() ), 400 );
		}

		$result = $this->service->scan_matches( $filters );
		wp_send_json_success(
			array(
				'count' => $result['count'],
				'limit' => DKBCE_COGS_Service::PREVIEW_LIMIT,
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
		$raw_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? $_POST['filters'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The COGS service unslashes, sanitizes, and validates each field.
		$action      = isset( $_POST['action_type'] ) ? $_POST['action_type'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The COGS service validates and sanitizes the action.
		$value       = isset( $_POST['action_value'] ) ? $_POST['action_value'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The COGS service validates and sanitizes the numeric value.
		$page_size   = isset( $_POST['page_size'] ) && is_scalar( $_POST['page_size'] ) ? absint( wp_unslash( $_POST['page_size'] ) ) : DKBCE_COGS_Service::PREVIEW_PAGE_SIZE;
		if ( ! in_array( $page_size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid number of products per page.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$request     = $this->get_validated_request( $raw_filters, $action, $value );
		if ( is_wp_error( $request ) ) {
			wp_send_json_error( array( 'message' => $request->get_error_message() ), 400 );
		}

		$result = $this->service->scan_matches( $request['filters'], $request['operation'], 1, $page_size );
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
				'preview_id' => $token,
				'count'      => $result['count'],
				'page'        => $result['page'],
				'page_size'   => $result['page_size'],
				'total_pages' => $result['total_pages'],
				'rows'       => $result['rows'],
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

		$token   = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$page    = isset( $_POST['page'] ) && is_scalar( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 0;
		$page_size = isset( $_POST['page_size'] ) && is_scalar( $_POST['page_size'] ) ? absint( wp_unslash( $_POST['page_size'] ) ) : 0;
		$preview = get_transient( 'dkbce_preview_' . $token );
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'The preview expired. Run the preview again before applying changes.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		if ( ! $page_size ) {
			$page_size = (int) $preview['page_size'];
		}
		if ( ! in_array( $page_size, DKBCE_COGS_Service::PREVIEW_PAGE_SIZES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid number of products per page.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$total_pages = max( 1, (int) ceil( $preview['total'] / $page_size ) );
		if ( $page < 1 || $page > $total_pages ) {
			wp_send_json_error( array( 'message' => __( 'That preview page is unavailable. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}

		$result = $this->service->scan_matches( $preview['filters'], $preview['operation'], $page, $page_size );
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
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Background processing is unavailable. No products were changed.', 'bulk-cogs-editor-for-woocommerce' ) ), 503 );
		}

		$token   = isset( $_POST['preview_id'] ) && is_string( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$preview = get_transient( 'dkbce_preview_' . $token );
		if ( ! preg_match( '/\A[0-9a-f-]{36}\z/i', $token ) || ! is_array( $preview ) || get_current_user_id() !== (int) $preview['user_id'] ) {
			wp_send_json_error( array( 'message' => __( 'The preview expired. Run the preview again before applying changes.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
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
		$state         = array(
			'operation_id'     => $operation_id,
			'user_id'          => get_current_user_id(),
			'created_at'       => time(),
			'started_at'       => 0,
			'completed_at'     => 0,
			'total'            => 0,
			'processed'        => 0,
			'succeeded'        => 0,
			'skipped'          => 0,
			'failed'           => 0,
			'action'           => $preview['operation']['action'],
			'value'            => $preview['operation']['value'],
			'operation'        => $preview['operation'],
			'filters'          => $preview['filters'],
			'status'           => 'pending',
			'stage'            => $selected_only ? 'processing' : 'snapshot',
			'type_index'       => 0,
			'page'             => 1,
			'snapshot_chunks'  => 0,
			'processed_chunks' => 0,
			'cancel_requested' => false,
			'errors'           => array(),
			'error_summary'    => '',
		);

		if ( $selected_only ) {
			$raw_selected_ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] ) ? wp_unslash( $_POST['selected_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each selected ID is validated as digits and converted with absint below.
			if ( count( $raw_selected_ids ) > DKBCE_COGS_Service::MAX_SELECTED_PRODUCTS ) {
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
			foreach ( $selected_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product || ! $this->service->product_matches( $product, $preview['filters'] ) ) {
					wp_send_json_error( array( 'message' => __( 'A selected product no longer matches the preview filters. Run the preview again.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
				}
			}
			$state['total']           = count( $selected_ids );
			$state['snapshot_chunks'] = 1;
			$this->store->save_chunk( $operation_id, 1, $selected_ids );
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
			$state['cancel_requested'] = true;
			$this->store->save( $state );
		}
		wp_send_json_success( array( 'state' => $this->public_operation( $state ) ) );
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
	 * Limit operation data returned to the browser.
	 *
	 * @param array $state Operation state.
	 * @return array
	 */
	private function public_operation( $state ) {
		return array(
			'operation_id'     => $state['operation_id'],
			'status'           => $state['status'],
			'stage'            => $state['stage'],
			'total'            => (int) $state['total'],
			'processed'        => (int) $state['processed'],
			'succeeded'        => (int) $state['succeeded'],
			'skipped'          => (int) $state['skipped'],
			'failed'           => (int) $state['failed'],
			'errors'           => $state['errors'],
			'error_summary'    => $state['error_summary'],
			'cancel_requested' => (bool) $state['cancel_requested'],
			'cancelled_note'   => isset( $state['cancelled_note'] ) ? $state['cancelled_note'] : '',
		);
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
