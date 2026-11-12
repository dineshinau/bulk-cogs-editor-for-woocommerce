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

	/**
	 * Register background and admin callbacks.
	 */
	public function __construct() {
		add_action( self::HOOK, array( $this, 'scan' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'display' ) );
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
				as_enqueue_async_action( self::HOOK, array( 0, 0, 0 ), self::GROUP, true );
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
		if ( $missing ) {
			/* translators: %s: number of products and variations without a cost. */
			$message = sprintf( _n( '%s product or variation is missing a COGS value.', '%s products and variations are missing COGS values.', $missing, 'bulk-cogs-editor-for-woocommerce' ), number_format_i18n( $missing ) );
		} else {
			/* translators: %s: number of products and variations with costs above their selling price. */
			$message = sprintf( _n( '%s product or variation has a COGS value above its current selling price.', '%s products and variations have COGS values above their current selling prices.', $above, 'bulk-cogs-editor-for-woocommerce' ), number_format_i18n( $above ) );
		}
		$message  = esc_html( $message ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=bulk-cogs-editor' ) ) . '">' . esc_html__( 'Review in Bulk COGS Editor', 'bulk-cogs-editor-for-woocommerce' ) . '</a> ';
		$message .= esc_html__( 'Counts refresh hourly. Dismiss to hide this notice for one hour.', 'bulk-cogs-editor-for-woocommerce' );
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
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'dkbce_dismiss_health' ),
			)
		);
	}

	/**
	 * Count a bounded cursor page through WooCommerce's product API.
	 *
	 * @param int $after Last product ID scanned.
	 * @param int $missing Accumulated missing costs.
	 * @param int $above Accumulated costs above price.
	 * @return void
	 * @throws RuntimeException When product IDs cannot be read.
	 */
	public function scan( $after = 0, $missing = 0, $above = 0 ) {
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
			set_transient(
				self::CACHE,
				array(
					'missing' => (int) $missing,
					'above'   => (int) $above,
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
		as_enqueue_async_action( self::HOOK, array( $after, (int) $missing, (int) $above ), self::GROUP, true );
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
