=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Donate link: https://dineshinaublog.wordpress.com/
Tags: woocommerce COGS, bulk cogs edit, cost of goods, product cogs management, product cogs editor
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Filter WooCommerce products, preview COGS changes, and apply bulk cost updates safely from the Products admin screen.

== Description ==

The editor uses WooCommerce's product COGS API. Cost of Goods Sold must be supported by the installed WooCommerce version and enabled in WooCommerce settings. If unavailable, the editor is disabled and explains what is needed.

== Features ==

* Filter products by title or SKU, category, product type, stock status, detected brand taxonomy, current price, and current COGS. Filters are combined to narrow the matching products.
* Select matching products, choose an action, preview the proposed changes, then confirm before applying. Previewing never changes product data.
* Set exact COGS; increase or decrease by a percentage or fixed amount; or clear COGS.
* Review up to 50 preview rows, including product images, edit links, current and calculated COGS, and the change.
* Process updates in Action Scheduler batches. View progress and success, skipped, and failed counts; cancel cooperatively while preserving completed updates.
* Use WooCommerce CRUD APIs for simple, variable, grouped, external, custom product types, and variations. Variations are treated as separate records, and variable parents are not changed when only variations are selected.
* Use the current WooCommerce product price for price filters and the supported COGS API for COGS filters. Values use store decimal precision with half-up rounding; decreases stop at zero.
* Relative actions skip products whose COGS is unset. WooCommerce's product COGS API converts a value of zero to empty; clearing uses the API's null value.
* Show brand filtering only when a public brand taxonomy is registered for products. Product matching uses bounded ID batches to limit memory use.

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

* Update costs after a supplier price change: filter affected products by category or brand, preview a percentage or fixed increase, then apply it to the matching products.
* Correct costs for a specific group: find products by title or SKU, select the products to change, preview exact COGS values or clear them, then confirm the update.

== Screenshots ==

= Developer Resources =

Bulk COGS Editor for WooCommerce is open-source software and is made to be extended. Developers can find sources at our public ([github repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce)) here.

== Changelog ==

= 1.0.0 (2026-10-08) =
* Initial release.
