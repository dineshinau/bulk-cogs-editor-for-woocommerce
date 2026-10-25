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
	const BATCH_SIZE          = 50;
	const BATCH_TIME_LIMIT    = 20;
	const PROGRESS_INTERVAL   = 25;
	const PROGRESS_TIME_LIMIT = 2;
	const CANCEL_INTERVAL     = 1;
	const OPERATION_LOCK_TTL  = 180;
	const STALE_OPERATION_AGE = 120;
	const HOOK                = 'dkbce_process_operation';
	const CLEANUP_HOOK        = 'dkbce_cleanup_operation';
	const GROUP               = 'bulk-cogs-editor';

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
	 * Timestamp of the last fresh cancellation check in this worker.
	 *
	 * @var float
	 */
	private $last_cancellation_check = 0.0;

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
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_operation' ) );
		add_action( 'dkbce_cleanup_import', array( $this, 'cleanup_import' ) );
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
	 * Requeue an operation whose worker has stopped making progress.
	 *
	 * @param array $state Operation state.
	 * @return void
	 */
	public function recover_if_stale( $state ) {
		if ( 'rollback' === ( $state['action'] ?? '' ) && get_option( 'dkbce_rollback_' . $state['rollback_source'], '' ) !== $state['operation_id'] ) {
			return;
		}
		if ( ! empty( $state['cleanup_pending'] ) ) {
			$this->enqueue_cleanup( $state['operation_id'] );
			return;
		}
		if ( in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled', 'ready', 'expired' ), true ) ) {
			return;
		}

		$last_update = isset( $state['updated_at'] ) ? absint( $state['updated_at'] ) : absint( $state['created_at'] );
		if ( time() - $last_update < self::STALE_OPERATION_AGE ) {
			return;
		}

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, array( $state['operation_id'] ), self::GROUP ) ) {
			return;
		}

		$this->enqueue( $state['operation_id'] );
	}

	/**
	 * Queue bounded cleanup for a terminal operation.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return bool
	 */
	private function enqueue_cleanup( $operation_id ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}
		$queued = as_enqueue_async_action( self::CLEANUP_HOOK, array( $operation_id ), self::GROUP, true );
		return (bool) $queued || ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::CLEANUP_HOOK, array( $operation_id ), self::GROUP ) );
	}

	/**
	 * Remove a terminal operation's temporary data in bounded, retryable batches.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	public function cleanup_operation( $operation_id ) {
		$lock = $this->acquire_operation_lock( $operation_id );
		if ( ! $lock ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + MINUTE_IN_SECONDS, self::CLEANUP_HOOK, array( $operation_id ), self::GROUP );
			}
			return;
		}

		try {
			$state = $this->store->get( $operation_id, true );
			if ( ! $state || empty( $state['cleanup_pending'] ) || ! in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) {
				return;
			}
			$cursor = isset( $state['cleanup_cursor'] ) ? absint( $state['cleanup_cursor'] ) : 1;
			$done   = $this->store->delete_chunks(
				$operation_id,
				$state['snapshot_chunks'],
				! empty( $state['operation']['source_id'] ) || ( ! empty( $state['reusable_selection'] ) && empty( $state['history_cleanup_pending'] ) ),
				$cursor,
				100
			);
			if ( $cursor <= absint( $state['snapshot_chunks'] ) ) {
				$state['cleanup_cursor'] = min( absint( $state['snapshot_chunks'] ) + 1, $cursor + 100 );
			}
			if ( $done && ! empty( $state['history_cleanup_pending'] ) ) {
				$retention = new DKBCE_Retention_Processor();
				if ( ! $retention->is_expired_operation( $state, true ) ) {
					// Settings or an active rollback may have changed since scheduling.
					unset( $state['history_cleanup_pending'] );
				} else {
					if ( empty( $state['history_cleanup_started'] ) ) {
						$state['history_cleanup_started'] = true;
						$this->store->save( $state );
					}
					$history = new DKBCE_History_Store();
					$done    = $history->delete( $operation_id );
					if ( $done ) {
						$state['history_cleanup_complete'] = true;
						unset( $state['history_cleanup_pending'] );
					}
				}
			}
			if ( $done ) {
				$state['cleanup_pending'] = false;
			}
			$this->store->save( $state );
			if ( ! $done ) {
				$this->enqueue_cleanup( $operation_id );
			}
		} finally {
			$this->release_operation_lock( $operation_id, $lock );
		}
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
		$started_at = microtime( true );
		$lock_token = $this->acquire_operation_lock( $operation_id );
		if ( ! $lock_token ) {
			return;
		}
		$this->last_cancellation_check = 0.0;

		$initial_state = false;
		try {
			$initial_state = $this->store->get( $operation_id );
			$this->process_operation_locked( $operation_id );
		} finally {
			try {
				// Read counters while locked so the next worker cannot advance them.
				$state = $this->store->get( $operation_id );
			} finally {
				$this->release_operation_lock( $operation_id, $lock_token );
			}
			$finished_at = microtime( true );
			$runner      = defined( 'WP_CLI' ) && WP_CLI ? 'WP-CLI' : ( wp_doing_cron() ? 'WP-Cron' : 'HTTP' );
			$processed   = isset( $state['processed'] ) ? (int) $state['processed'] : null;
			$total       = isset( $state['total'] ) ? (int) $state['total'] : null;
			$stage       = $state['stage'] ?? 'unknown';
			$remaining   = 'processing' === $stage && null !== $total && null !== $processed ? max( 0, $total - $processed ) : 'unknown';
			// Persisted counter growth includes any outcomes recovered on retry.
			$processed_delta = null !== $processed && isset( $initial_state['processed'] ) ? max( 0, $processed - (int) $initial_state['processed'] ) : 'unknown';
			$message         = sprintf(
				'COGS worker timing (operation: %s, runner: %s, started_at: %.6f, finished_at: %.6f, elapsed: %.4f seconds, processed: %s, remaining: %s, batch_size: %s, processed_delta: %s, total: %s, stage: %s).',
				$operation_id,
				$runner,
				$started_at,
				$finished_at,
				$finished_at - $started_at,
				$processed ?? 'unknown',
				$remaining,
				$state['batch_size'] ?? 'unknown',
				$processed_delta,
				$total ?? 'unknown',
				$stage
			);
			dkbce_wc_log( $message );
			if ( 'WP-CLI' === $runner && class_exists( 'WP_CLI' ) ) {
				WP_CLI::log( $message );
			}
		}
	}

	/**
	 * Clean import staging while excluding a concurrent preview worker.
	 *
	 * @param string $operation_id Import ID.
	 * @return void
	 */
	public function cleanup_import( $operation_id ) {
		$lock = $this->acquire_operation_lock( $operation_id );
		if ( ! $lock ) {
			as_schedule_single_action( time() + MINUTE_IN_SECONDS, 'dkbce_cleanup_import', array( $operation_id ), self::GROUP );
			return;
		}
		try {
			$imports = new DKBCE_Import_Processor();
			$imports->cleanup( $operation_id );
		} finally {
			$this->release_operation_lock( $operation_id, $lock );
		}
	}

	/**
	 * Process an operation after acquiring its worker lock.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	private function process_operation_locked( $operation_id ) {
		$deadline = microtime( true ) + self::BATCH_TIME_LIMIT;
		$state    = $this->store->get( $operation_id, true );
		if ( ! $state || in_array( $state['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled', 'ready', 'expired' ), true ) ) {
			return;
		}

		if ( 'rollback' === $state['action'] && get_option( 'dkbce_rollback_' . $state['rollback_source'], '' ) !== $operation_id ) {
			return;
		}

		if ( 'import_preview' === $state['action'] ) {
			$imports = new DKBCE_Import_Processor();
			$imports->process( $state, $this->store, $this->service );
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

		if ( ! empty( $state['operation']['source_id'] ) ) {
			$source = $this->store->get( $state['operation']['source_id'] );
			if ( ! $source || 'ready' !== $source['status'] || ( $source['applied_operation'] ?? '' ) !== $operation_id ) {
				$state['error_summary'] = __( 'The staged import is unavailable. Prepare a new preview.', 'bulk-cogs-editor-for-woocommerce' );
				$this->finish_operation( $state, 'failed' );
				return;
			}
		}

		$state['status']     = 'processing';
		$state['started_at'] = $state['started_at'] ? $state['started_at'] : time();
		$state['updated_at'] = time();
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
	 * Acquire an expiring lock so recovery actions cannot run concurrently.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return string|false Lock token, or false when another worker owns the lock.
	 */
	public function acquire_operation_lock( $operation_id ) {
		global $wpdb;
		$key   = 'dkbce_worker_lock_' . sanitize_key( $operation_id );
		$value = maybe_serialize(
			array(
				'token'      => wp_generate_uuid4(),
				'created_at' => time(),
			)
		);
		// Unique option names provide atomic ownership; lock records bypass option caches.
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, $key, $value, 'off' ) );
		if ( false === $inserted ) {
			return false;
		}
		if ( 0 === $inserted ) {
			// Read lock ownership fresh across requests.
			$stored   = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
			$existing = maybe_unserialize( $stored );
			if ( ! is_array( $existing ) || ! isset( $existing['created_at'] ) || time() - absint( $existing['created_at'] ) <= self::OPERATION_LOCK_TTL ) {
				return false;
			}
			// Replace only the exact expired owner observed.
			$replaced = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $value, $key, $stored ) );
			if ( 1 !== $replaced ) {
				return false;
			}
		}
		return $value;
	}

	/**
	 * Release only this request's serialized operation-lock record.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param string $lock_token Serialized ownership record.
	 * @return void
	 */
	public function release_operation_lock( $operation_id, $lock_token ) {
		global $wpdb;
		$key = 'dkbce_worker_lock_' . sanitize_key( $operation_id );
		// Conditional deletion cannot release a replacement owner's lock.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $key, $lock_token ) );
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

		if ( ! isset( $state['snapshot_after_id'] ) ) {
			// Resume older operations from their last durable chunk, not an offset.
			$last_chunk                 = $state['snapshot_chunks'] ? $this->store->get_chunk( $state['operation_id'], $state['snapshot_chunks'] ) : false;
			$state['snapshot_after_id'] = $last_chunk && $last_chunk['ids'] ? max( $last_chunk['ids'] ) : 0;
		}

		$filters              = $state['filters'] ?? array();
		$filters['page_no']   = 1;
		$filters['page_size'] = $state['batch_size'];
		$filters['after_id']  = $state['snapshot_after_id'];
		$filters['get_ids']   = true;
		$filters['count']     = false;

		if ( ! empty( $filters['search'] ) ) {
			$search_ids = $this->store->get_search_ids( $state['operation_id'] );
			if ( false === $search_ids ) {
				$search_ids = $this->service->search_product_ids( $filters['search'] );
				$this->store->save_search_ids( $state['operation_id'], $search_ids );
			}
			$filters['search_ids'] = $search_ids;
		}

		// Reuse a chunk saved just before an interrupted progress write.
		$next_chunk = $this->store->get_chunk( $state['operation_id'], $state['snapshot_chunks'] + 1 );
		if ( ! empty( $state['operation']['source_id'] ) ) {
			$imports     = new DKBCE_Import_Store();
			$product_ids = $next_chunk ? $next_chunk['ids'] : array_map( 'intval', wp_list_pluck( $imports->rows( $state['operation']['source_id'], $state['batch_size'], 0, $state['snapshot_after_id'] ), 'product_id' ) );
		} elseif ( 'rollback' === $state['action'] ) {
			$history     = new DKBCE_History_Store();
			$product_ids = $next_chunk ? $next_chunk['ids'] : $history->remaining_ids( $state['rollback_source'], $state['batch_size'], $state['snapshot_after_id'] );
		} else {
			$product_ids = $next_chunk ? $next_chunk['ids'] : $this->service->scan_matches( $filters );
		}

		if ( $product_ids ) {
			++$state['snapshot_chunks'];
			$state['total'] += count( $product_ids );
			if ( ! $next_chunk ) {
				$this->store->save_chunk( $state['operation_id'], $state['snapshot_chunks'], $product_ids );
			}
			$state['snapshot_after_id'] = max( $product_ids );
			return $this->save_progress( $state );
		}

		// Derive the total from durable chunks instead of fetching every ID twice.
		if ( isset( $state['expected_total'] ) && (int) $state['expected_total'] !== (int) $state['total'] ) {
			$state['error_summary'] = __( 'The product scope changed after confirmation. No products were updated. Prepare a fresh preview and confirm again.', 'bulk-cogs-editor-for-woocommerce' );
			$this->finish_operation( $state, 'failed' );
			return false;
		}
		$state['total_count'] = $state['total'];
		$state['stage']       = 'processing';

		dkbce_wc_log( 'Total snapshot IDs: ' . $state['total'] );

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
		// This internal WooCommerce API is not available in every supported version.
		$deferrer_class = \Automattic\WooCommerce\Internal\Caches\ProductTransientsDeferrer::class;
		if ( ! function_exists( 'wc_get_container' ) || ! class_exists( $deferrer_class ) ) {
			return $this->process_update_batch( $state, $deadline );
		}

		$deferrer = wc_get_container()->get( $deferrer_class );
		if ( ! is_callable( array( $deferrer, 'start_deferring' ) ) || ! is_callable( array( $deferrer, 'stop_deferring' ) ) ) {
			return $this->process_update_batch( $state, $deadline );
		}

		$deferrer->start_deferring();
		try {
			return $this->process_update_batch( $state, $deadline );
		} finally {
			// Flush this batch even on cancellation, a time limit, or an exception.
			$deferrer->stop_deferring();
		}
	}

	/**
	 * Apply one persisted batch, retaining per-product outcomes and checkpoints.
	 *
	 * @param array $state Operation state.
	 * @param float $deadline Time after which another product must not be started.
	 * @return bool Whether another chunk may run in this request.
	 */
	private function process_update_batch( &$state, $deadline ) {
		$chunk_number = $state['processed_chunks'] + 1;
		$chunk        = $this->store->get_chunk( $state['operation_id'], $chunk_number );

		if ( ! $chunk ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed', $summary );
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

		$pending_ids   = array_diff( $chunk['ids'], array_keys( $chunk['results'] ) );
		$import_values = array();
		if ( $pending_ids && microtime( true ) < $deadline ) {
			// Batch post, term and metadata reads; WooCommerce still owns all writes.
			_prime_post_caches( $pending_ids, true, true );
			if ( ! empty( $state['operation']['source_id'] ) ) {
				$imports       = new DKBCE_Import_Store();
				$import_values = $imports->values( $state['operation']['source_id'], $pending_ids );
			}
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
		$checkpoint_at    = microtime( true );
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

			if ( ! $this->check_cancellation( $state ) ) {
				return false;
			}

			$outcome = $this->process_product( $product_id, $state, $import_values );
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
			$this->store->save_outcome( $state['operation_id'], $product_id, $outcome );
			$chunk['results'][ $key ] = $outcome;
			$this->record_outcome( $state, $product_id, $outcome );
			++$since_checkpoint;
			$checkpoint_due = $since_checkpoint >= self::PROGRESS_INTERVAL || microtime( true ) - $checkpoint_at >= self::PROGRESS_TIME_LIMIT;
			// A completed chunk has its own checkpoint immediately below.
			if ( $checkpoint_due && count( $chunk['results'] ) < count( $chunk['ids'] ) ) {
				if ( ! $this->save_progress( $state ) ) {
					return false;
				}
				$since_checkpoint = 0;
				$checkpoint_at    = microtime( true );
			}
		}

		++$state['processed_chunks'];
		$state['counted_chunk_results'] = 0;
		if ( ! $this->save_progress( $state ) ) {
			return false;
		}
		if ( $state['processed_chunks'] >= $state['snapshot_chunks'] ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
			$this->finish_operation( $state, $summary['failed'] ? 'completed_with_errors' : 'completed', $summary );
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
		if ( ! $this->check_cancellation( $state, true ) ) {
			return false;
		}
		$state['updated_at'] = time();
		$this->store->save( $state );
		return true;
	}

	/**
	 * Poll cancellation independently of progress saves, without reading per product.
	 *
	 * @param array $state Operation state, modified by reference.
	 * @param bool  $force Whether to bypass the polling interval at a checkpoint.
	 * @return bool Whether processing may continue.
	 */
	private function check_cancellation( &$state, $force = false ) {
		$now = microtime( true );
		if ( empty( $state['cancel_requested'] ) && ! $force && $now - $this->last_cancellation_check < self::CANCEL_INTERVAL ) {
			return true;
		}
		$this->last_cancellation_check = $now;
		if ( ! empty( $state['cancel_requested'] ) || $this->store->is_cancel_requested( $state['operation_id'] ) ) {
			$state['cancel_requested'] = true;
			$this->finish_operation( $state, 'cancelled' );
			return false;
		}
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
	 * @param array  $summary Precomputed durable outcome summary, when available.
	 * @return void
	 */
	private function finish_operation( &$state, $status, $summary = null ) {
		if ( ! is_array( $summary ) ) {
			$summary = $this->store->summarize( $state['operation_id'], $state['snapshot_chunks'] );
		}
		$state['processed']    = $summary['processed'];
		$state['succeeded']    = $summary['succeeded'];
		$state['skipped']      = $summary['skipped'];
		$state['failed']       = $summary['failed'];
		$state['errors']       = $summary['errors'];
		$state['status']       = $status;
		$state['completed_at'] = time();

		if ( 'cancelled' === $status ) {
			$state['cancelled_note'] = 'rollback' === $state['action']
				? __( 'Already restored products keep their restored costs. Remaining products were not rolled back.', 'bulk-cogs-editor-for-woocommerce' )
				: __( 'Already processed products remain changed; no rollback was performed.', 'bulk-cogs-editor-for-woocommerce' );
		}

		if ( 'csv_import' === $state['operation']['action'] && empty( $state['operation']['source_id'] ) ) {
			$csv                 = new DKBCE_CSV_Service();
			$state['csv_report'] = $csv->line( array( 'id', 'new_cogs', 'status', 'error' ) );
			for ( $number = 1; $number <= $state['snapshot_chunks']; ++$number ) {
				$chunk = $this->store->get_chunk( $state['operation_id'], $number );
				foreach ( $chunk['ids'] ?? array() as $id ) {
					$result = $chunk['results'][ $id ] ?? array(
						'status'  => 'not_processed',
						'message' => __( 'Not processed before the operation stopped.', 'bulk-cogs-editor-for-woocommerce' ),
					);
					if ( 'success' !== $result['status'] ) {
						$state['csv_report'] .= $csv->line( array( $id, $state['operation']['values'][ $id ], $result['status'], $result['message'] ) );
					}
				}
			}
		}
		$state['cleanup_pending'] = true;
		$state['cleanup_cursor']  = 1;
		$this->store->save( $state );
		$this->enqueue_cleanup( $state['operation_id'] );
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
	 * Safely apply the operation to a single product.
	 *
	 * @param int   $product_id    Product ID.
	 * @param array $state          Operation state.
	 * @param array $import_values  Trusted staged values keyed by product ID.
	 * @return array
	 */
	private function process_product( $product_id, $state, $import_values = array() ) {
		$lock = $this->store->acquire_product_lock( $product_id, $state['operation_id'] );
		if ( false === $lock ) {
			dkbce_wc_log( 'Product lock already acquired for product ID: ' . $product_id );
			return array(
				'status'  => 'deferred',
				'message' => '',
			);
		}

		$product_saved = false;
		$journal       = null;
		try {
			if ( 'rollback' === $state['action'] || isset( $state['reviewed_rows'][ $product_id ] ) ) {
				// Re-read reviewed metadata after acquiring the product lock.
				wp_cache_delete( $product_id, 'post_meta' );
			}
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				dkbce_wc_log( ' Product does not exist for product ID: ' . $product_id );
				return array(
					'status'  => 'skipped',
					'message' => __( 'Product no longer exists.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}

			if ( $state['operation_id'] === $product->get_meta( '_dkbce_last_bulk_cogs_operation', true ) ) {
				$product_saved = true;
				$history       = new DKBCE_History_Store();
				$history->confirm( $state['operation_id'], $product_id );
				if ( 'rollback' === $state['action'] ) {
					$history->mark_restored( $state['rollback_source'], $product_id );
				}
				dkbce_wc_log( 'Product already processed for product ID: ' . $product_id );
				return array(
					'status'  => 'success',
					'message' => '',
				);
			}

			if ( 'csv_export' === $state['operation']['action'] || ( 'csv_import' === $state['operation']['action'] && ! user_can( $state['user_id'], 'edit_post', $product_id ) ) ) {
				return array(
					'status'  => 'failed',
					'message' => __( 'This operation cannot update this product.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}
			$operation = $state['operation'];
			$history   = new DKBCE_History_Store();
			$recorded  = $history->product( $state['operation_id'], $product_id );
			if ( $recorded && 'success' === $recorded['status'] ) {
				if ( 'rollback' === $state['action'] ) {
					$history->mark_restored( $state['rollback_source'], $product_id );
				}
				// A later operation may have replaced the product's last-operation marker.
				return array(
					'status'  => 'success',
					'message' => '',
				);
			}
			if ( ! empty( $operation['source_id'] ) ) {
				$operation['values'] = isset( $import_values[ $product_id ] ) ? array( $product_id => $import_values[ $product_id ] ) : array();
				unset( $operation['source_id'] );
			}
			if ( 'rollback' === $state['action'] ) {
				$original = $history->product( $state['rollback_source'], $product_id );
				if ( ! $original || 'success' !== $original['status'] || ! user_can( $state['user_id'], 'edit_post', $product_id ) ) {
					return array(
						'status'  => 'failed',
						'message' => __( 'The original cost is unavailable or this product cannot be edited.', 'bulk-cogs-editor-for-woocommerce' ),
					);
				}
				$current  = $product->get_cogs_value();
				$expected = null === $original['target_value'] ? null : (float) $original['target_value'];
				if ( 'skip_changed' === ( $state['rollback_policy'] ?? 'overwrite' ) && $current !== $expected ) {
					return array(
						'status'  => 'skipped',
						'message' => __( 'COGS changed after the original operation. The product was not rolled back.', 'bulk-cogs-editor-for-woocommerce' ),
					);
				}
				$result = array(
					'current' => $product->get_cogs_value(),
					'new'     => null === $original['original_value'] ? null : (float) $original['original_value'],
					'status'  => 'success',
				);
			} elseif ( $recorded ) {
				$result = array(
					'current' => null === $recorded['original_value'] ? null : (float) $recorded['original_value'],
					'new'     => null === $recorded['target_value'] ? null : (float) $recorded['target_value'],
					'status'  => 'success',
				);
			} else {
				$result = $this->service->calculate( $product, $operation );
			}
			if ( is_wp_error( $result ) ) {
				dkbce_wc_log( 'Product could not be calculated for product ID: ' . $product_id );
				return array(
					'status'  => 'failed',
					'message' => __( 'COGS could not be calculated.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}
			$review_changed = isset( $state['reviewed_rows'][ $product_id ] ) && ( $recorded
				? $product->get_cogs_value() !== ( null === $recorded['original_value'] ? null : (float) $recorded['original_value'] )
				: ! hash_equals( $state['reviewed_rows'][ $product_id ], $this->service->preview_signature( $result ) ) );
			if ( isset( $state['reviewed_rows'][ $product_id ] ) && $review_changed ) {
				return array(
					'status'  => 'failed',
					'message' => __( 'COGS changed after the reviewed preview. This product was not updated. Prepare a fresh preview and confirm again.', 'bulk-cogs-editor-for-woocommerce' ),
				);
			}
			if ( 'skipped' === $result['status'] ) {
				dkbce_wc_log( 'Product Skipped  for product ID: ' . $product_id );
				return array(
					'status'  => 'skipped',
					'message' => $result['reason'],
				);
			}

			// Record WooCommerce's normalized target, including zero becoming unset.
			$product->set_cogs_value( null === $result['new'] ? null : (float) $result['new'] );
			$result['new'] = $product->get_cogs_value();
			$journal       = $history->prepare( $state['operation_id'], $product_id, $result, $recorded );
			$product->set_cogs_value( null === $journal['target_value'] ? null : (float) $journal['target_value'] );
			$product->update_meta_data( '_dkbce_last_bulk_cogs_operation', $state['operation_id'] );
			if ( empty( $product->get_changes() ) ) {
				// Let WooCommerce normalize COGS, then persist retry protection without a full save.
				$product->save_meta_data();
			} else {
				$product->save();
			}
			$product_saved = true;
			$history->confirm( $state['operation_id'], $product_id );
			if ( 'rollback' === $state['action'] ) {
				$history->mark_restored( $state['rollback_source'], $product_id );
			}
			return array(
				'status'  => 'success',
				'message' => '',
			);
		} catch ( Throwable $exception ) {
			dkbce_wc_log( 'Operation id: ' . $state['operation_id'] . ', Product ID: ' . $product_id . ' Error: ' . $exception->getMessage() );
			if ( $journal || $product_saved ) {
				try {
					// A WooCommerce after-save hook can throw after the write committed.
					wp_cache_delete( $product_id, 'post_meta' );
					$saved   = wc_get_product( $product_id );
					$history = new DKBCE_History_Store();
					$record  = $history->product( $state['operation_id'], $product_id );
					$target  = $record && null !== $record['target_value'] ? (float) $record['target_value'] : null;
					if ( $record && $saved && $state['operation_id'] === $saved->get_meta( '_dkbce_last_bulk_cogs_operation', true ) && $saved->get_cogs_value() === $target ) {
						$history->confirm( $state['operation_id'], $product_id );
						if ( 'rollback' === $state['action'] ) {
							$history->mark_restored( $state['rollback_source'], $product_id );
						}
						return array(
							'status'  => 'success',
							'message' => '',
						);
					}
				} catch ( Throwable $recovery_exception ) {
					// Unreadable storage cannot establish whether the write committed.
					return array(
						'status'  => 'deferred',
						'message' => '',
					);
				}
			}
			if ( $product_saved ) {
				// Retry journal confirmation instead of losing a successfully saved cost.
				return array(
					'status'  => 'deferred',
					'message' => '',
				);
			}

			return array(
				'status'  => 'failed',
				'message' => __( 'Product could not be updated. Check the WooCommerce log for details.', 'bulk-cogs-editor-for-woocommerce' ),
			);
		} finally {
			$this->store->release_product_lock( $product_id, $lock );
		}
	}
}
