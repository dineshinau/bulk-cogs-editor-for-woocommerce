=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce COGS, bulk cogs edit, cost of goods, product cogs management, product cogs editor
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Filter WooCommerce products, preview COGS changes, and apply bulk cost updates safely from the Products admin screen.

== Description ==

The editor uses WooCommerce's product COGS API. Cost of Goods Sold must be supported by the installed WooCommerce version and enabled in WooCommerce settings. If unavailable, the editor is disabled and explains what is needed.

== Bulk COGS Editor for WooCommerce Features ==

* Filter products by title or SKU, category, product type, stock status, detected brand taxonomy, current price, and current COGS. Filters are combined to narrow the matching products.
* Select matching products, choose an action, preview the proposed changes, then confirm before applying. Previewing never changes product data. Changing the COGS action or its value refreshes the preview automatically.
* Set exact COGS; increase or decrease by a percentage or fixed amount; or clear COGS.
* Review matching products in a paginated table with product images, edit links, current and calculated COGS, and the change. Choose 10, 20, 50, or 100 products per page; the default is 20. Loading indicators appear while preview pages are fetched.
* Select or clear all products on the current preview page with the table's bulk-selection controls. Selections remain available across preview pages; selected-only updates support up to 500 products.
* Process updates in Action Scheduler batches. View progress, elapsed time, estimated remaining time, and success, skipped, and failed counts; the remaining time estimate appears after processing begins. Cancel cooperatively while preserving completed updates. When processing finishes, use the Refresh now link to refresh the page.
* Use WooCommerce CRUD APIs for simple, variable, grouped, external, custom product types, and variations. Variations are treated as separate records, and variable parents are not changed when only variations are selected.
* Use the current WooCommerce product price for price filters and the supported COGS API for COGS filters. Values use store decimal precision with half-up rounding; decreases stop at zero.
* Relative actions skip products whose COGS is unset. WooCommerce's product COGS API converts a value of zero to empty; clearing uses the API's null value.
* Show brand filtering only when a public brand taxonomy is registered for products. Product matching uses bounded ID batches to limit memory use.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bulk-cogs-editor-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.

2. Activate the plugin through the \'Plugins\' screen in WordPress

3. Navigate to Products > Bulk COGS Editor in the WordPress admin menu

4. Filter the products by title or SKU, category, product type, stock status, detected brand taxonomy, current price, and current COGS.

5. Select the products to change, choose an action, preview the proposed changes, then confirm before applying.

== Frequently Asked Questions ==

= Where can give feedback =
If you have any suggestion regarding the improvement of its feature, please leave a [Review](https://dineshinaublog.wordpress.com/bulk-cogs-editor-for-woocommerce/).

== Use Cases ==

* Update costs after a supplier price change: filter affected products by category or brand, preview a percentage or fixed increase, then apply it to the matching products.
* Correct costs for a specific group: find products by title or SKU, select the products to change, preview exact COGS values or clear them, then confirm the update.

== Screenshots ==
1. Display the settings page to filter and set the Cost of Goods price for multiple products in one go.

== Developer Resources ==

Bulk COGS Editor for WooCommerce is open-source software and is made to be extended. Developers can find sources at our public ([github repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce)) here.

== Changelog ==

= 1.0.0 (2026-10-08) =
* Initial release.
