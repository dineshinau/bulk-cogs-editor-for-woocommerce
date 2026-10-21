# History manual testing

Date: 2026-10-21. Implementation: `b17c366`. This supplements the
[source review][manual-source-1].

## Current status

All six reported issues have been fixed and rechecked. The original observations
below remain as the before-change record. See [Fix verification](#fix-verification)
for current results.

## Original scope and method

Browser checks used the local WordPress administration page at
`https://wsl.com/wcdev`, signed in as an administrator. Browser interactions
exercised actual controls and AJAX responses; no Playwright test suite was run.
Controlled failure checks used temporary WP-CLI scripts and the existing plugin
methods. No application code or installed hooks were changed.

Three disposable products were created with original costs of 10, 20, and unset.
Every cost-changing operation was restricted to those products. Notes and SKUs
used the unique prefix `dkbce-manual-20261021191038`.

The local background queue left the source operations pending during
observation.
For deterministic verification, only the specific fixture jobs were advanced
through `DKBCE_Bulk_Processor::process_operation()` using WP-CLI. Browser
polling
then displayed their terminal results. Automatic queue-runner execution was not
established by these checks.

## Browser results

| Check | Result | Observed evidence |
| --- | --- | --- |
| History loads | Pass | 110 original operations. |
| Numbered pagination | Pass | Page 2 showed different records. |
| Go to page | Pass | Page 3 displayed. |
| Page size | Pass | 20 rows; page 1 of 6. |
| Inline Show entries | Pass | Row layout confirmed. |
| Status filter | Pass | 10 cancelled; all matched. |
| Type filter | Pass | 19 set operations; all matched. |
| User filter | Pass | 109 admin operations; all matched. |
| Empty search | Pass | No operations found. |
| Reversed date range | Pass | Server rejected reversed dates. |
| Legacy details | Pass | Summary only; no originals. |
| Retention settings | Pass | 90/45 saved; 30 restored. |
| Invalid retention | Pass | Zero rejected. |
| Rollback settings | Pass | Both behaviors saved. |
| Confirmation gate | Pass | Checkbox required. |
| Overwrite review | Pass | Current 99; restore to 20. |
| Skip-changed review | Pass | Edited product marked skipped. |
| Queued cancellation | Pass | Cancelled; zero products updated. |
| Preserve editor draft | Fail | Selection and preview cleared. |
| Retry after skips | Fail | No retry after policy change. |
| Orphan claim handling | Fail | Review blocked by seeded claim. |
| Historical polling | Fail | One request in six seconds. |
| Stalled-job recovery | Fail | Refresh did not queue worker. |

The expected HTTP 400 from reversed-date validation appeared in the browser
console. It corresponds to the rejected input, not an unhandled JavaScript
error.

## Verified cost-changing flows

### Overwrite

The browser confirmed a set-to-30 operation on exactly the three fixture
products.
After it completed, one fixture was edited to 99. The browser then reviewed and
confirmed overwrite rollback.

| Product | Original | Before rollback | After rollback |
| --- | --- | --- | --- |
| Fixture 1 | 10 | 30 | 10 |
| Fixture 2 | 20 | 99 | 20 |
| Fixture 3 | Unset | 30 | Unset |

The worker reported three successes, zero skips, and zero failures. This also
verified restoration of an unset original cost.

### Skip changed

A second browser-confirmed operation set all three fixture costs to 40. Fixture
2
was then edited to 99 and Settings was changed to skip-changed. The review
marked
that fixture as skipped before confirmation.

| Product | Original for this operation | Before rollback | After rollback |
| --- | --- | --- | --- |
| Fixture 1 | 10 | 40 | 10 |
| Fixture 2 | 20 | 99 | 99 |
| Fixture 3 | Unset | 40 | Unset |

The worker reported two successes, one skip, and zero failures. After restoring
the overwrite setting, the source still had zero Review rollback buttons. This
reproduced source-review finding 2 for an incomplete rollback with skipped work.

### Cancellation

A third browser-confirmed operation set the three fixture costs to 50. Its
rollback was confirmed and then cancelled through the visible Cancel rollback
button while still queued. The cancellation response set `cancel_requested` to
true. The worker ended cancelled with zero products processed; all three costs
remained 50.

Mid-batch cancellation was not tested. This result demonstrates cancellation
before snapshot processing, not interruption after partial restoration.

## Controlled failure checks

### Finding 1: Save persisted, but the journal was not confirmed

A temporary callback threw from `woocommerce_after_product_object_save` only for
one fixture product and one fixture operation. The callback existed solely in
that WP-CLI process and was removed in a `finally` block.

Observed result:

- The product's persisted cost was 70.
- The operation ended `completed_with_errors`, with one failed product.
- Its product journal remained `prepared`.
- The confirmed-only history query returned zero rollback rows.

This reproduces a changed product being excluded from rollback history after a
post-save exception. No other product was targeted by the injected callback.

### Finding 3: Orphan startup claim

A disposable source operation and a claim pointing to a nonexistent rollback
UUID were seeded to represent the state left by interrupted startup. Opening
that source in the browser blocked Review rollback and offered View rollback
operation instead.

The resulting orphan state was exercised; an actual PHP process was not killed
during an HTTP startup request. Both the source and orphan claim were removed
during cleanup.

### Finding 4: Retention extended before deletion

A synthetic fixture operation was aged to 31 days. The real retention method
queued its history cleanup under the 30-day setting. That operation's scheduled
cleanup action was held, retention was extended to 90 days, and the existing
cleanup worker was invoked for that fixture only.

Observed result: all original-value rows were deleted and
`history_cleanup_complete` became true while the configured retention was 90
days. The original setting was restored in a `finally` block. This reproduces
the missing execution-time retention check without waiting for daily cron.

### Finding 6: Historical refresh did not recover a stalled operation

A synthetic fixture operation was marked processing with a ten-minute-old
progress timestamp and no scheduled worker. Opening it in History produced one
detail request. There were no additional detail requests during six seconds;
clicking Refresh status produced the second request.

The refreshed status remained processing. A server-side check after the browser
refresh still found no scheduled worker for that operation. The fixture was
removed without allowing it to update products.

## Cleanup and evidence

Cleanup completed and was checked from both the server and browser:

- Removed the three fixture products, IDs 1871, 1872, and 1873.
- Removed ten fixture operations and their history, outcomes, claims, and
  chunks.
- Unscheduled only background actions belonging to those fixture operations.
- Removed review tokens associated with the fixture operations.
- Restored 30-day retention and overwrite behavior.
- Restored the administrator's original latest-operation reference.
- The final browser search found zero operations matching the fixture prefix.
- The history total returned to the original 110 operations.

Temporary screenshots, fault results, cleanup results, and browser artifacts are
under `/tmp/dkbce-manual-history/`. They are local evidence, not durable
repository
attachments. Test-tagged WooCommerce log messages may remain as diagnostic
records. No administrator account was created or removed.

## Original verification limits

All six source-review issues have supporting manual observations or controlled
state/fault reproductions, with their distinctions documented above. That initial
verification preceded the fixes described below.

This run did not cover shop-manager/customer roles, mid-batch cancellation,
real concurrent requests, actual process termination during startup, browser
reload during an active rollback, large datasets, or automatic runner recovery.
The existing test suites were not executed. These results should not be treated
as proof of complete concurrency or failure safety.

## Fix verification

Date: 2026-10-21. Verified the current working tree with administrator browser
controls, real AJAX requests, and temporary WP-CLI scripts calling the real
worker. No repository test suite was added or run.

Three fresh disposable products used prefix `dkbce-fixes-20261021193212`.
Existing catalog products were outside every cost-changing operation.

- **1. After-save exception — Pass.** A scoped after-save exception left the
  operation completed with one success, a confirmed journal, and one rollback
  row.

- **2. Retry after skips — Pass.** First attempt restored two products and
  skipped one. After changing policy, the retry processed only that remaining
  product. A later edit to an already-restored product remained 77.

- **2. Retry after cancellation — Pass.** Cancelled before processing; a fresh
  reviewed attempt restored all three remaining products.

- **2. Retry after failure — Pass.** A scoped before-save failure produced two
  successes and one failure. The retry restored one product; a later edit to an
  already-restored product remained 66.

- **2. Browser retry — Pass.** Source details offered Review rollback again;
  review showed one row. Checkbox confirmation and actual rollback AJAX started
  a new attempt; it completed with one success.

- **3. Startup protection — Pass.** A held source lock rejected another start.
  After release, an orphan claim was repaired. Active and repeated same-token
  confirmations reused the same attempt.

- **3. Failed persistence — Pass.** A scoped option filter suppressed a state
  write; save threw and retained the prior durable state.

- **4. Extended retention — Pass.** Queued cleanup under 30 days, extended to 90
  before execution: the 31-day-old operation retained its original row and
  cleared pending history cleanup.

- **5. Bulk draft — Pass.** Bulk → History → Settings → Bulk retained identical
  preview content and both selected products.

- **5. CSV export draft — Pass.** CSV export → History → Settings → CSV retained
  identical preview content and both selected products.

- **6. Historical polling — Pass.** Three detail requests in about six seconds;
  zero additional detail requests during three seconds after leaving History.

- **6. Stalled recovery — Pass.** Opening historical details queued the isolated
  stale operation in Action Scheduler.

- **Snapshot counts — Pass.** Query capture during the browser-confirmed
  rollback recorded zero COUNT queries against the history-products table.

Successful rollback journals now mark original source rows restored while
preserving their recorded values for the normal history view. New review tokens
cover remaining rows only. Previously completed rollback journals are reconciled
into those checkpoints when the source is opened. Already restored products are
excluded even if their current cost later changes.

Startup, source retention scheduling, and destructive cleanup share the source
operation lock. State writes are read back before enqueueing. A persisted cleanup
started flag prevents offering a partial history as a complete rollback if
retention is extended after deletion has begun.

The stale test operation's scheduled worker also ran during browser verification
and changed only its disposable product. The later browser retry preserved that
already-restored product at 90 and restored only the remaining product to 20.
Focused retry checks separately verified preservation of deliberate later edits
at 77 and 66.

Cleanup removed all 17 fixture operations and all three disposable products.
History returned to 110 operations with zero fixture entries. Retention returned
to 30 days, policy to overwrite, and the administrator's original latest-operation
reference was restored.

Evidence is local to `/tmp/dkbce-history-fixes/`: `results.json`,
`failed-retry-results.json`, `cleanup-results.json`, temporary scripts, and
`browser-artifacts/`. These temporary files are not repository artifacts.

PHP syntax, PHPCBF and PHPCS passed for all five affected PHP files. Frontend
source passed syntax checking and the production build. Translation extraction,
Markdown lint, diff checking, and the project graph refresh are completion checks.

Limits: these checks did not stress simultaneous processes, process termination
at every startup instruction, mid-batch cancellation, partial destructive cleanup
over many batches, non-administrator roles, or large datasets. Query capture
verifies removal of the repeated aggregate; it is not a throughput benchmark.

[manual-source-1]: 2026-10-21-history-workflow-review.md
