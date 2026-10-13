<?php
/**
 * Action Scheduler worker for bulk COGS updates.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshots matching IDs and processes them in Action Scheduler batches.
 */
class DKBCE_Bulk_Processor {
	const BATCH_SIZE        = 50;
	const BATCH_TIME_LIMIT  = 20;
	const PROGRESS_INTERVAL = 5;
	const HOOK              = 'dkbce_process_operation';
	const GROUP             = 'bulk-cogs-editor';

	/** Operation state store.
	 *
	 * @var DKBCE_Operation_Store
	 */
	private $store;

	/** COGS filters and calculations.
	 *
	 * @var DKBCE_COGS_Service
	 */
	private $service;

	/**
	 * Set up the batch hook.
	 *
	 * @param DKBCE_Operation_Store $store Operation store.
	 * @param DKBCE_COGS_Service    $service COGS service.
	 */
	public function __construct( $store, $service ) {
		$this->store   = $store;
		$this->service = $service;
		add_action( self::HOOK, array( $this, 'process_operation' ) );
	}

	/**
	 * Queue an operation's next batch.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return bool
	 */
	public function enqueue( $operation_id ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		return (bool) as_enqueue_async_action( self::HOOK, array( $operation_id ), self::GROUP, false );
	}

	/**
	 * Get the product limit to persist when creating a new operation.
	 *
	 * @return int
	 */
	public function get_batch_size() {
		/**
		 * Filter the background product batch size, independently of preview pages.
		 *
		 * @param int $batch_size Products per batch, bounded between 1 and 1,000.
		 * @since 1.0.0
		 */
		$batch_size = apply_filters( 'dkbce_batch_size', self::BATCH_SIZE );
		return max( 1, min( DKBCE_COGS_Service::MAX_QUERY_BATCH_SIZE, absint( $batch_size ) ) );
	}

	/**
	 * Process persisted chunks within a bounded worker request.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	public function process_operation( $operation_id ) {
		$deadline = microtime( true ) + self::BATCH_TIME_LIMIT;
		$state    = $this->store->get( $operation_id );
		if ( ! $state || in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) {
			return;
		}

		if ( ! empty( $state['cancel_requested'] ) ) {
			dkbce_wc_log( 'Update cancelled.' );
			$this->finish_operation( $state, 'cancelled' );
			return;
		}

		if ( ! $this->service->is_cogs_available() ) {
			$state['status']        = 'failed';
			$state['error_summary'] = __( 'WooCommerce COGS is no longer available or enabled.', 'bulk-cogs-editor-for-woocommerce' );
			$this->finish_operation( $state, 'failed' );
			return;
		}

		$state['status']     = 'processing';
		$state['started_at'] = $state['started_at'] ? $state['started_at'] : time();
		$this->store->save( $state );

		// Reuse the loaded WordPress request across chunks. Bound memory growth too.
		$chunks = 0;
		while ( $chunks < 10 && microtime( true ) < $deadline ) {
			++$chunks;
			$continue = 'snapshot' === $state['stage']
				? $this->snapshot_batch( $state )
				: $this->update_batch( $state, $deadline );
			if ( ! $continue ) {
				return;
			}
		}

		$this->queue_next( $state );
	}

	/**
	 * Collect one bounded page of matching IDs before updates begin.
	 *
	 * @param array $state Operation state.
	 * @return bool Whether another chunk may run in this request.
	 */
	private function snapshot_batch( &$state ) {
		if ( ! isset( $state['batch_size'] ) ) {
			// Keep the page width of operations created before it was persisted.
			$first_chunk         = $state['snapshot_chunks'] ? $this->store->get_chunk( $state['operation_id'], 1 ) : false;
			$state['batch_size'] = $first_chunk ? count( $first_chunk['ids'] ) : self::BATCH_SIZE;
		}

		$filters              = $state['filters'] ?? array();
		$filters['page_no']   = $state['page'] ?? 1;
		$filters['page_size'] = $state['batch_size'];

		if ( ! isset( $state['total_count'] ) ) {
			$filters['get_ids']   = false;
			$filters['count']     = true;
			$state['total_count'] = $this->service->scan_matches( $filters );
		}

		$filters['get_ids'] = true;
		$filters['count']   = false;
		$product_ids        = $this->service->scan_matches( $filters );

		if ( $product_ids ) {
			++$state['snapshot_chunks'];
			$state['total'] += count( $product_ids );
			$this->store->save_chunk( $state['operation_id'], $state['snapshot_chunks'], $product_ids );
			++$state['page'];
			return $this->save_progress( $state );
		}

		// An empty page ends the scan even if products disappeared after counting.
		$state['stage'] = 'processing';

		dkbce_wc_log( 'Total IDs: ' . $state['total_count'] . ', Product IDs count: ' . count( $product_ids ) );

		if ( 0 === $state['total'] ) {
			$this->finish_operation( $state, 'completed' );
			return false;
		}

		return $this->save_progress( $state );
	}

	/**
	 * Process a persisted ID batch until its cooperative time budget expires.
	 *
	 * @param array $state Operation state.
	 * @param float $deadline Time after which another product must not be started.
	 * @return bool Whether another chunk may run in this request.
	 */
	private function update_batch( &$state, $deadline ) {
		$chunk_number = $state['processed_chunks'] + 1;
		$chunk        = $this->store->get_chunk( $state['operation_id'], $chunk_number );

		if ( ! $chunk ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed' );
			return false;
		}

		if ( ! isset( $state['counted_chunk_results'] ) ) {
			// Reconcile operations started before progress checkpoints were introduced.
			$this->apply_summary( $state );
			$state['counted_chunk_results'] = count( $chunk['results'] );
		} else {
			// Recover outcomes saved before an interrupted progress checkpoint.
			$counted_results = $state['counted_chunk_results'];
			$result_index    = 0;
			foreach ( $chunk['results'] as $product_id => $outcome ) {
				if ( $result_index >= $counted_results ) {
					$this->record_outcome( $state, $product_id, $outcome );
				}
				++$result_index;
			}
		}

		$pending_ids = array_diff( $chunk['ids'], array_keys( $chunk['results'] ) );
		if ( $pending_ids && microtime( true ) < $deadline ) {
			// Batch post, term and metadata reads; WooCommerce still owns all writes.
			_prime_post_caches( $pending_ids, true, true );
			$parent_ids = array();
			foreach ( $pending_ids as $product_id ) {
				$parent_id = wp_get_post_parent_id( $product_id );
				if ( $parent_id ) {
					$parent_ids[] = $parent_id;
				}
			}
			if ( $parent_ids ) {
				_prime_post_caches( array_unique( $parent_ids ), true, true );
			}
		}

		$since_checkpoint = 0;
		foreach ( $chunk['ids'] as $product_id ) {
			$key = (string) $product_id;
			if ( isset( $chunk['results'][ $key ] ) ) {
				continue;
			}

			if ( microtime( true ) >= $deadline ) {
				if ( $this->save_progress( $state ) ) {
					$this->queue_next( $state );
				}
				return false;
			}

			$outcome = $this->process_product( $product_id, $state );
			if ( 'deferred' === $outcome['status'] ) {
				if ( ! $this->save_progress( $state ) ) {
					return false;
				}
				$retry_action = function_exists( 'as_schedule_single_action' )
					? as_schedule_single_action( time() + 5, self::HOOK, array( $state['operation_id'] ), self::GROUP, false )
					: 0;
				if ( ! $retry_action ) {
					$state['error_summary'] = __( 'The background queue became unavailable. Some changes may already have been applied.', 'bulk-cogs-editor-for-woocommerce' );
					$this->finish_operation( $state, 'failed' );
				}
				return false;
			}
			$chunk['results'][ $key ] = $outcome;
			// Keep each outcome durable; only the aggregate counters are buffered.
			$this->store->update_chunk( $state['operation_id'], $chunk_number, $chunk );
			$this->record_outcome( $state, $product_id, $outcome );
			++$since_checkpoint;
			if ( $since_checkpoint >= self::PROGRESS_INTERVAL ) {
				if ( ! $this->save_progress( $state ) ) {
					return false;
				}
				$since_checkpoint = 0;
			}
		}

		++$state['processed_chunks'];
		$state['counted_chunk_results'] = 0;
		if ( ! $this->save_progress( $state ) ) {
			return false;
		}
		if ( $state['processed_chunks'] >= $state['snapshot_chunks'] ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed' );
			return false;
		}
		return true;
	}

	/**
	 * Include a durable product outcome in the next progress checkpoint.
	 *
	 * @param array $state Operation state, modified by reference.
	 * @param int   $product_id Product ID.
	 * @param array $outcome Product outcome.
	 * @return void
	 */
	private function record_outcome( &$state, $product_id, $outcome ) {
		++$state['processed'];
		++$state['counted_chunk_results'];
		if ( 'success' === $outcome['status'] ) {
			++$state['succeeded'];
		} elseif ( 'skipped' === $outcome['status'] ) {
			++$state['skipped'];
		} else {
			++$state['failed'];
			if ( count( $state['errors'] ) < 50 ) {
				$state['errors'][] = array(
					'product_id' => $product_id,
					'message'    => $outcome['message'],
				);
			}
		}
	}

	/**
	 * Check for cancellation and persist aggregate progress at a checkpoint.
	 *
	 * @param array $state Operation state, modified by reference.
	 * @return bool Whether processing may continue.
	 */
	private function save_progress( &$state ) {
		if ( ! empty( $state['cancel_requested'] ) || $this->store->is_cancel_requested( $state['operation_id'] ) ) {
			$state['cancel_requested'] = true;
			$this->finish_operation( $state, 'cancelled' );
			return false;
		}
		$this->store->save( $state );
		return true;
	}

	/**
	 * Apply a reliable final outcome summary.
	 *
	 * @param array $state Operation state, modified by reference.
	 * @return void
	 */
	private function apply_summary( &$state ) {
		$summary            = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
		$state['processed'] = $summary['processed'];
		$state['succeeded'] = $summary['succeeded'];
		$state['skipped']   = $summary['skipped'];
		$state['failed']    = $summary['failed'];
		$state['errors']    = $summary['errors'];
	}

	/**
	 * Mark an operation terminal and discard its product ID snapshots.
	 *
	 * @param array  $state Operation state, modified by reference.
	 * @param string $status Terminal status.
	 * @return void
	 */
	private function finish_operation( &$state, $status ) {
		$this->apply_summary( $state );
		$state['status']       = $status;
		$state['completed_at'] = time();

		if ( 'cancelled' === $status ) {
			$state['cancelled_note'] = __( 'Already processed products remain changed; no rollback was performed.', 'bulk-cogs-editor-for-woocommerce' );
		}

		$this->store->save( $state );
		$this->store->delete_chunks( $state['operation_id'], $state['snapshot_chunks'] );
		$duration = isset( $state['requested_at'] ) ? microtime( true ) - $state['requested_at'] : 0;
		dkbce_wc_log(
			sprintf(
				'COGS update completed in %.4f seconds (operation: %s, status: %s, processed: %d, succeeded: %d, skipped: %d, failed: %d).',
				$duration,
				$state['operation_id'],
				$status,
				$state['processed'],
				$state['succeeded'],
				$state['skipped'],
				$state['failed']
			)
		);
	}

	/**
	 * Queue another asynchronous batch or mark the operation failed safely.
	 *
	 * @param array $state Operation state.
	 * @return bool
	 */
	private function queue_next( $state ) {
		if ( $this->enqueue( $state['operation_id'] ) ) {
			return true;
		}

		$state['error_summary'] = __( 'The background queue became unavailable. Some changes may already have been applied.', 'bulk-cogs-editor-for-woocommerce' );
		$this->finish_operation( $state, 'failed' );
		return false;
	}

	/**
	 * Acquire a short-lived per-product lock to serialize concurrent operations.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $operation_id Operation UUID.
	 * @return bool
	 */
	private function acquire_product_lock( $product_id, $operation_id ) {
		$key  = 'dkbce_product_lock_' . absint( $product_id );
		$lock = array(
			'operation_id' => $operation_id,
			'created_at'   => time(),
		);
		if ( add_option( $key, $lock, '', false ) ) {
			return true;
		}

		$existing = get_option( $key, false );
		if ( is_array( $existing ) && isset( $existing['created_at'] ) && time() - absint( $existing['created_at'] ) > 900 ) {
			delete_option( $key );
			return add_option( $key, $lock, '', false );
		}

		return false;
	}

	/**
	 * Safely apply the operation to a single product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $state Operation state.
	 * @return array
	 */
	private function process_product( $product_id, $state ) {
		if ( ! $this->acquire_product_lock( $product_id, $state['operation_id'] ) ) {
			dkbce_wc_log( 'Product lock already acquired for product ID: ' . $product_id );
			return array(
				'status'  => 'deferred',
				'message' => '',
			);
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				dkbce_wc_log( ' Product does not exist for product ID: ' . $product_id );
				return array(
					'status'  => 'skipped',
					'message' => __( 'Product no longer exists.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}

			if ( $state['operation_id'] === $product->get_meta( '_dkbce_last_bulk_cogs_operation', true ) ) {
				dkbce_wc_log( 'Product already processed for product ID: ' . $product_id );
				return array(
					'status'  => 'success',
					'message' => '',
				);
			}

			$result = $this->service->calculate( $product, $state['operation'] );
			if ( is_wp_error( $result ) ) {
				dkbce_wc_log( 'Product could not be calculated for product ID: ' . $product_id );
				return array(
					'status'  => 'failed',
					'message' => __( 'COGS could not be calculated.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}
			if ( 'skipped' === $result['status'] ) {
				dkbce_wc_log( 'Product Skipped  for product ID: ' . $product_id );
				return array(
					'status'  => 'skipped',
					'message' => $result['reason'],
				);
			}

			$product->set_cogs_value( $result['new'] );
			$product->update_meta_data( '_dkbce_last_bulk_cogs_operation', $state['operation_id'] );
			$product->save();
			return array(
				'status'  => 'success',
				'message' => '',
			);
		} catch ( Throwable $exception ) {
			dkbce_wc_log( 'Operation id: ' . $state['operation_id'] . ', Product ID: ' . $product_id . ' Error: ' . $exception->getMessage() );

			return array(
				'status'  => 'failed',
				'message' => __( 'Product could not be updated. Check the WooCommerce log for details.', 'bulk-cogs-editor-for-woocommerce' ),
			);
		} finally {
			delete_option( 'dkbce_product_lock_' . absint( $product_id ) );
		}
	}
}
