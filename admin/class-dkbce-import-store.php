<?php
/**
 * Indexed staging for large imports.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persist rows independently of frequently updated operation counters.
 */
class DKBCE_Import_Store {
	/**
	 * Create staging storage on the first authorized upload.
	 *
	 * @return void
	 * @throws RuntimeException When the table is unavailable.
	 */
	public function install() {
		global $wpdb;
		if ( '1' === get_option( 'dkbce_import_schema' ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'dkbce_import_rows';
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			job_id varchar(36) NOT NULL,
			row_type tinyint unsigned NOT NULL DEFAULT 0,
			row_no bigint unsigned NOT NULL,
			product_id bigint unsigned NOT NULL DEFAULT 0,
			value longtext NOT NULL,
			sku text NOT NULL,
			error text NOT NULL,
			selected tinyint unsigned NOT NULL DEFAULT 1,
			result_status varchar(20) NOT NULL DEFAULT '',
			result_message text NOT NULL,
			PRIMARY KEY  (job_id,row_type,row_no),
			KEY product (job_id,row_type,product_id),
			KEY selection (job_id,row_type,selected,row_no)
		) $collate;"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify newly created private staging storage.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			throw new RuntimeException( esc_html__( 'Import staging storage could not be created.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		update_option( 'dkbce_import_schema', '1', false );
	}

	/**
	 * Persist one row idempotently, including duplicate-target rejection.
	 *
	 * @param string $job Job ID.
	 * @param array  $row Validated row.
	 * @param int    $type Zero for products, one for XLSX strings.
	 * @return void
	 * @throws RuntimeException When a database write fails.
	 */
	public function put( $job, $row, $type = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dkbce_import_rows';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Durable staging has no shared cache.
		$written = $wpdb->replace(
			$table,
			array(
				'job_id'         => $job,
				'row_type'       => $type,
				'row_no'         => $row['line'],
				'product_id'     => $row['id'] ?? 0,
				'value'          => $row['value'],
				'sku'            => $row['sku'] ?? '',
				'error'          => $row['error'] ?? '',
				'result_status'  => '',
				'result_message' => '',
				'selected'       => empty( $row['error'] ) ? 1 : 0,
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $written ) {
			throw new RuntimeException( esc_html__( 'An import row could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		if ( 0 === $type && ! empty( $row['id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Indexed duplicate check across all batches, including retries.
			$duplicate = $wpdb->get_var( $wpdb->prepare( 'SELECT row_no FROM %i WHERE job_id = %s AND row_type = 0 AND product_id = %d AND row_no <> %d LIMIT 1', $table, $job, $row['id'], $row['line'] ) );
			$this->check_error();
			if ( null !== $duplicate ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Invalidate every occurrence, not just the latest duplicate.
				$updated = $wpdb->update(
					$table,
					array(
						'error'    => __( 'Duplicate product in file. Remove duplicate rows and preview again.', 'bulk-cogs-editor-for-woocommerce' ),
						'selected' => 0,
					),
					array(
						'job_id'     => $job,
						'row_type'   => 0,
						'product_id' => $row['id'],
					),
					array( '%s', '%d' ),
					array( '%s', '%d', '%d' )
				);
				if ( false === $updated ) {
					throw new RuntimeException( esc_html__( 'Duplicate import rows could not be marked.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
			}
		}
	}

	/**
	 * Read one stored scalar value.
	 *
	 * @param string $job Job ID.
	 * @param int    $id Product ID or shared string index.
	 * @param int    $type Record type.
	 * @return string|null
	 */
	public function value( $job, $id, $type = 0 ) {
		global $wpdb;
		$field = 1 === $type ? 'row_no' : 'product_id';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Indexed staged data; null distinguishes a missing cell from an empty string.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM %i WHERE job_id = %s AND row_type = %d AND %i = %d AND error = '' AND selected = 1 LIMIT 1", $wpdb->prefix . 'dkbce_import_rows', $job, $type, $field, $id ) );
		$this->check_error();
		return $value;
	}

	/**
	 * Fetch a bounded preview page or cursor batch.
	 *
	 * @param string $job Job ID.
	 * @param int    $limit Rows per request.
	 * @param int    $offset Preview offset.
	 * @param int    $after Cursor for snapshots and error downloads, or -1 for paging.
	 * @param bool   $errors Read invalid rows instead.
	 * @return array
	 */
	public function rows( $job, $limit, $offset = 0, $after = -1, $errors = false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dkbce_import_rows';
		if ( $errors ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded error report page.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND error <> '' AND row_no > %d ORDER BY row_no LIMIT %d", $table, $job, max( 0, $after ), $limit ), ARRAY_A );
			$this->check_error();
			return $rows;
		}
		if ( $after >= 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cursor snapshot over a frozen validated selection.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND error = '' AND selected = 1 AND product_id > %d ORDER BY product_id LIMIT %d", $table, $job, $after, $limit ), ARRAY_A );
			$this->check_error();
			return $rows;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Paged preview never loads the whole import.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND error = '' AND selected = 1 ORDER BY row_no LIMIT %d OFFSET %d", $table, $job, $limit, $offset ), ARRAY_A );
		$this->check_error();
		return $rows;
	}

	/**
	 * Count staged valid and invalid product rows.
	 *
	 * @param string $job Job ID.
	 * @return array
	 */
	public function counts( $job ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate only, without loading row data.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, COALESCE(SUM(error = '' AND selected = 1), 0) AS valid, COALESCE(SUM(error <> ''), 0) AS invalid FROM %i WHERE job_id = %s AND row_type = 0", $wpdb->prefix . 'dkbce_import_rows', $job ), ARRAY_A );
		$this->check_error();
		return array_map( 'intval', $row );
	}

	/**
	 * Freeze exclusions after a preview has been claimed.
	 *
	 * @param string $job Job ID.
	 * @param array  $ids Excluded product IDs.
	 * @return void
	 * @throws RuntimeException When an exclusion is invalid or cannot be saved.
	 */
	public function exclude( $job, $ids ) {
		global $wpdb;
		foreach ( $ids as $id ) {
			if ( ! is_scalar( $id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $id ) || null === $this->value( $job, (int) $id ) ) {
				throw new RuntimeException( esc_html__( 'Invalid import selection. Prepare the preview again.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}
		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A claimed preview has one frozen selection.
			if ( false === $wpdb->update(
				$wpdb->prefix . 'dkbce_import_rows',
				array( 'selected' => 0 ),
				array(
					'job_id'     => $job,
					'row_type'   => 0,
					'product_id' => (int) $id,
				),
				array( '%d' ),
				array( '%s', '%d', '%d' )
			) ) {
				throw new RuntimeException( esc_html__( 'The import selection could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Persist a durable product outcome for a paged results report.
	 *
	 * @param string $job Import job.
	 * @param int    $id Product ID.
	 * @param array  $outcome Worker result.
	 * @return void
	 * @throws RuntimeException When results cannot be saved.
	 */
	public function outcome( $job, $id, $outcome ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Match the durable worker outcome without caching private report data.
		if ( false === $wpdb->update(
			$wpdb->prefix . 'dkbce_import_rows',
			array(
				'result_status'  => $outcome['status'],
				'result_message' => $outcome['message'],
			),
			array(
				'job_id'     => $job,
				'row_type'   => 0,
				'product_id' => $id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d', '%d' )
		) ) {
			throw new RuntimeException( esc_html__( 'The import result could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
	}

	/**
	 * Fetch a bounded page of unsuccessful or unprocessed import results.
	 *
	 * @param string $job Import job.
	 * @param int    $after Row cursor.
	 * @return array
	 */
	public function results( $job, $after ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded report cursor over selected staged rows.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND selected = 1 AND error = '' AND result_status <> 'success' AND row_no > %d ORDER BY row_no LIMIT 100", $wpdb->prefix . 'dkbce_import_rows', $job, $after ), ARRAY_A );
		$this->check_error();
		return $rows;
	}

	/**
	 * Remove expired staging rows in bounded batches.
	 *
	 * @param string $job Job ID.
	 * @return int Rows removed.
	 */
	public function delete_batch( $job ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Private expired import data only.
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE job_id = %s LIMIT 1000', $wpdb->prefix . 'dkbce_import_rows', $job ) );
		$this->check_error();
		return (int) $deleted;
	}

	/**
	 * Fail closed rather than treating a failed query as an empty import.
	 *
	 * @return void
	 * @throws RuntimeException When staging storage cannot be read or updated.
	 */
	private function check_error() {
		global $wpdb;
		if ( $wpdb->last_error ) {
			throw new RuntimeException( esc_html__( 'Import storage is unavailable. Try again when the database is available.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
	}
}
