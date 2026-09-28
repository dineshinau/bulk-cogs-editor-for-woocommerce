# Project Memory — WK Marketplace Plugin
<!-- Claude CLI persistent context — keep this file accurate and current -->
<!-- lwdt: 202604170000 -->

## Identity
- **Plugin:** WK Marketplace (`wk-marketplace`)
- **Version:** `(update after analysis)` ← update on every release
- **Stack:** PHP 7.4+, WordPress 6.7+, WooCommerce (latest compatible)
- **Repo root:** `./`
- **Main file:** `./wk-marketplace.php` (or check root for `Plugin Name:` header)

## Coding Rules (non-negotiable)
1. All PHP must pass `./vendor/bin/phpcs --standard=phpcs.xml` with **0 errors**.
2. Run `./vendor/bin/phpcbf --standard=phpcs.xml` before presenting any PHP code.
3. **Prefixes:** `wk_`, `wk_marketplace`, `wkpu_`, `wk_caching` — use consistently for all globals.
4. **Text domains:** `wk-marketplace`, `wkpu_updates`, `wk_caching` — no other domains.
5. **PHP 7.4 minimum** — no PHP 8.0+ syntax (`str_contains`, `match`, named args, enums) without compat shim.
6. Yoda conditions (`'value' === $var`), tabs (width 4), spaces inside `( )`.
7. Always `wp_unslash()` + sanitize `$_POST`/`$_GET`. Always `esc_*()` before echoing.
8. `$wpdb->prepare()` for **every** query containing dynamic data.
9. Verify nonce (`wp_verify_nonce`) before processing any form or AJAX input.
10. No `eval`, `goto`, `@` suppression, short open tags (`<?=`), or debug output in committed code.
11. No `var_dump()`, `print_r()`, `die()` — use `wp_die()` and proper logging.
12. DocBlocks on all functions, classes, and hooks (`@param`, `@return`, `@since`).

## PHPCS Excluded Paths (do not check these)
- `tests/`, `assets/`, `node_modules/`, `vendor/`, `build/`
- `modules/wk-plugin-updates/`, `wk_caching/`, `playwright-report/`

## Key Paths
| Path | Purpose |
|---|---|
| `includes/` | Core plugin classes |
| `admin/` | WP-Admin screens and settings |
| `public/` | Front-end / storefront logic |
| `templates/` | Overridable template files |
| `modules/` | Bundled sub-plugins (e.g. wk-plugin-updates) |
| `tests/` | PHPUnit unit tests |
| `assets/` | JS / CSS (excluded from PHPCS) |
| `build/` | Compiled assets (excluded from PHPCS) |
| `.agent/` | Project memory, features registry, plans |

## Active Hooks (summary)
<!-- Keep this updated — full registry in .agent/features.md -->
- See `.agent/features.md` for the complete hook, REST, and AJAX registry.

## Current Update Cycle
- **Plan:** `.agent/plans/plan.md`
- **Target version:** `(update after analysis)`
- **Status:** Planning

## Capabilities
Custom capabilities used in this project:
- `wk_marketplace_seller`
- `manage_woocommerce`
- `delete_published_products`

## Decisions & Context
<!-- Append timestamped decisions here so future sessions retain context -->
- 2026-04-17: Memory initialised via Claude CLI update-initiation prompt.

## Do NOT
- Do not modify files under `vendor/`, `node_modules/`, `build/`.
- Do not commit debug output (`var_dump`, `print_r`, `error_log` with secrets).
- Do not introduce PHP 8.0+ syntax without a compatibility shim.
- Do not rename or remove existing public hooks/filters without a `_doing_it_wrong()` deprecation notice.
- Do not use text domains other than those listed in Rule 4 above.
- Do not write raw SQL — always use `$wpdb->prepare()`.
