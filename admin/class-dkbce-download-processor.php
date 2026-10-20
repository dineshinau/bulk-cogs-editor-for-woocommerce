<?php
/**
 * Asynchronous private CSV downloads.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build export and import report files in bounded background batches.
 */
class DKBCE_Download_Processor {
	const HOOK         = 'dkbce_process_download';
	const CLEANUP_HOOK = 'dkbce_cleanup_download';
	const GROUP        = 'dkbce-bulk-cogs';
	const BATCH_SIZE   = 100;
	const RETENTION    = 2 * DAY_IN_SECONDS;

	/**
	 * Create a private CSV file and enqueue its bounded worker.
	 *
	 * @param array $state Download state.
	 * @return array
	 * @throws RuntimeException When the file or queue cannot be created.
	 */
	public function start( $state ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			throw new RuntimeException( esc_html__( 'Action Scheduler is required to prepare downloads.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$id   = wp_generate_uuid4();
		$path = wp_tempnam( 'dkbce-download-' . $id, get_temp_dir() );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Restrict this retained private download to its owner.
		if ( ! $path || ! chmod( $path, 0600 ) ) {
			if ( $path ) {
				wp_delete_file( $path );
			}
			throw new RuntimeException( esc_html__( 'A private download file could not be created.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$csv    = new DKBCE_CSV_Service();
		$header = 'export' === $state['mode'] ? array_merge( $state['columns'], array( 'new_cogs' ) ) : array( 'row', 'id', 'sku', 'new_cogs', 'error' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Initialize a private temporary download.
		$header_bytes = "\xEF\xBB\xBF" . $csv->line( $header );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Initialize a private temporary download.
		if ( false === file_put_contents( $path, $header_bytes ) ) {
			wp_delete_file( $path );
			throw new RuntimeException( esc_html__( 'The private download file could not be initialized.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$state['operation_id']     = $id;
		$state['action']           = 'server_download';
		$state['file']             = $path;
		$state['cursor']           = 0;
		$state['status']           = 'processing';
		$state['created_at']       = time();
		$state['updated_at']       = time();
		$state['rows']             = 0;
		$state['file_offset']      = strlen( $header_bytes );
		$state['cancel_requested'] = false;
		$store                     = new DKBCE_Operation_Store();
		$store->save( $state );
		if ( ! as_schedule_single_action( time() + self::RETENTION, self::CLEANUP_HOOK, array( $id ), self::GROUP ) || ! as_enqueue_async_action( self::HOOK, array( $id ), self::GROUP ) ) {
			wp_delete_file( $path );
			$state['status'] = 'failed';
			$store->save( $state );
			throw new RuntimeException( esc_html__( 'The download could not be queued.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $state;
	}

	/**
	 * Process bounded pages until the cooperative request deadline.
	 *
	 * @param string $id Download operation ID.
	 * @return void
	 * @throws RuntimeException When a staged batch or file write fails.
	 */
	public function process( $id ) {
		$store = new DKBCE_Operation_Store();
		$state = $store->get( $id );
		if ( ! $state || 'server_download' !== $state['action'] || 'processing' !== $state['status'] ) {
			return;
		}
		$deadline = microtime( true ) + 10;
		$csv      = new DKBCE_CSV_Service();
		$handle   = fopen( $state['file'], 'c+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private temporary file.
		if ( ! $handle ) {
			$state['status'] = 'failed';
			$store->save( $state );
			return;
		}
		// Discard an interrupted uncheckpointed batch before resuming its cursor.
		if ( ! ftruncate( $handle, $state['file_offset'] ) || -1 === fseek( $handle, $state['file_offset'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_ftruncate -- Restore the private file to its last durable checkpoint.
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private temporary file.
			$state['status']        = 'failed';
			$state['error_summary'] = __( 'The download file could not be resumed.', 'bulk-cogs-editor-for-woocommerce' );
			$store->save( $state );
			wp_delete_file( $state['file'] );
			return;
		}
		try {
			while ( microtime( true ) < $deadline ) {
				if ( $store->is_cancel_requested( $id ) ) {
					$state['status'] = 'cancelled';
					break;
				}
				$rows = $this->batch( $state );
				if ( ! $rows && empty( $state['batch_more'] ) ) {
					$state['status'] = 'completed';
					break;
				}
				foreach ( $rows as $row ) {
					if ( ! $this->write_all( $handle, $csv->line( $row ) ) ) {
						throw new RuntimeException( esc_html__( 'The download file could not be written.', 'bulk-cogs-editor-for-woocommerce' ) );
					}
					++$state['rows'];
				}
				$state['cursor']      = (int) $state['next_cursor'];
				$state['file_offset'] = ftell( $handle );
				unset( $state['next_cursor'] );
				if ( empty( $state['batch_more'] ) ) {
					$state['status'] = 'completed';
					break;
				}
			}
		} catch ( Throwable $exception ) {
			$state['status']        = 'failed';
			$state['error_summary'] = $exception->getMessage();
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private temporary file.
		}
		$state['updated_at'] = time();
		$store->save( $state );
		if ( in_array( $state['status'], array( 'cancelled', 'failed' ), true ) ) {
			wp_delete_file( $state['file'] );
		} elseif ( 'processing' === $state['status'] && ! as_enqueue_async_action( self::HOOK, array( $id ), self::GROUP ) ) {
			$state['status']        = 'failed';
			$state['error_summary'] = __( 'The download queue stopped.', 'bulk-cogs-editor-for-woocommerce' );
			$store->save( $state );
			wp_delete_file( $state['file'] );
		}
	}

	/**
	 * Write a complete CSV row to the private file.
	 *
	 * @param resource $handle File handle.
	 * @param string   $contents CSV row.
	 * @return bool
	 */
	private function write_all( $handle, $contents ) {
		$length = strlen( $contents );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, substr( $contents, $offset ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Write to a private temporary file.
			if ( false === $written || 0 === $written ) {
				return false;
			}
			$offset += $written;
		}
		return true;
	}

	/**
	 * Fetch one bounded CSV or report batch.
	 *
	 * @param array $state Download state, updated with next cursor.
	 * @return array
	 */
	private function batch( &$state ) {
		if ( 'export' === $state['mode'] ) {
			$filters              = $state['filters'];
			$filters['get_ids']   = true;
			$filters['after_id']  = $state['cursor'];
			$filters['page_size'] = self::BATCH_SIZE;
			$ids                  = ( new DKBCE_COGS_Service() )->scan_matches( $filters );
			_prime_post_caches( $ids, true, true );
			$rows = array();
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product || ! user_can( $state['user_id'], 'edit_post', $id ) ) {
					continue;
				}
				$values = array(
					'id'            => $id,
					'sku'           => $product->get_sku(),
					'name'          => $product->get_name(),
					'cogs'          => $product->get_cogs_value(),
					'regular_price' => $product->get_regular_price(),
					'sale_price'    => $product->get_sale_price(),
					'type'          => $product->get_type(),
				);
				$row    = array();
				foreach ( $state['columns'] as $column ) {
					$row[] = $values[ $column ];
				}
				$row[]  = '';
				$rows[] = $row;
			}
			$state['next_cursor'] = $ids ? max( $ids ) : $state['cursor'];
			$state['batch_more']  = self::BATCH_SIZE === count( $ids );
			return $rows;
		}
		$imports = new DKBCE_Import_Store();
		$rows    = $state['results'] ? $imports->results( $state['source_id'], $state['cursor'], $state['source_operation_id'] ?? '' ) : $imports->rows( $state['source_id'], self::BATCH_SIZE, 0, $state['cursor'], true );
		$output  = array();
		foreach ( $rows as $row ) {
			$message  = $state['results'] ? ( $row['result_message'] ? $row['result_message'] : __( 'Not processed before the operation stopped.', 'bulk-cogs-editor-for-woocommerce' ) ) : $row['error'];
			$output[] = array( $row['row_no'], $row['product_id'], $row['sku'], $row['value'], $message );
		}
		$state['next_cursor'] = $rows ? (int) end( $rows )['row_no'] : $state['cursor'];
		$state['batch_more']  = self::BATCH_SIZE === count( $rows );
		return $output;
	}

	/**
	 * Remove an expired private download and its state.
	 *
	 * @param string $id Download operation ID.
	 * @return void
	 */
	public function cleanup( $id ) {
		$store = new DKBCE_Operation_Store();
		$state = $store->get( $id );
		if ( $state && 'server_download' === $state['action'] ) {
			wp_delete_file( $state['file'] );
			delete_option( 'dkbce_operation_' . sanitize_key( $id ) );
			delete_option( 'dkbce_cancel_' . sanitize_key( $id ) );
		}
	}
}
