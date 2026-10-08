# WooCommerce Global Objects and Functions

This guide covers the correct way to access and interact with core WooCommerce objects.

## Core Objects

### Products

Always use `wc_get_product()` to retrieve product objects.

```php
// From ID
$product = wc_get_product( $product_id );

// From global $post (only in loop)
global $post;
$product = wc_get_product( $post->ID );
```

### Orders

Always use `wc_get_order()`.

```php
$order = wc_get_order( $order_id );
```

### Cart

Use the `WC()` global to access the cart session.

```php
// Get cart total
$total = WC()->cart->get_total();

// Add to cart
WC()->cart->add_to_cart( $product_id, $quantity );
```

## The `WC()` Global Helper

Prefer the `WC()` function over global variables like `$woocommerce`.

```php
// ✅ GOOD
WC()->session->get( 'key' );
WC()->mailer();

// ❌ AVOID
global $woocommerce;
$woocommerce->session->get( 'key' );
```

## Correct Method Usage (Modern WC)

### Getting Data

Always use getters. Never access properties directly.

```php
// ✅ GOOD
$sku = $product->get_sku();
$price = $product->get_price();

// ❌ WRONG
$sku = $product->sku;
```

### Setting Data

Always use setters and then call `save()`.

```php
$product->set_sku( 'NEW-SKU-123' );
$product->set_regular_price( '19.99' );
$product->save();
```

## Common Helper Functions

- `wc_get_template()`: For loading templates with overrides support.
- `wc_price()`: For formatting prices with currency symbols.
- `wc_get_endpoint_url()`: For linking to My Account or Checkout endpoints.
- `wc_add_notice()`: For showing feedback messages to users.
- `wc_get_cart_url()`: For getting the cart page link.

```php
wc_add_notice( __( 'Item added to your cart.', 'woocommerce' ), 'success' );
```
