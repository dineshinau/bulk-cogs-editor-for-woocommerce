=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce cogs, cost of goods sold, bulk edit, product cost, woocommerce bulk editor
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.2
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
* **Faster bulk updates.** A reported benchmark updated 10,000 products in 21 minutes 34 seconds, compared with 50–60 minutes in the initial implementation. See the performance results below for context.
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
* Batch cache preparation, fewer repeated database reads, and grouped WooCommerce cache cleanup reduce processing overhead while all COGS writes use WooCommerce CRUD APIs.
* Saved per-product outcomes and progress checkpoints allow interrupted operations to resume without blindly repeating completed updates.

**Product types**

* Uses WooCommerce CRUD APIs for simple, variable, grouped, external, and custom product types, and for variations.
* Variations are treated as separate records, and variable parent products are not changed when only variations are selected.

**Good to know**

* Relative actions (percentage or fixed amount) skip products whose COGS is not set. Use "Set exact" to add costs to those products.
* WooCommerce's product COGS API converts a value of zero to empty. Clearing uses the API's null value.

= Performance for large catalogs =

Bulk processing has been improved by reducing repeated product scans and counts, preparing product and variation-parent caches in batches, and grouping WooCommerce product cache cleanup. Progress counters are checkpointed while each product's outcome is saved for recovery. COGS updates continue to use WooCommerce's CRUD/data layer, preserving its hooks and cache handling.

In the developer's [reported benchmark results](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12), processing approximately 10,000 products improved from 50–60 minutes initially to 40–42 minutes, then 25–28 minutes. The latest reported run updated **10,000 products in 21 minutes 34 seconds, with zero skipped and zero failed** — about 57–64% less time than the initial implementation.

The latest result is a single benchmark run, not a guaranteed completion time. Actual performance depends on hosting, database speed, product types, installed extensions, and background worker availability. Repeated runs and larger catalogs still need benchmarking.

= Import CSV and XLSX files =

Choose CSV mode and Import on the Bulk COGS Editor page. Upload a UTF-8 CSV or an XLSX workbook. The upload limit follows WordPress's maximum upload size, shown beside the file field; the plugin does not impose a separate file-size or row-count cap. Hosting upload, memory, storage and execution limits still apply.

Exported CSV files end with a blank `new_cogs` column. Fill this column with the new costs, then import the file. Required headers are `new_cogs` and `id` or `sku`; legacy files may use `cogs` when `new_cogs` is absent. ID and SKU must agree when both are supplied. Blank costs are excluded; use decimal points without currency symbols. Duplicate product rows are excluded and included in the error report.

XLSX imports read the first worksheet's raw cell values. Replace formulas with values before uploading, and store SKUs as text to preserve leading zeros. XLSX needs PHP's ZIP, XMLReader and DOM extensions. XLSX text is decoded by its XML reader; Windows-1252 is not required. CSV remains UTF-8 only. Export still produces CSV.

Preview preparation runs in Action Scheduler batches without changing products. You can close the page and return to resume its progress display. Once ready, review paged products, download validation errors, select products and explicitly apply the changes. Preparation can be cancelled independently of an applied update. Original uploads are deleted when preparation ends; staged previews and reports expire through scheduled cleanup after approximately 24 hours, with retention extended while an applied update is active.

The WP-CLI command below processes both preview preparation and confirmed updates. Preview workers must run on the upload server as the same operating-system user as PHP, because original uploads are stored in private temporary files. If your shell uses a different user, ask your host to run the command as the PHP user; ordinary product updates do not need the uploaded file. During preparation, worker logs report the stage, checked row count and batch size. The total and remaining row counts are unknown until reading finishes.

= Run background updates with WP-CLI =

If your host provides WP-CLI and permits longer CLI processes, you can use Action Scheduler's existing CLI runner to process the Bulk COGS Editor queue. It reuses the loaded WordPress process across actions and avoids the default HTTP runner's sleep between asynchronous requests. The editor continues to show progress and accept cancellation requests.

After uploading an import for preview or confirming an update in the editor, run this command from your WordPress directory:

`wp action-scheduler run --group=bulk-cogs-editor --batch-size=1 --batches=0`

To check Action Scheduler queue status, run:

`wp action-scheduler status --group=bulk-cogs-editor`

This status report covers the site's full Action Scheduler queue. It does not filter by the Bulk COGS Editor group.

The command processes due actions for all Bulk COGS Editor operations on that site, not just the current user's update. It exits when no matching actions are due; it does not wait for future updates or delayed retries. The batch size here counts scheduled actions, not products. Product batches and the worker's cooperative time budget keep their existing limits.

Action Scheduler allows one claimed batch by default. While the editor's background worker is processing an update, the CLI command can report `There are too many concurrent batches.` If the update is still progressing in the editor and you want the CLI runner to work alongside it, add `--force`:

`wp action-scheduler run --group=bulk-cogs-editor --batch-size=1 --batches=0 --force`

The plugin locks each operation so two workers cannot advance the same operation at once. Forced runs can still add server load, and this command processes due Bulk COGS Editor actions for every user on the site. Use it only when concurrent processing is appropriate for your hosting capacity. Without `--force`, wait for the active Action Scheduler batch to finish and retry. Keep the normal queue runner available for delayed retries. A host-approved cron can invoke the command periodically, with a process lock to prevent overlapping CLI runs. Cron and PHP CLI resource limits depend on your hosting plan.

For a benchmark, prefix the command with `time` to include CLI startup and shutdown in its elapsed time. Also record the editor's full operation duration and final updated, skipped, and failed counts. Compare the same product mix, initial costs, filters, action, and hosting conditions. The CLI command can start after the operation has already begun, so its elapsed time alone is not the full operation duration.

With plugin logging enabled, WooCommerce > Status > Logs, source `dkbce`, includes one `COGS worker timing` entry for each worker that acquired the operation lock. Each entry identifies the operation, runner, start and finish timestamps, and elapsed seconds. For a single operation, sum worker elapsed times to measure time inside the worker callbacks; subtract each previous finish timestamp from the next start timestamp to measure gaps between workers. Gaps include scheduling, request startup, and other work outside the callback. Callback time includes snapshot collection, product processing, and batch cache flushing, but excludes WordPress startup, deferred shutdown work, and the timing log write itself. Use the runner labels to identify runs where HTTP, WP-Cron, and CLI processing were mixed.

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

= 1.1.1 (2026-10-20) =

* Fixed: Recover background COGS updates that stop progressing and allow cancellation cleanup from the editor.
* Fixed: Keep matching product counts visible when changing the selected COGS action.
* Fixed: Allow preview pagination during background updates after the preview token has been consumed, while keeping product selection disabled.
* Improved: Scroll to the bottom of the editor when a background update starts so its progress is visible.
* Improved: Prevent filter changes while an update is running and require products to be fetched before choosing an action. The editor guides users to Get products when they try.
* Improved: Place the action warning in the Step 2 title row and center it on larger screens.
* Improved: Explain how to refresh an expired preview and scroll to the notice so the instructions are visible.
* Improved: Collect matching products using a product ID cursor, cache search matches per operation, and recover saved snapshot chunks after interruptions.
* Improved: Group WooCommerce product transient invalidations within each update batch to reduce repeated cache cleanup.
* Improved: Bulk update performance through batch preparation of product and variation-parent caches, fewer repeated reads, and progress checkpoints that retain durable per-product outcomes. The latest reported benchmark updated 10,000 products in 21 minutes 34 seconds, compared with 50–60 minutes initially; results vary by store and require repeat benchmarking.
* Added: Select the first or last number of products across all matching results, or choose all results.
* Added: Clear a finished operation when using the Refresh now link.

= 1.1.0 (2026-10-14) =

* Fixed: Admin notices appearing between the page title and description.
* Fixed: Alignment of the top and bottom select-all checkboxes with product row checkboxes.
* Improved: Progress reporting with a right-aligned processed product count, percentage indicator, and elapsed and estimated remaining time.
* Added: A message during background updates explaining that you can close the browser and return later to view the results.
* Added: Keep the latest completed operation visible when returning to the editor.
* Added: Expanded page title to clarify Cost of Goods Sold (COGS).
* Added: Select-all controls in filter dropdowns and product counts beside category names.
* Added: Separate product and variation counts in matching results.
* Added: Documentation and video links in the plugin row and editor help section, with clearer COGS setup and usage instructions.
* Added: Clear cancelled updates from the editor using the Refresh now link.
* Fixed: Match variations through their parent product's categories, including when filtering for variations only.

= 1.0.0 (2026-10-10) =

* Initial release.
