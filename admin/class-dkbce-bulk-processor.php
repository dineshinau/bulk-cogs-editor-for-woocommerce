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
	const BATCH_SIZE = 50;
	const HOOK       = 'dkbce_process_operation';
	const GROUP      = 'bulk-cogs-editor';

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
		add_action( self::HOOK, array( $this, 'process_operation' ), 10, 1 );
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
	 * Process one snapshot or update batch.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	public function process_operation( $operation_id ) {
		$state = $this->store->get( $operation_id );
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

		if ( 'snapshot' === $state['stage'] ) {
			$this->snapshot_batch( $state );
			return;
		}

		$this->update_batch( $state );
	}

	/**
	 * Collect one bounded page of matching IDs before updates begin.
	 *
	 * @param array $state Operation state.
	 * @return void
	 */
	private function snapshot_batch( $state ) {
		$types      = $this->service->selected_types( $state['filters'] );
		$type_count = count( $types );
		while ( $state['type_index'] < $type_count ) {
			$type = $types[ $state['type_index'] ];
			$ids  = $this->service->query_ids( $state['filters'], $type, $state['page'] );
			if ( ! $ids ) {
				++$state['type_index'];
				$state['page'] = 1;
				continue;
			}

			$matched_ids = array();
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product && $this->service->product_matches( $product, $state['filters'] ) ) {
					$matched_ids[] = $id;
				}
			}
			if ( $matched_ids ) {
				++$state['snapshot_chunks'];
				$state['total'] += count( $matched_ids );
				$this->store->save_chunk( $state['operation_id'], $state['snapshot_chunks'], $matched_ids );
			}
			++$state['page'];
			$this->store->save( $state );
			$this->queue_next( $state );
			return;
		}

		$state['stage'] = 'processing';
		if ( 0 === $state['total'] ) {
			$this->finish_operation( $state, 'completed' );
		} else {
			$this->store->save( $state );
			$this->queue_next( $state );
		}
	}

	/**
	 * Process one persisted ID batch using WooCommerce CRUD.
	 *
	 * @param array $state Operation state.
	 * @return void
	 */
	private function update_batch( $state ) {
		$chunk_number = $state['processed_chunks'] + 1;
		$chunk        = $this->store->get_chunk( $state['operation_id'], $chunk_number );
		if ( ! $chunk ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed' );
			return;
		}

		foreach ( $chunk['ids'] as $product_id ) {
			$state = $this->store->get( $state['operation_id'] );
			if ( ! empty( $state['cancel_requested'] ) ) {
				$this->finish_operation( $state, 'cancelled' );
				return;
			}

			$key = (string) $product_id;
			if ( isset( $chunk['results'][ $key ] ) ) {
				continue;
			}

			$outcome = $this->process_product( $product_id, $state );
			if ( 'deferred' === $outcome['status'] ) {
				$retry_action = function_exists( 'as_schedule_single_action' )
					? as_schedule_single_action( time() + 5, self::HOOK, array( $state['operation_id'] ), self::GROUP, false )
					: 0;
				if ( ! $retry_action ) {
					$state['error_summary'] = __( 'The background queue became unavailable. Some changes may already have been applied.', 'bulk-cogs-editor-for-woocommerce' );
					$this->finish_operation( $state, 'failed' );
				}
				return;
			}
			$chunk['results'][ $key ] = $outcome;
			$this->store->update_chunk( $state['operation_id'], $chunk_number, $chunk );
			++$state['processed'];
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
			$this->store->save( $state );
		}
		$state = $this->store->get( $state['operation_id'] );
		++$state['processed_chunks'];
		if ( $state['processed_chunks'] >= $state['snapshot_chunks'] ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed' );
			return;
		}
		$this->store->save( $state );
		$this->queue_next( $state );
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
			return array(
				'status'  => 'deferred',
				'message' => '',
			);
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array(
					'status'  => 'skipped',
					'message' => __( 'Product no longer exists.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}

			if ( $state['operation_id'] === $product->get_meta( '_dkbce_last_bulk_cogs_operation', true ) ) {
				return array(
					'status'  => 'success',
					'message' => '',
				);
			}

			if ( ! $this->service->product_matches( $product, $state['filters'] ) ) {
				return array(
					'status'  => 'skipped',
					'message' => __( 'Product no longer matches the selected filters.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}

			$result = $this->service->calculate( $product, $state['operation'] );
			if ( is_wp_error( $result ) ) {
				return array(
					'status'  => 'failed',
					'message' => __( 'COGS could not be calculated.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}
			if ( 'skipped' === $result['status'] ) {
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
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					$exception->getMessage(),
					array(
						'source'       => 'bulk-cogs-editor',
						'product_id'   => $product_id,
						'operation_id' => $state['operation_id'],
					)
				);
			}
			return array(
				'status'  => 'failed',
				'message' => __( 'Product could not be updated. Check the WooCommerce log for details.', 'bulk-cogs-editor-for-woocommerce' ),
			);
		} finally {
			delete_option( 'dkbce_product_lock_' . absint( $product_id ) );
		}
	}
}
