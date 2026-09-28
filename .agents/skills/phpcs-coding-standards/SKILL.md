---
name: phpcs-coding-standards
description: >
  Enforces the project's WordPress + WooCommerce coding standards (phpcs.xml) when writing
  or reviewing PHP code. Use this skill whenever writing, generating, refactoring, or reviewing
  ANY PHP code in this project — even if the user doesn't mention standards. Also trigger when
  the user mentions "coding standards", "PHPCS", "sniff", "WordPress style", "WooCommerce code",
  "sanitization", "escaping", "nonce", "i18n", "text domain", or "prefix". Always consult before
  producing PHP output in an agentic coding session.
---

# PHP Coding Standards — WordPress + WooCommerce Plugin

Derived directly from `phpcs.xml` (ruleset: `WordPress + WooCommerce Coding Standards`, lwdt: 202602062000).

**Active bases:** `WordPress-Core`, `WordPress-Extra`, `WordPress-Docs`, `WooCommerce-Core`, `WooCommerce`, `PHPCompatibility`
**PHP target:** 7.4+ (no PHP 8-only syntax without a compat check)
**Min WP version:** 6.7

---

## Excluded Paths — Never apply standards checking to:
- `tests/`, `assets/`, `node_modules/`, `vendor/`, `build/`
- `modules/wk-plugin-updates/`, `wk_caching/`, `playwright-report/`

---

## Rule 1 — Indentation

Use **tabs**, not spaces. Tab width = 4.

```php
// ✅ Correct
function wk_my_function() {
	if ( $condition ) {
		do_something();
	}
}

// ❌ Wrong — spaces
function wk_my_function() {
    if ( $condition ) {
```

---

## Rule 2 — Naming Conventions & Prefixes

All globals (functions, classes, hooks, options, constants) **must be prefixed**.
Allowed prefixes from this project: `wk_`, `wk_marketplace`, `wkpu_`, `wk_caching`.

```php
// ✅ Functions
function wk_marketplace_get_seller_data( $user_id ) {}

// ✅ Classes
class WK_Marketplace_Seller {}

// ✅ Constants
define( 'WK_MARKETPLACE_VERSION', '1.0.0' );

// ✅ Hooks
add_action( 'wk_marketplace_before_seller_register', $callback );

// ❌ Missing prefix
function get_seller_data() {}
class Seller {}
```

Use `snake_case` for functions/variables, `PascalCase` for classes.

---

## Rule 3 — File Naming

Filenames must follow WordPress conventions:
- Class files: `class-{descriptor}.php` → e.g. `class-wk-marketplace-seller.php`
- All lowercase, hyphens as separators (not underscores)

---

## Rule 4 — Internationalization (i18n)

All user-facing strings must be wrapped. **Allowed text domains:**
- `wk-marketplace`
- `wkpu_updates`
- `wk_caching`

```php
// ✅ Correct
__( 'Add to cart', 'wk-marketplace' );
esc_html__( 'Seller dashboard', 'wk-marketplace' );
_n( '%s item', '%s items', $count, 'wk-marketplace' );

// ❌ Wrong domain
__( 'Hello', 'my-plugin' );

// ❌ No translation
echo 'Add to cart';
```

---

## Rule 5 — Security (DO NOT skip or weaken)

### 5a. Output Escaping — always escape before echoing
```php
// ✅ Correct
echo esc_html( $title );
echo esc_attr( $value );
echo esc_url( $link );
echo wp_kses_post( $content );
echo absint( $count );

// ❌ Never
echo $title;
echo $user_input;
```

### 5b. Input Sanitization — always sanitize `$_POST`, `$_GET`, `$_REQUEST`
`wc_clean()` is a registered custom sanitizer and is allowed.

```php
// ✅ Correct
$name  = sanitize_text_field( wp_unslash( $_POST['name'] ) );
$email = sanitize_email( wp_unslash( $_POST['email'] ) );
$price = wc_clean( wp_unslash( $_POST['price'] ) ); // wc_clean allowed
$id    = absint( $_GET['product_id'] );

// ❌ Wrong
$name = $_POST['name'];
```

### 5c. Nonce Verification — verify before processing any form/AJAX input
```php
// ✅ Correct
if ( ! isset( $_POST['wk_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wk_nonce'] ), 'wk_marketplace_action' ) ) {
	wp_die( esc_html__( 'Security check failed.', 'wk-marketplace' ) );
}
```

### 5d. No Silenced Errors
```php
// ❌ Never use @ error suppression
$result = @file_get_contents( $path );

// ✅ Use proper error handling
if ( file_exists( $path ) ) {
	$result = file_get_contents( $path );
}
```

---

## Rule 6 — Database / SQL

Always use prepared statements via `$wpdb`.

```php
// ✅ Correct
global $wpdb;
$results = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
		$user_id,
		'publish'
	)
);

// ❌ Never
$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE ID = " . $id );
```

Placeholders: `%d` integers, `%s` strings, `%f` floats.

---

## Rule 7 — Capabilities

Use proper capability checks. Custom capabilities allowed in this project:
- `wk_marketplace_seller`
- `manage_woocommerce`
- `delete_published_products`

```php
// ✅ Correct
if ( ! current_user_can( 'wk_marketplace_seller' ) ) {
	wp_die( esc_html__( 'Access denied.', 'wk-marketplace' ) );
}
```

---

## Rule 8 — Forbidden Constructs (errors, not warnings)

```php
// ❌ eval() — ERROR
eval( $code );

// ❌ goto — ERROR
goto my_label;

// ❌ Short open tags
<?= $var ?>
<? echo $var; ?>

// ✅ Always use full open tag
<?php echo esc_html( $var ); ?>

// ❌ Alternative PHP tags (ASP style)
<% $var %>

// ❌ Silenced errors — see Rule 5d
```

No `var_dump()`, `print_r()`, `die()` (use `wp_die()`), or debug output in committed code.

---

## Rule 9 — WordPress Spacing Conventions

Spaces inside parentheses for control structures and function calls:

```php
// ✅ Correct
if ( $condition ) {}
foreach ( $items as $item ) {}
$result = my_function( $arg1, $arg2 );

// ❌ Wrong
if ($condition) {}
my_function($arg);
```

Yoda conditions:

```php
// ✅ Yoda
if ( 'publish' === $status ) {}
if ( true === $flag ) {}

// ❌ Non-Yoda
if ( $status === 'publish' ) {}
```

---

## Rule 10 — Documentation (WordPress-Docs)

All functions, classes, and hooks need DocBlocks:

```php
/**
 * Retrieves seller data for a given user.
 *
 * @param int $user_id The WordPress user ID.
 * @return array|false Seller data array or false if not found.
 */
function wk_marketplace_get_seller( int $user_id ) {}

/**
 * Fires before a seller is registered.
 *
 * @param int $user_id The user ID being registered as seller.
 */
do_action( 'wk_marketplace_before_seller_register', $user_id );
```

Hook comment is **not** required (excluded: `WooCommerce.Commenting.CommentHooks.MissingHookComment`).

---

## Rule 11 — PHPCompatibility (PHP 7.4+)

Do not use PHP 8.0+ only features:
- ✅ Safe in 7.4: typed properties, arrow functions, `??=`, spread in arrays
- ❌ Avoid without polyfill: `str_contains()`, `str_starts_with()`, `str_ends_with()`, `match` (8.0), named args (8.0), enums (8.1)

```php
// ✅ 7.4-safe
if ( false !== strpos( $haystack, $needle ) ) {}

// ❌ PHP 8.0+ only
if ( str_contains( $haystack, $needle ) ) {}
```

---

## Verification Step (agentic bash sessions)

After generating PHP files, always run:

```bash
./vendor/bin/phpcs --standard=phpcs.xml <path/to/file.php>
# Auto-fix what's fixable:
./vendor/bin/phpcbf --standard=phpcs.xml <path/to/file.php>
./vendor/bin/phpcs --standard=phpcs.xml <path/to/file.php>
```

**Do not present code to the user until `phpcs` reports 0 errors.**

---

## Quick Reference Card

| Concern | Rule |
|---|---|
| Indentation | Tabs (width 4) |
| Naming | `snake_case` functions, `PascalCase` classes, prefixed globals (`wk_`) |
| File names | `class-wk-descriptor.php` (lowercase, hyphens) |
| Text domains | `wk-marketplace`, `wkpu_updates`, `wk_caching` |
| Escaping | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` |
| Sanitizing | `sanitize_text_field(wp_unslash(...))`, `wc_clean()` allowed |
| SQL | `$wpdb->prepare()` always |
| Nonces | Verify before any `$_POST`/`$_GET` processing |
| Forbidden | `eval`, `goto`, short tags, `@` suppression, debug output |
| PHP compat | 7.4+ minimum |
| Spacing | Spaces inside `( )` for control structures and calls |
| Conditions | Yoda style (`'value' === $var`) |
