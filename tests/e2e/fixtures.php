<?php
/**
 * Create and remove disposable WordPress fixtures for Playwright tests.
 *
 * Run through WP-CLI only. This file creates data only when explicitly asked.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create a WooCommerce product and record its ID for cleanup.
 *
 * @param WC_Product $product Product instance.
 * @param array      $values Product fields.
 * @param array      $state Fixture state, modified by reference.
 * @return int
 */
function dkbce_e2e_create_product( $product, $values, &$state ) {
	$product->set_name( $values['name'] );
	$product->set_status( 'publish' );
	$product->set_sku( $values['sku'] );
	$product->set_regular_price( (string) $values['price'] );
	$product->set_stock_status( $values['stock'] );
	$product->set_category_ids( array( $state['category_id'] ) );
	$product->set_cogs_value( $values['cogs'] );

	if ( isset( $values['product_url'] ) ) {
		$product->set_product_url( $values['product_url'] );
		$product->set_button_text( 'View fixture' );
	}

	$product_id = $product->save();
	if ( ! $product_id ) {
		WP_CLI::error( 'Could not create a disposable WooCommerce product.' );
	}

	$state['product_ids'][]              = absint( $product_id );
	$state['products'][ $values['key'] ] = absint( $product_id );

	return absint( $product_id );
}

/**
 * Remove only products, users, terms, and operation data recorded by setup.
 *
 * @param array $state Fixture metadata.
 * @return void
 */
function dkbce_e2e_cleanup( $state ) {
	$operation_ids       = array( $state['operation_id'] );
	$latest_operation_id = get_user_meta( $state['manager_id'], 'dkbce_latest_operation', true );
	if ( $latest_operation_id ) {
		$operation_ids[] = sanitize_text_field( $latest_operation_id );
	}
	$admin_latest_operation_id = get_user_meta( $state['admin_id'], 'dkbce_latest_operation', true );
	if ( $admin_latest_operation_id && $admin_latest_operation_id !== $state['admin_latest_before'] ) {
		$operation_ids[] = sanitize_text_field( $admin_latest_operation_id );
	}

	$store = new DKBCE_Operation_Store();
	foreach ( array_unique( $operation_ids ) as $operation_id ) {
		$operation = $store->get( $operation_id );
		if ( $operation && (int) $state['manager_id'] === (int) $operation['user_id'] ) {
			$store->delete_chunks( $operation_id, $operation['snapshot_chunks'] );
			delete_option( 'dkbce_operation_' . sanitize_key( $operation_id ) );
		}
	}

	foreach ( array_reverse( $state['product_ids'] ) as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$product->delete( true );
		}
		delete_option( 'dkbce_product_lock_' . absint( $product_id ) );
	}

	foreach ( array( $state['category_id'], $state['brand_id'] ) as $term_id ) {
		if ( $term_id ) {
			$term_taxonomy = $term_id === $state['brand_id'] ? $state['brand_taxonomy'] : 'product_cat';
			wp_delete_term( $term_id, $term_taxonomy );
		}
	}

	foreach ( array( $state['manager_id'], $state['customer_id'] ) as $user_id ) {
		if ( $user_id ) {
			wp_delete_user( $user_id );
		}
	}
	if ( $state['admin_latest_before'] ) {
		update_user_meta( $state['admin_id'], 'dkbce_latest_operation', $state['admin_latest_before'] );
	} else {
		delete_user_meta( $state['admin_id'], 'dkbce_latest_operation' );
	}
}

$state_path = getenv( 'DKBCE_E2E_STATE_FILE' );
$e2e_mode   = getenv( 'DKBCE_E2E_MODE' );
if ( ! $state_path ) {
	WP_CLI::error( 'DKBCE_E2E_STATE_FILE must point to a temporary state file.' );
}

if ( 'cleanup' === $e2e_mode ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$filesystem = $GLOBALS['wp_filesystem'];
	$state      = $filesystem->exists( $state_path ) ? json_decode( $filesystem->get_contents( $state_path ), true ) : false;
	if ( is_array( $state ) ) {
		dkbce_e2e_cleanup( $state );
	}
	WP_CLI::success( 'Temporary Playwright fixtures removed.' );
	return;
}

if ( 'nonce' === $e2e_mode ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$filesystem = $GLOBALS['wp_filesystem'];
	$state      = $filesystem->exists( $state_path ) ? json_decode( $filesystem->get_contents( $state_path ), true ) : false;
	if ( ! is_array( $state ) || empty( $state['customer_id'] ) ) {
		WP_CLI::error( 'Temporary customer fixture is unavailable.' );
	}
	$_COOKIE[ LOGGED_IN_COOKIE ] = sanitize_text_field( wp_unslash( getenv( 'DKBCE_E2E_CUSTOMER_COOKIE' ) ) );
	wp_set_current_user( $state['customer_id'] );
	WP_CLI::line( wp_create_nonce( 'dkbce_bulk_cogs' ) );
	return;
}

if ( 'create' !== $e2e_mode ) {
	WP_CLI::error( 'Set DKBCE_E2E_MODE to create or cleanup.' );
}

if ( ! method_exists( 'WC_Product', 'set_cogs_value' ) || ! \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' ) ) {
	WP_CLI::error( 'WooCommerce Cost of Goods Sold must be available and enabled.' );
}

$token = sanitize_key( getenv( 'DKBCE_E2E_TOKEN' ) );
if ( ! $token ) {
	WP_CLI::error( 'DKBCE_E2E_TOKEN must be set.' );
}

$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
	WP_CLI::error( 'The configured local admin test user was not found.' );
}

$state          = array(
	'token'               => $token,
	'manager_id'          => 0,
	'admin_id'            => absint( $admin_user->ID ),
	'admin_latest_before' => get_user_meta( $admin_user->ID, 'dkbce_latest_operation', true ),
	'manager_login'       => 'dkbce_e2e_manager_' . $token,
	'manager_password'    => wp_generate_password( 40, true, true ),
	'customer_id'         => 0,
	'customer_login'      => 'dkbce_e2e_customer_' . $token,
	'customer_password'   => wp_generate_password( 40, true, true ),
	'customer_nonce'      => '',
	'category_id'         => 0,
	'brand_id'            => 0,
	'brand_taxonomy'      => '',
	'product_ids'         => array(),
	'products'            => array(),
	'operation_id'        => wp_generate_uuid4(),
);
$setup_complete = false;
register_shutdown_function(
	function () use ( &$state, &$setup_complete ) {
		if ( ! $setup_complete ) {
			dkbce_e2e_cleanup( $state );
		}
	}
);

$manager_id = wp_insert_user(
	array(
		'user_login' => $state['manager_login'],
		'user_pass'  => $state['manager_password'],
		'user_email' => 'dkbce-manager-' . $token . '@example.test',
		'role'       => 'shop_manager',
	)
);
if ( is_wp_error( $manager_id ) ) {
	WP_CLI::error( 'Could not create a temporary shop manager.' );
}
$state['manager_id'] = absint( $manager_id );

$customer_id = wp_insert_user(
	array(
		'user_login' => $state['customer_login'],
		'user_pass'  => $state['customer_password'],
		'user_email' => 'dkbce-customer-' . $token . '@example.test',
		'role'       => 'customer',
	)
);
if ( is_wp_error( $customer_id ) ) {
	WP_CLI::error( 'Could not create a temporary customer.' );
}
$state['customer_id']    = absint( $customer_id );
$state['customer_nonce'] = '';

$category = wp_insert_term( 'DKBCE E2E ' . $token, 'product_cat' );
if ( is_wp_error( $category ) ) {
	WP_CLI::error( 'Could not create a temporary product category.' );
}
$state['category_id'] = absint( $category['term_id'] );

$brand_taxonomy = '';
foreach ( get_object_taxonomies( 'product', 'objects' ) as $product_taxonomy ) {
	$label = isset( $product_taxonomy->labels->singular_name ) ? $product_taxonomy->labels->singular_name : '';
	if ( ! empty( $product_taxonomy->public ) && preg_match( '/brand/i', $product_taxonomy->name . ' ' . $label ) ) {
		$brand_taxonomy = $product_taxonomy->name;
		break;
	}
}
if ( $brand_taxonomy ) {
	$brand = wp_insert_term( 'DKBCE E2E Brand ' . $token, $brand_taxonomy );
	if ( ! is_wp_error( $brand ) ) {
		$state['brand_taxonomy'] = $brand_taxonomy;
		$state['brand_id']       = absint( $brand['term_id'] );
	}
}

$prefix    = 'DKBCE-E2E-' . strtoupper( $token );
$simple_id = dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'simple',
		'name'  => 'DKBCE E2E Simple ' . $token,
		'sku'   => $prefix . '-SIMPLE',
		'price' => '20.00',
		'cogs'  => 10,
		'stock' => 'instock',
	),
	$state
);
if ( $state['brand_id'] ) {
	wp_set_object_terms( $simple_id, array( $state['brand_id'] ), $state['brand_taxonomy'] );
}

dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'zero',
		'name'  => 'DKBCE E2E Zero ' . $token,
		'sku'   => $prefix . '-ZERO',
		'price' => '25.00',
		'cogs'  => 0,
		'stock' => 'instock',
	),
	$state
);
dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'empty',
		'name'  => 'DKBCE E2E Empty ' . $token,
		'sku'   => $prefix . '-EMPTY',
		'price' => '30.00',
		'cogs'  => null,
		'stock' => 'instock',
	),
	$state
);
dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'decimal',
		'name'  => 'DKBCE E2E Decimal ' . $token,
		'sku'   => $prefix . '-DECIMAL',
		'price' => '13.00',
		'cogs'  => 9.99,
		'stock' => 'instock',
	),
	$state
);
dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'outofstock',
		'name'  => 'DKBCE E2E Out of Stock ' . $token,
		'sku'   => $prefix . '-OUT',
		'price' => '18.00',
		'cogs'  => 15,
		'stock' => 'outofstock',
	),
	$state
);
dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'onbackorder',
		'name'  => 'DKBCE E2E On Backorder ' . $token,
		'sku'   => $prefix . '-BACKORDER',
		'price' => '22.00',
		'cogs'  => 16,
		'stock' => 'onbackorder',
	),
	$state
);

$variable_id = dkbce_e2e_create_product(
	new WC_Product_Variable(),
	array(
		'key'   => 'variable',
		'name'  => 'DKBCE E2E Variable ' . $token,
		'sku'   => $prefix . '-VARIABLE',
		'price' => '40.00',
		'cogs'  => null,
		'stock' => 'instock',
	),
	$state
);
$variation   = new WC_Product_Variation();
$variation->set_parent_id( $variable_id );
dkbce_e2e_create_product(
	$variation,
	array(
		'key'   => 'variation',
		'name'  => 'DKBCE E2E Variation ' . $token,
		'sku'   => $prefix . '-VARIATION',
		'price' => '42.00',
		'cogs'  => 7.5,
		'stock' => 'instock',
	),
	$state
);

$grouped    = new WC_Product_Grouped();
$grouped_id = dkbce_e2e_create_product(
	$grouped,
	array(
		'key'   => 'grouped',
		'name'  => 'DKBCE E2E Grouped ' . $token,
		'sku'   => $prefix . '-GROUPED',
		'price' => '1.00',
		'cogs'  => null,
		'stock' => 'instock',
	),
	$state
);
$grouped->set_children( array( $simple_id ) );
$grouped->save();

$external = new WC_Product_External();
dkbce_e2e_create_product(
	$external,
	array(
		'key'         => 'external',
		'name'        => 'DKBCE E2E External ' . $token,
		'sku'         => $prefix . '-EXTERNAL',
		'price'       => '9.00',
		'cogs'        => null,
		'stock'       => 'instock',
		'product_url' => home_url(),
	),
	$state
);

dkbce_e2e_create_product(
	new WC_Product_Simple(),
	array(
		'key'   => 'xss',
		'name'  => '<img src=x onerror=alert(1)> ' . $token,
		'sku'   => $prefix . '-XSS',
		'price' => '5.00',
		'cogs'  => 1,
		'stock' => 'instock',
	),
	$state
);

for ( $index = 1; $index <= 55; $index++ ) {
	dkbce_e2e_create_product(
		new WC_Product_Simple(),
		array(
			'key'   => 'batch_' . $index,
			'name'  => 'DKBCE E2E Batch ' . $token . ' ' . $index,
			'sku'   => $prefix . '-BATCH-' . $index,
			'price' => '2.00',
			'cogs'  => 1,
			'stock' => 'instock',
		),
		$state
	);
}

$store = new DKBCE_Operation_Store();
$store->save(
	array(
		'operation_id'     => $state['operation_id'],
		'user_id'          => $state['manager_id'],
		'created_at'       => time(),
		'started_at'       => 0,
		'completed_at'     => 0,
		'total'            => 1,
		'processed'        => 0,
		'succeeded'        => 0,
		'skipped'          => 0,
		'failed'           => 0,
		'action'           => 'set',
		'value'            => '1.00',
		'operation'        => array(
			'action' => 'set',
			'value'  => '1.00',
		),
		'filters'          => array(),
		'status'           => 'pending',
		'stage'            => 'processing',
		'type_index'       => 0,
		'page'             => 1,
		'snapshot_chunks'  => 1,
		'processed_chunks' => 0,
		'cancel_requested' => false,
		'errors'           => array(),
		'error_summary'    => '',
	)
);

$setup_complete = true;
WP_CLI::line( wp_json_encode( $state ) );
