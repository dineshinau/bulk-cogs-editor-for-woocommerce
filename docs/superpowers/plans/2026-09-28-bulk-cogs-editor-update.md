# Bulk COGS Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an interactive Bulk Cost of Goods Sold (COGS) editor for WooCommerce products and variations with pagination, search, category filtering, AJAX batch saving, security validations, and test coverage.

**Architecture:** Extend `DKBCE_Admin_Functions` to query WooCommerce products and variations, render an admin table with inline editable COGS fields and quick bulk actions, and handle asynchronous batch persistence via a dedicated AJAX controller in `DKBCE_Admin_Hooks`. Support common WooCommerce COGS metadata keys (`_cogs_cost`, `_wc_cog_cost`, `_cost_of_goods`) with seamless fallbacks.

**Tech Stack:** PHP 7.4+, WordPress 6.5+, WooCommerce 7.0+, HPOS compatible, Vanilla JavaScript, PHPUnit, PHP_CodeSniffer.

**Spec:** `.agents/woocommerce-update-init.md`

## Global Constraints

- PHP compatibility target: 7.4+ (`declare(strict_types=1);` retained, no PHP 8.0+ syntax without polyfill).
- WordPress minimum version: 6.5.
- WooCommerce minimum version: 7.0 (HPOS compatibility declared and preserved).
- Prefixes: `dkbce_` for functions/variables/hooks/options, `DKBCE_` for constants and classes.
- Text domain: `bulk-cogs-editor-for-woocommerce` exclusively.
- Coding Standards: WordPress-Core, WordPress-Extra, WordPress-Docs, WooCommerce-Core, WooCommerce via `phpcs.xml` (0 errors required).
- Security: Capability check `manage_woocommerce`, nonce verification on all AJAX/form submissions, sanitization with `sanitize_text_field()` / `wc_clean()` / `absint()`, output escaping with `esc_html()`, `esc_attr()`, `esc_url()`.

## Review Focus

1. Product with no existing COGS meta value: should display an empty input or 0.00 without PHP notices, and properly save when a value is entered.
2. Variable product variations: should allow editing COGS individually for each child variation or in bulk across all variations of a parent product.
3. Negative or invalid numeric cost inputs: should be rejected or sanitized to positive float values without breaking database integrity.
4. Non-privileged or expired-nonce AJAX request: must immediately return HTTP 403 / JSON error without mutating any product metadata.
5. Large batch update (50+ products): must process efficiently within memory/execution limits and report per-item success/failure statuses.

---

### Task 1: Environment & Requirements Harmonization

**Files:**
- Modify: `bulk-cogs-editor-for-woocommerce.php:9`
- Modify: `readme.txt:7`
- Test: `tests/test-plugin-headers.php`

**Interfaces:**
- Consumes: Existing plugin bootstrap headers
- Produces: Consistent PHP version specification (PHP 7.4 minimum across all manifests)

- [ ] **Step 1: Write test for header consistency**

```php
public function test_plugin_headers_consistency(): void {
	$plugin_data = get_plugin_data( DKBCE_PLUGIN_FILE );
	$this->assertEquals( '7.4', $plugin_data['RequiresPHP'] );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/test-plugin-headers.php`
Expected: FAIL with assertion mismatch (plugin header has 8.0 while readme specifies 7.4)

- [ ] **Step 3: Update plugin header and readme.txt**

Update `bulk-cogs-editor-for-woocommerce.php` to set `Requires PHP: 7.4` to align with `readme.txt` and `phpcs.xml`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/test-plugin-headers.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add bulk-cogs-editor-for-woocommerce.php readme.txt
git commit -m "fix: harmonize minimum PHP requirement to 7.4 across headers"
```

---

### Task 2: COGS Metadata Helper Methods

**Files:**
- Modify: `admin/class-dkbce-admin-functions.php`
- Test: `tests/test-cogs-helpers.php`

**Interfaces:**
- Consumes: WooCommerce `WC_Product` objects and product IDs
- Produces:
  - `DKBCE_Admin_Functions::get_cogs_meta_key(): string`
  - `DKBCE_Admin_Functions::get_product_cogs( int $product_id ): float`
  - `DKBCE_Admin_Functions::update_product_cogs( int $product_id, float $cost ): bool`

- [ ] **Step 1: Write unit tests for COGS helper functions**

```php
public function test_get_and_update_product_cogs(): void {
	$product = WC_Helper_Product::create_simple_product();
	$updated = DKBCE_Admin_Functions::get_instance()->update_product_cogs( $product->get_id(), 15.50 );
	$this->assertTrue( $updated );
	$this->assertSame( 15.50, DKBCE_Admin_Functions::get_instance()->get_product_cogs( $product->get_id() ) );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/test-cogs-helpers.php`
Expected: FAIL with `Call to undefined method DKBCE_Admin_Functions::get_product_cogs()`

- [ ] **Step 3: Implement COGS helper methods in `admin/class-dkbce-admin-functions.php`**

Implement `get_cogs_meta_key()`, `get_product_cogs()`, and `update_product_cogs()` supporting `_cogs_cost` with fallbacks to `_wc_cog_cost` and `_cost_of_goods`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/test-cogs-helpers.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add admin/class-dkbce-admin-functions.php tests/test-cogs-helpers.php
git commit -m "feat: add COGS meta helper methods for retrieving and updating product cost"
```

---

### Task 3: Admin Product Query & Filtering

**Files:**
- Modify: `admin/class-dkbce-admin-functions.php`
- Test: `tests/test-product-query.php`

**Interfaces:**
- Consumes: Query arguments array `array $args` (search keyword, category term ID, per_page, paged)
- Produces: `DKBCE_Admin_Functions::get_products_for_cogs_editor( array $args ): array` returning products array and pagination metadata

- [ ] **Step 1: Write failing test for product query pagination and filtering**

```php
public function test_get_products_with_pagination(): void {
	$result = DKBCE_Admin_Functions::get_instance()->get_products_for_cogs_editor( array( 'per_page' => 10, 'paged' => 1 ) );
	$this->assertArrayHasKey( 'products', $result );
	$this->assertArrayHasKey( 'total', $result );
	$this->assertArrayHasKey( 'pages', $result );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/test-product-query.php`
Expected: FAIL with `Call to undefined method DKBCE_Admin_Functions::get_products_for_cogs_editor()`

- [ ] **Step 3: Implement `get_products_for_cogs_editor()`**

Use `wc_get_products()` with sanitized pagination, category filtering (`product_cat`), and search parameters (`s`).

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/test-product-query.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add admin/class-dkbce-admin-functions.php tests/test-product-query.php
git commit -m "feat: implement product query handler with pagination and filtering"
```

---

### Task 4: Admin Table UI & Bulk Toolbar

**Files:**
- Modify: `admin/class-dkbce-admin-functions.php`
- Modify: `admin/class-dkbce-admin-hooks.php`
- Test: `tests/test-admin-ui-render.php`

**Interfaces:**
- Consumes: `DKBCE_Admin_Functions::get_products_for_cogs_editor()`
- Produces: HTML table output in `render_bulk_cogs_editor_page()` with editable COGS input fields, regular price, sale price, profit margin indicator, and bulk action toolbar

- [ ] **Step 1: Write test for admin page HTML rendering**

```php
public function test_render_bulk_cogs_editor_page_output(): void {
	ob_start();
	DKBCE_Admin_Functions::get_instance()->render_bulk_cogs_editor_page();
	$output = ob_get_clean();
	$this->assertStringContainsString( 'dkbce-table', $output );
	$this->assertStringContainsString( 'dkbce-bulk-actions', $output );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/test-admin-ui-render.php`
Expected: FAIL with output missing expected table elements

- [ ] **Step 3: Implement UI rendering in `render_bulk_cogs_editor_page()`**

Render the filter bar (search, category dropdown), bulk action toolbar (set fixed value, increase/decrease by %, bulk save button), and responsive table with product image, SKU, title, price, COGS input, and margin calculation.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/test-admin-ui-render.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add admin/class-dkbce-admin-functions.php tests/test-admin-ui-render.php
git commit -m "feat: render bulk cogs editor table UI and bulk action controls"
```

---

### Task 5: AJAX Batch Saving & Security Hardening

**Files:**
- Modify: `admin/class-dkbce-admin-hooks.php`
- Modify: `admin/class-dkbce-admin-functions.php`
- Test: `tests/test-ajax-save.php`

**Interfaces:**
- Consumes: AJAX POST request with action `dkbce_save_bulk_cogs`, `security` nonce, and `cogs_data` array mapping product ID to cost
- Produces: `wp_send_json_success()` on successful update or `wp_send_json_error()` with descriptive message

- [ ] **Step 1: Write security and execution tests for AJAX save**

```php
public function test_ajax_rejects_missing_nonce(): void {
	$this->expectException( WPAjaxDieException::class );
	DKBCE_Admin_Functions::get_instance()->handle_ajax_save_bulk_cogs();
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/test-ajax-save.php`
Expected: FAIL with `Call to undefined method DKBCE_Admin_Functions::handle_ajax_save_bulk_cogs()`

- [ ] **Step 3: Implement AJAX endpoint with security validation**

Hook `wp_ajax_dkbce_save_bulk_cogs` in `DKBCE_Admin_Hooks`. In `handle_ajax_save_bulk_cogs()`, enforce `check_ajax_referer( 'dkbce_admin_nonce', 'security' )`, check `current_user_can( 'manage_woocommerce' )`, iterate sanitized product IDs and numeric cost values, update product meta, and return JSON summary.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/test-ajax-save.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add admin/class-dkbce-admin-hooks.php admin/class-dkbce-admin-functions.php tests/test-ajax-save.php
git commit -m "feat: add secure AJAX batch save handler with capability and nonce checks"
```

---

### Task 6: Standards Verification & Documentation

**Files:**
- Modify: `readme.txt`
- Modify: `languages/bulk-cogs-editor-for-woocommerce.pot`
- Test: PHPCS verification command

**Interfaces:**
- Consumes: All updated PHP and manifest files
- Produces: Clean PHPCS report with 0 errors/warnings and updated POT catalog

- [ ] **Step 1: Run PHPCS standard verification**

Run: `phpcs --standard=phpcs.xml .`
Expected: 0 errors, 0 warnings

- [ ] **Step 2: Update changelog in `readme.txt`**

Document v1.1.0 changes: new bulk COGS editor table, AJAX batch save, category filtering, search, and HPOS support.

- [ ] **Step 3: Regenerate POT translation catalog**

Run: `npm run make-pot` or `wp i18n make-pot . languages/bulk-cogs-editor-for-woocommerce.pot`
Expected: Updated POT file containing all new translatable strings.

- [ ] **Step 4: Commit**

```bash
git add readme.txt languages/bulk-cogs-editor-for-woocommerce.pot
git commit -m "docs: update readme changelog and regenerate POT translation catalog for v1.1.0"
```
