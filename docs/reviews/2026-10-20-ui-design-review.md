# Bulk COGS Editor UI design review

Reviewed on 20 October 2026 using the local WordPress admin at desktop (1440 px), tablet (1024 px), and mobile (390 px) widths. Reviewed Bulk edit, its populated product preview and confirmation, CSV Export, CSV Import, category options, and an existing completed operation. Import validation/error and active background states were reviewed in source; no new import, export, or product-update operation was started. No automated tests ran.

## Design direction

Use a calm, data-first WooCommerce workspace: a compact heading, clear mode navigation, progressive controls, a legible product table, and an always-visible selection summary. Modernize hierarchy and interactions before decorative styling. Keep the existing WordPress font and familiar admin conventions.

The current UI already has useful numbered sections, explicit confirmation, before/after costs, restrained table striping, lazy product images, and visible progress totals. Its largest weaknesses are competing selection concepts, long guidance, duplicated decoration, and task wording that leaks between modes.

The recommendations below are design judgments based on observation, not measured usability results. Validate them with representative store managers before claiming faster task completion.

## Priority changes

| Priority | Observed problem | Recommended change | Relative effort |
| --- | --- | --- | --- |
| P1 | Checkboxes, page selection, All/First/Last, and “Only apply…” compete | One selection model with a persistent selected count and explicit page/all-results scope | Medium; interaction and selection-state work |
| P1 | CSV Import/Export share bulk-edit empty-state instructions | Give each mode its own headings, empty states, progress labels, and next action | Small |
| P1 | Confirmation is a long paragraph without the chosen action/value | Show product count, operation, value, and scope as a short structured summary | Small–medium |
| P1 | The sample CSV link disappears in Import | Put “Download template” beside the import upload area | Small |
| P1 | Import starts with a dense technical paragraph | Keep required format guidance visible; disclose detailed rules separately | Small |
| P1 | Old completion panels remain under unrelated modes | Separate active work from recent activity; label history with task and time | Medium |
| P2 | Six large action cards dominate the page | Use compact Set cost / Adjust cost / Clear cost choices with contextual fields | Medium |
| P2 | Apply is below the full table | Add a sticky summary/action bar contained within the plugin content | Medium |
| P2 | Range controls wrap awkwardly and appear before data exists | Move range selection into a secondary selection menu after products load | Small–medium |
| P2 | Filters expose every field and long term lists | Searchable multi-selects, applied-filter chips, and an expandable advanced-filter area | Medium |
| P2 | Monetary comparison is visually secondary to IDs and SKU | Make Product, Current cost, New cost, and Change the main table columns | Small–medium |
| P2 | Mobile header and cards create excessive vertical travel | Compact header, vertically stacked controls, smaller completed sections, and mobile table defaults | Medium |
| P3 | Mixed gradients, icon tiles, step circles, and saturated blue compete | Use one accent, consistent spacing, lighter borders, fewer decorative icons | Small |

## Bulk edit workflow

### 1. Give selection one meaning

The populated table shows all visible rows checked, while the footer also offers an unchecked “Only apply to products checked in the preview table.” A user must understand both controls before knowing the final scope. The All/First/Last selector adds another model.

Use the existing initial all-matching selection, but state it clearly: **538 selected across all pages**. Row checkboxes modify that set. The header checkbox affects the current page and has an explicit accessible label. Show feedback such as **20 products on this page selected** and an explicit **Select all 538 matching products** action when switching from page scope. Keep **Clear selection** available and move First/Last into **Select a range…**.

Remove the extra “Only apply…” toggle after the unified model is implemented. Make the main action say **Review 538 changes**. The final dialog button says **Update 538 products**. Every count must refer to the same server-validated selection. Selection behavior changes need deliberate implementation; this is more than relabeling controls.

When filter refreshes preserve older constraints, show that fact beside the count: **Selection narrowed from your previous filters** with **Start a new selection**. A list of only the newest filters must not imply that those are the complete matching constraints.

### 2. Compress action configuration

Replace six illustrated cards with a compact control:

- **Set cost:** amount field with currency prefix.
- **Adjust cost:** Increase/Decrease, amount field, and %/currency selector.
- **Clear cost:** a clearly labeled choice with a short warning about removing stored costs.

Show one concise example below the active control, such as **A cost of $10.00 becomes $11.00** for a 10% increase. Label this an example, not an aggregate prediction. Preserve the before/after table as the actual review surface.

The current disabled/read-only action area depends on an attempted interaction to explain that products must be fetched first. Prefer an always-visible prerequisite message: **Choose products to configure changes**, with the relevant action nearby. Do not animate the Get products button as the primary explanation.

### 3. Put filters and results in a stable arrangement

Keep Search, Category, and Product type prominent. Put less frequently used Brand, Stock status, Price range, and Current cost range under **More filters**. Expose applied filters as removable chips and show **Clear filters** as a secondary action. Category and brand menus need search and a selected-count summary; avoid long concatenated names in the closed control.

Keep an explicit **Find products** or **Refresh products** button rather than automatically querying on every keystroke. Move it beside the filter inputs instead of before them in the mobile reading order. After results load, allow the filter panel to collapse while retaining its summary.

Progressive disclosure supports presenting frequent controls first while keeping secondary capabilities available. [Nielsen Norman Group guidance](https://www.nngroup.com/articles/progressive-disclosure/).

### 4. Make the table the working surface

Recommended default columns: Selection, Product, Current cost, New cost, Change. Show SKU as secondary product text. Move ID and Type to optional columns or compact badges; retain access for users who depend on them.

Right-align monetary amounts and use tabular numerals. Give New cost clear emphasis. Keep plus/minus signs and text alongside color, as the current change badges already partly do. Use a comfortable default row height around 40–44 px and offer a compact density option later if demand warrants it.

Use a sticky table header and a contained horizontal scroll area. On narrow screens, keep product identity and selection visible while costs scroll. Keep pagination compact and include a direct page control if arbitrary jumps remain supported. Use a single table toolbar for selection, range actions, and Columns; place page size with pagination.

Carbon's data-table guidance groups utilities in a toolbar and provides selection and expandable-row patterns. It is a useful reference without requiring a Carbon dependency. [Carbon data-table guidance](https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines).

When values or filters change, retain the table's visual position and mark its contents **Preview needs refreshing**. Disable confirmation until the replacement preview arrives. Use a stable loading treatment rather than repeatedly replacing the workspace with an empty state. Never present stale values as ready to apply.

### 5. Keep scope visible through confirmation

Replace the separate Step 4 card with a sticky action bar inside the plugin workspace. Example: **538 selected · Set cost to $12.50** followed by **Review changes**. Keep it above the bottom safe area, clear of WordPress navigation, and ensure it cannot obscure focused controls or the last row.

The confirmation should summarize:

| Detail | Example |
| --- | --- |
| Operation | Set cost to $12.50 |
| Scope | 538 selected products and variations |
| Review coverage | 20 products reviewed; other selected products are calculated during processing |
| Persistence | Changes already saved are not rolled back by cancellation |

Show zero-cost clearing guidance prominently when relevant to the selected action/value. Keep the general rule available in help. Do not show the same long warning paragraph for every operation. Preserve the final confirmation and all existing freshness safeguards.

## CSV Import

Keep the top-level Bulk edit / CSV navigation, with a clear Import / Export sub-navigation inside CSV. The current radio controls work, but persistent subtabs would make the active task easier to scan. Describe this area as **CSV / Excel import** where appropriate because XLSX is supported.

Use this order:

1. **Upload a file.** Show a clear drop area with **Choose file**, supported formats, and the actual site limit. Put **Download template** beside it. After selection, show file name, size, **Replace**, and **Remove**.
2. **Validate file.** Use a task-specific preparation state with rows checked and a cancel control. Distinguish validation from changing product costs.
3. **Review import.** Show separate valid/invalid counts, the preview, row errors, and **Download error report**. State whether valid rows can proceed when invalid rows are excluded. A table filter for invalid rows is useful follow-up work, not existing functionality.
4. **Confirm and update.** Reuse the selection summary and final confirmation pattern from Bulk edit.

Keep the initial guidance short: **CSV or XLSX. Include new_cogs and either id or sku.** Put encoding, legacy cogs behavior, ID/SKU agreement, formulas, decimal rules, blank values, and zero handling under **File requirements**. Critical rules still need to appear contextually before an affected action.

The current empty state tells import users to get products and choose an action. Replace it with **Upload a file to preview your cost changes**. Hide table utility controls until a file produces rows.

Column mapping, saved mappings, and downloadable spreadsheets are potential later features. They require new functionality and should not delay the core usability fixes.

## CSV Export

Use **Choose products → Choose columns → Review export**. The existing column list comes before product selection and uses internal names such as `regular_price`; present friendly labels such as **Regular price**, keeping actual CSV headers in help or a small example.

Describe the automatically added `new_cogs` column explicitly. A small header example can show what the file will contain. A later **For updating costs** preset could select sensible columns, but retain access to every currently supported column.

Replace **Review changes** with **Preview export** and use an export-specific empty state: **Choose products to preview your export**. The final summary should state selected row scope, selected columns, and CSV format. Prefer **Create CSV** while preparation is required, followed by **Download CSV** when ready. Avoid a single label that blurs those two states.

The export page still displays a previous bulk-update success panel. Put completed tasks in **Recent activity**, labeled with task type and completion time. A prior update result should not appear to be the result of the export currently being configured.

## Progress, errors, and history

The existing completed panel repeats success in its title, a full progress bar, counts, and a success notice. Reduce completion to a concise result: **538 products updated**, completion time, elapsed time, and **View details**. Give skipped/failed counts emphasis only when nonzero, while retaining full detail access. Remove an approximate remaining time of 00:00:00 from completed tasks.

For active tasks, show the phase first: Collecting products, Updating costs, Validating import, or Creating CSV. Use determinate progress only with a reliable denominator. Place the latest update time and connection/retry status beside the phase, with Cancel or Refresh status nearby. Cancellation copy should say **Stop remaining updates** and explain that saved changes remain.

Keep recoverable errors beside the control that can resolve them. Show stale-preview guidance at the preview and action bar with **Refresh preview**. Do not rely on scrolling to a generic page-top notice. A completed task belongs to history; an active task remains visible across relevant modes with a clear task label.

## Visual system and responsive behavior

- Keep the WordPress system font. Aim for readable 14 px body text and 12–13 px secondary text; much of the current helper/table copy is small.
- Use one primary accent, neutral surfaces, subtle borders, 6–8 px radii, and minimal shadows. Reserve green, red, and amber for meaningful states. A cost increase is directional information, not automatically a positive business outcome.
- Remove either the decorative colored section tiles or the numbered circles. Keeping both makes every section compete for attention. Keep one compact hierarchy cue.
- Use consistent 8/16/24 px spacing and approximately 36–40 px desktop controls. Use larger touch targets on mobile.
- At 390 px, the long product name wraps beside the logo and Help button into a tall heading. Shorten it to **Bulk COGS Editor**, place a short subtitle below, and move Help to a compact secondary action.
- Mobile already avoids page-wide horizontal overflow in the observed empty states. Preserve that behavior. Collapse completed configuration, keep the primary action reachable, and use one-column action fields rather than six tall cards.
- Verify keyboard operation, visible focus, labels, contrast, zoom, and screen-reader selection/progress announcements during implementation. This review does not certify accessibility compliance.

WordPress provides shared dashboard components that can guide familiar control behavior and styling. Reuse where appropriate without making a framework migration a prerequisite. [WordPress component reference](https://developer.wordpress.org/block-editor/reference-guides/components/).

## Recommended delivery sequence

1. Correct task-specific headings, empty states, template placement, confirmation copy, and activity labels.
2. Unify selection and add the sticky scope/action summary, preserving current authorization and validation behavior.
3. Simplify action configuration, filters, and table organization.
4. Refine mobile layout, visual tokens, and active/error states.
5. Consider optional workflow features only after the simpler design is evaluated.

For future evaluation, ask a first-time store manager to update selected products across two pages, explain the selection before confirming, import a file with invalid rows, and export a subset with chosen columns. Check task success, scope understanding, recovery, and time spent searching for the next action. These are proposed evaluation activities; none were run as automated tests in this review.

Runtime implementation is unchanged by this review. Browser captures were retained under `/tmp/dkbce-ui-review/`; the temporary authentication session is removed after inspection. Active updates, imports, exports, and their failure cases were not exercised end to end.
