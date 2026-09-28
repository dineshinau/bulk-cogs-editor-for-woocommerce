# PHP i18n Patterns for WooCommerce

This guide covers internationalization (i18n) standards for WooCommerce backend PHP development.

## Core Functions

### Basic Translation

Always use the `woocommerce` text domain for WooCommerce-related strings.

```php
// Simple string
__( 'Save changes', 'woocommerce' );

// Echoed string
_e( 'Order details', 'woocommerce' );
```

### Contextual Translation

Use `_x` when a word has multiple meanings and needs context for translators.

```php
_x( 'Post', 'verb', 'woocommerce' );
_x( 'Post', 'noun', 'woocommerce' );
```

### Pluralization

```php
printf(
    _n( '%d item', '%d items', $count, 'woocommerce' ),
    $count
);
```

### Strings with Placeholders

Use `printf` or `sprintf` with `__()`. **Never** use variables directly inside translation functions.

```php
// ✅ CORRECT
printf(
    /* translators: %s: customer name */
    __( 'Hello %s', 'woocommerce' ),
    $customer_name
);

// ❌ WRONG
__( "Hello $customer_name", 'woocommerce' );
```

## Translator Comments

Always provide context for placeholders. The comment must be immediately above the line with the translation function.

```php
/* translators: 1: product name 2: category name */
$message = sprintf( __( '%1$s in %2$s', 'woocommerce' ), $product_name, $category_name );
```

## Brand Names

Brand names like "WooCommerce" or "WooPayments" should often be treated as constants or placeholders if they are used frequently, but in many cases, they are kept in the string if they shouldn't be translated.

```php
sprintf(
    /* translators: %s: product name */
    __( 'Buy %s on WooCommerce', 'woocommerce' ),
    $product_name
);
```

## Best Practices

1. **No HTML in strings**: Keep HTML outside of translatable strings when possible to avoid translation breakage.
2. **Full Sentences**: Don't break sentences into multiple translation calls. Translators need the full context.
3. **Escaping**: Always escape translated strings that are being output to the browser.
   ```php
   echo esc_html__( 'Text', 'woocommerce' );
   echo esc_attr__( 'Title', 'woocommerce' );
   ```
4. **Text Domain**: Ensure you use the correct text domain. If building a plugin, use your plugin's text domain.
