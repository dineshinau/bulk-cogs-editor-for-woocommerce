<?php
/**
 * CSV validation and serialization.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read CSV data without changing products.
 */
class DKBCE_CSV_Service {
	/** Supported export columns. */
	const COLUMNS = array( 'id', 'sku', 'name', 'cogs', 'regular_price', 'sale_price', 'type' );

	/**
	 * Normalize and validate the column header.
	 *
	 * @param array $header Header cells.
	 * @return array
	 * @throws RuntimeException When required columns are absent or repeated.
	 */
	public function header( $header ) {
		$header = array_map(
			static function ( $column ) {
				return str_replace( ' ', '_', strtolower( trim( (string) $column ) ) );
			},
			$header
		);
		if ( count( $header ) !== count( array_unique( $header ) ) || ( ! in_array( 'new_cogs', $header, true ) && ! in_array( 'cogs', $header, true ) ) || ( ! in_array( 'id', $header, true ) && ! in_array( 'sku', $header, true ) ) ) {
			throw new RuntimeException( esc_html__( 'Headers must include new_cogs (or legacy cogs) and id or sku, with no duplicate columns.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		return $header;
	}

	/**
	 * Prime bounded input rows and validate a prefix within the worker deadline.
	 *
	 * @param array              $records Records containing line and fields, keyed in input order.
	 * @param array              $header Normalized header.
	 * @param int                $user_id Upload owner.
	 * @param DKBCE_COGS_Service $service Product calculations.
	 * @param float              $deadline Cooperative deadline.
	 * @return array Validated rows retaining their input keys.
	 */
	public function validate_rows( $records, $header, $user_id, $service, $deadline ) {
		$product_ids = array();
		$skus        = array();
		foreach ( $records as $record ) {
			if ( count( $record['fields'] ) !== count( $header ) ) {
				continue;
			}
			$data = array_combine( $header, $record['fields'] );
			$id   = trim( $data['id'] ?? '' );
			$sku  = trim( $data['sku'] ?? '' );
			if ( '' !== $id ) {
				if ( preg_match( '/\A[1-9][0-9]*\z/', $id ) && (string) absint( $id ) === $id ) {
					$product_ids[] = (int) $id;
				}
			} elseif ( '' !== $sku ) {
				$skus = array_merge( $skus, $this->sku_candidates( $sku ) );
			}
		}
		$sku_matches = $this->resolve_skus( array_values( array_unique( $skus ) ) );
		foreach ( $sku_matches as $ids ) {
			$product_ids = array_merge( $product_ids, $ids );
		}
		$this->prime_products( $product_ids );
		$rows = array();
		foreach ( $records as $key => $record ) {
			// Always finish one row so a slow cache-prime cannot prevent progress.
			if ( $rows && microtime( true ) >= $deadline ) {
				break;
			}
			$rows[ $key ] = $this->validate_row( $record['fields'], $header, $record['line'], $user_id, $service, $sku_matches );
		}
		return $rows;
	}

	/**
	 * Validate a single imported row without writing product data.
	 *
	 * @param array              $fields Input cells.
	 * @param array              $header Normalized header.
	 * @param int                $line Record number.
	 * @param int                $user_id Upload owner, including in CLI workers.
	 * @param DKBCE_COGS_Service $service Product calculations.
	 * @param array              $sku_matches Optional pre-resolved SKU candidates for this batch.
	 * @return array
	 */
	public function validate_row( $fields, $header, $line, $user_id, $service, $sku_matches = array() ) {
		$row = array(
			'line'  => $line,
			'id'    => 0,
			'sku'   => '',
			'value' => '',
			'error' => '',
		);
		if ( count( $fields ) !== count( $header ) ) {
			$row['error'] = __( 'The number of fields does not match the header.', 'bulk-cogs-editor-for-woocommerce' );
			return $row;
		}
		$value_column   = in_array( 'new_cogs', $header, true ) ? 'new_cogs' : 'cogs';
		$data           = array_combine( $header, $fields );
		$id             = trim( $data['id'] ?? '' );
		$row['sku']     = trim( $data['sku'] ?? '' );
		$row['value']   = trim( $data[ $value_column ] );
		$sku_candidates = $this->sku_candidates( $row['sku'] );
		$product        = false;
		if ( '' !== $id ) {
			$product = preg_match( '/\A[1-9][0-9]*\z/', $id ) && (string) absint( $id ) === $id ? wc_get_product( absint( $id ) ) : false;
		} elseif ( '' !== $row['sku'] ) {
			$ids = array();
			foreach ( $sku_candidates as $candidate ) {
				$matches = $sku_matches[ $candidate ] ?? $this->resolve_skus( array( $candidate ) )[ $candidate ];
				$ids     = array_values( array_unique( array_merge( $ids, $matches ) ) );
			}
			if ( 1 === count( $ids ) ) {
				$product = wc_get_product( $ids[0] );
			}
		}
		if ( ! $product || in_array( $product->get_status(), array( 'trash', 'auto-draft' ), true ) || ! user_can( $user_id, 'edit_post', $product->get_id() ) || ( '' !== $row['sku'] && ! in_array( $product->get_sku(), $sku_candidates, true ) ) ) {
			$row['error'] = __( 'Product is missing, ambiguous, inaccessible, or ID and SKU disagree.', 'bulk-cogs-editor-for-woocommerce' );
		} else {
			$row['id'] = $product->get_id();
			$precision = wc_get_price_decimals();
			$rounded   = preg_match( '/\A\d{1,12}(?:\.\d{1,18})?\z/', $row['value'] )
				? wc_format_decimal( $row['value'], $precision, false )
				: '';
			$action    = $service->validate_action( 'set', $rounded, $precision );
			if ( '' === $row['value'] || '' === $rounded || is_wp_error( $action ) ) {
				$row['error'] = __( 'Enter a non-negative decimal COGS value. Blank values are not imported.', 'bulk-cogs-editor-for-woocommerce' );
			} else {
				// Round imported amounts to the store's configured currency precision.
				$row['value'] = wc_format_decimal( $action['value'], false, true );
			}
		}
		return $row;
	}

	/**
	 * Reject unterminated quotes and characters outside quoted fields.
	 *
	 * @param string $contents UTF-8 CSV.
	 * @return bool
	 */
	public function valid_quoting( $contents ) {
		$state  = 'start';
		$length = strlen( $contents );
		for ( $index = 0; $index < $length; ++$index ) {
			$char = $contents[ $index ];
			if ( 'quoted' === $state ) {
				if ( '"' === $char ) {
					if ( $index + 1 < $length && '"' === $contents[ $index + 1 ] ) {
						++$index;
					} else {
						$state = 'closed';
					}
				}
			} elseif ( ',' === $char || "\r" === $char || "\n" === $char ) {
				$state = 'start';
			} elseif ( '"' === $char && 'start' === $state ) {
				$state = 'quoted';
			} elseif ( 'closed' === $state || '"' === $char ) {
				return false;
			} else {
				$state = 'unquoted';
			}
		}
		return 'quoted' !== $state;
	}

	/**
	 * Preserve literal and spreadsheet-protected forms of a SKU.
	 *
	 * @param string $sku Imported SKU.
	 * @return array
	 */
	private function sku_candidates( $sku ) {
		$candidates = array( $sku );
		if ( "'" === substr( $sku, 0, 1 ) && preg_match( '/\A[\x00-\x20]*[=+@-]|\A[\t\r\n]/', substr( $sku, 1 ) ) ) {
			$candidates[] = substr( $sku, 1 );
		}
		return $candidates;
	}

	/**
	 * Resolve a bounded set of SKUs with the existing exact-match fallback policy.
	 *
	 * @param array $skus Unique SKU strings.
	 * @return array Candidate product IDs keyed by the requested SKU.
	 */
	private function resolve_skus( $skus ) {
		global $wpdb;
		if ( ! $skus ) {
			return array();
		}
		$lookup_table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : '';
		$use_lookup   = $lookup_table && ! get_option( 'woocommerce_product_lookup_table_is_generating' );
		$eligible     = array();
		$fallback     = array();
		foreach ( $skus as $sku ) {
			if ( $use_lookup && 100 > strlen( $sku ) ) {
				$eligible[] = $sku;
			} else {
				$fallback[] = $sku;
			}
		}
		$matches = $this->query_skus( $eligible, $lookup_table );
		if ( false === $matches ) {
			// Some supported stores lack the optional lookup table or have it rebuilding.
			$fallback = array_merge( $fallback, $eligible );
			$matches  = array_fill_keys( $eligible, array() );
		}
		$product_ids = array();
		foreach ( $matches as $ids ) {
			$product_ids = array_merge( $product_ids, $ids );
		}
		$this->prime_products( $product_ids );
		foreach ( $eligible as $sku ) {
			$ids = $matches[ $sku ];
			if ( $ids ) {
				$verified = array();
				foreach ( $ids as $lookup_id ) {
					$product = wc_get_product( $lookup_id );
					if ( $product && $sku === $product->get_sku() ) {
						$verified[] = (int) $lookup_id;
					}
				}
				if ( count( $verified ) === count( $ids ) ) {
					continue;
				}
			}
			$fallback[] = $sku;
		}
		foreach ( $this->query_skus( $fallback ) as $sku => $ids ) {
			$matches[ $sku ] = $ids;
		}
		return $matches;
	}

	/**
	 * Batch bounded SKU probes without changing database collation or ambiguity limits.
	 *
	 * @param array  $skus Requested SKU strings.
	 * @param string $lookup_table Lookup table, or empty for source metadata.
	 * @return array|false Candidate IDs keyed by SKU, or false for a failed lookup-table query.
	 */
	private function query_skus( $skus, $lookup_table = '' ) {
		global $wpdb;
		$queries = array();
		$matches = array_fill_keys( $skus, array() );
		foreach ( $skus as $index => $sku ) {
			// Each branch retains its own LIMIT 2, including case-insensitive matches.
			$queries[] = $lookup_table
				? $wpdb->prepare( "(SELECT %d AS sku_index, posts.ID FROM %i AS lookup INNER JOIN %i AS posts ON posts.ID = lookup.product_id WHERE lookup.sku = %s AND posts.post_type IN ('product', 'product_variation') AND posts.post_status NOT IN ('trash', 'auto-draft') ORDER BY posts.ID LIMIT 2)", $index, $lookup_table, $wpdb->posts, $sku )
				: $wpdb->prepare( "(SELECT DISTINCT %d AS sku_index, p.ID FROM %i AS p INNER JOIN %i AS m ON p.ID = m.post_id WHERE m.meta_key = '_sku' AND m.meta_value = %s AND p.post_type IN ('product', 'product_variation') AND p.post_status NOT IN ('trash', 'auto-draft') LIMIT 2)", $index, $wpdb->posts, $wpdb->postmeta, $sku );
		}
		if ( $queries ) {
			$query = 1 === count( $queries ) ? substr( $queries[0], 1, -1 ) : implode( ' UNION ALL ', $queries );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Every bounded UNION branch is independently prepared above.
			$rows = $wpdb->get_results( $query, ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $rows ) ) {
				// A failed optional lookup-table query uses the same metadata fallback as before batching.
				return $lookup_table ? false : $matches;
			}
			foreach ( $rows as $row ) {
				$matches[ $skus[ (int) $row['sku_index'] ] ][] = (int) $row['ID'];
			}
		}
		return $matches;
	}

	/**
	 * Prime product and variation-parent data for a bounded validation batch.
	 *
	 * @param array $ids Product IDs.
	 * @return void
	 */
	private function prime_products( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return;
		}
		_prime_post_caches( $ids, true, true );
		$parents = array();
		foreach ( $ids as $id ) {
			$parent = wp_get_post_parent_id( $id );
			if ( $parent ) {
				$parents[] = $parent;
			}
		}
		if ( $parents ) {
			_prime_post_caches( array_unique( $parents ), true, true );
		}
	}

	/**
	 * Serialize cells with CSV quoting and spreadsheet formula protection.
	 *
	 * @param array $cells CSV cells.
	 * @return string
	 */
	public function line( $cells ) {
		$escaped = array();
		foreach ( $cells as $cell ) {
			$cell = (string) $cell;
			if ( preg_match( '/\A[\x00-\x20]*[=+@-]|\A[\t\r\n]/', $cell ) ) {
				$cell = "'" . $cell;
			}
			$escaped[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}
		return implode( ',', $escaped ) . "\r\n";
	}
}
