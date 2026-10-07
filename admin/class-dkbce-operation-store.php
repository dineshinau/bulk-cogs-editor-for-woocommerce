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
		return is_array( $state ) ? $state : false;
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
	 */
	public function update_chunk( $operation_id, $chunk_number, $chunk ) {
		update_option( $this->chunk_key( $operation_id, $chunk_number ), $chunk, false );
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
	 * Remove ID and outcome chunks after the operation is terminal.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $chunk_count Number of persisted chunks.
	 * @return void
	 */
	public function delete_chunks( $operation_id, $chunk_count ) {
		for ( $chunk_number = 1; $chunk_number <= $chunk_count; $chunk_number++ ) {
			delete_option( $this->chunk_key( $operation_id, $chunk_number ) );
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
