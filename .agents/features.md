# Plugin feature registry

## Project metadata

| Field | Value |
|---|---|
| Plugin | Bulk COGS Editor for WooCommerce |
| Version | 1.0.0 |
| Minimum WordPress | 6.0 (plugin header) |
| Minimum PHP | 8.0 |
| Minimum WooCommerce | 7.0 (COGS editor requires the installed COGS API and enabled feature) |
| Text domain | `bulk-cogs-editor-for-woocommerce` |
| Prefixes | `dkbce_`, `DKBCE_` |

## Features

| Feature | Location | Behavior |
|---|---|---|
| Plugin bootstrap and HPOS declaration | `bulk-cogs-editor-for-woocommerce.php` | Loads admin services on `plugins_loaded`; declares HPOS compatibility. |
| Products → Bulk COGS Editor | `admin/class-dkbce-admin-functions.php` | Admin-only page and per-screen assets; requires `edit_others_products`. |
| Filter and preview | `admin/class-dkbce-cogs-service.php` | ID-batched queries and shared product matching/calculation for count, preview, selected scope, and apply. |
| COGS operations | `admin/class-dkbce-cogs-service.php` | Exact, percent increase/decrease, fixed increase/decrease, and clear through WooCommerce product COGS methods. |
| Background processing | `admin/class-dkbce-bulk-processor.php` | Action Scheduler snapshots product IDs, then processes 50-product chunks; retries are idempotent per operation. |
| Operation state | `admin/class-dkbce-operation-store.php` | Non-autoloaded WordPress options for independent operation state and temporary ID chunks. |
| AJAX interface | `admin/class-dkbce-admin-functions.php` | Get products, preview, apply, progress, and cancellation; each request checks nonce and capability. |

## AJAX actions

| Action | State changing | Nonce | Access |
|---|---:|---|---|
| `dkbce_get_products` | No | `dkbce_bulk_cogs` | `edit_others_products` |
| `dkbce_preview` | No | `dkbce_bulk_cogs` | `edit_others_products` |
| `dkbce_apply` | Yes | `dkbce_bulk_cogs` | `edit_others_products` |
| `dkbce_progress` | No | `dkbce_bulk_cogs` | Owner or `manage_woocommerce` |
| `dkbce_cancel` | Yes | `dkbce_bulk_cogs` | Owner or `manage_woocommerce` |

## Product behavior

- Simple, variable, grouped, external, registered custom types, and variations can be matched.
- Variations are separate product records; filtering a variable parent does not update its variations.
- Search checks product title and SKU. Price ranges use current product price. Brand filtering appears only when a public product brand taxonomy is registered.
- Unset COGS is `null`; a stored zero is preserved as zero. Relative actions skip unset COGS. Clear uses `set_cogs_value( null )`.
- Currency results use store precision and half-up rounding. Decreases clamp at zero.
- The editor is unavailable if WooCommerce does not expose its COGS product methods or the COGS feature is disabled.

## Persistence and testing

- Operation state options use the `dkbce_operation_` prefix. Snapshot chunks use `dkbce_ids_`; both are non-autoloaded and chunks are removed when an operation ends.
- A product's hidden `_dkbce_last_bulk_cogs_operation` metadata value makes retries idempotent when WooCommerce has already saved the COGS change.
- No PHPUnit/Jest/Playwright suite is configured in the repository. Available checks are defined in `phpcs.xml` and `package.json`.
