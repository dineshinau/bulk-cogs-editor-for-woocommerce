<?php
/**
 * Authorized history, rollback and retention settings endpoints.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates history requests using the existing operation worker.
 */
class DKBCE_History_Controller {
	/**
	 * Register history endpoints.
	 */
	public function __construct() {
		foreach ( array( 'history', 'history_detail', 'rollback', 'settings', 'force_clear' ) as $action ) {
			add_action( 'wp_ajax_dkbce_' . $action, array( $this, 'ajax_' . $action ) );
		}
	}

	/**
	 * Return translated labels for the shared history interface.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'history'           => __( 'Operation history', 'bulk-cogs-editor-for-woocommerce' ),
			'historyHelp'       => __( 'Select an operation to view details or review a rollback.', 'bulk-cogs-editor-for-woocommerce' ),
			'details'           => __( 'Operation details', 'bulk-cogs-editor-for-woocommerce' ),
			'changes'           => __( 'Product changes', 'bulk-cogs-editor-for-woocommerce' ),
			'review'            => __( 'Review rollback', 'bulk-cogs-editor-for-woocommerce' ),
			'progress'          => __( 'Rollback progress and results', 'bulk-cogs-editor-for-woocommerce' ),
			'rollbackBehavior'  => __( 'Rollback behavior', 'bulk-cogs-editor-for-woocommerce' ),
			'overwrite'         => __( 'Overwrite current costs with original costs', 'bulk-cogs-editor-for-woocommerce' ),
			'skipChanged'       => __( 'Skip products whose costs changed afterward', 'bulk-cogs-editor-for-woocommerce' ),
			'policyHelp'        => __( 'Applies to new rollbacks. The chosen behavior is shown during review and remains fixed while the rollback runs.', 'bulk-cogs-editor-for-woocommerce' ),
			'skipWarning'       => __( 'Rollback restores original costs only when current costs still match the values saved by this operation. Products changed afterward or deleted will be skipped. Costs are checked again during processing.', 'bulk-cogs-editor-for-woocommerce' ),
			'skipConfirm'       => __( 'I understand that rollback restores original costs and skips products whose costs changed afterward.', 'bulk-cogs-editor-for-woocommerce' ),
			'settings'          => __( 'Settings', 'bulk-cogs-editor-for-woocommerce' ),
			'retention'         => __( 'History retention', 'bulk-cogs-editor-for-woocommerce' ),
			'retentionHelp'     => __( 'Keep operation history and original costs for this many days after completion. Expired history is deleted automatically and cannot be rolled back. Reducing this period also applies to existing history.', 'bulk-cogs-editor-for-woocommerce' ),
			'days30'            => __( '30 days', 'bulk-cogs-editor-for-woocommerce' ),
			'days90'            => __( '90 days', 'bulk-cogs-editor-for-woocommerce' ),
			'custom'            => __( 'Custom days', 'bulk-cogs-editor-for-woocommerce' ),
			'days'              => __( 'Days', 'bulk-cogs-editor-for-woocommerce' ),
			'save'              => __( 'Save settings', 'bulk-cogs-editor-for-woocommerce' ),
			'forceClear'        => __( 'Force clear current operation', 'bulk-cogs-editor-for-woocommerce' ),
			'forceClearWarning' => __( 'Warning: Use this only when your operation is stuck and normal cancellation does not work. This deletes your saved current operation and import references and requests cancellation. An active worker may continue briefly. Costs already changed are not restored. History and rollback data are kept. Check operation history before starting another update.', 'bulk-cogs-editor-for-woocommerce' ),
			'forceClearConfirm' => __( 'Force clear your current operation and import references? This cannot undo cost changes or immediately stop an active worker.', 'bulk-cogs-editor-for-woocommerce' ),
			'saved'             => __( 'Settings saved.', 'bulk-cogs-editor-for-woocommerce' ),
			'filter'            => __( 'Filter', 'bulk-cogs-editor-for-woocommerce' ),
			'allStatuses'       => __( 'All statuses', 'bulk-cogs-editor-for-woocommerce' ),
			'allActions'        => __( 'All operation types', 'bulk-cogs-editor-for-woocommerce' ),
			'allUsers'          => __( 'All users', 'bulk-cogs-editor-for-woocommerce' ),
			'userId'            => __( 'User ID', 'bulk-cogs-editor-for-woocommerce' ),
			'from'              => __( 'From date', 'bulk-cogs-editor-for-woocommerce' ),
			'to'                => __( 'To date', 'bulk-cogs-editor-for-woocommerce' ),
			'search'            => __( 'Search by operation ID, note, or product ID', 'bulk-cogs-editor-for-woocommerce' ),
			'id'                => __( 'Operation ID', 'bulk-cogs-editor-for-woocommerce' ),
			'date'              => __( 'Date & time', 'bulk-cogs-editor-for-woocommerce' ),
			'action'            => __( 'Operation type', 'bulk-cogs-editor-for-woocommerce' ),
			'products'          => __( 'Products', 'bulk-cogs-editor-for-woocommerce' ),
			'status'            => __( 'Status', 'bulk-cogs-editor-for-woocommerce' ),
			'user'              => __( 'User', 'bulk-cogs-editor-for-woocommerce' ),
			'note'              => __( 'Note / description', 'bulk-cogs-editor-for-woocommerce' ),
			'view'              => __( 'View details', 'bulk-cogs-editor-for-woocommerce' ),
			'old'               => __( 'Old COGS (original)', 'bulk-cogs-editor-for-woocommerce' ),
			'new'               => __( 'New COGS (after operation)', 'bulk-cogs-editor-for-woocommerce' ),
			'current'           => __( 'Current COGS (now)', 'bulk-cogs-editor-for-woocommerce' ),
			'restore'           => __( 'Rollback to (original)', 'bulk-cogs-editor-for-woocommerce' ),
			'change'            => __( 'Change', 'bulk-cogs-editor-for-woocommerce' ),
			'product'           => __( 'Product', 'bulk-cogs-editor-for-woocommerce' ),
			'sku'               => __( 'SKU', 'bulk-cogs-editor-for-woocommerce' ),
			'back'              => __( 'Back to history', 'bulk-cogs-editor-for-woocommerce' ),
			'backChanges'       => __( 'Back to product changes', 'bulk-cogs-editor-for-woocommerce' ),
			'confirm'           => __( 'Confirm rollback', 'bulk-cogs-editor-for-woocommerce' ),
			'warning'           => __( 'Rollback restores the original costs shown in this review. Later changes will be overwritten. Deleted products cannot be restored.', 'bulk-cogs-editor-for-woocommerce' ),
			'unavailable'       => __( 'Rollback is unavailable: this operation has no retained original values, is still running, has expired, or already has a rollback.', 'bulk-cogs-editor-for-woocommerce' ),
			'empty'             => __( 'No operations found.', 'bulk-cogs-editor-for-woocommerce' ),
			/* translators: %1$d: products with retained original costs, %2$d: successful products in the operation. */
			'partialHistory'    => __( 'Original values are available for %1$d of %2$d successful products. Only products with recorded original values can be rolled back.', 'bulk-cogs-editor-for-woocommerce' ),
			'noChanges'         => __( 'No recorded product changes. Older operations have summary history only.', 'bulk-cogs-editor-for-woocommerce' ),
			'indexing'          => __( 'Indexing older operation history…', 'bulk-cogs-editor-for-woocommerce' ),
			'show'              => __( 'Show entries', 'bulk-cogs-editor-for-woocommerce' ),
			'previous'          => __( 'Previous page', 'bulk-cogs-editor-for-woocommerce' ),
			'next'              => __( 'Next page', 'bulk-cogs-editor-for-woocommerce' ),
			/* translators: %1$d: current page, %2$d: total pages, %3$d: total entries. */
			'page'              => __( 'Page %1$d of %2$d · %3$d entries', 'bulk-cogs-editor-for-woocommerce' ),
			'updated'           => __( 'Products updated', 'bulk-cogs-editor-for-woocommerce' ),
			'started'           => __( 'Started at', 'bulk-cogs-editor-for-woocommerce' ),
			'completed'         => __( 'Completed at', 'bulk-cogs-editor-for-woocommerce' ),
			'duration'          => __( 'Duration', 'bulk-cogs-editor-for-woocommerce' ),
			'filters'           => __( 'Filters used', 'bulk-cogs-editor-for-woocommerce' ),
			'none'              => __( 'None', 'bulk-cogs-editor-for-woocommerce' ),
			'rounding'          => __( 'Rounding', 'bulk-cogs-editor-for-woocommerce' ),
			'roundingValue'     => __( 'WooCommerce cost precision', 'bulk-cogs-editor-for-woocommerce' ),
			'rollback'          => __( 'Rollback', 'bulk-cogs-editor-for-woocommerce' ),
			'noteHelp'          => __( 'Optional note for this operation', 'bulk-cogs-editor-for-woocommerce' ),
			'confirmCheck'      => __( 'I understand that rollback restores original costs and may overwrite later changes.', 'bulk-cogs-editor-for-woocommerce' ),
			'rollbackExists'    => __( 'View rollback operation', 'bulk-cogs-editor-for-woocommerce' ),
			'loading'           => __( 'Loading…', 'bulk-cogs-editor-for-woocommerce' ),
			'cancelRollback'    => __( 'Cancel rollback', 'bulk-cogs-editor-for-woocommerce' ),
			'actions'           => array(
				'set'              => __( 'Set exact cost', 'bulk-cogs-editor-for-woocommerce' ),
				'clear'            => __( 'Clear cost', 'bulk-cogs-editor-for-woocommerce' ),
				'increase_percent' => __( 'Percentage increase', 'bulk-cogs-editor-for-woocommerce' ),
				'decrease_percent' => __( 'Percentage decrease', 'bulk-cogs-editor-for-woocommerce' ),
				'increase_fixed'   => __( 'Fixed increase', 'bulk-cogs-editor-for-woocommerce' ),
				'decrease_fixed'   => __( 'Fixed decrease', 'bulk-cogs-editor-for-woocommerce' ),
				'csv_import'       => __( 'CSV import', 'bulk-cogs-editor-for-woocommerce' ),
				'rollback'         => __( 'Rollback', 'bulk-cogs-editor-for-woocommerce' ),
			),
			'statuses'          => array(
				'pending'               => __( 'Queued', 'bulk-cogs-editor-for-woocommerce' ),
				'processing'            => __( 'Processing', 'bulk-cogs-editor-for-woocommerce' ),
				'completed'             => __( 'Completed', 'bulk-cogs-editor-for-woocommerce' ),
				'completed_with_errors' => __( 'Partially completed', 'bulk-cogs-editor-for-woocommerce' ),
				'failed'                => __( 'Failed', 'bulk-cogs-editor-for-woocommerce' ),
				'cancelled'             => __( 'Cancelled', 'bulk-cogs-editor-for-woocommerce' ),
			),
		);
	}

	/**
	 * Authorize a request before reading any history or settings.
	 *
	 * @return void
	 */
	private function authorize() {
		if ( false === check_ajax_referer( DKBCE_Admin_Functions::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload this page.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_others_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage product COGS.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
	}

	/**
	 * Read a scalar request string, unslashed and sanitized.
	 *
	 * @param string $key Request key.
	 * @param string $fallback Default value.
	 * @return string
	 */
	private function input( $key, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every endpoint calls authorize before reading input.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $fallback;
	}

	/**
	 * Read an accessible operation or return a generic not-found response.
	 *
	 * @return array
	 */
	private function operation() {
		$id    = $this->input( 'operation_id' );
		$store = new DKBCE_Operation_Store();
		$state = preg_match( '/\A[0-9a-f-]{36}\z/i', $id ) ? $store->get( $id ) : false;
		if ( ! $state || ( get_current_user_id() !== (int) $state['user_id'] && ! current_user_can( 'manage_woocommerce' ) ) || in_array( $state['action'], array( 'import_preview', 'server_download' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This operation is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) ), 404 );
		}
		return $state;
	}

	/**
	 * Add history metadata to the shared progress response.
	 *
	 * @param array $state Operation state.
	 * @return array
	 */
	private function summary( $state ) {
		$admin = DKBCE_Admin_Functions::get_instance();
		$user  = get_userdata( $state['user_id'] );
		return array_merge(
			$admin->public_operation( $state ),
			array(
				'created_at'      => (int) $state['created_at'],
				'started_at'      => (int) $state['started_at'],
				'user'            => $user ? $user->display_name : __( 'Deleted user', 'bulk-cogs-editor-for-woocommerce' ),
				'note'            => $state['note'] ?? '',
				'value'           => $state['value'],
				'filters'         => $this->filter_summary( $state['filters'] ?? array() ),
				'rollback_source' => $state['rollback_source'] ?? '',
				'rollback_policy' => $state['rollback_policy'] ?? DKBCE_History_Store::rollback_policy(),
			)
		);
	}

	/**
	 * Format product filters without exposing internal snapshot and selection data.
	 *
	 * @param array $filters Stored product filters.
	 * @return array
	 */
	private function filter_summary( $filters ) {
		$labels  = array(
			'search'       => __( 'Search', 'bulk-cogs-editor-for-woocommerce' ),
			'category'     => __( 'Categories', 'bulk-cogs-editor-for-woocommerce' ),
			'brand'        => __( 'Brands', 'bulk-cogs-editor-for-woocommerce' ),
			'type'         => __( 'Product types', 'bulk-cogs-editor-for-woocommerce' ),
			'stock_status' => __( 'Stock status', 'bulk-cogs-editor-for-woocommerce' ),
			'price_min'    => __( 'Minimum price', 'bulk-cogs-editor-for-woocommerce' ),
			'price_max'    => __( 'Maximum price', 'bulk-cogs-editor-for-woocommerce' ),
			'cogs_min'     => __( 'Minimum COGS', 'bulk-cogs-editor-for-woocommerce' ),
			'cogs_max'     => __( 'Maximum COGS', 'bulk-cogs-editor-for-woocommerce' ),
		);
		$service = new DKBCE_COGS_Service();
		$types   = $service->get_product_types();
		$stocks  = wc_get_product_stock_status_options();
		$summary = array();
		foreach ( $labels as $key => $label ) {
			$values = $filters[ $key ] ?? '';
			if ( '' === $values || array() === $values ) {
				continue;
			}
			$display = array();
			foreach ( (array) $values as $value ) {
				if ( 'category' === $key || 'brand' === $key ) {
					$term      = get_term( (int) $value );
					$display[] = $term && ! is_wp_error( $term ) ? $term->name : (string) $value;
				} elseif ( 'type' === $key ) {
					$display[] = $types[ $value ] ?? $value;
				} elseif ( 'stock_status' === $key ) {
					$display[] = $stocks[ $value ] ?? $value;
				} else {
					$display[] = (string) $value;
				}
			}
			$summary[ $label ] = implode( ', ', $display );
		}
		return $summary;
	}

	/**
	 * Return filtered history, using bounded legacy indexing when necessary.
	 *
	 * @return void
	 * @throws RuntimeException When a date range is invalid; returned as JSON.
	 */
	public function ajax_history() {
		$this->authorize();
		try {
			$filters = array();
			foreach ( array( 'status', 'action', 'user_id', 'search' ) as $key ) {
				$filters[ $key ] = substr( $this->input( 'action' === $key ? 'action_type' : $key ), 0, 200 );
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				$filters['user_id'] = (string) get_current_user_id();
			}
			foreach ( array( 'from', 'to' ) as $key ) {
				$value = $this->input( $key );
				if ( '' !== $value ) {
					$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
					if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
						throw new RuntimeException( esc_html__( 'Enter a valid date range.', 'bulk-cogs-editor-for-woocommerce' ) );
					}
					$filters[ $key ] = 'to' === $key ? $date->modify( '+1 day' )->getTimestamp() - 1 : $date->getTimestamp();
				}
			}
			if ( isset( $filters['from'], $filters['to'] ) && $filters['from'] > $filters['to'] ) {
				throw new RuntimeException( esc_html__( 'The start date must come before the end date.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$history      = new DKBCE_History_Store();
			$store        = new DKBCE_Operation_Store();
			$page         = max( 1, absint( $this->input( 'page', '1' ) ) );
			$size         = min( 100, max( 1, absint( $this->input( 'size', '10' ) ) ) );
			$data         = $history->operations( $filters, $page, $size );
			$data['rows'] = array();
			foreach ( $data['ids'] as $id ) {
				$state = $store->get( $id );
				if ( $state ) {
					$data['rows'][] = $this->summary( $state );
				}
			}
			$data['users'] = $history->users( current_user_can( 'manage_woocommerce' ) ? 0 : get_current_user_id() );
			unset( $data['ids'] );
			wp_send_json_success( $data );
		} catch ( Throwable $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 400 );
		}
	}

	/**
	 * Return details, recorded changes, or a current-cost rollback review.
	 *
	 * @return void
	 */
	public function ajax_history_detail() {
		$this->authorize();
		$state = $this->operation();
		try {
			$store     = new DKBCE_Operation_Store();
			$processor = new DKBCE_Bulk_Processor( $store, new DKBCE_COGS_Service() );
			$processor->recover_if_stale( $state );
			$state     = $store->get( $state['operation_id'], true );
			$history   = new DKBCE_History_Store();
			$service   = new DKBCE_COGS_Service();
			$page      = max( 1, absint( $this->input( 'page', '1' ) ) );
			$size      = min( 100, max( 1, absint( $this->input( 'size', '10' ) ) ) );
			$review    = '1' === $this->input( 'review' );
			$policy    = DKBCE_History_Store::rollback_policy();
			$attempt   = $history->rollback_state( $state['operation_id'] );
			$data      = $history->products( $state['operation_id'], $size, ( $page - 1 ) * $size, -1, $review );
			$remaining = $review ? $data['total'] : $history->products( $state['operation_id'], 1, 0, -1, true )['total'];
			$rows      = array();
			foreach ( $data['rows'] as $record ) {
				$product                 = wc_get_product( $record['product_id'] );
				$original                = null === $record['original_value'] ? null : (float) $record['original_value'];
				$target                  = null === $record['target_value'] ? null : (float) $record['target_value'];
				$row                     = $product ? $service->preview_row(
					$product,
					array(
						'action' => 'clear',
						'value'  => '',
					)
				) : array(
					'id'        => (int) $record['product_id'],
					'name'      => __( 'Deleted product', 'bulk-cogs-editor-for-woocommerce' ),
					'sku'       => '',
					'edit_url'  => '',
					'image_url' => '',
					'status'    => 'success',
				);
				$row['current']          = $review && $product ? $product->get_cogs_value() : $original;
				$row['new']              = $review ? $original : $target;
				$row['change']           = (float) $row['new'] - (float) $row['current'];
				$row['change_direction'] = $row['change'] > 0 ? 'up' : ( $row['change'] < 0 ? 'down' : '' );
				$row['change_percent']   = $row['current'] ? $row['change'] / $row['current'] * 100 : null;
				if ( $review && $product && 'skip_changed' === $policy && $product->get_cogs_value() !== $target ) {
					$row['status'] = 'skipped';
					$row['reason'] = __( 'COGS changed after the original operation. The product will be skipped.', 'bulk-cogs-editor-for-woocommerce' );
				}
				if ( $review && ! $product ) {
					$row['status'] = 'skipped';
					$row['reason'] = __( 'Product no longer exists.', 'bulk-cogs-editor-for-woocommerce' );
				}
				$rows[] = $row;
			}
			$claim          = $attempt ? $attempt['operation_id'] : '';
			$active_attempt = $attempt && ! in_array( $attempt['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true );
			$terminal       = in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true );
			$eligible       = $terminal && empty( $state['history_cleanup_pending'] ) && empty( $state['history_cleanup_complete'] ) && empty( $state['history_cleanup_started'] ) && 'rollback' !== $state['action'] && $remaining > 0 && ! $active_attempt && time() - (int) ( ! empty( $state['completed_at'] ) ? $state['completed_at'] : $state['created_at'] ) < DKBCE_Retention_Processor::retention_seconds();
			$token          = '';
			if ( $review && $eligible ) {
				$token = wp_generate_uuid4();
				set_transient(
					'dkbce_rollback_review_' . $token,
					array(
						'operation_id' => $state['operation_id'],
						'user_id'      => get_current_user_id(),
						'total'        => $remaining,
						'policy'       => $policy,
					),
					15 * MINUTE_IN_SECONDS
				);
			}
			wp_send_json_success(
				array(
					'state'           => $this->summary( $state ),
					'rows'            => $rows,
					'total'           => $data['total'],
					'can_rollback'    => $eligible,
					'rollback_id'     => $claim,
					'review_token'    => $token,
					'rollback_policy' => $policy,
				)
			);
		} catch ( Throwable $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 400 );
		}
	}

	/**
	 * Start a single idempotent rollback after an explicit, unexpired review.
	 *
	 * @return void
	 * @throws RuntimeException When rollback cannot start; returned as JSON.
	 */
	public function ajax_rollback() {
		$this->authorize();
		$source = $this->operation();
		try {
			$token  = $this->input( 'review_token' );
			$review = get_transient( 'dkbce_rollback_review_' . $token );
			if ( ! is_array( $review ) || '1' !== $this->input( 'confirmed' ) || get_current_user_id() !== (int) $review['user_id'] || $review['operation_id'] !== $source['operation_id'] ) {
				throw new RuntimeException( esc_html__( 'Review this rollback again before confirming.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$state = $this->start_rollback( $source['operation_id'], $review, $token );
			wp_send_json_success(
				array(
					'operation_id' => $state['operation_id'],
					'state'        => $this->summary( $state ),
				)
			);
		} catch ( Throwable $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 400 );
		}
	}

	/**
	 * Serialize a reviewed rollback attempt with source retention and other starts.
	 *
	 * @param string $source_id Source operation UUID.
	 * @param array  $review User-bound reviewed scope and policy.
	 * @param string $token Review UUID, also used for idempotent request retries.
	 * @return array Persisted rollback state.
	 * @throws RuntimeException When review, locking or scheduling fails.
	 * @throws Throwable When persistence fails.
	 */
	private function start_rollback( $source_id, $review, $token ) {
		$service   = new DKBCE_COGS_Service();
		$store     = new DKBCE_Operation_Store();
		$processor = new DKBCE_Bulk_Processor( $store, $service );
		if ( ! $service->is_cogs_available() || ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new RuntimeException( esc_html__( 'COGS or background processing is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$lock = $processor->acquire_operation_lock( $source_id );
		if ( ! $lock ) {
			throw new RuntimeException( esc_html__( 'This operation is busy. Please try again shortly.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		try {
			$source = $store->get( $source_id, true );
			if ( ! $source || ( $review['policy'] ?? '' ) !== DKBCE_History_Store::rollback_policy() || ! in_array( $source['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) || 'rollback' === $source['action'] || ! empty( $source['history_cleanup_pending'] ) || ! empty( $source['history_cleanup_complete'] ) || ! empty( $source['history_cleanup_started'] ) || time() - (int) ( ! empty( $source['completed_at'] ) ? $source['completed_at'] : $source['created_at'] ) >= DKBCE_Retention_Processor::retention_seconds() ) {
				throw new RuntimeException( esc_html__( 'Review this rollback again before confirming.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$history  = new DKBCE_History_Store();
			$existing = $history->rollback_state( $source_id );
			if ( $existing && ( ( $existing['rollback_review_token'] ?? '' ) === $token || ! in_array( $existing['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) ) {
				$processor->recover_if_stale( $existing );
				return $existing;
			}
			$remaining = $history->products( $source_id, 1, 0, -1, true );
			if ( ! $remaining['total'] || (int) $review['total'] !== $remaining['total'] ) {
				throw new RuntimeException( esc_html__( 'The remaining rollback scope changed. Review it again.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			$id    = wp_generate_uuid4();
			$key   = 'dkbce_rollback_' . $source_id;
			$state = array(
				'operation_id'          => $id,
				'user_id'               => get_current_user_id(),
				'created_at'            => time(),
				'requested_at'          => microtime( true ),
				'started_at'            => 0,
				'completed_at'          => 0,
				'total'                 => 0,
				'processed'             => 0,
				'succeeded'             => 0,
				'skipped'               => 0,
				'failed'                => 0,
				'action'                => 'rollback',
				'value'                 => '',
				'operation'             => array(
					'action' => 'rollback',
					'value'  => '',
				),
				'filters'               => array(),
				'rollback_source'       => $source['operation_id'],
				'rollback_policy'       => $review['policy'],
				'rollback_review_token' => $token,
				'note'                  => $source['note'] ?? '',
				'status'                => 'pending',
				'stage'                 => 'snapshot',
				'snapshot_chunks'       => 0,
				'processed_chunks'      => 0,
				'counted_chunk_results' => 0,
				'batch_size'            => $processor->get_batch_size(),
				'expected_total'        => $review['total'],
				'cancel_requested'      => false,
				'errors'                => array(),
				'error_summary'         => '',
			);
			// A missing target from an interrupted startup is replaced under this lock.
			update_option( $key, $id, false );
			wp_cache_delete( $key, 'options' );
			if ( get_option( $key, '' ) !== $id ) {
				throw new RuntimeException( esc_html__( 'The rollback claim could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			try {
				$store->save( $state );
			} catch ( Throwable $exception ) {
				delete_option( $key );
				throw $exception;
			}
			if ( ! $processor->enqueue( $id ) ) {
				$state['status']        = 'failed';
				$state['completed_at']  = time();
				$state['error_summary'] = __( 'Background processing could not be started. No products were changed.', 'bulk-cogs-editor-for-woocommerce' );
				$store->save( $state );
				throw new RuntimeException( esc_html( $state['error_summary'] ) );
			}
			update_user_meta( get_current_user_id(), 'dkbce_latest_operation', $id );
			return $state;
		} finally {
			$processor->release_operation_lock( $source_id, $lock );
		}
	}

	/**
	 * Clear only the current user's reviewed operation references, even for stuck jobs.
	 *
	 * @return void
	 */
	public function ajax_force_clear() {
		$this->authorize();
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage these settings.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		if ( '1' !== $this->input( 'confirmed' ) ) {
			wp_send_json_error( array( 'message' => __( 'Confirm the warning before clearing the current operation.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
		}
		$user_id = get_current_user_id();
		$keys    = array( 'dkbce_latest_operation', 'dkbce_latest_import' );
		$ids     = array();
		foreach ( $keys as $key ) {
			$ids[ $key ] = get_user_meta( $user_id, $key, true );
			if ( $ids[ $key ] !== $this->input( $key ) ) {
				wp_send_json_error( array( 'message' => __( 'Your current operation changed. Reload this page before clearing it.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
			}
		}
		$store = new DKBCE_Operation_Store();
		foreach ( $ids as $key => $id ) {
			if ( '' === $id ) {
				continue;
			}
			$operation = $store->get( $id );
			if ( $operation && $user_id === (int) $operation['user_id'] && ! in_array( $operation['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled', 'expired' ), true ) ) {
				$store->request_cancellation( $id );
				if ( ! $store->is_cancel_requested( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'Cancellation could not be saved. Please try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 500 );
				}
			}
			// Match the reviewed value so another tab's new reference is never deleted.
			delete_user_meta( $user_id, $key, $id );
			if ( get_user_meta( $user_id, $key, true ) === $id ) {
				wp_send_json_error( array( 'message' => __( 'The operation could not be cleared. Please reload this page and try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 409 );
			}
		}
		wp_send_json_success();
	}

	/**
	 * Read or save retention settings for WooCommerce managers.
	 *
	 * @return void
	 */
	public function ajax_settings() {
		$this->authorize();
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage these settings.', 'bulk-cogs-editor-for-woocommerce' ) ), 403 );
		}
		if ( '1' === $this->input( 'save' ) ) {
			$days = $this->input( 'days' );
			if ( ! preg_match( '/\A[1-9][0-9]{0,4}\z/', $days ) || (int) $days > 36500 ) {
				wp_send_json_error( array( 'message' => __( 'Enter a retention period between 1 and 36,500 days.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			$policy = $this->input( 'rollback_policy', DKBCE_History_Store::rollback_policy() );
			if ( ! in_array( $policy, array( 'overwrite', 'skip_changed' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose a valid rollback behavior.', 'bulk-cogs-editor-for-woocommerce' ) ), 400 );
			}
			update_option( 'dkbce_retention_days', (int) $days, false );
			update_option( 'dkbce_rollback_policy', $policy, false );
			if ( (int) get_option( 'dkbce_retention_days', 30 ) !== (int) $days || DKBCE_History_Store::rollback_policy() !== $policy ) {
				wp_send_json_error( array( 'message' => __( 'Settings could not be saved. Please try again.', 'bulk-cogs-editor-for-woocommerce' ) ), 500 );
			}
		}
		wp_send_json_success(
			array(
				'dkbce_latest_operation' => get_user_meta( get_current_user_id(), 'dkbce_latest_operation', true ),
				'dkbce_latest_import'    => get_user_meta( get_current_user_id(), 'dkbce_latest_import', true ),
				'days'                   => DKBCE_Retention_Processor::retention_seconds() / DAY_IN_SECONDS,
				'rollback_policy'        => DKBCE_History_Store::rollback_policy(),
			)
		);
	}
}
