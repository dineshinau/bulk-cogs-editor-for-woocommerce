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
	 * Validate a single imported row without writing product data.
	 *
	 * @param array              $fields Input cells.
	 * @param array              $header Normalized header.
	 * @param int                $line Record number.
	 * @param int                $user_id Upload owner, including in CLI workers.
	 * @param DKBCE_COGS_Service $service Product calculations.
	 * @return array
	 */
	public function validate_row( $fields, $header, $line, $user_id, $service ) {
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
		$sku_candidates = array( $row['sku'] );
		if ( "'" === substr( $row['sku'], 0, 1 ) && preg_match( '/\A[\x00-\x20]*[=+@-]|\A[\t\r\n]/', substr( $row['sku'], 1 ) ) ) {
			$sku_candidates[] = substr( $row['sku'], 1 );
		}
		$product = false;
		if ( '' !== $id ) {
			$product = preg_match( '/\A[1-9][0-9]*\z/', $id ) && (string) absint( $id ) === $id ? wc_get_product( absint( $id ) ) : false;
		} elseif ( '' !== $row['sku'] ) {
			$ids = array();
			foreach ( $sku_candidates as $candidate ) {
				$ids = array_values( array_unique( array_merge( $ids, $this->sku_ids( $candidate ) ) ) );
			}
			if ( 1 === count( $ids ) ) {
				$product = wc_get_product( $ids[0] );
			}
		}
		if ( ! $product || in_array( $product->get_status(), array( 'trash', 'auto-draft' ), true ) || ! user_can( $user_id, 'edit_post', $product->get_id() ) || ( '' !== $row['sku'] && ! in_array( $product->get_sku(), $sku_candidates, true ) ) ) {
			$row['error'] = __( 'Product is missing, ambiguous, inaccessible, or ID and SKU disagree.', 'bulk-cogs-editor-for-woocommerce' );
		} else {
			$row['id'] = $product->get_id();
			$action    = $service->validate_action( 'set', $row['value'], 6 );
			if ( '' === $row['value'] || is_wp_error( $action ) ) {
				$row['error'] = __( 'Enter a non-negative decimal COGS value. Blank values are not imported.', 'bulk-cogs-editor-for-woocommerce' );
			} else {
				// COGS can retain more precision than the store's currency display setting.
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
	 * Resolve an exact SKU, detecting ambiguous stored identifiers.
	 *
	 * @param string $sku Exact SKU.
	 * @return array
	 */
	private function sku_ids( $sku ) {
		global $wpdb;
		$lookup_table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : '';
		if ( $lookup_table && 100 > strlen( $sku ) && ! get_option( 'woocommerce_product_lookup_table_is_generating' ) ) {
			// Indexed lookup candidates are verified against product objects; fall back to source metadata when lookup data is incomplete.
			$lookup_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT posts.ID FROM %i AS lookup INNER JOIN %i AS posts ON posts.ID = lookup.product_id WHERE lookup.sku = %s AND posts.post_type IN ('product', 'product_variation') AND posts.post_status NOT IN ('trash', 'auto-draft') ORDER BY posts.ID LIMIT 2",
					$lookup_table,
					$wpdb->posts,
					$sku
				)
			);
			if ( $lookup_ids ) {
				$verified_ids = array();
				foreach ( $lookup_ids as $lookup_id ) {
					$product = wc_get_product( $lookup_id );
					if ( $product && $sku === $product->get_sku() ) {
						$verified_ids[] = (int) $lookup_id;
					}
				}
				if ( count( $verified_ids ) === count( $lookup_ids ) ) {
					return $verified_ids;
				}
			}
		}

		// Bounded validation must detect duplicate current SKUs.
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = '_sku' AND m.meta_value = %s AND p.post_type IN ('product', 'product_variation') AND p.post_status NOT IN ('trash', 'auto-draft') LIMIT 2", $sku ) );
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
