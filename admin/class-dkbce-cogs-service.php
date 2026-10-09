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
	const PREVIEW_LIMIT         = 50;
	const PREVIEW_PAGE_SIZE     = 20;
	const PREVIEW_PAGE_SIZES    = array( 10, 20, 50, 100 );
	const MAX_SELECTED_PRODUCTS = 500;
	const MAX_AMOUNT            = '999999999999';

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
		foreach ( array( 'search', 'category', 'type', 'stock_status', 'brand', 'price_min', 'price_max', 'cogs_min', 'cogs_max' ) as $key ) {
			if ( isset( $raw[ $key ] ) && ! is_scalar( $raw[ $key ] ) ) {
				return new WP_Error( 'invalid_filters', __( 'The product filters are invalid.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		$filters = array(
			'search'       => isset( $raw['search'] ) ? trim( sanitize_text_field( wp_unslash( $raw['search'] ) ) ) : '',
			'category'     => isset( $raw['category'] ) ? absint( wp_unslash( $raw['category'] ) ) : 0,
			'type'         => isset( $raw['type'] ) ? sanitize_key( wp_unslash( $raw['type'] ) ) : 'any',
			'stock_status' => isset( $raw['stock_status'] ) ? sanitize_key( wp_unslash( $raw['stock_status'] ) ) : 'any',
			'brand'        => isset( $raw['brand'] ) ? absint( wp_unslash( $raw['brand'] ) ) : 0,
			'price_min'    => isset( $raw['price_min'] ) ? $this->validate_optional_amount( $raw['price_min'] ) : '',
			'price_max'    => isset( $raw['price_max'] ) ? $this->validate_optional_amount( $raw['price_max'] ) : '',
			'cogs_min'     => isset( $raw['cogs_min'] ) ? $this->validate_optional_amount( $raw['cogs_min'] ) : '',
			'cogs_max'     => isset( $raw['cogs_max'] ) ? $this->validate_optional_amount( $raw['cogs_max'] ) : '',
		);

		if ( strlen( $filters['search'] ) > 100 ) {
			return new WP_Error( 'invalid_search', __( 'Search must be 100 characters or fewer.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		if ( 'any' !== $filters['type'] ) {
			$types = $this->get_product_types();
			if ( ! isset( $types[ $filters['type'] ] ) ) {
				return new WP_Error( 'invalid_type', __( 'Choose a supported product type.', 'bulk-cogs-editor-for-woocommerce' ) );
			}
		}

		$stock_statuses = array( 'any', 'instock', 'outofstock', 'onbackorder' );
		if ( ! in_array( $filters['stock_status'], $stock_statuses, true ) ) {
			return new WP_Error( 'invalid_stock_status', __( 'Choose a supported stock status.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		if ( $filters['category'] && ! term_exists( $filters['category'], 'product_cat' ) ) {
			return new WP_Error( 'invalid_category', __( 'The selected product category no longer exists.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		$brand_taxonomy = $this->get_brand_taxonomy();
		if ( $filters['brand'] && ( ! $brand_taxonomy || ! term_exists( $filters['brand'], $brand_taxonomy->name ) ) ) {
			return new WP_Error( 'invalid_brand', __( 'The selected brand is unavailable.', 'bulk-cogs-editor-for-woocommerce' ) );
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
	 * @param mixed $action Raw action key.
	 * @param mixed $raw_value Raw amount.
	 * @return array|WP_Error
	 */
	public function validate_action( $action, $raw_value ) {
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

		$value = $this->validate_optional_amount( $raw_value );
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
	 * Parse a non-negative decimal string using store precision.
	 *
	 * @param mixed $raw Raw value.
	 * @return string|WP_Error
	 */
	private function validate_optional_amount( $raw ) {
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

		$precision = wc_get_price_decimals();
		$parts     = explode( '.', $value );
		if ( isset( $parts[1] ) && strlen( $parts[1] ) > $precision ) {
			return new WP_Error( 'invalid_precision', 'invalid' );
		}

		return wc_format_decimal( $value, $precision, false );
	}

	/**
	 * Return product types selected by normalized filters.
	 *
	 * @param array $filters Normalized filters.
	 * @return array
	 */
	public function selected_types( $filters ) {
		if ( 'any' !== $filters['type'] ) {
			return array( $filters['type'] );
		}

		return array_keys( $this->get_product_types() );
	}

	/**
	 * Get one bounded page of product IDs for one product type.
	 *
	 * @param array  $filters Normalized filters.
	 * @param string $type Product type.
	 * @param int    $page One-based page.
	 * @return array
	 */
	public function query_ids( $filters, $type, $page ) {
		$args = array(
			'type'    => $type,
			'limit'   => self::QUERY_BATCH_SIZE,
			'page'    => absint( $page ),
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'ids',
		);

		if ( 'any' !== $filters['stock_status'] ) {
			$args['stock_status'] = $filters['stock_status'];
		}
		if ( $filters['category'] && 'variation' !== $type ) {
			$args['product_category_id'] = $filters['category'];
		}

		$ids = wc_get_products( $args );
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Calculate a new COGS value. A null result means clear the COGS value.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $operation Validated action.
	 * @return array|WP_Error
	 */
	public function calculate( $product, $operation ) {
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

		$new_value = wc_format_decimal( (string) round( $new_value, wc_get_price_decimals(), PHP_ROUND_HALF_UP ), wc_get_price_decimals(), false );
		if ( strlen( strtok( $new_value, '.' ) ) > strlen( self::MAX_AMOUNT ) ) {
			return new WP_Error( 'amount_too_large', __( 'The calculated value exceeds the supported monetary limit.', 'bulk-cogs-editor-for-woocommerce' ) );
		}

		return array(
			'current' => $current,
			'new'     => (float) $new_value,
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
	 * Find matching IDs and count without retaining the full catalog in memory.
	 *
	 * @param array $filters Normalized filters.
	 *
	 * @return array
	 */
	public function scan_matches( $filters ) {
		$args = array(
			'type'     => $this->selected_types( $filters ),
			'return'   => 'ids',
			'limit'    => -1,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'paginate' => false,
		);

		if ( ! empty( $filters['search'] ) ) {
			$data_store      = WC_Data_Store::load( 'product' );
			$search_ids      = $data_store->search_products( $filters['search'], '', true, true ); // Name + SKU, includes variation parents.
			$args['include'] = $search_ids ? $search_ids : array( 0 ); // array(0) forces an empty result.
		}

		if ( 'any' !== $filters['stock_status'] ) {
			$args['stock_status'] = $filters['stock_status'];
		}
		if ( $filters['category'] && 'variation' !== $filters['type'] ) {
			$args['product_category_id'] = $filters['category'];
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

		$ids         = wc_get_products( $args );
		$total_count = is_array( $ids ) ? count( $ids ) : 0;

		if ( $filters['count'] ?? false ) {
			return $total_count;
		}

		$page_no   = empty( $filters['page_no'] ) ? 0 : absint( $filters['page_no'] );
		$page_size = $filters['page_size'] ?? self::PREVIEW_PAGE_SIZE;
		$page_no   = max( 1, $page_no );
		$page_size = in_array( absint( $page_size ), self::PREVIEW_PAGE_SIZES, true ) ? absint( $page_size ) : self::PREVIEW_PAGE_SIZE;

		$args['limit'] = $page_size;
		$args['page']  = $page_no;

		$product_ids = wc_get_products( $args );

		if ( $filters['get_ids'] ?? false ) {
			return array(
				'product_ids' => $product_ids,
				'count'       => $total_count,
			);
		}

		$rows      = array();
		$operation = $filters['operation'] ?? array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! ( $product instanceof WC_Product ) ) {
				continue;
			}
			$rows[] = $this->preview_row( $product, $operation );
			unset( $product );
			$this->free_memory();
		}

		return array(
			'count'       => $total_count,
			'rows'        => $rows,
			'page'        => $page_no,
			'page_size'   => $page_size,
			'total_pages' => max( 1, (int) ceil( $total_count / $page_size ) ),
		);
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
