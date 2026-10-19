<?php
/**
 * Background preview preparation and upload lifecycle.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepare previews through the existing Action Scheduler worker group.
 */
class DKBCE_Import_Processor {
	/**
	 * Accept a real PHP upload under the site's WordPress size limit.
	 *
	 * @param array $upload Validated upload metadata.
	 * @return array Durable initial state.
	 * @throws RuntimeException When an upload cannot be accepted or queued.
	 */
	public function upload( $upload ) {
		if ( UPLOAD_ERR_OK !== $upload['error'] || ! is_uploaded_file( $upload['tmp_name'] ) ) {
			throw new RuntimeException( esc_html__( 'Upload failed. Check the WordPress upload limit and try again.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$size = filesize( $upload['tmp_name'] );
		if ( ! $size || $size > wp_max_upload_size() ) {
			throw new RuntimeException( esc_html__( 'The file exceeds the WordPress maximum upload size or is empty.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$extension = strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'xlsx' ), true ) ) {
			throw new RuntimeException( esc_html__( 'Choose a UTF-8 CSV or XLSX file.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			throw new RuntimeException( esc_html__( 'Action Scheduler is required to prepare import previews.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$rows = new DKBCE_Import_Store();
		$rows->install();
		$id = wp_generate_uuid4();
		// Keep originals outside the uploads directory; only authorized AJAX returns staged data.
		// Respect WordPress's configured temporary directory; PHP's upload temp
		// directory can be on a separate mount where moving files is not allowed.
		$path = wp_tempnam( 'dkbce-' . $id, get_temp_dir() );
		if ( ! $path ) {
			throw new RuntimeException( esc_html__( 'A private temporary import file could not be created.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Restrict the private temporary upload before retaining it.
		$private = chmod( $path, 0600 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file -- Move only a PHP-verified upload into a server-generated private temporary path.
		if ( ! $private || ! move_uploaded_file( $upload['tmp_name'], $path ) ) {
			wp_delete_file( $path );
			throw new RuntimeException( esc_html__( 'The import file could not be stored.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		// Moving an upload can replace the destination mode.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- This file must remain private.
		if ( ! chmod( $path, 0600 ) ) {
			wp_delete_file( $path );
			throw new RuntimeException( esc_html__( 'The import file could not be protected.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$state = array(
			'operation_id'     => $id,
			'user_id'          => get_current_user_id(),
			'action'           => 'import_preview',
			'operation'        => array(
				'action' => 'import_preview',
				'value'  => '',
			),
			'file'             => $path,
			'format'           => $extension,
			'file_size'        => $size,
			'status'           => 'pending',
			'stage'            => 'xlsx' === $extension ? 'import_open' : 'import_rows',
			'header'           => array(),
			'cursor'           => 0,
			'offset'           => 0,
			'processed'        => 0,
			'total'            => 0,
			'estimated_total'  => 0,
			'batch_size'       => 250,
			'created_at'       => time(),
			'updated_at'       => time(),
			'error_summary'    => '',
			'cancel_requested' => false,
		);
		$store = new DKBCE_Operation_Store();
		$store->save( $state );
		if ( ! as_schedule_single_action( time() + DAY_IN_SECONDS, 'dkbce_cleanup_import', array( $id ), DKBCE_Bulk_Processor::GROUP ) || ! as_enqueue_async_action( DKBCE_Bulk_Processor::HOOK, array( $id ), DKBCE_Bulk_Processor::GROUP ) ) {
			wp_delete_file( $path );
			$state['status'] = 'failed';
			$store->save( $state );
			throw new RuntimeException( esc_html__( 'The import preview could not be queued.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		update_user_meta( $state['user_id'], 'dkbce_latest_import', $id );
		return $state;
	}

	/**
	 * Advance a preview under the bulk worker's operation lock.
	 *
	 * @param array                 $state Durable import state.
	 * @param DKBCE_Operation_Store $store Operation store.
	 * @param DKBCE_COGS_Service    $service Product service.
	 * @return void
	 * @throws RuntimeException Internally caught and recorded as a failed preview.
	 */
	public function process( $state, $store, $service ) {
		if ( in_array( $state['status'], array( 'ready', 'failed', 'cancelled', 'expired' ), true ) ) {
			return;
		}
		$previous_errors = libxml_use_internal_errors( true );
		libxml_clear_errors();
		try {
			if ( ! empty( $state['cancel_requested'] ) ) {
				$state['status'] = 'cancelled';
			} else {
				if ( ! user_can( $state['user_id'], 'edit_others_products' ) || ! $service->is_cogs_available() ) {
					throw new RuntimeException( esc_html__( 'Permission to prepare this import is no longer available.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				if ( ! is_readable( $state['file'] ) ) {
					throw new RuntimeException( esc_html__( 'The temporary upload is unavailable. Upload the file again.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
				$state['status'] = 'processing';
				$reader          = new DKBCE_Import_Reader();
				$rows            = new DKBCE_Import_Store();
				$deadline        = microtime( true ) + 10;
				if ( 'import_open' === $state['stage'] ) {
					$reader->prepare_xlsx( $state );
				}
				$done = 'csv' === $state['format'] ? $reader->csv_batch( $state, $rows, $service, $deadline ) : $reader->xlsx_batch( $state, $rows, $service, $deadline );
				if ( $done ) {
					if ( ! $state['header'] ) {
						throw new RuntimeException( esc_html__( 'No import header was found in the file.', 'bulk-cogs-editor-for-woocommerce' ) );
					}
					$state['counts'] = $rows->counts( $state['operation_id'] );
					$state['total']  = $state['counts']['valid'];
					$state['status'] = 'ready';
				}
			}
		} catch ( Throwable $exception ) {
			$state['status']        = 'failed';
			$state['error_summary'] = $exception->getMessage();
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_errors );
		}
		if ( $store->is_cancel_requested( $state['operation_id'] ) ) {
			$state['status'] = 'cancelled';
		}
		$state['updated_at'] = time();
		$store->save( $state );
		if ( in_array( $state['status'], array( 'ready', 'failed', 'cancelled' ), true ) ) {
			wp_delete_file( $state['file'] );
		} elseif ( ! as_enqueue_async_action( DKBCE_Bulk_Processor::HOOK, array( $state['operation_id'] ), DKBCE_Bulk_Processor::GROUP ) ) {
			$state['status']        = 'failed';
			$state['error_summary'] = __( 'The preview queue stopped. Upload the file again.', 'bulk-cogs-editor-for-woocommerce' );
			$store->save( $state );
			wp_delete_file( $state['file'] );
		}
	}

	/**
	 * Expire private uploads and remove staged data after its retention period.
	 *
	 * @param string $id Import ID.
	 * @return void
	 */
	public function cleanup( $id ) {
		$store = new DKBCE_Operation_Store();
		$state = $store->get( $id );
		if ( ! $state || 'import_preview' !== $state['action'] ) {
			return;
		}
		$claim_key = 'dkbce_preview_claim_' . sanitize_key( $id );
		if ( 'ready' === $state['status'] && empty( $state['applied_operation'] ) && ! add_option( $claim_key, 'expired', '', false ) ) {
			$claim = get_option( $claim_key );
			if ( is_array( $claim ) && time() - (int) $claim['created_at'] < HOUR_IN_SECONDS ) {
				as_schedule_single_action( time() + HOUR_IN_SECONDS, 'dkbce_cleanup_import', array( $id ), DKBCE_Bulk_Processor::GROUP );
				return;
			}
			// The Apply request may have saved its operation after the initial read.
			wp_cache_delete( 'dkbce_operation_' . sanitize_key( $id ), 'options' );
			$state = $store->get( $id );
			if ( ! $state ) {
				return;
			}
		}
		$operation = empty( $state['applied_operation'] ) ? false : $store->get( $state['applied_operation'] );
		if ( ( $operation && ! in_array( $operation['status'], array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true ) ) || ( in_array( $state['status'], array( 'pending', 'processing' ), true ) && time() - $state['updated_at'] < HOUR_IN_SECONDS ) ) {
			as_schedule_single_action( time() + DAY_IN_SECONDS, 'dkbce_cleanup_import', array( $id ), DKBCE_Bulk_Processor::GROUP );
			return;
		}
		$state['status'] = 'expired';
		$store->save( $state );
		wp_delete_file( $state['file'] );
		delete_transient( 'dkbce_preview_' . $id );
		$rows = new DKBCE_Import_Store();
		if ( $rows->delete_batch( $id ) ) {
			as_enqueue_async_action( 'dkbce_cleanup_import', array( $id ), DKBCE_Bulk_Processor::GROUP );
		} else {
			delete_option( 'dkbce_operation_' . sanitize_key( $id ) );
			delete_option( 'dkbce_cancel_' . sanitize_key( $id ) );
			delete_option( 'dkbce_preview_claim_' . sanitize_key( $id ) );
			delete_user_meta( $state['user_id'], 'dkbce_latest_import', $id );
		}
	}
}
