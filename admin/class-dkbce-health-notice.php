<?php
/**
 * Cached catalog COGS health notices.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scan costs in background batches and display a cached, optional notice.
 */
class DKBCE_Health_Notice {
	const HOOK  = 'dkbce_scan_cogs_health';
	const CACHE = 'dkbce_cogs_health';
	const GROUP = 'bulk-cogs-editor-health';
	const SCAN  = 'dkbce_cogs_health_scan';

	/**
	 * Register background and admin callbacks.
	 */
	public function __construct() {
		add_action( self::HOOK, array( $this, 'scan' ), 10, 4 );
		add_action( 'admin_notices', array( $this, 'display' ) );
		add_action( 'wp_ajax_dkbce_refresh_health', array( $this, 'refresh' ) );
		add_action( 'wp_ajax_dkbce_dismiss_health', array( $this, 'dismiss' ) );
	}

	/**
	 * Read the validated notice scope.
	 *
	 * @return string
	 */
	public static function scope() {
		return 'site' === get_option( 'dkbce_health_notice_scope', 'plugin' ) ? 'site' : 'plugin';
	}

	/**
	 * Display cached counts, scheduling a scan only when the cache expires.
	 *
	 * @return void
	 */
	public function display() {
		$screen = get_current_screen();
		if ( ! $screen || is_network_admin() || ! current_user_can( 'edit_others_products' ) || ! ( new DKBCE_COGS_Service() )->is_cogs_available() ) {
			return;
		}
		if ( 'plugin' === self::scope() && get_plugin_page_hookname( 'bulk-cogs-editor', 'edit.php?post_type=product' ) !== $screen->id ) {
			return;
		}
		$counts = get_transient( self::CACHE );
		if ( false === $counts ) {
			if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_enqueue_async_action' ) && ! as_has_scheduled_action( self::HOOK, null, self::GROUP ) ) {
				$this->start_scan();
			}
			return;
		}
		if ( (int) get_user_meta( get_current_user_id(), 'dkbce_health_dismissed_until', true ) > time() ) {
			return;
		}
		$missing = (int) $counts['missing'];
		$above   = (int) $counts['above'];
		if ( ! $missing && ! $above ) {
			return;
		}
		$message = $this->notice_message( $counts );
		wp_admin_notice(
			$message,
			array(
				'type'        => 'warning',
				'dismissible' => true,
				'id'          => 'dkbce-health-notice',
			)
		);
		$asset = require DKBCE_PLUGIN_DIR . 'assets/cogs-health-notice.asset.php';
		wp_enqueue_script( 'dkbce-health-notice', DKBCE_PLUGIN_URL . 'assets/cogs-health-notice.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script(
			'dkbce-health-notice',
			'DKBCEHealth',
			array(
				'url'          => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'dkbce_dismiss_health' ),
				'refreshNonce' => wp_create_nonce( 'dkbce_refresh_health' ),
				'refreshing'   => __( 'Refreshing COGS counts for your products and variations. The time depends on your catalog size; large stores may take several minutes.', 'bulk-cogs-editor-for-woocommerce' ),
				'failed'       => __( 'Could not refresh counts. Try again.', 'bulk-cogs-editor-for-woocommerce' ),
			)
		);
	}

	/**
	 * Format the cached notice and its refresh control.
	 *
	 * @param array $counts Complete catalogue counts.
	 * @return string Escaped notice HTML.
	 */
	private function notice_message( $counts ) {
		$missing = (int) $counts['missing'];
		$above   = (int) $counts['above'];
		if ( $missing ) {
			/* translators: %s: number of products and variations without a cost. */
			$message = sprintf( _n( '%s product or variation is missing a COGS value.', '%s products and variations are missing COGS values.', $missing, 'bulk-cogs-editor-for-woocommerce' ), number_format_i18n( $missing ) );
		} else {
			/* translators: %s: number of products and variations with costs above their selling price. */
			$message = sprintf( _n( '%s product or variation has a COGS value above its current selling price.', '%s products and variations have COGS values above their current selling prices.', $above, 'bulk-cogs-editor-for-woocommerce' ), number_format_i18n( $above ) );
		}
		$message  = esc_html( $message ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=bulk-cogs-editor' ) ) . '">' . esc_html__( 'Review in Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ) . '</a> ';
		$message .= esc_html__( 'Counts refresh automatically every hour. Dismiss this notice to hide it for one hour.', 'bulk-cogs-editor-for-woocommerce' );
		$message .= ' <button type="button" class="button-link dkbce-refresh-health">' . esc_html__( 'Refresh now', 'bulk-cogs-editor-for-woocommerce' ) . '</button> <span class="dkbce-health-status" role="status" aria-live="polite"></span>';
		return $message;
	}

	/**
	 * Schedule a fresh scan, superseding older scan results.
	 *
	 * @return string|WP_Error Scan UUID or scheduling failure.
	 */
	private function start_scan() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return new WP_Error( 'scan_unavailable', __( 'Background processing is unavailable. Try again later.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$scan_id = wp_generate_uuid4();
		// Keep the identity until the next scan replaces it: queue delays and
		// large catalogs can outlast the one-hour lifetime of completed counts.
		// Remove any timeout left by an older version before storing the identity.
		delete_transient( self::SCAN );
		if ( ! set_transient( self::SCAN, $scan_id ) ) {
			return new WP_Error( 'scan_unavailable', __( 'The refresh could not be started. Try again.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		try {
			$action_id = as_enqueue_async_action( self::HOOK, array( 0, 0, 0, $scan_id ), self::GROUP, true );
		} catch ( Throwable $exception ) {
			$action_id = 0;
		}
		if ( ! $action_id ) {
			return new WP_Error( 'scan_unavailable', __( 'The refresh could not be started. Try again.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $scan_id;
	}

	/**
	 * Start an authorized refresh or return its completed notice.
	 *
	 * @return void
	 */
	public function refresh() {
		check_ajax_referer( 'dkbce_refresh_health', 'nonce' );
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage product COGS.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		if ( ! ( new DKBCE_COGS_Service() )->is_cogs_available() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce Cost of Goods Sold is unavailable or disabled.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'refresh';
		if ( 'refresh' === $mode ) {
			$scan_id = $this->start_scan();
			if ( is_wp_error( $scan_id ) ) {
				wp_send_json_error( array( 'message' => $scan_id->get_error_message() ), 503 );
			}
			wp_send_json_success(
				array(
					'scan_id' => $scan_id,
					'ready'   => false,
				)
			);
		}
		$scan_id = isset( $_POST['scan_id'] ) && is_string( $_POST['scan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_id'] ) ) : '';
		if ( 'status' !== $mode || ! preg_match( '/\A[0-9a-f-]{36}\z/i', $scan_id ) || $scan_id !== $this->current_scan_id() ) {
			wp_send_json_error( array( 'message' => __( 'The refresh expired or another refresh was started. Try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
		}
		$counts = get_transient( self::CACHE );
		if ( is_array( $counts ) && ( $counts['scan_id'] ?? '' ) === $scan_id ) {
			$clear = ! $counts['missing'] && ! $counts['above'];
			wp_send_json_success(
				array(
					'ready'   => true,
					'clear'   => $clear,
					'message' => $clear ? '' : $this->notice_message( $counts ),
				)
			);
		}
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::HOOK, null, self::GROUP ) ) {
			wp_send_json_error( array( 'message' => __( 'The count refresh did not complete. Try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 503 );
		}
		wp_send_json_success( array( 'ready' => false ) );
	}

	/**
	 * Read the active scan marker without a stale per-request option cache.
	 *
	 * @return string Active UUID, or an empty string for legacy scans.
	 */
	private function current_scan_id() {
		if ( wp_using_ext_object_cache() ) {
			$scan_id = wp_cache_get( self::SCAN, 'transient', true );
		} else {
			wp_cache_delete( '_transient_' . self::SCAN, 'options' );
			$scan_id = get_transient( self::SCAN );
		}
		return is_string( $scan_id ) ? $scan_id : '';
	}

	/**
	 * Count a bounded cursor page through WooCommerce's product API.
	 *
	 * @param int    $after Last product ID scanned.
	 * @param int    $missing Accumulated missing costs.
	 * @param int    $above Accumulated costs above price.
	 * @param string $scan_id Current scan UUID, or empty for older queued scans.
	 * @return void
	 * @throws RuntimeException When product IDs cannot be read.
	 */
	public function scan( $after = 0, $missing = 0, $above = 0, $scan_id = '' ) {
		if ( $this->current_scan_id() !== $scan_id ) {
			return;
		}
		$service = new DKBCE_COGS_Service();
		if ( ! $service->is_cogs_available() ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded cursor scan; only complete aggregate results are cached for one hour.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM %i p WHERE p.ID > %d AND p.post_status = 'publish' AND (p.post_type = 'product' OR (p.post_type = 'product_variation' AND EXISTS (SELECT 1 FROM %i parent WHERE parent.ID = p.post_parent AND parent.post_status = 'publish'))) ORDER BY p.ID LIMIT 100", $wpdb->posts, absint( $after ), $wpdb->posts ) );
		if ( $wpdb->last_error || ! is_array( $ids ) ) {
			throw new RuntimeException( 'COGS health scan could not read products.' );
		}
		if ( ! $ids ) {
			if ( $this->current_scan_id() !== $scan_id ) {
				return;
			}
			set_transient(
				self::CACHE,
				array(
					'missing' => (int) $missing,
					'above'   => (int) $above,
					'scan_id' => $scan_id,
				),
				HOUR_IN_SECONDS
			);
			return;
		}
		$service->prime_products( $ids );
		$started = microtime( true );
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$after   = (int) $id;
			if ( $product && ! $product->is_type( array( 'variable', 'grouped' ) ) ) {
				$cost = $product->get_cogs_value();
				if ( null === $cost && $product->is_type( 'variation' ) ) {
					$parent = wc_get_product( $product->get_parent_id() );
					$cost   = $parent ? $parent->get_cogs_value() : null;
				}
				if ( null === $cost ) {
					++$missing;
				} elseif ( '' !== $product->get_price() && $product->get_cogs_total_value() > (float) $product->get_price() ) {
					++$above;
				}
			}
			if ( microtime( true ) - $started >= 10 ) {
				break;
			}
		}
		if ( $this->current_scan_id() === $scan_id ) {
			as_enqueue_async_action( self::HOOK, array( $after, (int) $missing, (int) $above, $scan_id ), self::GROUP, true );
		}
	}

	/**
	 * Hide the notice for one hour for the authenticated user.
	 *
	 * @return void
	 */
	public function dismiss() {
		check_ajax_referer( 'dkbce_dismiss_health', 'nonce' );
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), 'dkbce_health_dismissed_until', time() + HOUR_IN_SECONDS );
		wp_send_json_success();
	}
}
