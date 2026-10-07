=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Donate link: https://dineshinaublog.wordpress.com/
Tags: bulk edit, WooCommerce COGS, cost of goods sold
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Bulk COGS Editor adds an admin-only workflow under Products → Bulk COGS Editor. Filter products, preview the proposed cost changes, then apply one of six bulk COGS actions.

== Description ==

The editor uses WooCommerce's product COGS API. Cost of Goods Sold must be supported by the installed WooCommerce version and enabled in WooCommerce settings. If unavailable, the editor is disabled and explains what is needed.

== Features ==

= Features =

* Filter by product name or SKU, category, product type, stock status, detected brand taxonomy, current price, and current COGS.
* Preview up to 50 matching products before any changes are made.
* Set exact COGS, increase or decrease by percentage or fixed amount, or clear COGS.
* Process changes in Action Scheduler batches with progress, skipped and failed product counts, and cooperative cancellation.
* Update products and variations as separate records through WooCommerce CRUD APIs.

== Product types and calculations ==

Simple, variable, grouped, external, custom WooCommerce product types, and variations are included. A variable product and each of its variations are separate records; the variable parent is not changed when only variations are selected.

Price filters use the current WooCommerce product price. COGS filters use the supported product COGS getter. Currency values are rounded to the store's configured decimal precision using half-up rounding. Decreases are clamped at zero. Relative actions skip products with an unset COGS value; a stored zero is treated as a real value. Clear removes the value using WooCommerce's null COGS API.

Brand filtering appears only when a public brand taxonomy is registered for products. Product matching is performed in bounded ID batches; preview output is limited to 50 rows.

= Connect with me =

* **Website** - https://dineshinaublog.wordpress.com/
* **Facebook** - https://www.facebook.com/dineshinau/
* **X** - https://x.com/dineshinau/
* **LinkedIn** - https://www.linkedin.com/in/dineshinau/

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bulk-cogs-editor-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.

2. Activate the plugin through the \'Plugins\' screen in WordPress

== Frequently Asked Questions ==

= Where can give feedback =
If you have any suggestion regarding the improvement of its feature, please leave a [Review](https://dineshinaublog.wordpress.com/bulk-cogs-editor-for-woocommerce/).

== Use Cases ==

Here are just a few use cases of Bulk COGS Editor for WooCommerce


== Screenshots ==


= Developer Resources =

Bulk COGS Editor for WooCommerce is open-source software and is made to be extended. Developers can find sources at our public ([github repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce)) here.

== Changelog ==

= 1.0.0 (2026-10-07) =
* Added: Filter, preview, and apply a bulk COGS operation from Products → Bulk COGS Editor.
* Added: Six COGS actions, bounded Action Scheduler processing, progress reporting, and cancellation.
* Added: Product type, category, stock, price, COGS, and conditional brand filters.
* Added: WooCommerce COGS API and feature availability checks.

= 1.0.0 (2026-09-28) =
* Initial release.
* Added: Admin-only product filters, COGS preview, and six bulk update actions.
* Added: Action Scheduler batches, progress reporting, error summaries, and cancellation.
* Added: Conditional brand taxonomy detection and separate variation handling.
