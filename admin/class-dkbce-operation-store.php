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
	 * @return array|false
	 */
	public function get( $operation_id ) {
		$state = get_option( $this->operation_key( $operation_id ), false );
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
	 */
	public function save( $state ) {
		$key = $this->operation_key( $state['operation_id'] );
		if ( false === get_option( $key, false ) ) {
			add_option( $key, $state, '', false );
		} else {
			update_option( $key, $state, false );
		}
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
	 */
	public function get_chunk( $operation_id, $chunk_number ) {
		$chunk = get_option( $this->chunk_key( $operation_id, $chunk_number ), false );
		return is_array( $chunk ) ? $chunk : false;
	}

	/**
	 * Save per-product outcomes for a snapshot chunk.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_number One-based batch number.
	 * @param array  $chunk Batch data.
	 * @return void
	 * @throws RuntimeException When the product outcome cannot be persisted.
	 */
	public function update_chunk( $operation_id, $chunk_number, $chunk ) {
		global $wpdb;

		$key   = $this->chunk_key( $operation_id, $chunk_number );
		$value = maybe_serialize( $chunk );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Internal non-autoloaded state; invalidate its option cache below.
		$updated = $wpdb->update( $wpdb->options, array( 'option_value' => $value ), array( 'option_name' => $key ), array( '%s' ), array( '%s' ) );
		wp_cache_delete( $key, 'options' );
		if ( 0 === $updated ) {
			// An identical retry is valid, but a missing snapshot must not lose an outcome.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Confirm durable state after a zero-row update.
			$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
			if ( $value === $stored ) {
				return;
			}
		}
		if ( ! $updated ) {
			throw new RuntimeException( 'Could not persist the bulk COGS product outcome.' );
		}
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Unique option_name provides atomic exclusion; cache invalidated on acquisition.
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, $key, $value, 'off' ) );
		if ( false === $inserted ) {
			return false;
		}
		if ( 0 === $inserted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock ownership must be read fresh across workers.
			$stored   = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
			$existing = maybe_unserialize( $stored );
			if ( ! is_array( $existing ) || ! isset( $existing['created_at'] ) || time() - absint( $existing['created_at'] ) <= 900 ) {
				return false;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reclaim only the exact expired record observed, never a replacement owner's lock.
			$replaced = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $value, $key, $stored ) );
			if ( 1 !== $replaced ) {
				return false;
			}
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Conditional deletion prevents expired workers releasing replacement locks.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $key, $lock ) );
		wp_cache_delete( $key, 'options' );
	}

	/**
	 * Rebuild reliable counters from recorded per-product outcomes.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_count Number of persisted chunks.
	 * @return array
	 */
	public function summarize( $operation_id, $chunk_count ) {
		$summary = array(
			'processed' => 0,
			'succeeded' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		for ( $chunk_number = 1; $chunk_number <= $chunk_count; $chunk_number++ ) {
			$chunk = $this->get_chunk( $operation_id, $chunk_number );
			if ( ! $chunk || empty( $chunk['results'] ) ) {
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

	/**
	 * Remove ID chunks, outcomes, and cancellation requests after completion.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_count Number of persisted chunks.
	 * @return void
	 */
	public function delete_chunks( $operation_id, $chunk_count ) {
		for ( $chunk_number = 1; $chunk_number <= $chunk_count; $chunk_number++ ) {
			delete_option( $this->chunk_key( $operation_id, $chunk_number ) );
		}
		delete_option( $this->cancellation_key( $operation_id ) );
		delete_option( 'dkbce_search_' . sanitize_key( $operation_id ) );
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
