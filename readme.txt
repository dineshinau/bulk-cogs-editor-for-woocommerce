=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce cogs, bulk cogs, cost of goods, woocommerce bulk editor, product cost
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

== Requirements ==

* WordPress 6.7 or later
* WooCommerce 10.3 or later
* PHP 7.4 or later
* WooCommerce Cost of Goods Sold feature enabled

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bulk-cogs-editor-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.

2. Activate the plugin through the \'Plugins\' screen in WordPress

3. Navigate to Products > Bulk COGS Editor in the WordPress admin menu

4. Filter the products by title or SKU, category, product type, stock status, detected brand taxonomy, current price, and current COGS.

5. Select the products to change, choose an action, preview the proposed changes, then confirm before applying.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce's native Cost of Goods Sold feature? =
Yes. Bulk COGS Editor is designed specifically to work with WooCommerce's native Cost of Goods Sold (COGS) feature. Cost of Goods Sold must be supported by your installed WooCommerce version and enabled in WooCommerce settings. If it is disabled or unavailable, the editor explains what is required.

= Can I preview changes before applying them? =
Yes. You can preview all proposed cost changes in a paginated table showing product images, current COGS, calculated COGS, and the exact difference before confirming any update. Previewing never modifies your store data.

= How does the plugin handle variable products and variations? =
Variations are treated as individual product records. You can filter and update COGS on variations directly, and variable parent products will not be changed when only variations are selected and updated.

= What happens to products without a COGS value during relative adjustments? =
Relative actions (percentage increase/decrease and fixed amount increase/decrease) automatically skip products that do not have an existing COGS value set. To assign cost values to those products, use the "Set exact" action.

= Can I safely update large catalogs without timing out? =
Yes. Updates are processed in background batches via Action Scheduler. You can monitor live progress, elapsed time, and estimated remaining time, and you can cooperatively cancel processing at any time without losing already completed updates.

= Does this plugin modify product prices? =
No. The plugin only modifies the WooCommerce Cost of Goods Sold value.

= Does it use an external service? =
No. The plugin processes product data on your WordPress/WooCommerce installation.

= Where can I give feedback? =
If you have any suggestion regarding the improvement of its feature, please [open an issue](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/issues/) on GitHub.

== Use Cases ==

* Update costs after a supplier price change: filter affected products by category or brand, preview a percentage or fixed increase, then apply it to the matching products.
* Correct costs for a specific group: find products by title or SKU, select the products to change, preview exact COGS values or clear them, then confirm the update.

== Screenshots ==
1. Display the settings page to filter and set the Cost of Goods price for multiple products in one go.

== Developer Resources ==

Bulk COGS Editor for WooCommerce is open-source software and is made to be extended. Developers can find sources at our public ([github repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce)) here.

== Privacy ==

Bulk COGS Editor for WooCommerce does not send product, customer, order, or store data to external services.
All COGS calculations and updates are performed locally on your WordPress/WooCommerce installation.

== Changelog ==

= 1.0.0 (2026-10-10) =
* Initial release.
