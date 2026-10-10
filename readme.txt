=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce cogs, cost of goods sold, bulk edit, product cost, woocommerce bulk editor
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Bulk update WooCommerce Cost of Goods Sold (COGS). Filter products, preview changes, and apply cost updates in safe background batches.

== Description ==

**Bulk COGS Editor for WooCommerce** lets you update the Cost of Goods Sold (COGS) of many products at once, right from the Products admin screen. Filter your catalog, choose an action, preview every change, and apply it in background batches.

It works with WooCommerce's native Cost of Goods Sold feature, so there is nothing extra to set up beyond turning COGS on in WooCommerce.

= Why use Bulk COGS Editor? =

* **Save time when supplier prices change.** Raise or lower costs for a whole category, brand, or group of products in one pass instead of editing products one by one.
* **Preview before you apply.** See the current COGS, the calculated COGS, and the exact change for every product. Previewing never changes your store data.
* **Built for large catalogs.** Updates run in Action Scheduler batches, with live progress and the option to cancel.
* **Your prices stay untouched.** The plugin only changes the Cost of Goods Sold value.

= How it works =

1. **Filter** products by title or SKU, category, brand, product type, stock status, current price, or current COGS.
2. **Choose an action:** set an exact COGS, increase or decrease it by a percentage or a fixed amount, or clear it.
3. **Preview** the proposed changes in a paginated table.
4. **Apply** the update and follow the progress, elapsed time, and success, skipped, and failed counts.

= Features =

**Filtering**

* Filter by title or SKU, category, product type, stock status, brand, current price, and current COGS. Filters are combined to narrow the matching products.
* Brand filtering appears only when a public brand taxonomy is registered for products.
* Price filters use the current WooCommerce product price. COGS filters use the supported COGS API.

**Actions**

* Set an exact COGS value.
* Increase or decrease COGS by a percentage.
* Increase or decrease COGS by a fixed amount.
* Clear COGS.
* Values use store decimal precision with half-up rounding, and decreases stop at zero.

**Preview and selection**

* Review matching products in a paginated table with product images, edit links, current and calculated COGS, and the change.
* Choose 10, 20, 50, or 100 products per page. The default is 20.
* Changing the action or its value refreshes the preview automatically.
* Select or clear all products on the current preview page. Selections are kept across preview pages, and selected-only updates support up to 500 products.

**Safe background processing**

* Updates are processed in Action Scheduler batches.
* View progress, elapsed time, estimated remaining time, and success, skipped, and failed counts. The estimate appears once processing has started.
* Cancel at any time. Updates that already completed are kept.
* Product matching uses bounded ID batches to limit memory use.

**Product types**

* Uses WooCommerce CRUD APIs for simple, variable, grouped, external, and custom product types, and for variations.
* Variations are treated as separate records, and variable parent products are not changed when only variations are selected.

**Good to know**

* Relative actions (percentage or fixed amount) skip products whose COGS is not set. Use "Set exact" to add costs to those products.
* WooCommerce's product COGS API converts a value of zero to empty. Clearing uses the API's null value.

= Use cases =

* **Supplier price change:** filter the affected products by category or brand, preview a percentage or fixed increase, then apply it to the matching products.
* **Correct a specific group of products:** find products by title or SKU, select the ones to change, preview exact COGS values or clear them, then confirm the update.
* **Add missing costs:** use "Set exact" to give a COGS value to products that do not have one yet.

= Requirements =

* WordPress 6.7 or later
* WooCommerce 10.3 or later
* PHP 7.4 or later
* WooCommerce Cost of Goods Sold feature enabled in WooCommerce settings

If Cost of Goods Sold is unavailable or disabled, the editor is turned off and explains what is needed.

= Privacy =

Bulk COGS Editor for WooCommerce does not send product, customer, order, or store data to external services. All COGS calculations and updates are performed locally on your WordPress/WooCommerce installation.

= Developer resources =

Bulk COGS Editor for WooCommerce is open-source software and is made to be extended. The source code is available in the [GitHub repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce).

= Connect with the developer =

* [Website](https://dineshinaublog.wordpress.com/)
* [Facebook](https://www.facebook.com/dineshinau/)
* [X](https://x.com/dineshinau/)
* [LinkedIn](https://www.linkedin.com/in/dineshinau/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bulk-cogs-editor-for-woocommerce` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure the Cost of Goods Sold feature is enabled in WooCommerce settings.
4. Go to Products > Bulk COGS Editor in the WordPress admin menu.
5. Filter your products, choose an action, preview the changes, then confirm to apply them.

== Frequently Asked Questions ==

= How do I bulk update Cost of Goods Sold (COGS) in WooCommerce? =
Go to Products > Bulk COGS Editor, filter the products you want to change, choose an action (set exact, increase or decrease by a percentage or fixed amount, or clear), review the preview, and confirm. Updates are applied in background batches.

= Does this plugin work with WooCommerce's native Cost of Goods Sold feature? =
Yes. Bulk COGS Editor is designed specifically for WooCommerce's native Cost of Goods Sold (COGS) feature. COGS must be supported by your installed WooCommerce version and enabled in WooCommerce settings. If it is disabled or unavailable, the editor explains what is required.

= Which WooCommerce version do I need? =
WooCommerce 10.3 or later, with the Cost of Goods Sold feature enabled in WooCommerce settings.

= Can I increase all product costs by a percentage? =
Yes. Filter the products you want, choose "Increase by percentage", enter the value, and review the preview before applying. You can also decrease by a percentage, or increase or decrease by a fixed amount.

= Can I preview changes before applying them? =
Yes. You can preview all proposed cost changes in a paginated table showing product images, current COGS, calculated COGS, and the exact difference before confirming any update. Previewing never modifies your store data.

= How does the plugin handle variable products and variations? =
Variations are treated as individual product records. You can filter and update COGS on variations directly, and variable parent products will not be changed when only variations are selected and updated.

= What happens to products without a COGS value during relative adjustments? =
Relative actions (percentage and fixed amount increases or decreases) skip products that do not have a COGS value set. To assign costs to those products, use the "Set exact" action.

= Can I safely update large catalogs without timing out? =
Yes. Updates are processed in background batches via Action Scheduler. You can monitor live progress, elapsed time, and estimated remaining time, and you can cancel processing at any time without losing already completed updates.

= Does this plugin modify product prices? =
No. The plugin only modifies the WooCommerce Cost of Goods Sold value.

= Does it use an external service? =
No. The plugin processes product data on your WordPress/WooCommerce installation.

= Where can I give feedback? =
If you have a suggestion to improve the plugin, please [open an issue](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/issues/) on GitHub or use the support forum on WordPress.org.

== Screenshots ==

1. **The Bulk COGS Editor screen:**  filter WooCommerce products, choose a COGS action, review the preview, and apply the update.
2. **Filter products:** by title or SKU, category, brand, product type, stock status, price, and COGS range, then choose an action: set exact COGS, increase or decrease by a percentage or fixed amount, or clear COGS.
3. **Preview every change:** before applying it. The paginated table shows each product's image, SKU, type, current COGS, new COGS, and the exact change, with 10, 20, 50, or 100 products per page.
4. **Choose exactly which products to update:** Untick individual products or use "Select all products on this page", then apply the update only to the checked products.
5. **A confirmation dialog shows** how many products will be changed before any COGS value is modified.
6. **The update starts in the background:** The plugin collects the matching products and shows progress with a Cancel operation button.
7. **Live progress:** while the batches are processed: products processed, success, skipped, and failed counts, elapsed time, and estimated time remaining.
8. **Update completed:** final success, skipped, and failed counts, with a Refresh now link to reload the page.

== Changelog ==

= 1.0.0 (2026-10-10) =
* Initial release.
