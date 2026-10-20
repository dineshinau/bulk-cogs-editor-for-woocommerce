<?php
/**
 * COGS filtering and calculation services.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides supported COGS access, product matching, validation, and calculations.
 */
class DKBCE_COGS_Service {
	const QUERY_BATCH_SIZE      = 50;
	const MAX_QUERY_BATCH_SIZE  = 1000;
	const PREVIEW_LIMIT         = 50;
	const PREVIEW_PAGE_SIZE     = 20;
	const PREVIEW_PAGE_SIZES    = array( 10, 20, 50, 100 );
	const MAX_SELECTED_PRODUCTS = 500;
	const MAX_AMOUNT            = '999999999999';

	/**
	 * Search results reused during the current request.
	 *
	 * @var array
	 */
	private $search_cache = array();

	/**
	 * Whether WooCommerce COGS is available and enabled.
	 *
	 * @return bool
	 */
	public function is_cogs_available() {
		return class_exists( 'WC_Product' )
			&& method_exists( 'WC_Product', 'get_cogs_value' )
			&& method_exists( 'WC_Product', 'set_cogs_value' )
			&& class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' )
			&& method_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil', 'feature_is_enabled' )
			&& \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
	}

	/**
	 * Get available product types, including variations as separate records.
	 *
	 * @return array
	 */
	public function get_product_types() {
		$types              = function_exists( 'wc_get_product_types' ) ? wc_get_product_types() : array();
		$types['variation'] = __( 'Variation', 'bulk-cogs-editor-for-woocommerce' );

		return $types;
	}

	/**
	 * Detect a public brand taxonomy attached to products.
	 *
	 * @return WP_Taxonomy|false
	 */
	public function get_brand_taxonomy() {
		$taxonomies = get_object_taxonomies( 'product', 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			$label = isset( $taxonomy->labels->singular_name ) ? $taxonomy->labels->singular_name : '';
			if ( ! empty( $taxonomy->public ) && preg_match( '/brand/i', $taxonomy->name . ' ' . $label ) ) {
				return $taxonomy;
			}
		}

		return false;
	}

	/**
	 * Validate and normalize filter values.
	 *
	 * @param mixed $raw Raw request data.
	 * @return array|WP_Error
	 */
	public function validate_filters( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'invalid_filters', __( 'The product filters are invalid.', 'bulk-cogs-editor-for-woocommerce' ) );
		}
		foreach ( array( 'search', 'price_min', 'price_max', 'cogs_min', 'cogs_max' ) as $key ) {
			if ( isset( $raw[ $key ] ) && ! is_scalar( $raw[ $key ] ) ) {
				return new WP_Error( 'invalid_filters', __( 'The product filters are invalid.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		foreach ( array( 'category', 'type', 'stock_status', 'brand' ) as $key ) {
			$values = isset( $raw[ $key ] ) ? (array) $raw[ $key ] : array();
			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) ) {
					return new WP_Error( 'invalid_filters', __( 'The product filters are invalid.', 'bulk-cogs-editor-for-woocommerce' ) );
				}
			}
			$raw[ $key ] = $values;
		}

		$filters = array(
			'search'       => isset( $raw['search'] ) ? trim( sanitize_text_field( wp_unslash( $raw['search'] ) ) ) : '',
			'category'     => array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $raw['category'] ) ) ) ) ),
			'type'         => array_values( array_unique( array_diff( array_map( 'sanitize_key', wp_unslash( $raw['type'] ) ), array( 'any', '' ) ) ) ),
			'stock_status' => array_values( array_unique( array_diff( array_map( 'sanitize_key', wp_unslash( $raw['stock_status'] ) ), array( 'any', '' ) ) ) ),
			'brand'        => array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $raw['brand'] ) ) ) ) ),
			'price_min'    => isset( $raw['price_min'] ) ? $this->validate_optional_amount( $raw['price_min'] ) : '',
			'price_max'    => isset( $raw['price_max'] ) ? $this->validate_optional_amount( $raw['price_max'] ) : '',
			'cogs_min'     => isset( $raw['cogs_min'] ) ? $this->validate_optional_amount( $raw['cogs_min'] ) : '',
			'cogs_max'     => isset( $raw['cogs_max'] ) ? $this->validate_optional_amount( $raw['cogs_max'] ) : '',
		);

		if ( strlen( $filters['search'] ) > 100 ) {
			return new WP_Error( 'invalid_search', __( 'Search must be 100 characters or fewer.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		$types = $this->get_product_types();
		foreach ( $filters['type'] as $type ) {
			if ( ! isset( $types[ $type ] ) ) {
				return new WP_Error( 'invalid_type', __( 'Choose supported product types.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		if ( array_diff( $filters['stock_status'], array( 'instock', 'outofstock', 'onbackorder' ) ) ) {
			return new WP_Error( 'invalid_stock_status', __( 'Choose supported stock statuses.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		foreach ( $filters['category'] as $category ) {
			if ( ! term_exists( $category, 'product_cat' ) ) {
				return new WP_Error( 'invalid_category', __( 'A selected product category no longer exists.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		$brand_taxonomy = $this->get_brand_taxonomy();
		foreach ( $filters['brand'] as $brand ) {
			if ( ! $brand_taxonomy || ! term_exists( $brand, $brand_taxonomy->name ) ) {
				return new WP_Error( 'invalid_brand', __( 'A selected brand is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		foreach ( array( 'price', 'cogs' ) as $range ) {
			$min = $filters[ $range . '_min' ];
			$max = $filters[ $range . '_max' ];
			if ( is_wp_error( $min ) || is_wp_error( $max ) ) {
				return new WP_Error( 'invalid_range', __( 'Enter valid non-negative values for the price and COGS ranges.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			if ( '' !== $min && '' !== $max && (float) $min > (float) $max ) {
				return new WP_Error( 'invalid_range_order', __( 'Each minimum must be less than or equal to its maximum.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		return $filters;
	}

	/**
	 * Validate an operation type and value.
	 *
	 * @param mixed    $action Raw action key.
	 * @param mixed    $raw_value Raw amount.
	 * @param int|null $precision Optional decimal precision override.
	 * @return array|WP_Error
	 */
	public function validate_action( $action, $raw_value, $precision = null ) {
		$action  = is_string( $action ) ? sanitize_key( wp_unslash( $action ) ) : '';
		$actions = array( 'set', 'increase_percent', 'decrease_percent', 'increase_fixed', 'decrease_fixed', 'clear' );
		if ( ! in_array( $action, $actions, true ) ) {
			return new WP_Error( 'invalid_action', __( 'Choose one of the available COGS actions.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		if ( 'clear' === $action ) {
			return array(
				'action' => $action,
				'value'  => '',
			);
		}

		$value = $this->validate_optional_amount( $raw_value, $precision );
		if ( is_wp_error( $value ) || '' === $value ) {
			return new WP_Error( 'invalid_amount', __( 'Enter a valid non-negative number.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		if ( false !== strpos( $action, 'percent' ) && (float) $value > 10000 ) {
			return new WP_Error( 'percentage_too_large', __( 'The percentage cannot exceed 10,000%.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		return array(
			'action' => $action,
			'value'  => $value,
		);
	}

	/**
	 * Parse a non-negative decimal string using store precision by default.
	 *
	 * @param mixed    $raw Raw value.
	 * @param int|null $precision Optional decimal precision override.
	 * @return string|WP_Error
	 */
	private function validate_optional_amount( $raw, $precision = null ) {
		if ( '' === $raw || null === $raw ) {
			return '';
		}
		if ( ! is_scalar( $raw ) ) {
			return new WP_Error( 'invalid_amount', 'invalid' );
		}

		$value = trim( (string) wp_unslash( $raw ) );
		if ( ! preg_match( '/\A\d{1,12}(?:\.\d{1,6})?\z/', $value ) ) {
			return new WP_Error( 'invalid_amount', 'invalid' );
		}

		$decimal_precision = null === $precision ? wc_get_price_decimals() : min( 6, absint( $precision ) );
		$parts             = explode( '.', $value );
		if ( isset( $parts[1] ) && strlen( $parts[1] ) > $decimal_precision ) {
			return new WP_Error( 'invalid_precision', 'invalid' );
		}

		return wc_format_decimal( $value, $decimal_precision, false );
	}

	/**
	 * Return product types selected by normalized filters.
	 *
	 * @param array $filters Normalized filters.
	 * @return array
	 */
	public function selected_types( $filters ) {
		if ( ! empty( $filters['type'] ) && 'any' !== $filters['type'] ) {
			return (array) $filters['type'];
		}

		return array_keys( $this->get_product_types() );
	}

	/**
	 * Calculate a new COGS value. A null result means clear the COGS value.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $operation Validated action.
	 * @return array|WP_Error
	 */
	public function calculate( $product, $operation ) {
		if ( 'csv_import' === $operation['action'] ) {
			$id = $product->get_id();
			if ( ! empty( $operation['source_id'] ) ) {
				$imports             = new DKBCE_Import_Store();
				$value               = $imports->value( $operation['source_id'], $id );
				$operation['values'] = null === $value ? array() : array( $id => $value );
			}
			if ( ! isset( $operation['values'][ $id ] ) ) {
				return new WP_Error( 'csv_target', __( 'This product is not in the validated CSV.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
			return $this->calculate(
				$product,
				array(
					'action'             => 'set',
					'value'              => $operation['values'][ $id ],
					'preserve_precision' => true,
				)
			);
		}
		if ( 'csv_export' === $operation['action'] ) {
			return array(
				'current' => $product->get_cogs_value(),
				'new'     => $product->get_cogs_value(),
				'status'  => 'success',
			);
		}
		$current = $product->get_cogs_value();
		$action  = $operation['action'];

		if ( 'clear' === $action ) {
			return array(
				'current' => $current,
				'new'     => null,
				'status'  => 'success',
			);
		}

		if ( 'set' !== $action && null === $current ) {
			return array(
				'current' => null,
				'new'     => null,
				'status'  => 'skipped',
				'reason'  => __( 'Current COGS is not set.', 'bulk-cogs-editor-for-woocommerce' ),
			);
		}

		$value = (float) $operation['value'];
		switch ( $action ) {
			case 'set':
				$new_value = $value;
				break;
			case 'increase_percent':
				$new_value = $current * ( 1 + $value / 100 );
				break;
			case 'decrease_percent':
				$new_value = max( 0, $current * ( 1 - $value / 100 ) );
				break;
			case 'increase_fixed':
				$new_value = $current + $value;
				break;
			case 'decrease_fixed':
				$new_value = max( 0, $current - $value );
				break;
			default:
				return new WP_Error( 'invalid_action', __( 'Choose a supported COGS action.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		$precision = ! empty( $operation['preserve_precision'] ) ? 6 : wc_get_price_decimals();
		$new_value = wc_format_decimal( (string) round( $new_value, $precision, PHP_ROUND_HALF_UP ), $precision, false );
		if ( strlen( strtok( $new_value, '.' ) ) > strlen( self::MAX_AMOUNT ) ) {
			return new WP_Error( 'amount_too_large', __( 'The calculated value exceeds the supported monetary limit.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		return array(
			'current' => $current,
			// WooCommerce normalizes zero costs to an unset COGS value.
			'new'     => 0.0 === (float) $new_value ? null : (float) $new_value,
			'status'  => 'success',
		);
	}

	/**
	 * Build a safe preview row for a product.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $operation Validated action.
	 * @return array|WP_Error
	 */
	public function preview_row( $product, $operation ) {
		$result = $this->calculate( $product, $operation );
		if ( is_wp_error( $result ) ) {
			$result = array(
				'current' => $product->get_cogs_value(),
				'new'     => null,
				'status'  => 'failed',
				'reason'  => $result->get_error_message(),
			);
		}

		$change = null;
		if ( 'success' === $result['status'] && null !== $result['new'] && null !== $result['current'] ) {
			$change = $result['new'] - $result['current'];
		}

		$types     = $this->get_product_types();
		$edit_url  = get_edit_post_link( $product->get_id(), 'raw' );
		$image_url = $product->get_image_id()
			? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' )
			: '';
		if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
			$image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
		}

		$change_percent   = null;
		$change_direction = '';
		if ( 'success' === $result['status'] && null !== $result['current'] ) {
			if ( null !== $result['new'] ) {
				$change_direction = $result['new'] > $result['current'] ? 'up' : ( $result['new'] < $result['current'] ? 'down' : '' );
				if ( 0 !== (float) $result['current'] ) {
					$change_percent = ( ( $result['new'] - $result['current'] ) / $result['current'] ) * 100;
				}
			} elseif ( $result['current'] > 0 ) {
				$change_direction = 'down';
			}
		}

		return array(
			'id'               => $product->get_id(),
			'edit_url'         => $edit_url ? esc_url_raw( $edit_url ) : '',
			'image_url'        => $image_url ? esc_url_raw( $image_url ) : '',
			'name'             => $product->get_name(),
			'sku'              => $product->get_sku(),
			'type'             => $product->get_type(),
			'type_label'       => isset( $types[ $product->get_type() ] ) ? $types[ $product->get_type() ] : $product->get_type(),
			'current'          => $result['current'],
			'new'              => $result['new'],
			'change'           => $change,
			'change_percent'   => $change_percent,
			'change_direction' => $change_direction,
			'status'           => $result['status'],
			'reason'           => isset( $result['reason'] ) ? $result['reason'] : '',
		);
	}

	/**
	 * Fingerprint the values and outcome presented for a reviewed product.
	 *
	 * @param array $result Calculation result or preview row.
	 * @return string
	 */
	public function preview_signature( $result ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					null === $result['current'] ? null : (float) $result['current'],
					null === $result['new'] ? null : (float) $result['new'],
					$result['status'],
				)
			)
		);
	}

	/**
	 * Resolve WooCommerce text, SKU and global identifier search matches once.
	 *
	 * @param string $search Search term.
	 * @return array
	 */
	public function search_product_ids( $search ) {
		if ( array_key_exists( $search, $this->search_cache ) ) {
			return $this->search_cache[ $search ];
		}
		$data_store                    = WC_Data_Store::load( 'product' );
		$this->search_cache[ $search ] = array_map( 'absint', $data_store->search_products( $search, '', true, true ) );
		return $this->search_cache[ $search ];
	}

	/**
	 * Find matching IDs and count without retaining the full catalog in memory.
	 *
	 * @param array $filters Normalized filters.
	 *
	 * @return array|int
	 */
	public function scan_matches( $filters ) {
		$args = $this->matching_args( $filters );

		$total_count = 0;
		if ( ! empty( $filters['count'] ) ) {
			$count_result = wc_get_products( $args );
			$total_count  = is_object( $count_result ) && isset( $count_result->total ) ? (int) $count_result->total : 0;
		}

		if ( $filters['count'] ?? false ) {
			if ( ! empty( $filters['count_breakdown'] ) ) {
				$variation_count = 0;
				if ( $total_count && in_array( 'variation', $args['type'], true ) ) {
					$variation_args             = $args;
					$variation_args['type']     = array( 'variation' );
					$variation_args['limit']    = 1;
					$variation_args['paginate'] = true;
					$variations                 = wc_get_products( $variation_args );
					$variation_count            = (int) $variations->total;
				}

				return array(
					'count'           => $total_count,
					'product_count'   => $total_count - $variation_count,
					'variation_count' => $variation_count,
				);
			}
			return $total_count;
		}

		$page_no   = empty( $filters['page_no'] ) ? 0 : absint( $filters['page_no'] );
		$page_size = $filters['page_size'] ?? self::PREVIEW_PAGE_SIZE;
		$page_no   = max( 1, $page_no );
		if ( ! empty( $filters['get_ids'] ) ) {
			$page_size = max( 1, min( self::MAX_QUERY_BATCH_SIZE, absint( $page_size ) ) );
		} else {
			$page_size = in_array( absint( $page_size ), self::PREVIEW_PAGE_SIZES, true ) ? absint( $page_size ) : self::PREVIEW_PAGE_SIZE;
		}

		$args['limit']    = $page_size;
		$args['page']     = $page_no;
		$args['paginate'] = empty( $filters['get_ids'] );
		if ( isset( $filters['after_id'] ) && ! empty( $filters['get_ids'] ) ) {
			$args['dkbce_after_id'] = absint( $filters['after_id'] );
			$args['page']           = 1;
		}

		$page_result = wc_get_products( $args );

		if ( $filters['get_ids'] ?? false ) {
			return $page_result;
		}
		$total_count = is_object( $page_result ) && isset( $page_result->total ) ? (int) $page_result->total : $total_count;
		$product_ids = is_object( $page_result ) && isset( $page_result->products ) ? $page_result->products : array();

		$rows      = array();
		$operation = $filters['operation'] ?? array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! ( $product instanceof WC_Product ) ) {
				continue;
			}
			$rows[] = $this->preview_row( $product, $operation );
			unset( $product );
		}
		$this->free_memory();

		return array(
			'count'       => $total_count,
			'rows'        => $rows,
			'page'        => $page_no,
			'page_size'   => $page_size,
			'total_pages' => max( 1, (int) ceil( $total_count / $page_size ) ),
		);
	}

	/**
	 * Build matching arguments, intersecting any earlier selection filters.
	 *
	 * @param array $filters Normalized filters from an authorized preview.
	 * @return array
	 */
	private function matching_args( $filters ) {
		$args = array(
			'type'     => $this->selected_types( $filters ),
			'return'   => 'ids',
			'limit'    => 1,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'paginate' => true,
		);

		// Exclusions are validated against the user's preview before snapshotting.
		if ( ! empty( $filters['selection_range'] ) ) {
			$args['dkbce_selection_range'] = array( $filters['selection_range']['min'], $filters['selection_range']['max'] );
		}
		if ( ! empty( $filters['excluded_ids'] ) ) {
			$args['exclude'] = array_map( 'absint', $filters['excluded_ids'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$search_ids      = $filters['search_ids'] ?? $this->search_product_ids( $filters['search'] );
			$args['include'] = $search_ids ? $search_ids : array( 0 ); // array(0) forces an empty result.
		}

		if ( ! empty( $filters['stock_status'] ) && 'any' !== $filters['stock_status'] ) {
			$args['stock_status'] = $filters['stock_status'];
		}
		if ( $filters['category'] ) {
			$args['dkbce_category_ids'] = $filters['category'];
		}

		if ( $filters['brand'] ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => $filters['brand'],
			);
		}

		// Price and COGS ranges.
		$meta_query = array();
		foreach ( array(
			'_price'            => array( $filters['price_min'], $filters['price_max'] ),
			'_cogs_total_value' => array( $filters['cogs_min'], $filters['cogs_max'] ),
		) as $key => $range ) {
			$clause = $this->range_clause( $key, $range[0], $range[1] );
			if ( $clause ) {
				$meta_query[] = $clause;
			}
		}

		if ( $meta_query ) {
			$args['dkbce_meta_query'] = $meta_query;
		}

		$groups = empty( $args['dkbce_category_ids'] ) ? array() : array( $args['dkbce_category_ids'] );
		foreach ( $filters['selection_filters'] ?? array() as $previous ) {
			$prior = $this->matching_args( $previous );
			$types = array_values( array_intersect( $args['type'], $prior['type'] ) );
			if ( ! $types ) {
				$args['include'] = array( 0 );
			} else {
				$args['type'] = $types;
			}
			if ( isset( $prior['include'] ) ) {
				$ids             = isset( $args['include'] ) ? array_intersect( $args['include'], $prior['include'] ) : $prior['include'];
				$args['include'] = $ids ? array_values( $ids ) : array( 0 );
			}
			if ( isset( $prior['stock_status'] ) ) {
				$stock = isset( $args['stock_status'] ) ? array_intersect( (array) $args['stock_status'], (array) $prior['stock_status'] ) : $prior['stock_status'];
				if ( ! $stock ) {
					$args['include'] = array( 0 );
				} else {
					$args['stock_status'] = array_values( (array) $stock );
				}
			}
			$groups = array_merge( $groups, $prior['dkbce_category_groups'] );
			foreach ( array( 'tax_query', 'dkbce_meta_query' ) as $key ) {
				$args[ $key ] = array_merge( $args[ $key ] ?? array(), $prior[ $key ] ?? array() );
			}
		}
		$args['dkbce_category_groups'] = $groups;
		unset( $args['dkbce_category_ids'] );
		return $args;
	}

	/**
	 * Apply the snapshot cursor and match product or parent categories.
	 *
	 * @param string   $where Existing query conditions.
	 * @param WP_Query $query Product query.
	 * @return string
	 */
	public function filter_category_where( $where, $query ) {
		global $wpdb;

		$after_id        = $query->get( 'dkbce_after_id' );
		$selection_range = $query->get( 'dkbce_selection_range' );
		if ( is_array( $selection_range ) && 2 === count( $selection_range ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", absint( $selection_range[0] ), absint( $selection_range[1] ) );
		}
		if ( is_numeric( $after_id ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", absint( $after_id ) );
		}

		$groups = $query->get( 'dkbce_category_groups' );
		if ( ! is_array( $groups ) ) {
			$category_ids = $query->get( 'dkbce_category_ids' );
			$groups       = $category_ids ? array( $category_ids ) : array();
		}
		foreach ( $groups as $category_ids ) {
			$tax_query = new WP_Tax_Query(
				array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array_map( 'absint', (array) $category_ids ),
						'include_children' => true,
					),
				)
			);
			$sql       = $tax_query->get_sql( 'dkbce_category_product', 'ID' );

			// EXISTS preserves one result per record even when several categories match.
			$where .= " AND EXISTS (SELECT 1 FROM {$wpdb->posts} AS dkbce_category_product {$sql['join']}
			WHERE dkbce_category_product.ID = CASE
				WHEN {$wpdb->posts}.post_type = 'product_variation' THEN {$wpdb->posts}.post_parent
				ELSE {$wpdb->posts}.ID END
			AND dkbce_category_product.post_type = 'product' {$sql['where']})";

		}
		return $where;
	}

	/**
	 * Free memory.
	 */
	private function free_memory() {
		global $wpdb;
		$wpdb->queries = array(); // Only grows if SAVEQUERIES is on.
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime(); // WP 6.0+, clears the non-persistent cache only.
		}
	}

	/**
	 * Build range clause for meta query.
	 *
	 * @param string $key Meta key.
	 * @param string $min Minimum value.
	 * @param string $max Maximum value.
	 * @return array|null
	 */
	private function range_clause( $key, $min, $max ) {
		if ( '' === $min && '' === $max ) {
			return null;
		}
		if ( '' !== $min && '' !== $max ) {
			return array(
				'key'     => $key,
				'value'   => array( (float) $min, (float) $max ),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(20,6)',
			);
		}
		return array(
			'key'     => $key,
			'value'   => (float) ( '' !== $min ? $min : $max ),
			'compare' => '' !== $min ? '>=' : '<=',
			'type'    => 'DECIMAL(20,6)',
		);
	}
}
