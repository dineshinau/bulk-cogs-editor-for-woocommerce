=== Bulk COGS Editor for WooCommerce ===
Contributors: dineshinau
Tags: woocommerce cogs, cost of goods sold, bulk edit, product cost, csv import
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Fast WooCommerce COGS bulk editing with Action Scheduler, previews, CSV/XLSX import, CSV export, history, and rollback.

== Description ==

**Bulk COGS Editor for WooCommerce** is built for WooCommerce's native Cost of Goods Sold (COGS) feature. Filter products, preview proposed product cost changes, and process bulk updates in background Action Scheduler batches with live progress and cancellation. Import costs from CSV or XLSX, export product cost data, and use operation history to inspect outcomes or review a rollback.

It works with the native WooCommerce COGS feature, so there is nothing extra to set up beyond enabling Cost of Goods Sold in WooCommerce. For setup instructions and a step-by-step guide, see the [Bulk COGS Editor documentation](https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/).

= Watch the plugin in action =

See how this WooCommerce bulk editor helps you filter and preview products, apply bulk updates, import and export product cost data, review operation history, and roll back recorded changes safely.

https://youtu.be/sOEOg0xiqYs

= Why use Bulk COGS Editor? =

* **Save time when supplier prices change.** Raise or lower costs for a whole category, brand, or group of products in one pass instead of editing products one by one.
* **Preview before you apply.** See the current COGS, the calculated COGS, and the exact change for every product. Previewing never changes your store data.
* **Built for large catalogs.** Updates run in Action Scheduler batches, with live progress and the option to cancel.
* **Faster bulk updates.** The latest reported WP-CLI benchmark updated 10,000 products in 15 minutes 24 seconds, compared with 50–60 minutes in the initial implementation. See the performance results below for context.
* **Review and reverse recorded updates.** Find past operations in History, inspect recorded product costs and results, and review a rollback to retained original costs.
* **Choose your rollback policy.** Restore original costs even after later edits, or skip products whose costs changed afterward. Set the history retention period to suit your store.
* **Your prices stay untouched.** The plugin only changes the Cost of Goods Sold value.

= How it works =

1. **Filter** products by title or SKU, category, brand, product type, stock status, current price, or current COGS.
2. **Choose an action:** set an exact COGS, increase or decrease it by a percentage or a fixed amount, or clear it.
3. **Preview** the proposed changes in a paginated table.
4. **Apply** the update and follow the progress, elapsed time, and success, skipped, and failed counts.

= Features =

**Filtering**

* Filter by title or SKU, category, product type, stock status, brand, current price, and current COGS. Filters are combined to narrow the matching products.
* Search within filter dropdowns, select multiple values, and see a selected-option count. Use "Select all" to select or clear every option; a partially selected checkbox indicates that only some options are selected.
* Keep Search, Category, and Product type visible, with Brand, Stock status, Price, and COGS ranges under More filters. Remove individual filters with chips or use Clear filters. Collapse Product filters while keeping the filter summary and Find products available.
* Category options show product counts in parentheses. Parent and child categories are available, and selecting a parent category also includes its child categories.
* Category filtering matches variations through their parent product's categories, including when only the Variation product type is selected.
* Brand filtering appears only when a public brand taxonomy is registered for products.
* Price filters use the current WooCommerce product price. Product cost and COGS filters use the supported WooCommerce COGS API.

**Actions**

* Set an exact COGS value.
* Increase or decrease COGS by a percentage.
* Increase or decrease COGS by a fixed amount.
* Clear COGS.
* Compact Set, Adjust, and Clear controls show only the fields needed for the chosen action, with an illustrative before-and-after example and a warning when clearing costs.
* Values use store decimal precision with half-up rounding, and decreases stop at zero.

**Preview and selection**

* See the total number of matches with separate product and variation counts before previewing changes.
* Identify increases in green and decreases in red, with directional arrows and exact change amounts.
* Keep your current preview and selected products when visiting History or Settings and returning to the same editor.
* Review matching products in a paginated table with product images, edit links, right-aligned current and calculated COGS, and the change. Sticky headers and fixed selection and product columns on mobile help you keep track while scrolling.
* Show or hide optional columns and keep your display preferences. New users start with Selection, Product, Current COGS, New COGS, and Change; SKU appears beneath the product name when its separate column is hidden.
* Choose 10, 20, 50, or 100 products per page. The default is 20. Use the footer pagination or Go to page to navigate directly.
* Changing the action or its value refreshes the preview automatically. Previous rows stay visible while the preview refreshes or another page loads. Outdated results are marked, and selection and confirmation remain disabled until fresh results are ready.
* Use Refresh preview beside the table when results need refreshing. Errors and recovery guidance appear near the relevant controls.
* See the selected total across all pages. Select or clear the current page, all matching products, or the first or last number of matches. Individual selections support up to 500 products; all-matching and range selections support larger scopes.
* Keep the selected count and action visible in a sticky summary. Review the operation, selection, and review coverage in the confirmation before applying.
* Start a new selection to reset prior filter constraints; Clear filters only clears the current filter inputs.

**CSV and XLSX workflow**

* Use separate Import and Export guidance, preview headings, and confirmation labels.
* Download a sample CSV beside the upload field, review expandable file requirements, and replace or remove the selected file before validation.
* Review import validation errors before applying selected products. Completed CSV exports provide an explicit download link.
* Expand Recent activity to inspect the latest completed operation, including the updated count, completion time, elapsed time, and any skipped or failed products.

**History and rollback**

* Open the third tab, History, to browse operations by status, operation type, user, date range, or a search for operation ID, note, or product ID.
* Use numbered pagination, page-size choices, and Go to page in both the operation list and recorded product changes.
* Inspect the operation action, user, filters, optional note, timestamps, duration, outcome counts, and recorded original and updated costs.
* Review current costs against retained original costs before confirming a rollback. Rollback runs in background batches with progress, results, and cancellation.
* Retry remaining products after a rollback finishes with skips or failures, or is cancelled. Products already restored are excluded from later attempts, preserving subsequent edits to them.
* Follow active historical operations with automatic status refresh and stale-worker recovery. Older operations without retained original values remain summary-only and cannot be rolled back.

**Settings**

* Choose history retention of 30 days, 90 days, or a custom period from 1 to 36,500 days. The default is 30 days.
* Choose whether new rollbacks overwrite current costs with original costs or skip products whose costs changed after the source operation. Overwrite is the default; each rollback keeps the behavior reviewed when it starts.
* Retention applies to existing completed history too. Expired records are removed by scheduled cleanup; deleted original values cannot be recovered by extending retention afterward.

**In-editor guidance and mobile layout**

* Help and instructions change with the selected Bulk edit tab or CSV Import or Export task, so each workflow has its own steps. Documentation and video links remain available.
* A simplified layout emphasizes filters, cost comparisons, and the next action, with larger touch controls on mobile and a sticky summary that accounts for device safe areas.

**Safe background processing**

* Updates are processed in Action Scheduler batches.
* View the task name, progress, elapsed time, estimated remaining time, and success, skipped, and failed counts. The estimate appears once processing has started; preparation shows an indeterminate progress indicator while the total is unknown.
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

In the developer's [reported benchmark results](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12), processing approximately 10,000 products improved from 50–60 minutes initially to 40–42 minutes, then 25–28 minutes. The latest reported WP-CLI run updated **10,000 products in 15 minutes 24 seconds, with zero skipped and zero failed** — about 69–74% less elapsed time than the initial implementation.

The supplied completion screenshot records an editor operation elapsed time of **00:15:24**, not a separately measured end-to-end CLI runtime. The latest result is a single benchmark run, not a guaranteed completion time. Actual performance depends on hosting, database speed, product types, installed extensions, and background worker availability. Repeated runs and larger catalogs still need benchmarking.

= Import CSV and XLSX files =

Choose CSV mode and Import on the Bulk COGS Editor page. Upload a UTF-8 CSV or an XLSX workbook. The upload limit follows WordPress's maximum upload size, shown beside the file field; the plugin does not impose a separate file-size or row-count cap. Hosting upload, memory, storage and execution limits still apply.

Exported CSV files end with a blank `new_cogs` column. Fill this column with the new costs, then import the file. Required headers are `new_cogs` and `id` or `sku`; legacy files may use `cogs` when `new_cogs` is absent. ID and SKU must agree when both are supplied. Blank costs are excluded; use decimal points without currency symbols. Duplicate product rows are excluded and included in the error report.

XLSX imports read the first worksheet's raw cell values. Replace formulas with values before uploading, and store SKUs as text to preserve leading zeros. XLSX needs PHP's ZIP, XMLReader and DOM extensions. XLSX text is decoded by its XML reader; Windows-1252 is not required. CSV remains UTF-8 only. Export still produces CSV.

Preview preparation runs in Action Scheduler batches without changing products. You can close the page and return to resume its progress display. Once ready, review paged products, download validation errors, select products and explicitly apply the changes. Preparation can be cancelled independently of an applied update. Original uploads are deleted when preparation ends; staged previews and reports expire through scheduled cleanup after approximately 24 hours, with retention extended while an applied update is active.

Completed bulk operation history, original cost records, and one-use preview claims use the retention period in Settings: 30 days by default, 90 days, or custom days. A bounded daily cleanup removes expired records and rechecks retention before deleting history. Active operations and staged imports that still support a report are retained until their work and report dependencies are finished. Preview claims protect against replay and concurrent Apply requests until their retention and dependency checks permit removal.

The WP-CLI command below processes both preview preparation and confirmed updates. Preview workers must run on the upload server as the same operating-system user as PHP, because original uploads are stored in private temporary files. If your shell uses a different user, ask your host to run the command as the PHP user; ordinary product updates do not need the uploaded file. During preparation, worker logs report the stage, checked row count and batch size. The total and remaining row counts are unknown until reading finishes.

= Run background updates with WP-CLI =

Use Action Scheduler's CLI runner to process the plugin queue while the editor continues to show progress and accept cancellations. See the [Action Scheduler documentation](https://actionscheduler.org/) for more information.

After uploading an import for preview or confirming an update in the editor, run this command from your WordPress directory:

`wp action-scheduler run --group=bulk-cogs-editor --batch-size=1 --batches=0`

Check the queue with:

`wp action-scheduler status --group=bulk-cogs-editor`

The status report covers the site's full Action Scheduler queue. The runner handles due plugin actions for all users, exits when none remain, and does not wait for retries. `--batch-size` counts actions, not products.

If another worker owns the available batch, wait for it to finish or run:

`wp action-scheduler run --group=bulk-cogs-editor --batch-size=1 --batches=0 --force`

Operation locks prevent simultaneous work on one operation, but `--force` increases server load. Keep the normal runner enabled for retries. A host-approved cron may invoke the command with a process lock.

For performance checks, prefix the command with `time`. WooCommerce > Status > Logs under source `dkbce` records worker timing and runner details.

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

Bulk COGS Editor for WooCommerce does not send product, customer, order, or store data to external services. All WooCommerce COGS calculations and updates are performed locally on your WordPress/WooCommerce installation.

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

= Do I need to read the documentation or watch the video before using the editor? =
The editor includes Help and instructions for Bulk edit, CSV Import, and CSV Export. The steps change with your selected task. Field guidance, cost examples, previews, and a confirmation summary help you review changes before applying them. Documentation and video links are available for more detail.

= Why are previous preview rows still visible but unavailable for selection? =
The editor keeps previous rows visible while results refresh or another page loads. When results are outdated, selection and confirmation are disabled. Wait for the refresh to finish, or use Refresh preview beside the table when prompted. Review the fresh results before confirming.

= Where can I see the result of my last update? =
Expand Recent activity to see the latest completed operation, its updated count, completion time, and elapsed time. Skipped or failed products and cancellation or failure outcomes are highlighted. Cancelling an operation keeps changes that were already saved.

= Can I roll back a completed update? =

Open History, select View details for the source operation, then choose Review rollback when retained original costs are available. Review the current and original values, check the confirmation, and choose Confirm rollback. Older summary-only or expired operations cannot be rolled back. Cancelling an operation keeps changes already saved; restoring them requires a separate rollback.

= What if product costs changed after the original update? =

Settings lets you choose whether new rollbacks overwrite current costs with the recorded original costs or skip products changed afterward. The chosen policy appears during review and remains fixed for that attempt. After a terminal incomplete rollback, review a new attempt for remaining products; products already restored are excluded even if edited again later.

= How long is operation history retained? =

Choose 30 days, 90 days, or custom days in Settings. The default is 30 days; custom values support 1 to 36,500 days. Shortening retention also applies to existing history. Scheduled cleanup removes expired records, so extending retention cannot recover original values already deleted.

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

1. **Bulk editor:** Product filters, cost actions, a preview, and explicit confirmation in the Bulk edit tab.
2. **Product preview:** Current and proposed COGS, green increases and red decreases, cross-page selection, optional columns, and numbered pagination.
3. **CSV export:** Output column choices and product filters for spreadsheet workflows.
4. **CSV and Excel import:** Template download, file requirements, and CSV or XLSX upload controls.
5. **Operation history:** Filterable operation records with statuses, outcomes, notes, and numbered page navigation.
6. **Operation details:** Existing summary-only history with operation metadata, results, and the notice explaining that original values are unavailable.
7. **Settings:** History retention presets and custom days, plus overwrite or skip-changed rollback behavior.

== Upgrade Notice ==

= 1.2.0 =
Version 1.2.0 adds CSV and XLSX import, CSV export, operation history, and rollback controls. The plugin updates its database tables automatically when you upgrade.

== Changelog ==

= 1.2.0 (2026-10-24) =

* Fixed: Restore green increases and red decreases in the review table's Change column.
* Fixed: Load History reliably and preserve bulk and CSV previews and selected products when visiting History or Settings.
* Fixed: Reconcile costs saved before an after-save exception so successfully changed products remain recorded and eligible for rollback.
* Fixed: Allow reviewed retries after cancelled, failed, or skipped rollbacks, processing only remaining products and preserving later edits to products already restored.
* Fixed: Repair interrupted rollback startup claims, verify operation persistence before scheduling, and prevent concurrent starts with ownership locks.
* Fixed: Recheck configured retention before deleting queued history and prevent partially deleted records from being offered as complete rollbacks.
* Fixed: Refresh active historical operation details and requeue stalled workers through the existing recovery path.
* Fixed: Report import preview errors instead of leaving preparation failures unclear.
* Fixed: Copy uploads into private import storage so background preparation can reopen them after the upload request ends.
* Improved: Count matching products and variations through paginated database totals without loading every matching product ID into PHP.
* Improved: Resolve import SKUs through WooCommerce's indexed lookup storage when available, verify candidates against product objects, and retain metadata fallback for unsupported or incomplete lookup data while preserving ambiguity checks.
* Improved: Reuse WooCommerce search matches within a request and the authorized preview lifetime, and persist search matches for applied operations rather than repeating broad searches for each batch.
* Improved: Process multiple bounded XLSX batches with one open XML reader per worker request, subject to the cooperative deadline, reducing repeated reader setup and prefix traversal within that request.
* Improved: Reuse staged import values in previews, read update values once per chunk, cache repeated XLSX shared strings within a bounded cache, and prepare product caches in batches for previews and exports.
* Improved: Save one durable outcome per operation and product in an indexed table instead of repeatedly rewriting growing chunk results, preserving retry protection and compatibility with existing chunk-backed operations.
* Improved: Reuse indexed outcome summaries during finalization and clean up completed chunks and outcomes in resumable background batches while preserving import report data.
* Improved: Acquire and release private product locks without invalidating unrelated WordPress negative-option cache entries, retaining atomic ownership and expiry checks.
* Improved: Refine import and export controls and progress displays, and include processed counts, remaining work, batch size, counter changes, and operation stage in worker timing logs and WP-CLI output.
* Improved: Clarify Bulk edit, Import, and Export guidance with task-specific previews, upload details, a nearby CSV template, structured confirmation, explicit export downloads, and expandable recent activity.
* Improved: Unify product selection with cross-page totals, page/all/range/clear controls, and a sticky action summary while preserving server-validated selection limits.
* Improved: Replace action cards with compact Set, Adjust, and Clear controls, contextual fields, cost examples, and a visible Find products prerequisite.
* Improved: Add searchable filter menus, selected-option counts, removable filter chips, Clear filters, and collapsible primary and advanced filters.
* Improved: Prioritize product and cost comparisons, retain optional columns and saved preferences, keep table headers and mobile product identity visible while scrolling, and group page size and direct page navigation in the footer.
* Improved: Change Help and instructions to match the selected Bulk edit, CSV Import, or CSV Export task.
* Improved: Keep previous preview rows visible during refresh and pagination, mark outdated results, and prevent selection or confirmation until fresh results are ready.
* Improved: Place preview refresh controls, validation errors, and recovery guidance beside the relevant workflow controls.
* Improved: Identify the active update task, avoid misleading percentages while preparation totals are unknown, clarify that cancellation keeps saved changes, and retain keyboard focus on progress controls when redrawn.
* Improved: Summarize recent activity with updated counts, completion and elapsed times, and prominent skipped, failed, or incomplete outcomes.
* Improved: Simplify editor styling and improve mobile touch targets, stacked controls, scrollable help, and safe-area spacing for the sticky summary.
* Improved: Reuse review tables and progress/result rendering across bulk updates and history; use consistent numbered pagination, page-size controls, Go to page, and inline Show entries controls.
* Improved: Collect remaining rollback product IDs through bounded cursor reads without repeating a full history-product count per batch.
* Improved: The latest reported WP-CLI benchmark updated 10,000 products in 15 minutes 24 seconds, with zero skipped and zero failed, compared with 50–60 minutes initially. This single run records editor operation elapsed time; results vary by store and require repeat benchmarking.
* Updated: Refresh the translation template, import and worker documentation, QA and performance review reports, and development code graph.
* Updated: Document History, rollback policies, retention settings, and recovery behavior, with refreshed screenshots from the wpdev development store.
* Added: Export matching products to CSV with a blank `new_cogs` column for preparing cost updates.
* Added: Import UTF-8 CSV and XLSX files, validate product IDs or SKUs and new COGS values with up to six decimal places, then preview changes before explicitly applying them. Continue accepting the legacy `cogs` header when `new_cogs` is absent.
* Added: Prepare import previews in background batches, with validation progress, paginated results, product selection, downloadable validation and update reports, and independent cancellation of preparation.
* Added: Store import rows in an indexed staging table, delete original uploads when preparation ends, and schedule staged preview and report cleanup after approximately 24 hours, extending retention while an applied update is active.
* Added: Generate CSV exports and import reports asynchronously on the server, with progress, cancellation, private download files, interruption checkpoints, and scheduled download cleanup after two days.
* Added: Browse and filter operation history in a third History tab, with operation details, recorded product changes, optional notes, and background rollback review, confirmation, progress, cancellation, and results.
* Added: Configure 30-day, 90-day, or custom history retention and overwrite or skip-changed rollback behavior in a fourth Settings tab.
* Added: Remove expired operation history, original cost records, and preview claims through bounded daily cleanup using the configured retention period, preserving active operations, import report dependencies, and preview replay protection.
* Added: Upgrade import and outcome database tables using `DKBCE_DB_VERSION`, version comparison, and WordPress `dbDelta()` when the stored schema version is older.
* Added: Load plugin classes on demand through a `DKBCE_` prefix autoloader.
* Added: Provide a development WP-CLI profiler for generated range-query SQL, EXPLAIN plans, installed indexes, repeated timings, and ordered-ID comparisons. Document local findings without changing runtime range predicates, indexes, or pagination.

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
* Improved: Bulk update performance through batch preparation of product and variation-parent caches, fewer repeated reads, and progress checkpoints that retain durable per-product outcomes. A reported benchmark for this version updated 10,000 products in 21 minutes 34 seconds, compared with 50–60 minutes initially; results vary by store and require repeat benchmarking.
* Added: Select the first or last number of products across all matching results, or choose all results.
* Added: Clear a finished operation when using the Refresh now link.

= 1.1.0 (2026-10-14) =

* Fixed: Admin notices appearing between the page title and description.
* Fixed: Alignment of the top and bottom select-all checkboxes with product row checkboxes.
* Fixed: Match variations through their parent product's categories, including when filtering for variations only.
* Improved: Progress reporting with a right-aligned processed product count, percentage indicator, and elapsed and estimated remaining time.
* Added: A message during background updates explaining that you can close the browser and return later to view the results.
* Added: Keep the latest completed operation visible when returning to the editor.
* Added: Expanded page title to clarify Cost of Goods Sold (COGS).
* Added: Select-all controls in filter dropdowns and product counts beside category names.
* Added: Separate product and variation counts in matching results.
* Added: Documentation and video links in the plugin row and editor help section, with clearer COGS setup and usage instructions.
* Added: Clear cancelled updates from the editor using the Refresh now link.

= 1.0.0 (2026-10-10) =

* Initial release.
