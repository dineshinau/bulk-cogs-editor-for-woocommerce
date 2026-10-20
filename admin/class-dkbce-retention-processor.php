<?php
/**
 * Bounded retention cleanup for completed operations and preview claims.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retain completed operation history for a fixed period, then remove it safely.
 */
class DKBCE_Retention_Processor {
	const HOOK          = 'dkbce_cleanup_retained_operations';
	const GROUP         = 'bulk-cogs-editor';
	const BATCH_SIZE    = 100;
	const RETENTION     = 30 * DAY_IN_SECONDS;
	const CURSOR_OPTION = 'dkbce_retention_cursor';

	/**
	 * Register the recurring cleanup and its scheduling callback.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'cleanup' ) );
	}

	/**
	 * Ensure the daily retention sweep remains scheduled.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			return;
		}
		as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), self::GROUP, true );
	}

	/**
	 * Process one bounded page of plugin operation and claim options.
	 *
	 * @return void
	 */
	public function cleanup() {
		global $wpdb;

		$cursor         = absint( get_option( self::CURSOR_OPTION, 0 ) );
		$like_operation = $wpdb->esc_like( 'dkbce_operation_' ) . '%';
		$like_claim     = $wpdb->esc_like( 'dkbce_preview_claim_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Scan only this plugin's indexed option-name prefixes, using a durable option_id cursor and a fixed page size.
		$option_names = $wpdb->get_results(
			$wpdb->prepare(
				' SELECT option_id, option_name FROM %i WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s) ORDER BY option_id ASC LIMIT %d',
				$wpdb->options,
				$cursor,
				$like_operation,
				$like_claim,
				self::BATCH_SIZE
			),
			ARRAY_A
		);
		if ( ! is_array( $option_names ) ) {
			dkbce_wc_log( 'Bulk COGS retention scan could not read plugin options.' );
			return;
		}
		if ( ! $option_names ) {
			update_option( self::CURSOR_OPTION, 0, false );
			return;
		}

		foreach ( $option_names as $option_row ) {
			$name  = $option_row['option_name'];
			$value = get_option( $name, false );
			if ( 0 === strpos( $name, 'dkbce_operation_' ) ) {
				$this->cleanup_operation_option( $name, $value );
			} elseif ( 0 === strpos( $name, 'dkbce_preview_claim_' ) ) {
				$this->cleanup_claim_option( $name, $value );
			}
		}

		$last_row = end( $option_names );
		$next     = count( $option_names ) < self::BATCH_SIZE ? 0 : absint( $last_row['option_id'] );
		update_option( self::CURSOR_OPTION, $next, false );

		if ( self::BATCH_SIZE === count( $option_names ) && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), self::GROUP, true );
		}
	}

	/**
	 * Retain a terminal operation and its dependencies until its history expires.
	 *
	 * @param string     $option_name Operation option name.
	 * @param array|bool $state       Persisted operation state.
	 * @return void
	 */
	private function cleanup_operation_option( $option_name, $state ) {
		if ( ! is_array( $state ) || empty( $state['operation_id'] ) || ! $this->is_expired_operation( $state, true ) ) {
			return;
		}

		if ( ! empty( $state['cleanup_pending'] ) ) {
			$this->enqueue_operation_cleanup( $state['operation_id'] );
			return;
		}

		if ( ! array_key_exists( 'cleanup_cursor', $state ) ) {
			$state['cleanup_pending'] = true;
			$state['cleanup_cursor']  = 1;
			$store                    = new DKBCE_Operation_Store();
			$store->save( $state );
			$this->enqueue_operation_cleanup( $state['operation_id'] );
			return;
		}

		$operation_id = sanitize_key( $state['operation_id'] );
		$claim_token  = sanitize_text_field( $state['preview_claim_token'] ?? '' );
		delete_option( $option_name );
		delete_option( 'dkbce_search_' . $operation_id );
		delete_option( 'dkbce_cancel_' . $operation_id );
		if ( $claim_token ) {
			delete_option( 'dkbce_preview_claim_' . $claim_token );
		}
		if ( ! empty( $state['user_id'] ) ) {
			delete_user_meta( absint( $state['user_id'] ), 'dkbce_latest_operation', $operation_id );
		}
	}

	/**
	 * Remove a claim only after its linked operation or orphan retention expires.
	 *
	 * @param string     $option_name Claim option name.
	 * @param array|bool $claim       Persisted claim data.
	 * @return void
	 */
	private function cleanup_claim_option( $option_name, $claim ) {
		if ( ! is_array( $claim ) || empty( $claim['created_at'] ) || time() - absint( $claim['created_at'] ) < self::RETENTION ) {
			return;
		}

		if ( ! empty( $claim['operation_id'] ) ) {
			$operation = get_option( 'dkbce_operation_' . sanitize_key( $claim['operation_id'] ), false );
			if ( is_array( $operation ) && ! $this->is_expired_operation( $operation ) ) {
				return;
			}
		}

		delete_option( $option_name );
	}

	/**
	 * Determine whether an operation can expire without breaking active work or reports.
	 *
	 * @param array $state                   Operation state.
	 * @param bool  $include_cleanup_pending Include states that still need bounded cleanup.
	 * @return bool
	 */
	private function is_expired_operation( $state, $include_cleanup_pending = false ) {
		$terminal_statuses = array( 'completed', 'completed_with_errors', 'failed', 'cancelled' );
		if ( empty( $state['operation_id'] ) || ! in_array( $state['status'] ?? '', $terminal_statuses, true ) || ( ! $include_cleanup_pending && ! empty( $state['cleanup_pending'] ) ) ) {
			return false;
		}
		if ( 'import_preview' === ( $state['action'] ?? '' ) || 'server_download' === ( $state['action'] ?? '' ) ) {
			return false;
		}

		$finished_at = absint( $state['completed_at'] ?? $state['created_at'] ?? 0 );
		if ( ! $finished_at || time() - $finished_at < self::RETENTION ) {
			return false;
		}

		if ( ! empty( $state['operation']['source_id'] ) ) {
			$source = get_option( 'dkbce_operation_' . sanitize_key( $state['operation']['source_id'] ), false );
			if ( is_array( $source ) && 'import_preview' === ( $source['action'] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Requeue resumable snapshot and outcome cleanup for an expired operation.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	private function enqueue_operation_cleanup( $operation_id ) {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( DKBCE_Bulk_Processor::CLEANUP_HOOK, array( $operation_id ), self::GROUP, true );
		}
	}
}
