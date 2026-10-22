# Manual workflow testing report

- Date: 2026-10-22
- Plugin: Bulk COGS Editor for WooCommerce 1.1.2
- Initial test environment: saved local `wpdev` site (`https://wsl.com/wpdev`), Chrome/Chromium, desktop 1440×1000 and mobile 390×844
- Fix verification environment: local `wcdev` site (`https://wsl.com/wcdev`), Chrome/Chromium, desktop and mobile 390×844

## Summary

The core product workflows completed successfully with disposable products and users. Bulk calculations, preview safety, background updates, persistence, history, rollback, CSV export/import, settings persistence, responsive rendering, and authorization were exercised through the WordPress admin UI. Operation records confirmed that the tested set, rollback, export, and import jobs completed without product failures.

Two user-facing issues and one automated-test maintenance issue were found. All three were subsequently fixed and verified on `wcdev`. The complete updated browser suite passed 16/16 cases, and its disposable products and users were removed successfully.

## Issues

### 1. Sticky Apply changes panel obscures mobile filter controls

- Severity: Medium
- Status: Fixed and verified on `wcdev`
- Area: Bulk edit, responsive/mobile layout
- Environment: 390×844 viewport
- Reproduction:
  1. Open Products → Bulk COGS Editor.
  2. Stay in Bulk edit mode before preparing a preview.
  3. Use a 390 px-wide viewport and scroll through Product filters.
- Actual: The sticky Apply changes panel remains visible while disabled and overlays the Product type and Stock status controls. The covered controls and their labels are difficult to read or operate until the page is scrolled past the overlap.
- Expected: A disabled, pre-preview action panel should not obscure filter controls. It should either stop being sticky at this width/state, collapse to a compact bar, or appear after a preview is available.
- Evidence: Reproduced repeatedly in clean fixture runs. The panel text was “Choose products to preview your cost changes” and the disabled button was “Review changes.”
- Resolution: The Apply changes panel now returns to normal document flow at widths up to 600 px. A 390×844 browser regression check confirmed `position: static` and no panel shadow.

### 2. Singular confirmation copy says “1 products”

- Severity: Low
- Status: Fixed and verified on `wcdev`
- Area: Apply confirmation dialog
- Reproduction:
  1. Find and preview one product.
  2. Click Apply changes.
- Actual: The dialog says, “You are about to modify COGS for 1 products.”
- Expected: “1 product” for a singular selection.
- Impact: Cosmetic grammar issue in a high-attention confirmation step.
- Resolution: Singular confirmation strings are now used for one-product bulk edits and exports. The apply workflow assertion confirmed “1 product.”

### 3. Repository browser suite is out of sync with the current UI

- Severity: Medium (development/quality risk; not a production runtime failure)
- Status: Fixed and verified on `wcdev`
- Command: `DKBCE_BASE_URL=https://wsl.com/wpdev DKBCE_WP_PATH=/var/www/html/wpdev npm run test:e2e`
- Actual: 15/15 cases stop at the shared loader because the suite expects the removed heading “Bulk Cost of Goods Sold (COGS) Editor.” The live page now uses “Bulk COGS Editor.” Further inspection also found obsolete assumptions including a `#dkbce-preview` button and native visible multi-select controls; the current UI auto-refreshes previews and replaces native filter selects with checkbox popovers.
- Expected: The suite should target the current heading and interaction model so it can validate the workflows it describes.
- Impact before resolution: The suite produced a complete false-negative run and could not protect current behavior.
- Resolution: The suite now follows the current heading, popover filters, auto-preview workflow, action selectors, responsive layout, pagination, operation status, and persisted settings. The updated command against `wcdev` passed 16/16 cases in 2.1 minutes.

## Passed workflow coverage

- Admin page access, current heading, four workspace modes, and safe initial state.
- SKU search and one-product result retrieval.
- Category filtering through the current checkbox popover, combined filtering, filter chips, and clearing filters.
- Reversed price-range validation with no operation started.
- Preview calculations for set exact, increase/decrease percentage, increase/decrease fixed amount, and clear.
- Preview safety: the stored current COGS stayed unchanged during calculation previews.
- Relative adjustment of an empty COGS value was skipped with the documented explanation.
- Confirmation dialog cancel path remained non-destructive.
- Confirmed one-product set operation completed successfully (`processed: 1`, `succeeded: 1`, `failed: 0`).
- History list and operation details loaded.
- Reviewed rollback completed successfully (`processed: 1`, `succeeded: 1`, `failed: 0`).
- Retention input enforced the documented one-day minimum; a valid custom value persisted. The original 30-day value was restored after testing.
- CSV export job completed and downloaded a file containing the selected fixture SKU and `new_cogs` column.
- CSV import accepted one valid SKU, excluded and reported one unknown SKU, and completed the valid update (`processed: 1`, `succeeded: 1`, `failed: 0`).
- Customer account could not access the privileged editor controls.
- No page-level horizontal overflow was observed at 390×844.

## Test data and cleanup

- All changed products and users were uniquely named disposable fixtures created by `tests/e2e/fixtures.php`.
- The final observed fixture COGS of `12.34` came from the successful CSV-import test.
- The admin latest-operation pointer was isolated during testing so older saved activity did not contaminate clean workflows.
- Retention was restored to 30 days before cleanup.
- Expected HTTP 400 console noise came from the deliberately invalid import row; the aborted download navigation was caused by Playwright accepting the CSV download and was not treated as a plugin failure.

## Notes on result interpretation

Some automated assertions initially timed out because completed operation panels are moved or hidden when the UI renders Recent activity, and success notices use the shared `notice-info` style rather than `notice-success`. Persisted operation records and downloaded/imported data were used to distinguish those harness mismatches from product failures.
