# Workflow review implementation

Source: `docs/reviews/2026-10-20-workflow-review.md`. Implement findings in their listed order.

1. Preserve exclusions and range selection through action/value refreshes. Intersect prior filters when filters change; expose the resulting selected count. Reset filters starts a new scope. Changes: preview handlers, matching queries, frontend selection state.
2. Normalize calculated zero to WooCommerce's empty COGS value and explain clearing in preview/confirmation. Changes: calculations and frontend copy.
3. Record the calculation shown for each reviewed row. Revalidate selected reviewed rows before claiming a preview or queuing work; require a fresh preview and confirmation on mismatch. Guard again under the worker's product lock, preserving retry protection. Unvisited rows remain sample-based; the UI must explain that limitation.
4. Expose the last progress checkpoint and queue health. Distinguish waiting, snapshot collection, updates, delayed work, polling failures, and terminal results. Offer status refresh and retain cancellation.

Acceptance: exclusions survive edits; filter refreshes do not broaden scope; confirmation uses the displayed selection; zero previews show Empty; reviewed rows cannot silently receive recalculated values; recovered operations show useful queue status.

Checks: PHP formatter, syntax, PHPCS; frontend build and generated asset review; Graphify refresh and symbol coverage; final diff checks. No tests, product updates, or automatic commits.

## Implementation outcome

All four implementation steps are complete. Filter refreshes retain previous filter constraints as intersections; Reset filters explicitly starts a new selection. A selection supports up to twelve distinct prior filter constraints before requiring a reset. Excluded IDs remain authorized across replacement previews, including exclusions outside the new intersection. Confirmation uses the selected count rather than subtracting every historical exclusion from the new match count.

Calculated zero is represented as `null`, so both the preview and worker use WooCommerce's empty-cost result. Preview and confirmation explain clearing.

Freshness signatures cover the current value, proposed value, and calculation outcome for rows returned on visited preview pages. Apply rejects changed reviewed values before claiming the token, preserving the opportunity to refresh and confirm again. Workers guard those signatures after acquiring a product lock and after checking retry protection. Unvisited products retain the existing calculation-at-processing behavior, now explained in the interface. Count changes between confirmation and completed snapshot collection stop the operation before updates begin. This is not a historical snapshot of every catalog value or membership before previewing.

Recovery includes checkpoint timestamps, waiting/delayed/unavailable status, a status refresh button, polling retries, and separate snapshot counts. Terminal status takes precedence over an unfinished snapshot stage. Older poll responses cannot overwrite newer responses or a cancellation response.

PHP formatting and syntax checks passed. PHPCS found no errors and one existing `meta_query` performance warning in the query extension; its exit status remains nonzero because of that warning. The frontend production build passed and rebuilt JavaScript and its asset version. Diff checks passed. Browser tests and manual acceptance scenarios were not run.
