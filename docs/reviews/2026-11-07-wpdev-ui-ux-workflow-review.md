# wpdev UI/UX workflow review

Review date: 2026-11-07 UTC. Status: awaiting manual review. No implementation approved by this report.

## Purpose and scope

Improve task completion, confidence, and repeat use of Bulk COGS Editor. Each finding below can be reviewed and implemented separately. Engagement benefits are hypotheses, not measured results.

The review used the UI/UX Pro Max skill and Playwright CLI against `https://wsl.com/wpdev/wp-admin/edit.php?post_type=product&page=bulk-cogs-editor`. Desktop viewport: 1440 × 1000. Mobile checks: 390 × 844 and 320 × 800. Markdown guidance was used for this report.

The live wpdev interface differs from the checked-out workspace. Findings describe the live interface. Reproduce each finding against the intended implementation branch before editing; local source locations below are navigation hints, not proof of deployed behavior.

No product updates, rollbacks, cancellation requests, settings saves, or preference changes were submitted. Preview requests can create temporary server-side preview state. No import file was uploaded and no export job was started. Existing history supplied completion and stalled-operation evidence.

## Coverage and limitations

| Workflow | Review performed | Limitation |
| --- | --- | --- |
| Entry and product selection | Initial layout, all-product search, filtered search, changed filters, empty result | No exhaustive taxonomy combination testing |
| Cost preview | Set exact cost to $12 in preview; inspected current/new costs, selection counts, and confirmation | Relative and clear-cost actions were not executed |
| Confirmation | Opened and dismissed dialog; checked initial focus, Escape, and focus return | Final update deliberately not submitted |
| CSV export | Inspected columns, filters, instructions, and empty preview | No export generation or download timing tested |
| CSV/XLSX import | Inspected requirements, upload limit, and missing-file validation | Valid, malformed, large, and formula-containing files not tested |
| History | Inspected list, completed operation, product changes, and existing stalled operation | Did not change or recover existing operations |
| Rollback | Opened review of a completed operation and inspected warning/acknowledgment | Did not confirm rollback |
| Settings | Read retention, rollback behavior, and force-clear guidance | Did not save or force-clear |
| Responsive and keyboard | Checked page width at 390/320 pixels; inspected mobile preview; tested modal Escape/focus return | Not a full accessibility, screen-reader, contrast, or cross-browser audit |

## What already works well

- Cost actions remain disabled until matching products are found, with an explanation.
- Preview presents current cost, new cost, and signed change before confirmation.
- Selection totals distinguish all pages from the current page.
- Changed filters mark the previous preview stale and block confirmation.
- Confirmation discloses review coverage and warns that saved changes remain after cancellation.
- The confirmation dialog initially focuses Cancel. Escape closes it and returns focus to the review button.
- Rollback review compares current and original values and requires acknowledgment of overwrite behavior.
- The tested bulk page has no page-level horizontal overflow at either 390 or 320 pixels.
- Existing progress details expose updated, skipped, failed, and last-reported information.

Preserve these behaviors when implementing the improvements.

## Review queue

P1 means fix first because the issue affects confidence, scope comprehension, or recovery. P2 improves task flow and clarity. P3 is an optional product enhancement. These priorities describe UX impact, not security severity.

All approval and completion boxes start unchecked. Approval applies only to the individual finding.

| ID | Priority | Finding | Evidence class | Approved | Implemented |
| --- | --- | --- | --- | --- | --- |
| UX-01 | P1 | Reset obsolete review counts after an empty search | Observed defect | [ ] | [ ] |
| UX-02 | P1 | Make whole-catalog selection an explicit decision | Observed behavior; proposed change | [ ] | [ ] |
| UX-03 | P1 | Keep current and new costs visible together on mobile | Observed friction | [ ] | [ ] |
| UX-04 | P1 | Suppress unreliable stalled-operation estimates | Observed defect | [ ] | [ ] |
| UX-05 | P1 | Explain the actual reason rollback is unavailable | Observed ambiguity | [ ] | [ ] |
| UX-06 | P2 | Align workflow steps, section numbers, and labels | Observed inconsistency | [ ] | [ ] |
| UX-07 | P2 | Reduce inactive content and scrolling | Observed friction | [ ] | [ ] |
| UX-08 | P2 | Clarify reset actions and their scope | Observed ambiguity | [ ] | [ ] |
| UX-09 | P2 | Provide one actionable empty-results state | Observed friction | [ ] | [ ] |
| UX-10 | P2 | Simplify preview and confirmation explanations | Observed friction | [ ] | [ ] |
| UX-11 | P2 | Use mode-specific CSV labels and guidance | Observed inconsistency | [ ] | [ ] |
| UX-12 | P2 | Make import validation specific and actionable | Observed missing-file message; proposed extension | [ ] | [ ] |
| UX-13 | P2 | Make history easier to scan and revisit | Observed friction; proposed enhancement | [ ] | [ ] |
| UX-14 | P2 | Separate current-operation status from rollback status | Observed inconsistency | [ ] | [ ] |
| UX-15 | P2 | Show rollback availability and retention consequences | Proposed enhancement | [ ] | [ ] |
| UX-16 | P3 | Add reusable selections and guided starting points | Proposed enhancement | [ ] | [ ] |
| UX-17 | P3 | Add useful completion actions | Proposed enhancement | [ ] | [ ] |

## Findings and acceptance criteria

### UX-01 — Reset obsolete review counts after an empty search

**Evidence:** Find all products, preview a $12 exact cost, then search `zzzz-no-match-uxreview` and click Find products. The page reports no matches but the disabled action still says `Review 20,097 changes`. The button is disabled; this review did not find an enabled path to apply that stale selection.

**Improvement:** Reset the action label and summary whenever the matching set becomes empty or invalid. Use `Review changes` while unavailable and explain the next required action.

**Acceptance criteria:**

- Reproducing the sequence leaves no old selection count in the review action.
- Confirmation remains disabled for an empty result.
- A subsequent successful search displays only the new selection count.

### UX-02 — Make whole-catalog selection an explicit decision

**Evidence:** With no filters, Find products returned 20,097 matches: 6,487 products and 13,610 variations. Entering a cost selected all 20,097 across all pages, although only 10 rows were displayed. Existing counts and confirmation coverage help, but the default scope is very broad.

**Improvement:** Offer an explicit choice between selecting visible products and all matching products. Show the total, product/variation split, and scope beside that choice. Consider starting with no selection; this is a behavior change requiring manual approval, not a cosmetic fix.

**Acceptance criteria:**

- Users explicitly choose whole-result selection before it becomes active.
- Page selection, global selection, exclusions, and range selection remain distinguishable.
- Confirmation uses the exact selected total and reviewed coverage.
- Pagination never silently changes the selection scope.

### UX-03 — Keep current and new costs visible together on mobile

**Evidence:** At 390 pixels, the preview table occupies a 328-pixel container but has 672 pixels of horizontal content. The initial view shows selection, product, and current cost; new cost and change are offscreen. The table also has its own vertical scrolling area. There was no page-level overflow.

**Improvement:** Prioritize product plus current/new cost in a compact mobile view. Put SKU/type in expandable details, or provide an obvious horizontal-scroll cue with a persistent product identity column. Avoid requiring both vertical and horizontal table scrolling for the basic comparison.

**Acceptance criteria:**

- At 390 and 320 pixels, users can compare current and proposed costs without losing product identity.
- Selection remains operable and clearly associated with each product.
- All secondary columns remain available.
- Keyboard users can reach and scroll any retained overflow container.

This is an usability improvement, not a claimed WCAG failure: data tables can qualify for the two-dimensional-layout exception in [W3C reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

### UX-04 — Suppress unreliable stalled-operation estimates

**Evidence:** An existing October 9 operation still showed `Processing`, `Waiting for a background worker…`, and `Cancellation requested…` on November 7. It showed elapsed time `695:21:09` and approximate remaining time `5547:27:29`. The interface already warned that no progress had been reported for at least two minutes.

**Improvement:** Show a clear waiting/stalled display state alongside the underlying operation status. Hide estimates when recent progress cannot support them. Present elapsed time in days/hours for long intervals and prioritize the last-progress timestamp and recovery instructions.

**Acceptance criteria:**

- Stale progress replaces remaining time with `Estimate unavailable until processing resumes` or equivalent.
- Cancellation-requested state clearly explains that worker acknowledgment is pending.
- Resumed progress restores estimates only after a meaningful sample exists.
- Status copy does not claim failure or successful cancellation without backend evidence.

The review establishes misleading presentation, not the backend cause of this historical stall.

### UX-05 — Explain the actual reason rollback is unavailable

**Evidence:** The historical operation showed: `Rollback is unavailable: this operation has no retained original values, is still running, has expired, or already has a rollback.` Nearby text reported zero retained original values and a processing state. The message lists alternatives instead of resolving them.

**Improvement:** Return and display the actual blocking reasons. Give a next step only when one is possible, such as waiting for completion or viewing an existing rollback.

**Acceptance criteria:**

- Each operation shows specific applicable reasons, not a generic list joined by “or.”
- Missing original values are distinguished from retention expiry and pending work.
- The UI never implies unrecoverable original values can be restored by refreshing.

### UX-06 — Align workflow steps, section numbers, and labels

**Evidence:** The top bulk stepper has three steps. The body labels selection as 1, cost update as 2, review cost changes as 3, review changes as 4, and recent activity as 5. Steps 3 and 4 have nearly identical names.

**Improvement:** Use one consistent sequence: Select products, Set cost update, Review and confirm. Treat recent activity as an unnumbered supporting panel. Either combine preview and confirmation preparation or clearly distinguish their roles.

**Acceptance criteria:**

- Stepper and body use the same number of steps and matching names.
- Current-step indication follows actual readiness.
- Recent activity never appears to be a required step.
- CSV and rollback flows have their own consistent sequence.

### UX-07 — Reduce inactive content and scrolling

**Evidence:** Initial view renders disabled cost controls, a large empty preview, a separate disabled review section, and recent activity. On mobile these form a long page before any useful preview exists. The review action follows the table rather than remaining close to the selection summary.

**Improvement:** Compact inactive sections into brief explanations and expand them when ready. Add a compact review summary near the table; consider a sticky action only if it does not obscure content or keyboard focus.

**Acceptance criteria:**

- Initial state emphasizes finding products and clearly explains the next step.
- A ready preview exposes its selection count and next action without scrolling through unrelated sections.
- Expanded sections remain discoverable and keyboard accessible.
- Any sticky action works at narrow widths and does not cover focused controls.

### UX-08 — Clarify reset actions and their scope

**Evidence:** `Clear filters`, `Start a new selection`, and `Clear selection` appear at different places in the same workflow. Their effects on filters, action values, selected rows, and preview are not evident from their labels alone.

**Improvement:** Use explicit labels such as `Reset filters`, `Deselect all products`, and `Start over`. Give Start over a short explanation of what will be discarded. Keep ordinary deselection immediate; do not add unnecessary confirmation dialogs.

**Acceptance criteria:**

- Each control has one clear, documented scope.
- Filter resets and row deselection do not unexpectedly discard unrelated work.
- A full reset clears obsolete summaries and restores the initial state consistently.

### UX-09 — Provide one actionable empty-results state

**Evidence:** The no-match search repeated `No products match the selected filters` and retained the generic promotional preview empty state. The prior example cost remained visible in disabled controls.

**Improvement:** Replace generic preview content with a concise no-results explanation and a useful recovery action. Distinguish first use, no matches, stale preview, and request failure.

**Acceptance criteria:**

- No-match state displays one primary explanation.
- Users can clear the search or adjust filters directly from that state.
- A retained cost value is clearly presented as a draft, not an active preview.
- Errors and loading states cannot be confused with zero matches.

### UX-10 — Simplify preview and confirmation explanations

**Evidence:** Every preview includes a dense paragraph about reviewed pages, recalculation during processing, and stale values. Confirmation gives a raw `Selection 20097` and explains that only 10 selected products were reviewed.

**Improvement:** Lead with plain-language scope: `Set cost to $12.00 for 20,097 selected products and variations`. Show review coverage separately. Keep the detailed consistency explanation in an accessible disclosure. Standardize number formatting. Label page-only summaries explicitly; do not imply the whole catalog was examined.

**Acceptance criteria:**

- Users can identify the action, value, scope, and review coverage without reading implementation details.
- Reviewed-page checks and cancellation consequences remain disclosed.
- Counts use consistent formatting and accurately include variations.
- Clear-cost and relative-action explanations remain accurate for empty/zero costs.

### UX-11 — Use mode-specific CSV labels and guidance

**Evidence:** The mode is named `CSV import / export`, but import supports XLSX. Export's empty preview still says `A clear view of every cost change` and `Preview first. Apply when you are ready`, although export does not change costs. Export instructions are a long paragraph containing several separate rules.

**Improvement:** Label the entry `Import / export`, then explicitly name supported formats per action. Use export-specific empty states and a short sequence: choose products, choose columns, preview, download. Explain `new_cogs` as “New cost” while retaining the required header.

**Acceptance criteria:**

- Export has no wording suggesting it will apply cost changes.
- Import advertises CSV/XLSX support without implying XLSX export support.
- Required identifier and new-cost columns are understandable before download/upload.
- Inherited filters remain visible when switching modes.

### UX-12 — Make import validation specific and actionable

**Evidence:** Clicking Validate file without a file produces `Choose a CSV or XLSX file within the WordPress upload limit`. The message combines missing-file, format, and size concerns. The live limit is 2 MB. Import requirements describe zero-cost behavior as “WooCommerce empty-cost behavior,” which can be unclear to a merchant.

**Improvement:** Give distinct inline messages for no file, unsupported format, and excessive size. Explain blank versus zero costs with a small example. For row errors, evaluate a grouped summary with row number, identifier, reason, and correction guidance; that part requires a separate import test.

**Acceptance criteria:**

- No-file validation says to choose a file and identifies the upload control.
- Size errors include the current site limit; format errors name supported formats.
- Blank and zero values have clear, accurate examples.
- Row-level errors, when tested later, provide actionable context without losing the selected file unnecessarily.

Use clear corrective instructions and associate inline errors with their controls, following [W3C form notification guidance](https://www.w3.org/WAI/tutorials/forms/notifications/).

### UX-13 — Make history easier to scan and revisit

**Evidence:** Full UUIDs lead each history row. Details are appended below the history list rather than replacing it. Selecting a mode or operation does not change the page URL. User filters include several raw `User ID` entries; the review did not determine whether those users were deleted.

**Improvement:** Lead rows with action, date, status, and affected count. Shorten displayed IDs with full-value copy access. Provide a dedicated details view or clear anchor, preserve filter/scroll context on return, and consider a permission-checked deep link to an operation. Use meaningful fallback labels for unavailable user names.

**Acceptance criteria:**

- Users can identify a recent operation without reading its UUID.
- Full identifiers remain accessible for support and search.
- Back navigation preserves list context.
- Any deep link handles unavailable/expired operations and unauthorized access correctly.
- Missing users are not mislabeled as known or active users.

### UX-14 — Separate current-operation status from rollback status

**Evidence:** History renders a section titled `Rollback progress and results` while showing a completed bulk update. Opening the older processing operation places its bulk progress beneath the same rollback heading.

**Improvement:** Name the panel for the operation actually being displayed. Distinguish the selected historical operation, a related rollback, and any globally current operation.

**Acceptance criteria:**

- A bulk update is never presented as rollback progress.
- Status panels identify the operation they belong to.
- Switching details cannot leave status from a different operation under the selected heading.

### UX-15 — Show rollback availability and retention consequences

**Evidence:** Settings explain that shortening retention also affects existing history. Rollback review warns that later changes will be overwritten and requires acknowledgment. The inspected history list does not show a rollback expiry date or availability summary.

**Improvement:** Show an availability/expiry summary in operation details. Before saving a shorter retention period, explain its impact on existing records. Show the effective overwrite/skip policy prominently in rollback review. Counts of affected records are optional and need backend support.

**Acceptance criteria:**

- Available rollback records show their retention deadline or an accurate unavailable reason.
- Retention reductions disclose irreversible consequences before saving.
- Rollback review clearly states the effective policy and preserves the current acknowledgment safeguard.
- Unknown impact is labeled unknown rather than estimated as fact.

### UX-16 — Add reusable selections and guided starting points

**Evidence class:** Product enhancement hypothesis. This is not a verified defect. The inspected entry view provides filters and help but no saved-selection control.

**Improvement:** Consider explicit, user-saved filter presets for recurring work and short starting points such as updating one category or importing supplier costs. Keep advanced filters available. Adding a new filter such as “missing cost” would be separate functionality, not merely a UI label.

**Acceptance criteria:**

- Applying a preset never starts an update.
- Saved criteria are visible, editable, and re-evaluated before confirmation.
- Removed categories or unavailable filters produce clear guidance.
- Scope, ownership, and storage of presets are agreed before implementation.

### UX-17 — Add useful completion actions

**Evidence class:** Product enhancement hypothesis based on an existing completed-operation panel. Fresh completion was not exercised.

**Improvement:** Pair outcome counts with useful next actions: View changed products, Review skipped/failed products when present, View operation history, and Start another update. Keep rollback secondary and subject to its existing safeguards.

**Acceptance criteria:**

- Completion distinguishes success, partial completion, failure, and cancellation.
- Relevant next actions appear without suggesting all selected products succeeded.
- A new workflow does not silently reuse an old broad selection.
- Recovery actions retain product-level outcomes and never automatically retry or roll back.

## Suggested implementation order

1. Review UX-01, UX-04, UX-05, and UX-14 first: specific misleading states with bounded fixes.
2. Decide UX-02 selection behavior, then align the flow through UX-06 and UX-10.
3. Improve layout and recovery with UX-03 and UX-07 through UX-09.
4. Improve transfer workflows with UX-11 and UX-12; authorize representative import/export tests separately.
5. Improve history and retention guidance with UX-13 and UX-15.
6. Evaluate UX-16 and UX-17 only after core workflow changes have been reviewed.

For each approved item, reproduce the issue, confirm the responsible source, implement the smallest complete change, and verify its acceptance criteria. Do not treat this report as authorization to implement every item or run data-changing scenarios.

## Evidence and follow-up validation

Browser evidence was captured temporarily under `/tmp/dkbce-ux-review/`: `initial.yml`, `desktop.png`, `preview.png`, `mobile.png`, `mobile-preview.png`, and `history.png`. These files are session artifacts and may not survive cleanup. Reproduction steps and relevant observed values are included above so the report remains useful without them.

Additional scenarios need explicit implementation-time planning: malformed import rows, partial import success, download completion/failure, relative and clear-cost previews, conflict handling, session expiry, fresh completion, cancellation acknowledgment, and rollback conflicts. Do not report those as passed based on this review.

Suggested measures after approved changes: time to first valid preview, successful task completion, selection corrections before confirmation, error recovery time, and repeated use of saved workflows. Establish a baseline before claiming improvement. No analytics instrumentation was added.

## Likely implementation areas

- `src/bulk-cogs-editor.js`: mode transitions, selection summaries, preview, dialogs, history, and progress presentation.
- `src/bulk-cogs-editor.scss`: responsive layout, tables, visual hierarchy, and spacing.
- `admin/class-dkbce-admin-functions.php`: rendered markup, translatable copy, and UI response data.
- Operation/history/settings services: only where precise status reasons, retention impact, or new preset storage require backend support; confirm actual files and ownership first.
- Generated assets and translation catalogs: regenerate through the project workflow when approved source changes require them; do not edit generated files manually.

No product code was changed for this review. The pre-existing `readme.txt` edit was left untouched.
