# Bulk COGS Editor for WooCommerce

Review and update product costs in bulk, or transfer costs with CSV and Excel files, directly from your WooCommerce admin.

Bulk COGS Editor works with **WooCommerce’s native Cost of Goods Sold (COGS)** feature. Find the right products, preview the exact changes, choose which records to update, and explicitly confirm before background processing starts. Product selling prices stay untouched.

**Version:** 1.1.2 · **License:** GPLv3 · **HPOS compatible**

[Documentation](https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/) · [Video demonstration](https://www.youtube.com/watch?v=R-kw_6g2JIg) · [Report an issue](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/issues)

![Bulk editing workflow with product filters, cost action controls, and a preview of proposed changes](docs/screenshots/bulk-editor.png)

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Features](#features)
- [Bulk editing workflow](#bulk-editing-workflow)
- [CSV export](#csv-export)
- [CSV and Excel import](#csv-and-excel-import)
- [Background processing and recovery](#background-processing-and-recovery)
- [Frequently asked questions](#frequently-asked-questions)
- [Development and support](#development-and-support)

## Requirements and installation

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.7+ |
| WooCommerce | 10.3+ |
| PHP | 7.4+ |
| WooCommerce COGS | Enabled in WooCommerce settings |

1. Install the plugin through WordPress, or upload the plugin folder to `wp-content/plugins/bulk-cogs-editor-for-woocommerce/`.
2. Activate **Bulk COGS Editor for WooCommerce**.
3. Enable WooCommerce’s native Cost of Goods Sold feature.
4. Open **Products → Bulk COGS Editor**.

If COGS is unavailable or disabled, the editor explains what is required. Use a packaged plugin build when installing a ZIP; a source checkout needs its dependencies and compiled assets for development.

## Features

### Find the products you need

- Combine searches by product name, SKU, GTIN, ISBN, EAN, or UPC with category, product type, stock status, brand, price, and current COGS filters.
- Search inside multi-select filter menus, select individual options or all options, and see selected-option counts.
- Include child categories when choosing a parent category. Variations match categories through their parent product.
- Keep primary filters close at hand and expand **More filters** for additional criteria.
- Remove individual filter chips, clear filters, or start a new selection.
- See the full matching count with separate product and variation totals.

Brand filtering appears when a public product brand taxonomy is available. Category option counts come from WooCommerce’s stored product counts; the matching result count reflects the current filter combination.

### Set, adjust, or clear costs

| Action | Example |
| --- | --- |
| Set exact cost | Set matching products to 25.00 |
| Increase by percentage | Increase existing costs by 5% |
| Decrease by percentage | Reduce existing costs by 10% |
| Increase by fixed amount | Add 2.50 to existing costs |
| Decrease by fixed amount | Subtract 1.00 from existing costs |
| Clear cost | Remove the saved COGS value |

The editor shows contextual fields, an illustrative before-and-after example, and a warning for clearing costs. Calculations use store decimal precision and half-up rounding; decreases stop at zero.

Relative adjustments skip products without a saved cost. Use **Set exact cost** to assign missing costs. WooCommerce treats zero as an empty cost; clearing uses the COGS API’s null value.

### Preview and choose your update scope

- Compare current COGS, new COGS, and the amount and percentage of each change before applying.
- Identify products through images, names, SKUs, and product edit links.
- Show or hide optional columns and retain display preferences.
- Browse 10, 20, 50, or 100 records per page, or jump directly to a page.
- Select the current page, all matches, individual records across pages, or the first or last number of matches.
- See the selection total across pages and keep the action visible in a sticky summary.
- Review the action, selection scope, and preview review coverage in the confirmation dialog.

Individual selections support up to **500 products**. All-matching and range selections support larger scopes. Outdated preview rows remain visible for context, but selection and confirmation are disabled until fresh results are ready. A nearby **Refresh preview** control helps recover when needed.

![Product preview showing current and proposed costs with cross-page selection controls](docs/screenshots/product-preview.png)

### Guidance and responsive controls

Help and instructions follow the selected **Bulk edit**, **CSV Import**, or **CSV Export** task. File requirements, cost examples, nearby validation messages, and recovery controls guide each workflow. Documentation and video links remain available.

The interface uses colored workflow icons, clear step numbers, larger mobile touch controls, sticky table headers, and fixed selection and product columns on small screens. The action summary accounts for device safe areas.

### Product and variation support

Costs are written through WooCommerce CRUD APIs for simple, variable, grouped, external, and custom product types, and for variations. Variations are separate records: selecting only variations does not change their variable parent’s cost.

## Bulk editing workflow

1. Choose **Bulk edit**.
2. Set your filters and choose **Find products**.
3. Choose **Set exact cost**, **Adjust existing cost**, or **Clear cost**. Enter the value where applicable.
4. Review the automatically generated preview and select the products to update.
5. Choose **Review changes**, inspect the confirmation summary, and confirm when ready.
6. Follow progress and review the final outcome.

Previewing does not change product costs. Updating begins only after confirmation.

## CSV export

Use exports to review existing costs in a spreadsheet or prepare a file for importing new values.

1. Choose **CSV import / export → Export CSV**.
2. Choose the output columns and filter the products.
3. Find and preview the matching products.
4. Start the export and wait for server-side file generation.
5. Use the explicit download link when the file is ready.

Exports cover **all matching pages** and always append a blank `new_cogs` column. Fill that column to prepare cost updates. Product type filters determine whether variable parents, variations, or both are included. Export produces CSV, including when the source of a previous import was XLSX.

![CSV export controls with column choices and product filtering](docs/screenshots/csv-export.png)

## CSV and Excel import

Import **UTF-8 CSV** or **XLSX** files. A downloadable CSV template and expandable file requirements are available beside the upload controls.

![File import controls with template download, requirements, and validation](docs/screenshots/csv-import.png)

### File format

Supply `new_cogs` and at least one product identifier: `id` or `sku`.

```csv
sku,new_cogs
TSHIRT-BLUE-S,12.50
TSHIRT-BLUE-M,13.25
```

| Column | Purpose |
| --- | --- |
| `id` | WordPress product or variation ID |
| `sku` | Product or variation SKU |
| `new_cogs` | New cost, using a decimal point and no currency symbol |
| `cogs` | Current cost in exports; accepted as the legacy import value only when `new_cogs` is absent |

- If both ID and SKU are supplied, they must identify the same product.
- Blank costs are excluded. Duplicate product rows are excluded and included in the error report.
- Import values support up to six decimal places; product updates follow WooCommerce’s cost handling.
- XLSX imports read the **first worksheet’s raw cell values**. Replace formulas with values and store SKUs as text to retain leading zeros.
- WordPress’s upload limit is displayed beside the file field. The plugin has no separate file-size or row-count cap; hosting limits still apply.

### Validate, review, then apply

1. Choose **CSV import / export → Import CSV**.
2. Drop a CSV or XLSX file onto the upload area, or choose a file. Replace or remove it if needed.
3. Choose **Validate file** to prepare a preview in background batches.
4. Review the paginated results and download validation errors when available.
5. Select the products to update, review the confirmation, and explicitly apply the changes.
6. Review the outcome and download update reports when available.

Preparing an import preview does not update products. Preparation can be cancelled independently of an applied update, and you can close the page and return to its progress display.

Original uploads are deleted when preparation ends. Staged previews and reports expire through scheduled cleanup after approximately **24 hours**, with retention extended while an applied update is active. Generated export and report download files have scheduled cleanup after **two days**.

## Background processing and recovery

Updates run in **Action Scheduler** batches. The editor displays the task, processed count, progress, elapsed time, estimated remaining time when available, and updated, skipped, and failed counts. Preparation avoids a misleading percentage when the total is not yet known.

- Close the browser and return later to view progress or results.
- Request cancellation while work is running. **Already saved changes are kept; cancellation is not a rollback.**
- Saved per-product outcomes and checkpoints support recovery without blindly repeating completed updates.
- Bounded product queries, batch cache preparation, indexed outcomes, and grouped cache cleanup reduce processing overhead.
- Expand **Recent activity** to inspect the latest completed operation, including completion time, elapsed time, and any skipped or failed records.

Completed operation history and linked preview claims are retained for at least **30 days**, then removed through bounded daily cleanup. Active operations and import report dependencies are preserved until their work is finished.

### Optional WP-CLI runner

After preparing an import or confirming an update in the editor, a host-approved CLI process can run due plugin actions:

```bash
wp action-scheduler run --group=bulk-cogs-editor --batch-size=1 --batches=0
```

This processes due Bulk COGS Editor actions for **all users on the site** and exits when no matching actions are due. The batch size counts scheduled actions, not products. Keep the normal queue runner available for delayed retries.

Import preview workers must run on the upload server as the same operating-system user as PHP so they can read private temporary uploads. See [the operational notes in readme.txt](readme.txt) for concurrency, queue status, timing logs, and hosting considerations.

### Performance

The developer’s [reported benchmark](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12) updated **10,000 products in 21 minutes 34 seconds**, with zero skipped and zero failed, compared with 50–60 minutes in the initial implementation.

This is a reported single run, not a guaranteed completion time. Results depend on hosting, database speed, product mix, installed extensions, and worker availability. Repeated runs and larger-catalog benchmarks are still needed.

## Frequently asked questions

### Does this replace WooCommerce’s native COGS feature?

No. It uses WooCommerce’s native feature and requires it to be enabled. The plugin changes product cost values; selling prices remain unchanged.

### Can I preview without changing my store?

Yes. Product previews and import validation do not change costs. An update requires explicit confirmation.

### Why did a percentage or fixed adjustment skip some products?

Relative actions skip records without a saved COGS value. Use **Set exact cost** to assign those records a cost.

### Why does selecting every category differ from leaving the category filter empty?

Selecting categories requires a matching category assignment. Products without categories, and variations whose parents have no matching categories, are excluded. Clear the category selection to remove that restriction.

### Can I update variations without changing their parents?

Yes. Filter for variations and review the resulting records. Variations have their own COGS values and are updated separately.

### Why are some preview controls disabled?

The editor disables selection and confirmation while results are outdated or loading, and prevents conflicting changes during an active update. Refresh the preview when prompted and review the current results.

### Does the plugin send store data to external services?

No. Product matching, calculations, previews, imports, exports, and cost updates run on your WordPress installation.

## Development and support

Frontend sources are in `src/`; generated production assets are in `assets/`. Backend code is in `admin/`, with bootstrap and compatibility declarations in `bulk-cogs-editor-for-woocommerce.php`.

For a source checkout:

```bash
npm ci
npm run build
```

Use the repository’s [project instructions](AGENTS.md) for contribution conventions and supported checks. See [readme.txt](readme.txt) for the full release history, including version **1.1.2**.

- [Usage documentation](https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/)
- [Video demonstration](https://www.youtube.com/watch?v=R-kw_6g2JIg)
- [Issues and feature requests](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/issues)
- [Performance discussion](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12)

Screenshots show the plugin on a development store; product data and results are illustrative. Licensed under [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html).
