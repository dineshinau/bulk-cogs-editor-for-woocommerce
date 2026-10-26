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
		DKBCE_Database_Installer::maybe_upgrade();
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
		// Durable staging has no shared cache.
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
			// Indexed duplicate check across all batches, including retries.
			$duplicate = $wpdb->get_var( $wpdb->prepare( 'SELECT row_no FROM %i WHERE job_id = %s AND row_type = 0 AND product_id = %d AND row_no <> %d LIMIT 1', $table, $job, $row['id'], $row['line'] ) );
			$this->check_error();
			if ( null !== $duplicate ) {
				// Invalidate every occurrence, not just the latest duplicate.
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
	 * Stage a bounded product batch and invalidate duplicates across the whole job.
	 *
	 * The reader advances its cursor only after both writes and duplicate marking
	 * succeed. Replaying an interrupted batch restores the same row keys and errors.
	 *
	 * @param string $job Job ID.
	 * @param array  $rows Validated product rows.
	 * @return void
	 * @throws RuntimeException When staging or duplicate marking fails.
	 */
	public function put_rows( $job, $rows ) {
		global $wpdb;
		if ( ! $rows ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( strlen( $row['value'] ) > 65535 || strlen( $row['sku'] ?? '' ) > 65535 || strlen( $row['error'] ?? '' ) > 65535 ) {
				// Keep large cells on the scalar path, including wpdb's TEXT length checks.
				foreach ( $rows as $single ) {
					$this->put( $job, $single );
				}
				return;
			}
		}
		$table        = $wpdb->prefix . 'dkbce_import_rows';
		$placeholders = array();
		$args         = array( $table );
		$product_ids  = array();
		foreach ( $rows as $row ) {
			$placeholders[] = "(%s, 0, %d, %d, %s, %s, %s, '', '', %d)";
			$args           = array_merge( $args, array( $job, $row['line'], $row['id'], $row['value'], $row['sku'], $row['error'], empty( $row['error'] ) ? 1 : 0 ) );
			if ( $row['id'] ) {
				$product_ids[] = (int) $row['id'];
			}
		}
		$placeholders = implode( ', ', $placeholders );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Seven values per generated row plus the table identifier.
		$query = $wpdb->prepare(
			"REPLACE INTO %i (job_id, row_type, row_no, product_id, value, sku, error, result_status, result_message, selected) VALUES $placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only fixed placeholder tuples are interpolated.
			$args
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The bounded row tuples and all values are prepared above.
		if ( false === $wpdb->query( $query ) ) {
			throw new RuntimeException( esc_html__( 'An import row could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$product_ids = array_values( array_unique( $product_ids ) );
		if ( ! $product_ids ) {
			return;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per bounded product ID plus table and job.
		$query = $wpdb->prepare(
			"SELECT product_id FROM %i WHERE job_id = %s AND row_type = 0 AND product_id IN ($placeholders) GROUP BY product_id HAVING COUNT(*) > 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only generated integer placeholders are interpolated.
			array_merge( array( $table, $job ), $product_ids )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared table, job, and bounded product IDs above.
		$duplicates = $wpdb->get_col( $query );
		$this->check_error();
		if ( ! $duplicates ) {
			return;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $duplicates ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per duplicate ID plus table, error, and job.
		$query = $wpdb->prepare(
			"UPDATE %i SET error = %s, selected = 0 WHERE job_id = %s AND row_type = 0 AND product_id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only generated integer placeholders are interpolated.
			array_merge( array( $table, __( 'Duplicate product in file. Remove duplicate rows and preview again.', 'bulk-cogs-editor-for-woocommerce' ), $job ), array_map( 'intval', $duplicates ) )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared update for all occurrences of the duplicate product IDs.
		if ( false === $wpdb->query( $query ) ) {
			throw new RuntimeException( esc_html__( 'Duplicate import rows could not be marked.', 'bulk-cogs-editor-for-woocommerce' ) );
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
		// Indexed staged data; null distinguishes a missing cell from an empty string.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM %i WHERE job_id = %s AND row_type = %d AND %i = %d AND error = '' AND selected = 1 LIMIT 1", $wpdb->prefix . 'dkbce_import_rows', $job, $type, $field, $id ) );
		$this->check_error();
		return $value;
	}

	/**
	 * Fetch trusted staged values for a bounded product batch.
	 *
	 * @param string $job Import job.
	 * @param array  $product_ids Product IDs.
	 * @return array Values keyed by product ID.
	 */
	public function values( $job, $product_ids ) {
		global $wpdb;
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( ! $product_ids ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count is generated from the bounded, absolute product IDs below.
		$query = $wpdb->prepare(
			"SELECT product_id, value FROM %i WHERE job_id = %s AND row_type = 0 AND error = '' AND selected = 1 AND product_id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The placeholder list is generated from the bounded, absolute product IDs below.
			array_merge( array( $wpdb->prefix . 'dkbce_import_rows', $job ), $product_ids )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The dynamic placeholder list above is prepared with absolute IDs and the table identifier.
		$rows = $wpdb->get_results( $query, ARRAY_A );
		$this->check_error();
		$values = array();
		foreach ( $rows as $row ) {
			$values[ (int) $row['product_id'] ] = $row['value'];
		}
		return $values;
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
			// Bounded error report page.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND error <> '' AND row_no > %d ORDER BY row_no LIMIT %d", $table, $job, max( 0, $after ), $limit ), ARRAY_A );
			$this->check_error();
			return $rows;
		}
		if ( $after >= 0 ) {
			// Cursor snapshot over a frozen validated selection.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND error = '' AND selected = 1 AND product_id > %d ORDER BY product_id LIMIT %d", $table, $job, $after, $limit ), ARRAY_A );
			$this->check_error();
			return $rows;
		}
		// Paged preview never loads the whole import.
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
		// Aggregate only, without loading row data.
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
			// A claimed preview has one frozen selection.
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
	 * Freeze a bounded explicit selection from validated staging rows.
	 *
	 * @param string $job Claimed import job.
	 * @param array  $ids Authorized product IDs.
	 * @return void
	 * @throws RuntimeException When the selection is missing or cannot be saved.
	 */
	public function select_only( $job, $ids ) {
		global $wpdb;
		if ( ! $ids || count( $this->values( $job, $ids ) ) !== count( $ids ) ) {
			throw new RuntimeException( esc_html__( 'Select at least one valid product to import.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One bounded placeholder per validated product ID.
		$query = $wpdb->prepare(
			"UPDATE %i SET selected = (product_id IN ($placeholders)) WHERE job_id = %s AND row_type = 0 AND error = ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only the generated placeholder list is interpolated.
			array_merge( array( $wpdb->prefix . 'dkbce_import_rows' ), $ids, array( $job ) )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared selection update for an owned, claimed import.
		$wpdb->query( $query );
		$this->check_error();
	}

	/**
	 * Fetch a bounded page of unsuccessful or unprocessed import results.
	 *
	 * @param string $job          Import job.
	 * @param int    $after        Row cursor.
	 * @param string $operation_id Applied operation UUID for outcome lookup.
	 * @return array
	 */
	public function results( $job, $after, $operation_id = '' ) {
		global $wpdb;
		if ( $operation_id ) {
			$operations = new DKBCE_Operation_Store();
			$operations->ensure_outcomes_table();
			// Bounded report cursor joins the authoritative durable outcome.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT staged.*, COALESCE(NULLIF(outcomes.status, ''), staged.result_status) AS result_status, COALESCE(outcomes.message, staged.result_message) AS result_message FROM %i AS staged LEFT JOIN %i AS outcomes ON outcomes.operation_id = %s AND outcomes.product_id = staged.product_id WHERE staged.job_id = %s AND staged.row_type = 0 AND staged.selected = 1 AND staged.error = '' AND COALESCE(NULLIF(outcomes.status, ''), staged.result_status) <> 'success' AND staged.row_no > %d ORDER BY staged.row_no LIMIT 100",
					$wpdb->prefix . 'dkbce_import_rows',
					$wpdb->prefix . 'dkbce_operation_outcomes',
					sanitize_key( $operation_id ),
					$job,
					$after
				),
				ARRAY_A
			);
		} else {
			// Bounded compatibility query for reports created before outcome storage was upgraded.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE job_id = %s AND row_type = 0 AND selected = 1 AND error = '' AND result_status <> 'success' AND row_no > %d ORDER BY row_no LIMIT 100", $wpdb->prefix . 'dkbce_import_rows', $job, $after ), ARRAY_A );
		}
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
		// Private expired import data only.
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
