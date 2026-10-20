# UI design implementation

Source: `docs/reviews/2026-10-20-ui-design-review.md`.

1. Correct Bulk edit, Import, and Export wording; move the template beside upload; add file details, structured confirmation, and labeled recent activity.
2. Unify selection across pages with an explicit count, page/all/clear controls, range menu, and sticky action summary. Preserve server authorization, preview ownership, freshness checks, and selected-scope validation.
3. Replace large action cards with compact contextual configuration. Add searchable term menus, progressive filters and chips, and reorganize table defaults without losing optional columns or saved preferences.
4. Apply restrained visual tokens and responsive layouts, stable stale-preview states, and clearer progress/error presentation.

Primary files: admin controller, COGS service and import selection storage where required; JavaScript, SCSS, rebuilt assets, translation template. No queue/write algorithm changes, dependency changes, version changes, automatic commits, or optional column-mapping/preset features.

Acceptance: all three modes use accurate instructions; selection count and confirmation agree with the submitted scope; page/all/range/clear actions stay distinct; stale previews cannot apply; import requirements and errors remain available; monetary comparisons stay readable; mobile has no page overflow; all controls retain keyboard access and visible focus.

Checks: affected PHP formatting/syntax/PHPCS, production build and generated asset review, manual browser inspection of read-only flows and confirmation at desktop/tablet/mobile sizes, Markdown lint, translation extraction, Graphify update and bounded coverage query, final diff review. No automated tests or product updates unless separately requested.

## Manual review checkpoint: stages 1 and 2

The user requested a manual review checkpoint before proceeding further. Stages 1 and 2 are implemented; stages 3 and 4 remain pending. The compact action configuration and filter/table redesign have not been implemented.

Completed: task-specific CSV headings and empty states, template placement, expandable file requirements, selected-file details, structured confirmation, explicit download link after CSV preparation, labeled/collapsed recent activity, a unified selection toolbar, all/page/clear/range selection, and a sticky selection/action summary. The old visible selected-only toggle is removed from the interaction; its checked internal input preserves the existing request contract. Explicit selections are validated against owned preview rows. Imports freeze the selected staged rows after claiming the preview. Manual explicit selection uses the existing 500-product limit; all-results and range selections retain their broader scope.

Read-only browser inspection confirmed: clear selection disables confirmation; 20 selected products remain selected across page navigation; selecting the first five updates the review button to five; a one-product explicit selection survives value and filter refreshes; Import/Export use their own empty-state guidance; the import template is visible; table utilities are hidden before preview data exists; the 390 px selection layout has no page-wide overflow. Confirmation was opened and cancelled without applying updates. No imports, exports, product updates, or automated tests were started for verification.

PHP formatting and syntax checks passed. PHPCS reports zero errors and the existing `meta_query` performance warning. The production build passed. Import/export update execution and download completion were reviewed in source, not exercised end to end.

For manual review:

1. Bulk edit: find products, enter a value, clear selection, select one page, visit another page, and confirm the displayed total remains accurate.
2. Select all results and use Select a range to choose First or Last N. Open the confirmation and compare operation, scope, coverage, and final button count. Cancel to avoid updating products.
3. Change an action/value or narrow filters and check selection preservation and the prior-filter constraint notice. Start a new selection resets that scope.
4. CSV Import: check the template, file requirements, file name/size, Replace, Remove, and upload-specific empty state. CSV Export: check its preview wording and friendly column labels.
5. Check the sticky summary and expandable Recent activity on desktop and mobile.

Proceed to stage 3 only after the user's manual review.


## Manual review checkpoint: stage 3

Stage 3 is implemented following the user's request to continue. Stage 4 remains pending manual review.

Completed: native Set/Adjust/Clear configuration with contextual direction, unit and amount fields; an illustrative cost calculation and clear-cost warning; a visible prerequisite instead of interaction blocking and button animation. Existing backend action keys are preserved.

Search, Category and Product type remain prominent. More filters contains Stock, Brand and numeric ranges. Filter menus support text search and selected-count summaries. Removable chips and Clear filters expose the current filter inputs; Start a new selection still resets the earlier selection constraints. The filter panel can collapse while chips and Find products remain visible.

The table puts Selection, Product, Current cost, New cost and Change first. New users start with ID, SKU and Type hidden; saved column choices retain their original keys. SKU appears beneath the product name when its separate column is hidden. Monetary values align right, New cost is emphasized, headers remain visible within the scroll area, and selection/product identity remain fixed during mobile horizontal scrolling. Columns joins the selection toolbar; page size and a direct page jump sit with pagination.

Validation: PHP formatting and syntax passed; PHPCS has zero errors and the existing `meta_query` warning. Production assets rebuilt successfully. Read-only browser inspection covered category search, selected-count summaries, removable chips, Clear filters, page jumps, percentage adjustment, Clear cost, collapsed filters, CSV export preview, and CSV import layout. Bulk edit at 390 px and CSV at tablet width had no page-wide overflow. Existing saved column preferences were retained. Actual updates, imports and export generation were not executed; no automated tests were added or run.

For manual review:

1. Find products and try Set, Adjust (both directions and units), and Clear. Compare the example and actual table values; open confirmation and cancel.
2. Search within Category or Brand, select multiple values, remove chips, and compare Clear filters with Start a new selection. Collapse Product filters and expand More filters.
3. Review column order, optional columns, SKU placement, footer page size and Go to page. Reload and check that saved column choices persist.
4. Check table scrolling on mobile and repeat the filter/table inspection in CSV Export. Check that Import retains its file-specific controls.

Pause here before stage 4: visual tokens, responsive polish, stable stale-preview/loading states, and progress/error presentation.
