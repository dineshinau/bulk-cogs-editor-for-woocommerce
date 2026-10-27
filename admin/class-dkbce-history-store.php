<?php
/**
 * Indexed operation history and immutable product cost journals.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retains operation summaries separately from transient worker chunks.
 */
class DKBCE_History_Store {
	/**
	 * Confirmed index rows, shared by store instances within this request.
	 *
	 * @var array
	 */
	private static $indexed_rows = array();

	/**
	 * Get the configured policy for costs changed after the original operation.
	 *
	 * @return string
	 */
	public static function rollback_policy() {
		return 'skip_changed' === get_option( 'dkbce_rollback_policy', 'overwrite' ) ? 'skip_changed' : 'overwrite';
	}

	/**
	 * Read a bounded list of users represented in visible operation history.
	 *
	 * @param int $owner Limit non-manager choices to their own account.
	 * @return array
	 */
	public function users( $owner = 0 ) {
		global $wpdb;
		// Only users represented in authorized history are listed.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT h.user_id, u.display_name FROM %i h LEFT JOIN %i u ON h.user_id = u.ID WHERE (%d = 0 OR h.user_id = %d) ORDER BY u.display_name LIMIT 100', $wpdb->prefix . 'dkbce_history', $wpdb->users, $owner, $owner ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
	/**
	 * Index a supported operation for filtering and pagination.
	 *
	 * @param array $state Operation state.
	 * @return void
	 * @throws RuntimeException When the summary cannot be saved.
	 */
	public function index( $state ) {
		global $wpdb;
		if ( ! empty( $state['history_cleanup_complete'] ) ) {
			return;
		}
		if ( ! in_array( $state['action'] ?? '', array( 'set', 'clear', 'increase_percent', 'decrease_percent', 'increase_fixed', 'decrease_fixed', 'csv_import', 'rollback' ), true ) ) {
			return;
		}
		$summary = array_map(
			'strval',
			array(
				'operation_id' => $state['operation_id'],
				'created_at'   => $state['created_at'],
				'user_id'      => $state['user_id'],
				'action'       => $state['action'],
				'status'       => $state['status'],
				'note'         => $state['note'] ?? '',
			)
		);
		$id      = $state['operation_id'];
		if ( ! array_key_exists( $id, self::$indexed_rows ) ) {
			DKBCE_Database_Installer::maybe_upgrade();
			$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT operation_id, created_at, user_id, action, status, note FROM %i WHERE operation_id = %s', $wpdb->prefix . 'dkbce_history', $id ), ARRAY_A );
			if ( $wpdb->last_error ) {
				throw new RuntimeException( esc_html__( 'Operation history could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			self::$indexed_rows[ $id ] = is_array( $stored ) ? array_map( 'strval', $stored ) : null;
		}
		if ( self::$indexed_rows[ $id ] === $summary ) {
			return;
		}
		// Counters and timestamps belong to operation state, not this summary index.
		$result = $wpdb->replace(
			$wpdb->prefix . 'dkbce_history',
			$summary,
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			unset( self::$indexed_rows[ $id ] );
			throw new RuntimeException( esc_html__( 'Operation history could not be saved.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		self::$indexed_rows[ $id ] = $summary;
	}

	/**
	 * Index one bounded page of operations created before history was available.
	 *
	 * @return bool Whether legacy indexing is complete.
	 */
	public function backfill() {
		global $wpdb;
		$cursor = (int) get_option( 'dkbce_history_index_cursor', 0 );
		if ( -1 === $cursor ) {
			return true;
		}
		// Bounded migration of existing operation options.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_id, option_name FROM %i WHERE option_id > %d AND option_name LIKE %s ORDER BY option_id LIMIT 100', $wpdb->options, $cursor, $wpdb->esc_like( 'dkbce_operation_' ) . '%' ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return false;
		}
		foreach ( $rows as $row ) {
			$state = get_option( $row['option_name'] );
			if ( is_array( $state ) ) {
				$this->index( $state );
			}
			$cursor = (int) $row['option_id'];
		}
		$complete = count( $rows ) < 100;
		update_option( 'dkbce_history_index_cursor', $complete ? -1 : $cursor, false );
		return $complete;
	}

	/**
	 * Read a paginated, authorized history list.
	 *
	 * @param array $filters Validated filter strings.
	 * @param int   $page Page number.
	 * @param int   $size Page size.
	 * @return array
	 * @throws RuntimeException When history cannot be read.
	 */
	public function operations( $filters, $page, $size ) {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		$indexed = $this->backfill();
		$where   = '1=1';
		$args    = array( $wpdb->prefix . 'dkbce_history', $wpdb->options );
		foreach ( array( 'status', 'action', 'user_id' ) as $key ) {
			if ( '' !== ( $filters[ $key ] ?? '' ) ) {
				$where .= " AND h.$key = %s";
				$args[] = $filters[ $key ];
			}
		}
		foreach ( array(
			'from' => '>=',
			'to'   => '<=',
		) as $key => $comparison ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$where .= " AND h.created_at $comparison %d";
				$args[] = $filters[ $key ];
			}
		}
		if ( ! empty( $filters['search'] ) ) {
			$where .= ' AND (h.operation_id LIKE %s OR h.note LIKE %s OR h.operation_id IN (SELECT operation_id FROM %i WHERE product_id = %d))';
			$args[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$args[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$args[] = $wpdb->prefix . 'dkbce_history_products';
			$args[] = absint( $filters['search'] );
		}
		// Exclude stale index rows whose durable operation state no longer exists.
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- SQL fragments contain only fixed clauses and placeholders; all values are prepared.
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i h INNER JOIN %i o ON o.option_name = CONCAT('dkbce_operation_', h.operation_id) WHERE $where", $args ) );
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Same prepared filters, with bounded pagination.
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT h.operation_id FROM %i h INNER JOIN %i o ON o.option_name = CONCAT('dkbce_operation_', h.operation_id) WHERE $where ORDER BY h.created_at DESC, h.operation_id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $size, ( $page - 1 ) * $size ) ) ) );
		if ( null === $total || ! is_array( $rows ) ) {
			throw new RuntimeException( esc_html__( 'Operation history could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return array(
			'ids'     => $rows,
			'total'   => (int) $total,
			'indexed' => $indexed,
		);
	}

	/**
	 * Read one product's immutable journal entry.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $product_id Product ID.
	 * @return array|null
	 * @throws RuntimeException When the journal cannot be read.
	 */
	public function product( $operation_id, $product_id ) {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		// A unique journal entry must be read fresh for retry protection.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE operation_id = %s AND product_id = %d', $wpdb->prefix . 'dkbce_history_products', $operation_id, $product_id ), ARRAY_A );
		if ( $wpdb->last_error ) {
			throw new RuntimeException( esc_html__( 'Product history could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $row;
	}

	/**
	 * Persist original and intended values before changing a product.
	 *
	 * @param string           $operation_id Operation UUID.
	 * @param int              $product_id Product ID.
	 * @param array            $result Calculated current and new costs.
	 * @param array|null|false $recorded Journal read for this operation/product under its lock;
	 *                                  null means absent, false requests a fresh lookup.
	 * @return array Frozen journal entry.
	 * @throws RuntimeException When the original values cannot be saved.
	 */
	public function prepare( $operation_id, $product_id, $result, $recorded = false ) {
		global $wpdb;
		$row = false === $recorded ? $this->product( $operation_id, $product_id ) : $recorded;
		if ( $row ) {
			return $row;
		}
		// Match the nullable string values persisted by the journal's %s formats.
		$row = array(
			'operation_id'   => $operation_id,
			'product_id'     => (string) (int) $product_id,
			'original_value' => null === $result['current'] ? null : (string) $result['current'],
			'target_value'   => null === $result['new'] ? null : (string) $result['new'],
			'status'         => 'prepared',
		);
		// The product lock excludes competing preparations for this operation.
		$saved = $wpdb->insert(
			$wpdb->prefix . 'dkbce_history_products',
			$row,
			array( '%s', '%d', '%s', '%s', '%s' )
		);
		if ( 1 !== $saved ) {
			throw new RuntimeException( esc_html__( 'Original product costs could not be saved. The product was not changed.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $row;
	}

	/**
	 * Confirm a product save, including recovery after an interrupted worker.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $product_id Product ID.
	 * @return void
	 * @throws RuntimeException When confirmation cannot be persisted.
	 */
	public function confirm( $operation_id, $product_id ) {
		global $wpdb;
		// Confirmation is idempotent and tied to the product's saved operation marker.
		$result = $wpdb->update(
			$wpdb->prefix . 'dkbce_history_products',
			array( 'status' => 'success' ),
			array(
				'operation_id' => $operation_id,
				'product_id'   => $product_id,
			),
			array( '%s' ),
			array( '%s', '%d' )
		);
		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'Product history could not be confirmed.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
	}

	/**
	 * Mark an original product as restored after its rollback journal is durable.
	 *
	 * @param string $source_id Original operation UUID.
	 * @param int    $product_id Product ID.
	 * @return void
	 * @throws RuntimeException When restoration cannot be recorded.
	 */
	public function mark_restored( $source_id, $product_id ) {
		global $wpdb;
		// The product lock protects this idempotent restoration checkpoint.
		$result = $wpdb->update(
			$wpdb->prefix . 'dkbce_history_products',
			array( 'status' => 'restored' ),
			array(
				'operation_id' => $source_id,
				'product_id'   => $product_id,
			),
			array( '%s' ),
			array( '%s', '%d' )
		);
		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'The restored product could not be recorded.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
	}

	/**
	 * Recover restoration checkpoints from an existing attempt, including legacy jobs.
	 *
	 * @param string $source_id Original operation UUID.
	 * @return array|false Last linked rollback, or false for a missing claim target.
	 * @throws RuntimeException When restoration checkpoints cannot be recovered.
	 */
	public function rollback_state( $source_id ) {
		global $wpdb;
		wp_cache_delete( 'dkbce_rollback_' . sanitize_key( $source_id ), 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$id    = get_option( 'dkbce_rollback_' . sanitize_key( $source_id ), '' );
		$store = new DKBCE_Operation_Store();
		$state = $id ? $store->get( $id, true ) : false;
		if ( ! $state || ( $state['rollback_source'] ?? '' ) !== $source_id ) {
			return false;
		}
		// Recover only products confirmed by the linked rollback, without changing original values.
		$result = $wpdb->query( $wpdb->prepare( "UPDATE %i s INNER JOIN %i r ON r.product_id = s.product_id AND r.operation_id = %s AND r.status = 'success' SET s.status = 'restored' WHERE s.operation_id = %s AND s.status = 'success'", $wpdb->prefix . 'dkbce_history_products', $wpdb->prefix . 'dkbce_history_products', $id, $source_id ) );
		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'Rollback progress could not be recovered.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $state;
	}

	/**
	 * Read one cursor page of remaining rollback IDs without a full count.
	 *
	 * @param string $operation_id Original operation UUID.
	 * @param int    $size Frozen worker batch size.
	 * @param int    $after_id Last collected product ID.
	 * @return array
	 * @throws RuntimeException When snapshot IDs cannot be read.
	 */
	public function remaining_ids( $operation_id, $size, $after_id ) {
		global $wpdb;
		// Bounded primary-key cursor read; reviewed totals are checked separately.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT product_id FROM %i WHERE operation_id = %s AND status = 'success' AND product_id > %d ORDER BY product_id LIMIT %d", $wpdb->prefix . 'dkbce_history_products', $operation_id, absint( $after_id ), absint( $size ) ) );
		if ( ! is_array( $ids ) || $wpdb->last_error ) {
			throw new RuntimeException( esc_html__( 'Rollback products could not be collected.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return array_map( 'absint', $ids );
	}

	/**
	 * Read a bounded page of recorded changes or rollback snapshot IDs.
	 *
	 * @param string $operation_id Operation UUID.
	 * @param int    $size Page size.
	 * @param int    $offset Offset for display pages.
	 * @param int    $after_id Cursor for background snapshots; negative disables it.
	 * @param bool   $remaining Read only products that have not been restored.
	 * @return array
	 * @throws RuntimeException When changes cannot be read.
	 */
	public function products( $operation_id, $size = 20, $offset = 0, $after_id = -1, $remaining = false ) {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		$status = $remaining ? "status = 'success'" : "status IN ('success', 'restored')";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Status is a fixed internal clause; pages and snapshots are bounded.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE operation_id = %s AND $status AND product_id > %d ORDER BY product_id LIMIT %d OFFSET %d", $wpdb->prefix . 'dkbce_history_products', $operation_id, max( 0, $after_id ), $size, $offset ), ARRAY_A );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The same fixed status clause is used for the indexed count.
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE operation_id = %s AND $status", $wpdb->prefix . 'dkbce_history_products', $operation_id ) );
		if ( ! is_array( $rows ) || null === $total ) {
			throw new RuntimeException( esc_html__( 'Product history could not be read.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return array(
			'rows'  => $rows,
			'total' => (int) $total,
		);
	}

	/**
	 * Delete a bounded page of expired changes, followed by its summary.
	 *
	 * @param string $operation_id Expired operation UUID.
	 * @return bool Whether cleanup completed.
	 * @throws RuntimeException When deletion fails.
	 */
	public function delete( $operation_id ) {
		global $wpdb;
		DKBCE_Database_Installer::maybe_upgrade();
		// Bounded retention deletion scoped to one expired operation.
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE operation_id = %s LIMIT 500', $wpdb->prefix . 'dkbce_history_products', $operation_id ) );
		if ( false === $deleted ) {
			throw new RuntimeException( esc_html__( 'Expired history could not be removed.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		if ( 500 === $deleted ) {
			return false;
		}
		// Remove the summary only after its product rows are gone.
		unset( self::$indexed_rows[ $operation_id ] );
		return false !== $wpdb->delete( $wpdb->prefix . 'dkbce_history', array( 'operation_id' => $operation_id ), array( '%s' ) );
	}
}
