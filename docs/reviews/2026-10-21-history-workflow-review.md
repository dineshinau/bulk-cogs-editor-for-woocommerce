# History workflow review

Date: 2026-10-21. Reviewed implementation: `b17c366`, with graph refresh
`5126877`.

## Current status

All six findings and the additional snapshot-count observation are fixed in the
working tree. Focused browser checks and controlled failure checks passed on
2026-10-21; see [fix verification](2026-10-21-history-manual-testing.md#fix-verification).
The findings below describe the original implementation and retain its original
evidence references. They are historical observations, not outstanding defects.

- **1. Ambiguous save.** Re-read persisted marker and target; confirm successful
  writes and defer unreadable recovery.

- **2. Incomplete rollback.** Track restored source rows; review and retry only
  remaining rows after a terminal attempt.

- **3. Interrupted startup.** Serialize startup and cleanup with an expiring
  ownership lock; repair orphan claims and verify state persistence.

- **4. Retention extension.** Recheck current retention before deletion; record
  when destructive cleanup has started.

- **5. Lost draft.** Preserve the editor preview and selection across History
  and Settings visits.

- **6. Historical recovery.** Poll active details independently and invoke
  stale-worker recovery on refresh.

- **Repeated snapshot counts.** Read remaining IDs through a bounded cursor
  without a full count per batch.

## Original result and scope

Six issues were found: three high-priority recovery or data-integrity issues and
three medium-priority retention or user-flow issues. The initial review applied
no behavior fixes; the follow-up implementation is described above.

The review followed operation creation, original-cost capture, history indexing
and filtering, product-change pagination, rollback review and confirmation,
background processing, cancellation, progress recovery, settings, and retention
cleanup. Findings below are supported by source inspection. The initial pass did
not execute the proposed reproductions. Subsequent browser and controlled
failure results are documented in [History manual testing][manual-source-1].

Read-only inspection of the current development database found:

- 110 indexed operations, including one completed rollback.
- 1,076 confirmed product journal entries and no prepared entries.
- One rollback claim and no orphan rollback claims.
- Retention set to 30 days and rollback behavior set to overwrite.
- The completed rollback processed 538 products successfully, with no failures
  or skips. All 538 recorded rollback targets matched the corresponding source
  operation's original costs. This checks retained records, not current product
  values or every failure path.

The initial source-review pass made no product, settings, or cleanup changes.
The subsequent manual-testing pass used disposable fixtures, restored settings,
and removed its test data; see the linked manual report. Fix verification used a
second isolated set of disposable products and also removed its test data.

## Findings

### 1. P1: A post-save exception can leave a changed product outside rollback history

Evidence: [bulk processor, lines 851–881][manual-source-2], [durable outcomes,
line 551][manual-source-3], and [confirmed-only history query, line
240][manual-source-4].

The worker prepares the original-cost journal, calls `$product->save()`, and
only then sets `$product_saved = true` and confirms the journal. WooCommerce
saves product data before firing `woocommerce_after_product_object_save`; an
exception from that hook can therefore occur after the database write but before
`save()` returns. The installed WooCommerce implementation shows that ordering
in `includes/abstracts/abstract-wc-product.php`, lines 1565–1584.

In this case, the catch block sees `$product_saved` as false and returns a
failed outcome. The batch persists that outcome and does not retry the product.
Its journal stays prepared, while both Product changes and rollback snapshots
select only confirmed entries. A product can therefore retain its changed cost
but be omitted from rollback eligibility.

Proposed reproduction: in an isolated environment, make an after-save callback
throw after a product cost has been persisted; inspect the saved cost, failed
outcome, and prepared journal.

Recommended fix: reconcile the saved operation marker and persisted cost after
an ambiguous save exception. Recover journal confirmation through a retryable
path when the write actually succeeded, rather than permanently recording
failure without reconciliation.

### 2. P1: Cancelled, failed, or skipped rollbacks cannot be retried

Evidence: [rollback eligibility, lines 333–335][manual-source-5], [claim
creation and reuse, lines 392–403][manual-source-6], and [claim deletion at
expiry, line 131][manual-source-7].

Any existing `dkbce_rollback_<source>` claim disables Review rollback. A

repeated start request returns the existing operation even when it is terminal.
The claim is not released after cancellation, processing failure, or a completed
rollback that skipped changed products. There is no resume or
retry-remaining-products path.

For example, cancelling after 100 of 538 products are restored leaves the other
438 products changed, with no supported way to finish that source operation's
rollback. Similarly, switching Settings from skip-changed to overwrite cannot
restore products skipped by an earlier rollback.

Proposed reproduction: cancel a rollback partway through, or complete one with
skip-changed products; reopen its source operation and attempt another reviewed
rollback.

Recommended fix: distinguish duplicate requests for the same attempt from a new,
explicitly reviewed recovery attempt. Allow resuming or retrying remaining
products after a terminal incomplete rollback, while preserving durable outcomes
and preventing concurrent attempts.

### 3. P1: Interrupted rollback startup can leave an unrecoverable orphan claim

Evidence: [claim creation, lines 391–403][manual-source-8], [operation save,
lines 438–443][manual-source-9], and [state persistence, lines
106–114][manual-source-10].

The source claim is created before the rollback state is saved. If the PHP
process terminates between those writes, the claim points to a nonexistent
operation. Subsequent requests return “This rollback is already being prepared,”
but there is no timeout, claim repair, or recovery job. The source remains
blocked for its retained lifetime unless repaired manually.

The state store also ignores the return values of `add_option()` and
`update_option()`. A failed option write can therefore bypass the controller's
exception cleanup and leave a claimed, queued operation with no durable state.

Proposed reproduction: interrupt startup immediately after claim creation,
before the operation option exists. Retry through the source operation's review
flow.

Recommended fix: use a recoverable startup claim with creation time and
ownership, verify operation persistence before enqueueing, and reclaim abandoned
claims atomically. Coordinate repair with retention so active startup cannot be
mistaken for an abandoned attempt.

### 4. P2: Queued cleanup ignores a subsequently extended retention period

Evidence: [retention queues cleanup, lines 120–126][manual-source-11] and
[cleanup deletes history, lines 130–157][manual-source-12].

The retention sweep checks expiry and persists `history_cleanup_pending`. The
background cleanup worker later deletes journal rows based on that flag without
rechecking the current retention setting. If an administrator extends retention
after the cleanup is queued but before deletion begins, history still gets
deleted even though it is now inside the configured period.

Proposed reproduction: let a 31-day-old operation qualify under 30-day
retention, hold its queued cleanup, change retention to 90 days, and then run
the held cleanup in an isolated environment.

Recommended fix: recheck current expiry and active rollback dependencies under
the operation lock before the first destructive history batch. Define the
behavior when retention changes after partial deletion; do not present partially
purged originals as a complete rollback source.

### 5. P2: History and Settings navigation discards the current review and selection

Evidence: [mode-switch handler, lines 797–807][manual-source-13], [preview
invalidation, lines 1094–1119][manual-source-14], and [selection reset, lines
1125–1138][manual-source-15].

Every tab switch calls `resetSelection()` and `invalidatePreview(true, true)`,
including visits to History and Settings. Those calls clear the preview token,
reviewed rows, selected and excluded IDs, selected range, product count, and
rendered preview. Returning to Bulk edit or CSV import/export requires
rebuilding the review and selection.

This is especially disruptive when an administrator opens Settings to inspect
rollback behavior or History to inspect an earlier operation while preparing a
narrowly selected update.

Proposed reproduction: preview products, select a subset, visit History or
Settings, and return to the original editor tab.

Recommended fix: preserve editor state when entering or leaving
informational/settings tabs. Invalidate it only when an actual editor input or
cost-changing operation requires a fresh preview.

### 6. P2: Historical active-operation details do not poll or recover stalled jobs

Evidence: [historical details rendering, lines 383–389][manual-source-16],
[history-detail endpoint, lines 288–363][manual-source-17], and [existing
progress recovery, line 1745][manual-source-18].

Opening View details for an active historical operation renders a snapshot and
adds a manual Refresh status button. It does not start the existing progress
polling loop. Refresh calls `history_detail`, which does not call
`recover_if_stale()`, unlike the normal progress endpoint.

Consequently, an administrator monitoring an operation other than their latest
operation sees stale progress until manually refreshing. If that operation has
lost its queued worker, refreshing its historical details cannot trigger the
recovery supported by the regular progress flow.

Proposed reproduction: inspect an active operation that is not the current
user's latest operation. For the recovery case, use an isolated stalled
operation with no queued worker and refresh its historical details.

Recommended fix: attach an operation-specific polling/recovery path while
historical details are open, and stop it when the viewed operation changes or
the tab closes. Keep it independent of the current editor's operation state.

## Existing safeguards and limits

Source inspection found nonce and capability checks, operation ownership/manager
access checks, an explicit rollback confirmation token bound to the user and
policy, frozen batch sizes, per-product locks, independent cancellation
requests, WooCommerce CRUD writes, and durable original/target values.
Skip-changed behavior compares costs again during processing. Legacy operations
without originals are intentionally summary-only.

The previous reserved AJAX `action` collision is fixed: History sends
`action_type`, and the controller maps it to the stored action filter.

These observations do not establish fault tolerance under all save hooks,
database failures, or concurrent requests. The subsequent manual report
distinguishes observed administrator flows, controlled fault checks, and
untested concurrency or role scenarios.

## Additional performance observation

`DKBCE_History_Store::products()` runs a full confirmed-row count for every
call, including each cursor-based rollback snapshot batch. The snapshot path
uses the rows and already has a reviewed expected total. A separate count-free
cursor read would avoid repeated aggregate work on large histories. No
throughput or regression claim is made because this review did not benchmark it.

## Original recommended order

1. Reconcile ambiguous saved products and repair interrupted startup claims.
2. Add a supported recovery path for incomplete rollbacks.
3. Recheck retention before destructive history cleanup.
4. Preserve editor state across History/Settings navigation and connect
   historical progress recovery.
5. Use the documented manual scenarios to verify fixes when implemented.

[manual-source-1]: 2026-10-21-history-manual-testing.md
[manual-source-2]: ../../admin/class-dkbce-bulk-processor.php#L851
[manual-source-3]: ../../admin/class-dkbce-bulk-processor.php#L551
[manual-source-4]: ../../admin/class-dkbce-history-store.php#L240
[manual-source-5]: ../../admin/class-dkbce-history-controller.php#L333
[manual-source-6]: ../../admin/class-dkbce-history-controller.php#L392
[manual-source-7]: ../../admin/class-dkbce-retention-processor.php#L131
[manual-source-8]: ../../admin/class-dkbce-history-controller.php#L391
[manual-source-9]: ../../admin/class-dkbce-history-controller.php#L438
[manual-source-10]: ../../admin/class-dkbce-operation-store.php#L106
[manual-source-11]: ../../admin/class-dkbce-retention-processor.php#L120
[manual-source-12]: ../../admin/class-dkbce-bulk-processor.php#L130
[manual-source-13]: ../../src/bulk-cogs-editor.js#L797
[manual-source-14]: ../../src/bulk-cogs-editor.js#L1094
[manual-source-15]: ../../src/bulk-cogs-editor.js#L1125
[manual-source-16]: ../../src/bulk-cogs-editor.js#L383
[manual-source-17]: ../../admin/class-dkbce-history-controller.php#L288
[manual-source-18]: ../../admin/class-dkbce-admin-functions.php#L1745
