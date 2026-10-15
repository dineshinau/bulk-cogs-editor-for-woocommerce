=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce cogs, cost of goods sold, bulk edit, product cost, woocommerce bulk editor
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Bulk update WooCommerce Cost of Goods Sold (COGS). Filter products, preview changes, and apply cost updates in safe background batches.

== Description ==

**Bulk COGS Editor for WooCommerce** lets you update the Cost of Goods Sold (COGS) of many products at once, right from the Products admin screen. Filter your catalog, choose an action, preview every change, and apply it in background batches.

It works with WooCommerce's native Cost of Goods Sold (COGS) feature, so there is nothing extra to set up beyond enabling COGS in WooCommerce. For setup instructions and a step-by-step guide, see the [Bulk COGS Editor documentation](https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/).

= Watch the plugin in action =

See how Bulk COGS Editor for WooCommerce helps you filter products, preview COGS changes, and apply bulk updates safely.

https://www.youtube.com/watch?v=R-kw_6g2JIg

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
* Select multiple values in each filter dropdown, or use "Select all" to select or clear every option. A partially selected checkbox indicates that only some options are selected.
* Category options show product counts in parentheses. Parent and child categories are available, and selecting a parent category also includes its child categories.
* Category filtering matches variations through their parent product's categories, including when only the Variation product type is selected.
* Brand filtering appears only when a public brand taxonomy is registered for products.
* Price filters use the current WooCommerce product price. COGS filters use the supported COGS API.

**Actions**

* Set an exact COGS value.
* Increase or decrease COGS by a percentage.
* Increase or decrease COGS by a fixed amount.
* Clear COGS.
* Values use store decimal precision with half-up rounding, and decreases stop at zero.

**Preview and selection**

* See the total number of matches with separate product and variation counts before previewing changes.
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
* [GitHub](https://github.com/dineshinau/)

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

= How does "Select all" work in the filter dropdowns? =
Each category, product type, stock status, and available brand dropdown has a "Select all" checkbox. Check it to select every option, or uncheck it to clear the selection. You can still select individual options. An empty filter does not restrict the results.

= Why can selecting all categories return fewer matches than leaving the category filter empty? =
Selecting all categories requires products to have a category assignment. Variations match the categories assigned to their parent product. Products without categories, and variations whose parent has no categories, are excluded. Use the "All categories" link in the dropdown or clear the category selection to remove this restriction.

= Are child categories included? =
Yes. The dropdown lists both parent and child categories in a flat list. Selecting a parent category also matches products in its child categories and variations of those products.

= What do the counts beside category names mean? =
The number in parentheses is WooCommerce's stored product count for that category. It does not count variations as separate records and does not change when you adjust the other filters. Use "Get products" to see the matching total and its product and variation breakdown for your current filters.

= Does the matching total include variations? =
Yes. The result message shows the total followed by separate product and variation counts, for example: "950 matches: 300 products and 650 variations." The product count excludes variation records. The counts cover all matching results, not just the current preview page.

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

1. **The Bulk COGS Editor screen:** Select products, choose a COGS update action, review changes, and apply the update in four steps.
2. **Filter products:** Select multiple categories and filter by name, SKU or product identifier, brand, product type, stock status, price, and current COGS. See matching product and variation counts before previewing changes.
3. **Preview COGS changes:** Review a 5% increase in the paginated table, with product images, IDs, SKUs, types, current COGS, new COGS, and the amount and percentage of each change.
4. **Choose which products to update:** Review the product selections, then enable "Only apply to products checked in the preview table" to limit the update to checked products.
5. **Confirm COGS changes:** Check the number of products to be modified before choosing Apply changes or Cancel.
6. **Collect matching products:** The update prepares the matching products and displays progress, outcome counts, elapsed time, and a Cancel operation button.
7. **Track live progress:** See the processed product count, percentage complete, updated, skipped, and failed counts, elapsed time, and estimated time remaining. Updates continue in the background when you close the browser.
8. **Manage preview columns:** Show or hide columns such as ID, SKU, product type, current COGS, new COGS, and change to customize the preview table.
9. **Update completed:** View 100% progress, final updated, skipped, and failed counts, and elapsed time, with a Refresh now link to reload the page.
10. **Request cancellation:** The progress panel shows "Cancellation requested..." while the running update responds to the request.
11. **Update cancelled:** Review the final processed and outcome counts. Already processed products remain changed; cancellation does not roll back completed updates.

== Changelog ==

= 1.1.1 (2026-10-15) =

* Fixed: Recover background COGS updates that stop progressing and allow cancellation cleanup from the editor.
* Fixed: Keep matching product counts visible when changing the selected COGS action.
* Improved: Prevent filter changes while an update is running and require products to be fetched before choosing an action. The editor guides users to Get products when they try.
* Improved: Place the action warning in the Step 2 title row and center it on larger screens.
* Added: Select the first or last number of products across all matching results, or choose all results.
* Added: Clear a finished operation when using the Refresh now link.

= 1.1.0 (2026-10-14) =

* Fixed: Admin notices appearing between the page title and description.
* Fixed: Alignment of the top and bottom select-all checkboxes with product row checkboxes.
* Improved: Progress reporting with a right-aligned processed product count, percentage indicator, and elapsed and estimated remaining time.
* Added: A message during background updates explaining that you can close the browser and return later to view the results.
* Added: Keep the latest completed operation visible when returning to the editor.
* Added: Expanded page title to clarify Cost of Goods Sold (COGS).

= 1.0.0 (2026-10-10) =

* Initial release.
