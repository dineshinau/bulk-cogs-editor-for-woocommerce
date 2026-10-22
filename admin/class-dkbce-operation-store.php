<?php
/**
 * Persistent operation state for bulk COGS jobs.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores operation state and bounded product ID batches in non-autoloaded options.
 */
class DKBCE_Operation_Store {
	/**
	 * Get the operation outcomes table name.
	 *
	 * @return string
	 */
	private function outcomes_table() {
		global $wpdb;
		return $wpdb->prefix . 'dkbce_operation_outcomes';
	}

	/**
	 * Install the indexed, per-product outcome storage when first needed.
	 *
	 * @return void
	 * @throws RuntimeException When the outcomes table cannot be created.
	 */
	public function ensure_outcomes_table() {
		DKBCE_Database_Installer::maybe_upgrade();
	}

	/**
	 * Get the operation option name.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return string
	 */
	private function operation_key( $operation_id ) {
		return 'dkbce_operation_' . sanitize_key( $operation_id );
	}

	/**
	 * Get an operation state.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param bool   $fresh Bypass request-local option caches after acquiring a lock.
	 * @return array|false
	 */
	public function get( $operation_id, $fresh = false ) {
		$key = $this->operation_key( $operation_id );
		if ( $fresh ) {
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
		$state = get_option( $key, false );
		if ( ! is_array( $state ) ) {
			return false;
		}

		$state['cancel_requested'] = ! empty( $state['cancel_requested'] ) || $this->is_cancel_requested( $operation_id );
		return $state;
	}

	/**
	 * Request cancellation independently of worker progress writes.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 */
	public function request_cancellation( $operation_id ) {
		update_option( $this->cancellation_key( $operation_id ), true, false );
	}

	/**
	 * Read a cancellation request made by another PHP request.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return bool
	 */
	public function is_cancel_requested( $operation_id ) {
		$key = $this->cancellation_key( $operation_id );
		wp_cache_delete( $key, 'options' );

		// A long-running worker can also have cached the absence of this option.
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $key ] ) ) {
			unset( $notoptions[ $key ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		return (bool) get_option( $key, false );
	}

	/**
	 * Get the separate cancellation option name.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return string
	 */
	private function cancellation_key( $operation_id ) {
		return 'dkbce_cancel_' . sanitize_key( $operation_id );
	}

	/**
	 * Save an operation state without autoloading it.
	 *
	 * @param array $state Operation state.
	 * @return void
	 * @throws RuntimeException When the operation cannot be persisted.
	 */
	public function save( $state ) {
		$key = $this->operation_key( $state['operation_id'] );
		if ( false === get_option( $key, false ) ) {
			add_option( $key, $state, '', false );
		} else {
			update_option( $key, $state, false );
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( get_option( $key, false ) !== $state ) {
			throw new RuntimeException( esc_html__( 'The operation could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$history = new DKBCE_History_Store();
		$history->index( $state );
	}

	/**
	 * Get cached search matches separately from frequently written progress.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return array|false False means the search has not been resolved yet.
	 */
	public function get_search_ids( $operation_id ) {
		return get_option( 'dkbce_search_' . sanitize_key( $operation_id ), false );
	}

	/**
	 * Persist search matches, including an empty result, without autoloading.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param array  $ids Search matches.
	 * @return void
	 */
	public function save_search_ids( $operation_id, $ids ) {
		add_option( 'dkbce_search_' . sanitize_key( $operation_id ), $ids, '', false );
	}

	/**
	 * Save one snapshot batch of product IDs and per-product outcomes.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_number One-based batch number.
	 * @param array  $ids Product IDs.
	 * @return void
	 */
	public function save_chunk( $operation_id, $chunk_number, $ids ) {
		$this->ensure_outcomes_table();
		$key = $this->chunk_key( $operation_id, $chunk_number );
		add_option(
			$key,
			array(
				'ids'     => array_map( 'absint', $ids ),
				'results' => array(),
			),
			'',
			false
		);
	}

	/**
	 * Get one snapshot chunk.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_number One-based batch number.
	 * @return array|false
	 * @throws RuntimeException When durable outcomes cannot be read.
	 */
	public function get_chunk( $operation_id, $chunk_number ) {
		global $wpdb;

		$chunk = get_option( $this->chunk_key( $operation_id, $chunk_number ), false );
		if ( ! is_array( $chunk ) ) {
			return false;
		}

		$this->ensure_outcomes_table();
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $chunk['ids'] ) ) ) );
		if ( ! $product_ids ) {
			return $chunk;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The arguments include one replacement for each bounded product ID.
		$query = $wpdb->prepare(
			"SELECT product_id, status, message FROM %i WHERE operation_id = %s AND product_id IN ($placeholders) ORDER BY product_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder list is generated from bounded absolute product IDs.
			array_merge( array( $this->outcomes_table(), sanitize_key( $operation_id ) ), $product_ids )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The dynamic placeholder list is prepared above.
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			throw new RuntimeException( esc_html__( 'Bulk operation outcomes could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		foreach ( $rows as $row ) {
			$chunk['results'][ (int) $row['product_id'] ] = array(
				'status'  => $row['status'],
				'message' => $row['message'],
			);
		}
		ksort( $chunk['results'], SORT_NUMERIC );

		return $chunk;
	}

	/**
	 * Persist one immutable product outcome, idempotently.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $product_id Product ID.
	 * @param array  $outcome Product outcome.
	 * @return void
	 * @throws RuntimeException When the outcome cannot be persisted consistently.
	 */
	public function save_outcome( $operation_id, $product_id, $outcome ) {
		global $wpdb;

		$this->ensure_outcomes_table();
		$operation_id = sanitize_key( $operation_id );
		$product_id   = absint( $product_id );
		$table        = $this->outcomes_table();
		// The primary key makes each durable product outcome idempotent.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (operation_id, product_id, status, message) VALUES (%s, %d, %s, %s)',
				$table,
				$operation_id,
				$product_id,
				$outcome['status'],
				$outcome['message']
			)
		);
		if ( 1 === $inserted ) {
			return;
		}
		if ( 0 !== $inserted ) {
			throw new RuntimeException( esc_html__( 'The bulk product outcome could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		// Verify an identical retry against the unique outcome key.
		$stored = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT status, message FROM %i WHERE operation_id = %s AND product_id = %d',
				$table,
				$operation_id,
				$product_id
			),
			ARRAY_A
		);
		if ( is_array( $stored ) && $stored['status'] === $outcome['status'] && $stored['message'] === $outcome['message'] ) {
			return;
		}

		throw new RuntimeException( esc_html__( 'A conflicting bulk product outcome already exists.', 'bulk-cogs-editor-for-woocommerce' ) );
	}

	/**
	 * Acquire a product lock without the general option API's read/cache overhead.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $operation_id Operation UUID.
	 * @return string|false Serialized ownership record, or false when unavailable.
	 */
	public function acquire_product_lock( $product_id, $operation_id ) {
		global $wpdb;

		$key   = 'dkbce_product_lock_' . absint( $product_id );
		$value = maybe_serialize(
			array(
				'operation_id' => $operation_id,
				'created_at'   => time(),
				'token'        => wp_generate_uuid4(),
			)
		);
		// Product locks are private records accessed exclusively through direct SQL.
		// Unique option_name provides atomic exclusion; lock records do not use the option cache.
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, $key, $value, 'off' ) );
		if ( false === $inserted ) {
			return false;
		}
		if ( 0 === $inserted ) {
			// Lock ownership must be read fresh across workers.
			$stored   = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
			$existing = maybe_unserialize( $stored );
			if ( ! is_array( $existing ) || ! isset( $existing['created_at'] ) || time() - absint( $existing['created_at'] ) <= 900 ) {
				return false;
			}
			// Reclaim only the exact expired record observed, never a replacement owner's lock.
			$replaced = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $value, $key, $stored ) );
			if ( 1 !== $replaced ) {
				return false;
			}
		}
		return $value;
	}

	/**
	 * Release only the product lock owned by this worker.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $lock Serialized ownership record returned on acquisition.
	 * @return void
	 */
	public function release_product_lock( $product_id, $lock ) {
		global $wpdb;

		$key = 'dkbce_product_lock_' . absint( $product_id );
		// Conditional deletion prevents expired workers releasing replacement locks; lock records do not use the option cache.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $key, $lock ) );
	}

	/**
	 * Rebuild reliable counters from recorded per-product outcomes.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_count Number of persisted chunks for legacy recovery.
	 * @return array
	 * @throws RuntimeException When durable outcomes cannot be summarized.
	 */
	public function summarize( $operation_id, $chunk_count ) {
		global $wpdb;

		$summary = array(
			'processed' => 0,
			'succeeded' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		$this->ensure_outcomes_table();
		// The operation ID index supports a single aggregate across all durable outcomes.
		// Reconcile counters from the indexed durable outcome store.
		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS processed, SUM(status = 'success') AS succeeded, SUM(status = 'skipped') AS skipped FROM %i WHERE operation_id = %s",
				$this->outcomes_table(),
				sanitize_key( $operation_id )
			),
			ARRAY_A
		);
		if ( ! is_array( $counts ) ) {
			throw new RuntimeException( esc_html__( 'Bulk operation outcomes could not be summarized.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$summary['processed'] = absint( $counts['processed'] );
		$summary['succeeded'] = absint( $counts['succeeded'] );
		$summary['skipped']   = absint( $counts['skipped'] );
		$summary['failed']    = max( 0, $summary['processed'] - $summary['succeeded'] - $summary['skipped'] );
		if ( 0 === $summary['processed'] && $chunk_count > 0 ) {
			// Operations created before indexed outcomes stored results in their chunk options.
			for ( $chunk_number = 1; $chunk_number <= $chunk_count; $chunk_number++ ) {
				$chunk = get_option( $this->chunk_key( $operation_id, $chunk_number ), false );
				if ( ! is_array( $chunk ) || empty( $chunk['results'] ) ) {
					continue;
				}
				foreach ( $chunk['results'] as $product_id => $result ) {
					++$summary['processed'];
					if ( 'success' === $result['status'] ) {
						++$summary['succeeded'];
					} elseif ( 'skipped' === $result['status'] ) {
						++$summary['skipped'];
					} else {
						++$summary['failed'];
						if ( count( $summary['errors'] ) < 50 ) {
							$summary['errors'][] = array(
								'product_id' => absint( $product_id ),
								'message'    => $result['message'],
							);
						}
					}
				}
			}
			return $summary;
		}

		if ( $summary['failed'] ) {
			// The bounded query retrieves only the error preview.
			$errors = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT product_id, message FROM %i WHERE operation_id = %s AND status NOT IN ('success', 'skipped') ORDER BY product_id LIMIT 50",
					$this->outcomes_table(),
					sanitize_key( $operation_id )
				),
				ARRAY_A
			);
			if ( ! is_array( $errors ) ) {
				throw new RuntimeException( esc_html__( 'Bulk operation errors could not be summarized.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			foreach ( $errors as $error ) {
				$summary['errors'][] = array(
					'product_id' => absint( $error['product_id'] ),
					'message'    => $error['message'],
				);
			}
		}

		return $summary;
	}

	/**
	 * Remove ID chunks, outcomes, and cancellation requests after completion.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_count Number of persisted chunks.
	 * @param bool   $preserve_outcomes Keep outcomes needed by an import report.
	 * @param int    $cursor First chunk to remove in this batch.
	 * @param int    $limit Maximum number of chunks to remove.
	 * @return bool Whether all cleanup work is complete.
	 * @throws RuntimeException When durable outcomes cannot be removed.
	 */
	public function delete_chunks( $operation_id, $chunk_count, $preserve_outcomes = false, $cursor = 1, $limit = 100 ) {
		global $wpdb;

		$end = min( absint( $chunk_count ), absint( $cursor ) + absint( $limit ) - 1 );
		for ( $chunk_number = absint( $cursor ); $chunk_number <= $end; $chunk_number++ ) {
			delete_option( $this->chunk_key( $operation_id, $chunk_number ) );
		}
		if ( $end < absint( $chunk_count ) ) {
			return false;
		}

		if ( ! $preserve_outcomes ) {
			$this->ensure_outcomes_table();
			// Limit each cleanup request to a bounded number of rows.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE operation_id = %s LIMIT 500',
					$this->outcomes_table(),
					sanitize_key( $operation_id )
				)
			);
			if ( false === $deleted ) {
				throw new RuntimeException( esc_html__( 'Bulk operation outcomes could not be removed.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			if ( 500 === $deleted ) {
				return false;
			}
		}

		delete_option( $this->cancellation_key( $operation_id ) );
		delete_option( 'dkbce_search_' . sanitize_key( $operation_id ) );
		return true;
	}

	/**
	 * Delete every outcome owned by one completed operation.
	 *
	 * @param string $operation_id Operation UUID.
	 * @return void
	 * @throws RuntimeException When operation outcomes cannot be removed.
	 */
	public function delete_outcomes( $operation_id ) {
		global $wpdb;
		$this->ensure_outcomes_table();

		// Remove only outcomes owned by this completed operation.
		$deleted = $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE operation_id = %s', $this->outcomes_table(), sanitize_key( $operation_id ) )
		);
		if ( false === $deleted ) {
			throw new RuntimeException( esc_html__( 'Bulk operation outcomes could not be removed.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
	}

	/**
	 * Create a predictable option name for a chunk.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_number One-based batch number.
	 * @return string
	 */
	private function chunk_key( $operation_id, $chunk_number ) {
		return sprintf( 'dkbce_ids_%s_%06d', sanitize_key( $operation_id ), absint( $chunk_number ) );
	}
}
