# Targeted blog update: Bulk COGS Editor 1.2.0

Article: https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/

Prepared against the live WordPress.org 1.2.0 listing, GitHub readme, and all seven current repository screenshots. This is an edit pack for the existing article, not a replacement article. Editorial instructions identify where each passage belongs; only the supplied article copy should be pasted into the post. No changes have been published.

## 1. Keep the existing title and URL

Keep **How to Bulk Edit WooCommerce COGS (Cost of Goods Sold) Safely** and the current permalink. The article already answers the main bulk-editing query. Add the new workflows under descriptive headings rather than crowding every feature into the title.

Suggested meta description, if your WordPress.com setup exposes that field:

> Bulk edit WooCommerce COGS, import costs from CSV or Excel, export CSV files, and review history with rollback controls. Follow the step-by-step guide.

Use “bulk edit WooCommerce COGS” as the primary topic. The new sections naturally cover “import WooCommerce product costs from CSV,” “Excel COGS import,” “export WooCommerce COGS,” and “roll back product cost changes.” These are editorial targets, not measured search-volume claims.

## 2. Extend the introduction

Insert after the introductory paragraph ending “background batches.” Keep the other introductory paragraphs.

**Article copy:**

Version 1.2.0 also adds CSV and Excel (XLSX) import, CSV export, operation history, and rollback controls. You can now prepare individual product costs in a spreadsheet, review earlier updates, and restore eligible changes while their original values are retained.

Replace only the paragraph beginning “In this guide, I will explain…” with:

**Article copy:**

In this guide, I will explain how to filter products, preview and apply bulk COGS changes, and monitor background updates. I will also show you how to import costs from CSV or Excel, export product cost data, review History, and configure retention and rollback behavior in Settings.

## 3. Extend the existing feature list

Under “What is Bulk COGS Editor for WooCommerce?”, append these bullets to the existing list:

- Import different product costs from CSV or Excel (XLSX) using product IDs or SKUs.
- Export matching product data and current costs to CSV, with a blank `new_cogs` column for preparing updates.
- Download validation errors and update reports.
- Review previous operations and their results in History.
- Roll back eligible updates while recorded original costs are retained.
- Choose history retention and rollback behavior in Settings.

Keep the paragraph explaining that selling prices are unchanged.

## 4. Refresh the existing bulk-edit instructions

These are local edits within the current tutorial. Preserve the explanations of filters, calculations, variations, background processing, and cancellation.

### Step 1: Open the Bulk COGS Editor

After the admin navigation path, insert:

**Article copy:**

The editor has four tabs: **Bulk edit**, **CSV import / export**, **History**, and **Settings**. Choose **Bulk edit** to apply one cost action to a selected group of products.

### Step 2 and the supplier example

Change the button label **Get products** to **Find products** wherever the post tells readers what to click, including the supplier example’s step heading.

### Step 3: Choose how the COGS value should change

Replace the sentence “The Choose COGS update action section provides six different operations.” with:

**Article copy:**

Use the Update action control to set an exact cost, adjust an existing cost, or clear it. For an adjustment, choose Increase or Decrease, then select Percentage or a fixed amount. These controls support the six cost operations explained below.

Keep the six existing calculation explanations. Where the post uses “Increase by percentage” as a button label, describe choosing **Adjust existing cost → Increase → Percentage** instead.

### Step 4: Preview

Keep the explanation of current cost, proposed cost, and change. Add:

**Article copy:**

Increases appear in green and decreases in red. Review the selected product count as well as the calculated values before continuing. Values shown on reviewed pages are checked again before an update; if those costs change, refresh the preview and confirm the revised values.

### Step 5: Product selection

Replace the passage beginning “You can use: Select all products on this page” through “Selected-only operations support up to 500 products.” with:

**Article copy:**

Use the row checkboxes to select individual products, or select all products on the current page. The selection summary shows how many products are selected across all pages and on the page you are viewing.

You can also select all matching products, clear the selection, or use **Select a range** to choose a group across the results. Check the total before reviewing the update so the operation includes the products you intend to change.

### Step 6: Confirmation

Replace the existing Step 6 body, keeping its heading:

**Article copy:**

Once you are satisfied with the preview, check the selected count in the action summary. You can add an optional note, such as “October supplier cost increase,” to help identify the operation later.

Click the review button to inspect the proposed update, then confirm it to start processing. Product costs do not change merely because you found products or opened a preview.

### Step 9: Completion

Replace only the sentences about the Refresh now link and the last completed operation remaining visible with:

**Article copy:**

Expand **Recent activity → View details** to inspect the latest completed operation, including its updated count, completion time, elapsed time, and any reported problems. Open **History** to find earlier operations and their recorded results.

## 5. Add the spreadsheet workflows

Insert the following two H2 sections after “Step 9: Review the completed COGS update” and before “What happens if you cancel a COGS update?”. Keep Manage Columns in its existing position.

**Article copy:**

## How to export WooCommerce COGS to CSV

Export is useful when you want to review current costs in a spreadsheet or prepare different new costs for individual products.

1. Go to **Products → Bulk COGS Editor → CSV import / export**.
2. Choose **Export CSV**.
3. Choose the CSV columns you need, keeping a product ID or SKU so you can identify products when importing updates.
4. Filter the products you want and click **Find products**.
5. Review the preview and selected products, then click **Create CSV** and download the file when it is ready. Keep the page open until the download is ready.

The export includes a blank `new_cogs` column. Enter the new cost for each product you want to update, then import the edited file. Keep its product IDs and SKUs unchanged.

You can open the CSV in Excel to prepare your updates. The plugin exports CSV files; it does not create XLSX exports. Exporting does not change product costs.

*[Insert repository screenshot-3.png here. Caption: Choose export columns and review matching products before downloading WooCommerce product costs as CSV.]*

## How to import WooCommerce product costs from CSV or Excel

Use an import when each product needs a different cost, such as when a supplier sends an updated cost sheet. Bulk edit remains useful when the same percentage, amount, or exact cost applies to a group.

### Prepare your product cost file

Open **CSV import / export**, choose **Import CSV**, and use **Download template** as your starting point. Despite the control’s name, this workflow accepts both CSV and Excel (XLSX) files.

Your file needs a `new_cogs` column and either `id` or `sku`:

```csv
id,sku,new_cogs
123,PRODUCT-001,25.50
124,PRODUCT-002,40.00
```

Replace the sample identifiers with products from your own store. If you provide both ID and SKU, they must identify the same product.

- Enter decimal costs without currency symbols.
- Save CSV files using UTF-8 encoding.
- Leave a cost blank to skip that row; a blank import value does not clear an existing cost.
- Avoid duplicate product rows. Duplicates are excluded and listed in the validation error report.
- For XLSX, put your data on the first worksheet, replace formulas with values, and store SKUs as text to preserve leading zeros.

XLSX import requires the PHP ZIP, XMLReader, and DOM extensions on your server. The maximum upload size shown beside the file field comes from your WordPress configuration. Hosting resources also affect how large a file you can process.

### Validate, preview, and confirm the import

1. Choose your CSV or XLSX file.
2. Click **Validate file** to prepare the import preview.
3. Wait for preparation to finish, then inspect the matched products and proposed costs.
4. Download validation errors, if any, and correct affected rows before including them in an update.
5. Select the products to update, review the changes, and confirm the operation.
6. Follow the background update and review its final results.

Validation and preview preparation do not change product data. Preparation runs in background batches, so you can leave the page and return to check its progress. Confirmed cost updates also run in the background.

Prepared import previews and reports normally expire after about 24 hours, with retention extended while an applied update is active. If a preview expires, upload and validate the file again.

*[Insert repository screenshot-4.png here. Caption: Upload a CSV or Excel file and validate product costs before applying an import.]*

## 6. Clarify cancellation and add History, rollback, and Settings

Keep the existing cancellation explanation, including the example of 150 updated products. Replace the sentence “This behavior is explicitly documented by the plugin.” with:

**Article copy:**

Cancellation stops further processing; restoring changes already saved requires a separate rollback from History, where recorded original costs are still available.

Insert the following sections immediately after the cancellation section and before “How products without an existing COGS value are handled”.

**Article copy:**

## Review WooCommerce COGS update history

Open the **History** tab to find previous operations and inspect their results. This is useful when you need to check a supplier update, investigate a cancelled operation, or find the changes associated with a particular note.

Filter the records by status, operation type, user, or date range. You can also search by operation ID, note, or product ID.

Choose **View details** to inspect an operation’s recorded information. Where original values are retained, you can review product changes and check whether rollback is available.

*[Insert repository screenshot-5.png here. Caption: Filter operation history and open an earlier WooCommerce COGS update to inspect its results.]*

## How to roll back WooCommerce COGS changes

If an update needs to be reversed, History lets you review a rollback before restoring recorded original costs.

1. Open **History** and find the operation.
2. Choose **View details**.
3. Select **Review rollback** when it is available.
4. Compare current costs with the recorded original values and check the rollback policy.
5. Choose **Confirm rollback**, then monitor the results.

Rollback is available only for eligible changes whose original values are still retained. Expired operations and older records containing summary results alone cannot be rolled back.

If a cost changed again after the original operation, the rollback policy determines what happens. You can preserve later edits by skipping changed products, or restore the recorded originals by overwriting current costs. Choose the policy in Settings before starting the rollback review.

If a rollback is cancelled or does not finish, review a new attempt for the remaining products. Products already restored are excluded from that retry.

*[Insert a newly captured rollback review screenshot here. The current repository screenshot-6.png shows summary-only history without retained original values, so it does not demonstrate an available rollback.]*

## Configure history retention and rollback behavior

Open **Settings** to control how long operation history and original costs are kept, and how future rollbacks handle later cost edits.

### History retention

Choose **30 days**, **90 days**, or a custom period from **1 to 36,500 days**. The default is 30 days.

Shortening retention also affects existing history. Extending it cannot recover original cost records already removed by scheduled cleanup. Choose a period that covers the time you normally need to review supplier updates and correct mistakes.

### Rollback behavior

- **Overwrite:** Restore recorded original costs even when a product’s cost has been edited since the operation.
- **Skip changed products:** Preserve later cost edits by skipping products whose costs have changed since the operation.

The policy shown during rollback review stays fixed for that attempt. Click **Save settings** after making changes.

*[Insert repository screenshot-7.png here. Caption: Set history retention and choose how rollback handles later product cost edits.]*

## 7. Make small additions to existing supporting sections

### Useful use cases

Append these two use cases:

**Import individual supplier costs**

Prepare a CSV or Excel file when each product needs a different new cost, then validate and review the import before applying it.

**Review or reverse an earlier update**

Find the operation in History, inspect its results, and review a rollback when original costs remain available.

### Large stores

Keep the background-processing explanation. Replace only “Product matching also uses bounded ID batches to reduce memory requirements.” with:

**Article copy:**

The plugin processes work in batches and records progress and product outcomes to support recovery after interruptions. Completion time depends on your hosting, database, installed extensions, and background worker availability.

### Final thoughts

Keep the current closing section and download links. Add this paragraph before the download call to action:

**Article copy:**

For individual supplier costs, use CSV or Excel import and CSV export alongside the bulk editor. History helps you review previous operations, while Settings controls how long original costs remain available and how eligible rollbacks handle later edits.

## 8. Update and extend the FAQ

Replace the answer to “Can I update only selected products?” with:

Yes. Use the preview checkboxes, page selection, all-matching selection, or range controls to choose products. Check the selected total before reviewing and confirming the update.

Replace the answer to “Can I stop a WooCommerce COGS update after it starts?” with:

Yes. Click **Cancel operation** to request cancellation. Costs already saved remain changed. To restore eligible changes, open the operation in History and review a separate rollback while its original values are retained.

Update the existing percentage-adjustment FAQ to describe **Adjust existing cost → Increase → Percentage**, and describe reviewing and confirming the update rather than the old Apply changes button.

Append these four FAQs:

### Can I import WooCommerce COGS from CSV or Excel?

Yes. Upload a UTF-8 CSV or XLSX file containing `new_cogs` and either `id` or `sku`. Validate the file, review the proposed costs, and confirm the selected updates.

### Can I export WooCommerce product costs to Excel?

You can export costs as CSV and open the file in Excel. The export includes a blank `new_cogs` column for preparing an import. Native XLSX export is not available.

### Can I undo a completed COGS update?

Yes, when the operation is eligible and its original cost records remain available. Open **History → View details → Review rollback**, check the proposed restoration, and confirm it. Summary-only and expired records cannot be rolled back.

### How long can I keep COGS operation history?

Settings offers 30 days, 90 days, or a custom period from 1 to 36,500 days. The default is 30 days. Increasing retention cannot restore records already deleted by cleanup.

## 9. Screenshot replacement plan

Repository files: https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/tree/main/org-assets

The post currently has 11 numbered screenshot positions. Repository filenames now represent different features; do not replace them by matching numbers alone.

| Existing article position | Image to use or capture | Suggested alt text |
| --- | --- | --- |
| 1 — Main editor | Current repository screenshot-1.png | Bulk COGS Editor showing filters, cost actions, and product preview |
| 2 — Multiple categories | Capture current filter menus with several categories selected | Filter multiple WooCommerce categories before updating product costs |
| 3 — 5% preview | Current repository screenshot-2.png | Preview a five percent COGS increase with current and proposed product costs |
| 4 — Product selection | Capture current selection controls with a meaningful partial selection | Select WooCommerce products across preview pages before updating COGS |
| 5 — Confirmation | Capture the current confirmation dialog | Review selected products and confirm a WooCommerce COGS update |
| 6 — Preparing operation | Capture current preparation state | WooCommerce COGS update preparing products for background processing |
| 7 — Running operation | Capture current progress state | Background COGS update showing updated, skipped, and failed products |
| 8 — Manage columns | Capture current Manage columns panel | Choose visible columns in the WooCommerce COGS preview |
| 9 — Completion | Capture current completed operation with details expanded | Completed COGS update with final product counts and elapsed time |
| 10 — Cancellation requested | Capture current cancellation-requested state | Cancellation requested for a running WooCommerce COGS update |
| 11 — Cancelled operation | Capture current cancelled state | Cancelled COGS operation showing results saved before processing stopped |
| New — CSV export | Current repository screenshot-3.png | Export WooCommerce product costs to CSV with selected columns |
| New — CSV/XLSX import | Current repository screenshot-4.png | Upload and validate a CSV or Excel file containing new product costs |
| New — History | Current repository screenshot-5.png | Filter WooCommerce COGS operation history by status, type, user, and date |
| New — Rollback review | Capture an eligible operation with original values and rollback controls | Review current and original WooCommerce costs before confirming rollback |
| New — Settings | Current repository screenshot-7.png | Configure COGS history retention and rollback behavior |

Repository screenshot-6.png is optional for explaining why older operations cannot be rolled back. If used there, caption it: “Older summary-only operations can show results without retained original costs, so rollback is unavailable.”

Upload replacement images to the post’s Media Library and use those copies. Give files descriptive names, preserve readable interface text, and remove the literal “SCREENSHOT N” editorial labels from the published article. The sample upload limit shown in screenshot-4.png is specific to that test site; do not describe it as the plugin’s fixed limit.

## 10. Editorial checks before publishing

- Keep existing headings and explanations except for the targeted replacements above.
- Use H2 for the new export, import, History, rollback, and Settings sections; use H3 for their subtopics.
- Add links to these new sections in a contents block if the article uses one.
- Keep the original publication date and update the modified date when the edits actually go live. A short “Updated for version 1.2.0” note can sit near the introduction.
- Review the current embedded video before changing it. The readme now references https://youtu.be/sOEOg0xiqYs; use it only after confirming it demonstrates the workflow you want readers to follow.
- Avoid promising a particular completion time or unlimited capacity. Background updates depend on workers being available; export specifically asks readers to keep the page open until download is ready.
- Preview the edited post on desktop and mobile, checking image readability, table overflow, and the CSV code block.

## Sources checked

- Existing article: https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/
- Live plugin listing, showing version 1.2.0: https://wordpress.org/plugins/bulk-cogs-editor-for-woocommerce/
- Repository readme: https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/blob/main/readme.txt
- Current screenshot assets, visually reviewed: https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/tree/main/org-assets

The live listing and readme describe some controls generically as CSV mode / Import / Apply. This edit pack follows the labels visible in the supplied current screenshots where those differ, and uses generic review-and-confirm wording where a current confirmation screenshot is unavailable.
