# Security Patterns for WooCommerce

This guide covers security best practices for WooCommerce development, focusing on data sanitization, validation, and escaping.

## Data Sanitization (Inputs)

Always sanitize user input before using it in logic or saving to the database.

| Type | Function |
|------|----------|
| Text | `sanitize_text_field()` |
| Email | `sanitize_email()` |
| URL | `esc_url_raw()` |
| Key/Slug | `sanitize_key()` |
| Array | `array_map( 'sanitize_text_field', $array )` |

```php
$product_name = sanitize_text_field( $_POST['product_name'] ?? '' );
```

## Data Validation

Verify data before processing it.

```php
if ( ! is_numeric( $order_id ) ) {
    return;
}

if ( ! is_email( $user_email ) ) {
    throw new Exception( 'Invalid email' );
}
```

## Data Escaping (Outputs)

Always escape data right before echoing it. This is the last line of defense against XSS.

| Type | Function |
|------|----------|
| HTML Content | `esc_html()` |
| HTML Attributes | `esc_attr()` |
| URLs | `esc_url()` |
| JavaScript | `esc_js()` |
| textarea | `esc_textarea()` |

```php
echo '<div class="name">' . esc_html( $name ) . '</div>';
echo '<input value="' . esc_attr( $value ) . '">';
```

## Nonces

Always use nonces to protect against CSRF attacks in forms and AJAX requests.

### Form Verification

```php
// In the form
wp_nonce_field( 'my_plugin_action', 'my_plugin_nonce' );

// In the processing logic
if ( ! isset( $_POST['my_plugin_nonce'] ) || ! wp_verify_nonce( $_POST['my_plugin_nonce'], 'my_plugin_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### URL/AJAX Verification

```php
// Creating link
$url = wp_nonce_url( admin_url( 'admin-post.php?action=my_action' ), 'my_action_nonce' );

// Verification
check_admin_referer( 'my_action_nonce' );
```

## Database Safety

Use `$wpdb->prepare()` for custom SQL queries to prevent SQL injection.

```php
global $wpdb;
$id = 123;
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}posts WHERE ID = %d",
    $id
) );
```

## Capabilities and Permissions

Always check if the current user has the authority to perform an action.

```php
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    return;
}

if ( ! $order->get_customer_id() === get_current_user_id() ) {
    return;
}
```

## WooCommerce Specific Security

- Use `WC_Validation` class for common e-commerce validation (postcodes, phone numbers).
- Use `WC_Data` getters and setters which perform some internal checks.
- Be careful with `WC_Checkout::process_checkout` and related hooks.
