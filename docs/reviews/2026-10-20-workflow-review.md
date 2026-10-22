# Manual workflow review

Review date: 20 October 2026. Reviewed plugin 1.1.2 on local WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.3.6. WooCommerce COGS was enabled. Browser checks used a temporary shop manager and 23 disposable products, including an unset COGS value, a variable product, and two variations. Temporary data was removed after review.

This review combines browser-observed behavior with source inspection. It is not a compatibility matrix across supported WordPress and WooCommerce versions, a load test, or a security audit. No permanent product data was used.

## Findings

### P1: Keep product selection stable when an edit refreshes the preview

**Observed:** Search for the 23 QA products, uncheck one row, then change the action value. The refreshed preview checked that row again and confirmation increased from 22 to 23 products. The interface does not explain that the selection reset.

**Why it matters:** A routine value correction can widen a bulk update to include products the user deliberately excluded. The confirmation count is the only visible indication.

**Improvement:** Preserve the checked product IDs when an action or value refreshes the same result set. When filters change, intersect the selection with the new matches and clearly report any change. Keep confirmation scope synchronized with the displayed selection.

**Acceptance:** Exclusions survive action and value edits; filter changes cannot silently add excluded products; confirmation count always matches the selected product scope.

### P2: Show WooCommerce's stored result when the proposed COGS is zero

**Observed:** Set exact COGS to `0`; preview displays `$0.00`. The UI and README explain WooCommerce stores zero as empty, so the preview does not show the resulting stored value.

**Improvement:** Render the proposed value as `Empty` and explain that zero clears the cost, or require the user to choose Clear COGS explicitly. Keep the preview, confirmation, and final result consistent.

**Acceptance:** A zero-value preview states exactly what WooCommerce will store and whether the change clears an existing cost.

### P2: Make preview freshness visible before applying

**Observed in a controlled stale-data check:** Preview a 10% increase from $10 to $11, change the product's current COGS to $20 outside the editor, then apply. The operation recalculated against current product data and set the value to $22. This avoids overwriting from stale arithmetic, but the user confirms a preview showing $11 and receives $22.

**Improvement:** Revalidate affected values before starting the job. If values changed, stop and request a fresh preview, or clearly show the recalculated scope and values before confirmation.

**Acceptance:** The confirmed operation cannot produce values that differ from the reviewed preview without another user confirmation.

### P2: Clarify progress and status after returning to a saved operation

**Source-based improvement:** The page restores the latest operation and polls its durable status after reload. Provide a clear recovery state if the background queue is delayed or unavailable, including when progress last changed and what action the user can take. Distinguish snapshot collection from product updates in the progress display.

**Acceptance:** Reloading during queued, active, failed, or cancelled work gives a useful status and does not imply that an idle queue is processing.

## Workflow results

| Workflow | Result | Evidence |
| --- | --- | --- |
| Open editor as shop manager | Pass | Page loaded; product menu contains Bulk COGS Editor; filters, help, action cards, preview, and apply sections rendered. |
| Require product query before editing | Pass | Trying the value field before fetching products showed the “Fetch matching products” notice. |
| Search and match counts | Pass | Unique QA search returned 23 matches: 21 products and 2 variations. |
| Preview and pagination | Pass | Preview displayed 20 rows on page one and 3 on page two. Returning to page one retained the unchecked row. |
| Six bulk actions | Pass | Exact set, percentage increase/decrease, fixed increase/decrease, and clear rendered expected sample calculations. Relative actions marked missing COGS as skipped. Decrease was floored at zero. |
| Selection and confirmation | Pass with P1 issue | Unchecking one row changed confirmation from 23 to 22. Changing the value refreshed the preview and restored the row, changing confirmation back to 23. |
| Confirmation keyboard behavior | Pass | Dialog focused Cancel on open; Shift+Tab from Cancel wrapped focus to Apply; Escape closed dialog. |
| Background apply | Pass | A one-product update completed; operation showed 1 processed, 1 updated, 0 skipped, 0 failed. The operation recalculated a deliberately changed current COGS from $20 to $22 using the previewed 10% action. |
| CSV export preview and download | Pass | 23 matching rows; first page showed 20; status column displayed Ready; confirmed export downloaded `product-cogs.csv` (2,011 bytes). Product COGS did not change during export. |
| CSV import | Pass | Background preparation staged a file with one valid row, one unknown SKU, and one blank cost. Preview showed the valid $22 to $23 change, rejected both invalid rows with row-level reasons, and downloaded `cogs-import-errors.csv`. Confirmed apply updated the one valid product; final state was 1 processed, 1 updated, 0 skipped, 0 failed. |
| XLSX import | Source reviewed | Reader streams the first worksheet, requires ZIP, XMLReader, and DOM extensions, and rejects formulas. Upload, workbook parsing, and formula handling still need browser verification. |
| Filters, cancellation, resume, and error reports | Not fully exercised | Source review covered handler and state paths. These need dedicated manual checks with category/type/stock/brand and numeric ranges, slow or stopped workers, cancelled jobs, and failed import rows. |

## Suggested next manual pass

1. Upload CSV cases with valid ID and SKU, mismatched ID/SKU, unknown product, blank cost, malformed decimal, duplicate target, and quoted commas. Check validation report, selection, apply, and result report for failed rows.
2. Repeat with XLSX containing values, formulas, leading-zero SKUs, missing worksheet support, and malformed workbook input.
3. Check each filter alone and in combinations. Include variations through parent categories, empty categories, no-result ranges, min greater than max, brands absent, and custom product types.
4. Start a multi-batch update, reload the page, confirm recovery, cancel, and verify already completed items remain changed while later items stop.
5. Repeat selection checks after changing action, value, filters, page size, and preview page. Confirm the dialog count always matches intended rows.
6. Repeat at narrow viewport and with keyboard-only navigation. Check focus visibility, filter dropdown operation, table navigation, status announcements, and dialog focus restoration.

## Source areas reviewed

Admin screen and AJAX flows in `admin/class-dkbce-admin-functions.php`; hook registration in `admin/class-dkbce-admin-hooks.php`; filtering and calculations in `admin/class-dkbce-cogs-service.php`; batch processing and import preparation in `admin/class-dkbce-bulk-processor.php`, `admin/class-dkbce-import-processor.php`, `admin/class-dkbce-import-reader.php`, and `admin/class-dkbce-csv-service.php`; UI interactions in `src/bulk-cogs-editor.js`; product data and support notes in `readme.txt`.
