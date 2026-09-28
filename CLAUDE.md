# Project Memory — Bulk COGS Editor for WooCommerce
<!-- Claude CLI persistent context — keep this file accurate and current -->
<!-- lwdt: 202609282135 -->

## Identity
- **Plugin:** Bulk COGS Editor for WooCommerce `bulk-cogs-editor-for-woocommerce`
- **Version:** `1.0.0` (update this on every release)
- **Stack:** PHP 7.4+, WordPress 6.5+, WooCommerce 7.0+ (HPOS compatible)
- **Repo root:** `./`
- **Main file:** `./bulk-cogs-editor-for-woocommerce.php`

## Coding Rules (non-negotiable)
1. All PHP must pass `phpcs --standard=phpcs.xml` with **0 errors**.
2. Run `phpcbf --standard=phpcs.xml` before presenting any PHP code.
3. Prefixes: `dkbce_`, `DKBCE_` — use consistently for functions, classes, constants, hooks, and options.
4. Text domain: `bulk-cogs-editor-for-woocommerce` exclusively.
5. PHP 7.4 minimum — no PHP 8.0+ syntax without a compatibility shim.
6. Yoda conditions (`'value' === $var`), tabs (width 4), spaces inside `( )`.
7. Always `wp_unslash()` + sanitize `$_POST`/`$_GET` (or `wc_clean()`). Always `esc_*()` on output.
8. `$wpdb->prepare()` for every query with dynamic data.
9. Verify nonce (`wp_verify_nonce` or `check_ajax_referer`) before processing any form or AJAX request.
10. Check capabilities (`current_user_can( 'manage_woocommerce' )`).
11. No `eval`, `goto`, `@` suppression, short open tags, or debug output (`var_dump`, `print_r`) in committed code.

## Key Paths
| Path | Purpose |
|---|---|
| `admin/` | Admin classes, hooks, UI rendering, and AJAX controllers |
| `languages/` | POT localization catalog and translation files |
| `bulk-cogs-editor-for-woocommerce.php` | Main entry point, bootstrap, constants, and HPOS declaration |
| `tests/` | PHPUnit unit and integration tests |
| `.agents/` | Agent rules, memory, features registry, and plans |

## Active Hooks (summary)
- `plugins_loaded` -> `dkbce_load_plugin_files()`
- `before_woocommerce_init` -> `dkbce_declare_hpos_compatibility()`
- `admin_menu` -> `DKBCE_Admin_Functions::register_submenu_page()`
- See `.agents/features.md` for full registry.

## Current Update Cycle
- **Plan:** `.agents/plans/plan.md`
- **Target version:** `1.1.0`
- **Status:** Planning

## Decisions & Context
<!-- Append decisions here so future sessions have context -->
- 2026-04-17: Memory initialised via Claude CLI update-initiation prompt.
- 2026-09-28: Reconciled memory from template to actual `bulk-cogs-editor-for-woocommerce` specifications; established features registry and update plan.

## Do NOT
- Do not modify files under `vendor/`, `node_modules/`, `build/`.
- Do not commit debug output (`var_dump`, `print_r`, `error_log` with secrets).
- Do not introduce PHP 8.0+ syntax without polyfill.
- Do not rename existing public hooks without a deprecation notice.
- Do not use text domains other than `bulk-cogs-editor-for-woocommerce`.
