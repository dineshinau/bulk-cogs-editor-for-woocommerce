# Bulk update, import validation, and CSV export performance review

Reviewed on 25 October 2026. This records the source review and the agreed order
of improvements. The user requested implementation one improvement at a time,
with a review pause after each completed improvement.

## Evidence and reported baseline

This is a source-based review. No product-update benchmark, automated test,
database change, or queue execution was performed during the review. Query-count
estimates describe inspected paths; they are not measured time savings.

| Workflow | Reported result | Source |
| --- | --- | --- |
| Bulk COGS update | 10,000 products in 15 minutes 24 seconds; approximately 10.8 products/second | [Readme](../readme.txt), Performance for large catalogs |
| CSV creation | 10,000 records in 11 seconds | [Blog](https://dineshinaublog.wordpress.com/2026/10/11/bulk-edit-woocommerce-cogs/), step 10 |
| Apply imported costs | 10,000 products in 18 minutes 25 seconds | Same blog, step 19 |

The documented bulk-update count is 10,000, not 15,000. The readme reports editor
operation elapsed time from a WP-CLI run; CLI wall time was not measured
separately. The blog does not establish a separate import-validation duration.
These observations cover different workloads and are not controlled comparisons.

## Recommended improvements

### 1. Remove repeated journal reads during product updates

For a newly updated product, `process_product()` reads its history journal,
then `prepare()` reads it again before insertion and again afterward. This
normally produces three SELECTs for the same operation/product entry.

Reuse the journal lookup made under the product lock and return the inserted
data after verifying that insertion succeeded. Preserve the journal's original
and target values, null handling, retry behavior, and existing callers that do
not supply a prior lookup. This can remove two SELECTs per newly journaled
product: 20,000 fewer SELECTs for 10,000 such products, by source inspection.

Evidence: [bulk processor](../admin/class-dkbce-bulk-processor.php),
`process_product()`; [history store](../admin/class-dkbce-history-store.php),
`product()` and `prepare()`.

### 2. Validate and stage imported rows in batches

Each input row loads a product separately. SKU-only rows resolve SKUs individually.
Staging performs a REPLACE and, for resolved products, a duplicate lookup.

Collect bounded batches, resolve SKUs together, prime product and parent caches,
and batch staging writes and duplicate detection. Preserve permissions, ID/SKU
agreement, error reporting, and duplicate rejection across batches and retries.
This is the strongest structural opportunity in CSV validation.

Evidence: [CSV service](../admin/class-dkbce-csv-service.php), `validate_row()`
and `sku_ids()`; [import store](../admin/class-dkbce-import-store.php), `put()`.

### 3. Use the available import worker time more effectively

CSV preparation processes at most 250 records per action, then saves and
requeues even when its ten-second budget has time remaining. A 10,000-row file
requires approximately 41 actions including the header, assuming full batches.

Process multiple bounded batches within the worker budget, with cancellation
checks, durable cursor checkpoints, and a memory limit. Bulk updates already
reuse workers across chunks; import preparation does not.

Evidence: [import reader](../admin/class-dkbce-import-reader.php), `csv_batch()`;
[import processor](../admin/class-dkbce-import-processor.php), `process()`.

### 4. Reduce repeated progress-state writes

Every progress save rewrites and verifies the operation state, then replaces the
history index entry even when its indexed fields have not changed.

Update that index only when relevant fields change. Separate large reviewed-row
signatures and frozen selection data from frequently written progress counters.

Evidence: [operation store](../admin/class-dkbce-operation-store.php), `save()`;
[history store](../admin/class-dkbce-history-store.php), `index()`.

### 5. Batch the work performed when Apply is clicked

Import exclusions require one validation SELECT and one UPDATE per excluded
product. Excluding 5,000 products therefore requests approximately 10,000 queries
before processing starts. Reviewed-row preflight also loads products individually.

Validate exclusions as bounded sets and update those sets. Reuse batched product
reads and staged values in preflight while preserving authorization, frozen
selection, and freshness checks. Large preflight workloads need bounded execution.

Evidence: [import store](../admin/class-dkbce-import-store.php), `exclude()`;
[admin controller](../admin/class-dkbce-admin-functions.php),
`revalidate_reviewed_rows()`.

### 6. Optimize CSV creation selectively

Export already uses cursor queries, cache priming, and multiple batches per
worker. Its reported 11-second result makes it a lower priority than updates.

Prime variation parents, evaluate only requested column getters, and reduce large
selection data in saved state. Add memory limits before increasing its current
100-row batch size. Preserve WooCommerce getter behavior, authorized selection,
CSV quoting, formula protection, and resumable file offsets.

Exports use scheduler group `dkbce-bulk-cogs`; updates and validation use
`bulk-cogs-editor`. A CLI runner targeting only the latter does not process
exports. Review runner coverage when measuring queue delays.

Evidence: [download processor](../admin/class-dkbce-download-processor.php),
`GROUP`, `process()`, and `batch()`.

### 7. Fix repeated parsing for large XLSX imports

Each XLSX batch reopens XML and scans past previously processed rows before
reaching new rows. Later batches repeat increasingly large amounts of work.

Use a resumable intermediate format or another bounded parser design that avoids
rescanning completed prefixes. Preserve shared strings, sparse cells, formula
rejection, private storage, cancellation, and interruption recovery. CSV already
uses a byte cursor and does not have this restart pattern.

Evidence: [import reader](../admin/class-dkbce-import-reader.php), `xlsx_batch()`.

## Measurement and invariants

Measure product loading, journal persistence, WooCommerce saves and hooks,
staging, and gaps between workers separately before tuning batch sizes.
The current bulk worker uses 50-product chunks and a 20-second worker budget,
with up to ten chunks per callback. It already primes product/parent caches,
defers transient invalidation, batches imported-value reads, and avoids a full
product save when normalized costs are unchanged.

The readme's 15:24 run already used WP-CLI. Runner changes alone do not establish
further gains. See [Action Scheduler performance guidance](https://actionscheduler.org/perf/).

Keep WooCommerce CRUD, public hooks, durable outcomes, original-cost journals,
retry protection, frozen batch sizes, and independent cancellation. Use equivalent
starting catalogs for comparisons; repeating a set operation can otherwise turn
a changed-cost workload into an unchanged-cost workload. Record simple products
and variations separately, along with cold/warm cache conditions. Do not promise
a percentage speedup before measurement.

## Implementation sequence and review gate

Implement recommendations 1 through 7 in order. Stop after each for user review.
Approval of one step does not approve proceeding through all remaining steps.

### Step 1 acceptance and checks

- Change only the bulk processor and history store runtime paths needed to reuse
  the journal lookup and eliminate its post-insert SELECT.
- Keep a fresh journal read after acquiring the product lock. Reuse existing
  prepared entries, retain completed-entry handling, and keep exception recovery
  reads fresh.
- Persist original and normalized target values before saving a product. A failed
  insertion must prevent the product save. Preserve null and decimal-string values.
- Retain the existing three-argument `prepare()` call behavior for other callers.
- Run PHPCBF, PHP syntax checks, and PHPCS for both changed PHP files; review the
  diff, refresh Graphify, and confirm the affected symbol is indexed.
- Do not add or run tests, benchmarks, product updates, or commits without a
  separate request, following the project instructions.

### Step 1 implementation result

Committed in `ceadca7`, with graph and translation updates in `0b8f674` and
`f4a9681`. The user authorized proceeding to step 2. At the time of this review,
steps 3 through 7 had not started.

The worker passes its lock-protected journal lookup to `prepare()`. An existing
entry is reused; an absent entry is inserted without repeating the lookup.
After exactly one row is inserted, `prepare()` returns the inserted data without
another SELECT. Costs are converted to nullable strings before insertion, matching
the existing journal storage and readback representation. The optional argument
defaults to a fresh lookup for existing three-argument callers.

By source inspection, a new journal in the worker requires one history SELECT
instead of three. Reusing an existing prepared journal requires one instead of
two. The post-exception recovery path still reads persisted history afresh.
Product locks, journal confirmation, WooCommerce saves, and outcome persistence
retain their existing flow. No runtime speedup has been measured.

Checks completed:

- PHPCBF, PHP syntax checks, and PHPCS passed for both changed PHP files using
  `python3 scripts/php-quality.py all admin/class-dkbce-bulk-processor.php admin/class-dkbce-history-store.php`.
  The documented `npm run php:quality` command is absent from `package.json`;
  the existing helper was invoked directly.
- Markdown formatting was checked with the installed Markdownlint CLI and the
  installed WordPress Scripts Markdownlint configuration.
- Source review covered fresh journals, prepared-entry reuse, completed-entry
  handling, failed insertion, null costs, legacy callers, and exception recovery.
  This is static review, not executed behavioral testing.

Automated tests and product-update benchmarks have not been run.

### Step 2 plan and acceptance

Batch import validation and staging in the CSV service, import reader, and import
store. No schema changes, new dependencies, worker scheduling changes, or product
updates are part of this step.

1. Collect up to 50 input rows, flushing when buffered cell data reaches 64 KiB.
   A single larger record retains the existing per-record behavior.
2. Resolve SKU candidates in bounded queries that preserve database collation,
   per-SKU ambiguity limits, exact product verification, and metadata fallback.
   Prime product and parent caches before row validation.
3. Write validated rows together and detect duplicates against the whole staged
   job, marking every occurrence. Keep the scalar path for shared strings and
   oversized text that needs WordPress's per-column validation.
4. Advance CSV offsets and XLSX cursors only after staging and duplicate marking
   succeed. If the deadline interrupts validation, stage only its completed prefix.
5. Preserve header/row errors, permission checks, ID/SKU matching, formula-safe
   SKU handling, decimal precision, sparse XLSX rows, and replay safety.

Acceptance checks: PHP formatting, syntax, PHPCS, Markdown lint, source review of
the retry/deadline paths, graph refresh and symbol coverage, and final diff checks.
Behavioral tests and benchmarks remain unrun unless requested. Stop for user review
after this step; do not start recommendation 3.

### Step 2 implementation and review

Implemented bounded CSV/XLSX row validation and staging. The reader buffers up to
50 rows or approximately 64 KiB of cell data, then the CSV service resolves SKU
candidates in grouped queries, primes product/parent caches, and validates rows.
The import store writes the batch together and finds duplicate IDs across the job,
marking every occurrence with the existing error. Oversized text and XLSX shared
strings retain the scalar storage path. Input cursors advance only through rows
that were durably staged; a deadline leaves an unvalidated suffix to be retried.

The lookup query keeps the prior per-SKU `LIMIT 2`, exact SKU verification,
database collation, product status filters, and postmeta fallback. A failed or
unavailable optional lookup table also falls back to postmeta. No schema, product
data, worker scheduling, or dependency changes were made.

PHP formatting, syntax checks, and PHPCS passed for the three changed PHP files
with `python3 scripts/php-quality.py all admin/class-dkbce-csv-service.php
admin/class-dkbce-import-reader.php admin/class-dkbce-import-store.php`.
Markdownlint passed. A read-only check on local MySQL 8.0.46 confirmed the batched
lookup and fallback query plans use their SKU/meta-key indexes; `EXPLAIN` also
accepted the multirow staging statement without executing a write. Source review
covered replay, duplicate rows across batches, missing lookup-table fallback,
partial validation at the deadline, and cursor advancement. This is not behavioral
test coverage. Runtime speedup has not been measured; automated tests and the
planned product benchmark remain unrun.

Graph refreshed successfully (419 nodes, 895 edges); the `validate_rows` symbol is
present in the refreshed graph. Markdownlint and `git diff --check` passed. The
graph query is navigation evidence only; source review remains authoritative.

### Step 3 plan and acceptance

Reuse each import-preview worker for up to ten existing reader batches within its
ten-second cooperative deadline. Keep the operation's frozen batch size and the
reader's existing per-row limits. Check independent cancellation before each
batch, and save the staged cursor after each unfinished batch before continuing.
Stop starting additional batches when allocated memory reaches the lower of
32 MiB above the worker's starting allocation or 80% of PHP's configured limit.
An unlimited PHP setting retains the 32 MiB growth ceiling. These are cooperative
limits between batches, not hard limits on a single record's cost.

Affected code: `admin/class-dkbce-import-processor.php`. Reuse the existing reader,
operation lock, staging store, and scheduling path. Preserve ready/failed/cancelled
handling, private upload cleanup, and XLSX stage transitions. Verify by source
review, PHP formatting/syntax/PHPCS, Markdownlint, graph refresh and symbol review,
and `git diff --check`. No automated tests or import benchmarks are authorized.
Stop after this recommendation for user review.

### Step 3 implementation and review

Implemented the worker loop and memory ceiling in `DKBCE_Import_Processor::process()`.
An unfinished reader batch saves its durable state before another batch starts.
The loop checks the independent cancellation flag before every batch, and the
existing final cancellation check still takes precedence over completion. EOF
computes preview counts once; unfinished work follows the existing enqueue path.
Exceptions, terminal-state cleanup, and XLSX shared-string/worksheet transitions
retain their existing handling.

With the default CSV batch size, an action can now read up to 2,500 records rather
than 250 if time and memory allow. A 10,000-row file could therefore need about
five actions including its header instead of 41. This is a source-based upper
bound on work per action, not a measured speedup or an action-count guarantee.
XLSX still rescans XML on each reader batch; recommendation 7 addresses that
separate cost.

PHPCBF, PHP syntax checks, and PHPCS passed using
`python3 scripts/php-quality.py all admin/class-dkbce-import-processor.php`.
The formatter's alignment changes were reviewed. Source review covered terminal
states, cancellation before/between/after batches, checkpoints on partial reads,
deadline expiry, finite/unlimited memory settings, workbook-stage transitions,
and requeue failure. Behavioral tests and runtime benchmarks were not run.
Markdown, graph, and final diff check results accompany the handoff. Recommendation
4 has not started; this implementation is ready for user review.

### Step 4 plan and acceptance

Step 3 was committed in `31bdaa0`, with its graph refresh in `14aee51`.
Reduce progress-write work in the operation store and history index:

1. Compare the six indexed summary fields with the stored history row once per
   operation per request. Cache confirmed summaries for subsequent saves and write
   only when those fields change. Failed writes must remain retryable; deletion
   must invalidate the cached summary.
2. Store stable `reviewed_rows` and `filters` separately from progress counters.
   Keep the first save inline, so separate data always has a parent operation.
   On a later save with unchanged inputs, verify the separate option before
   replacing inline fields with a reference. Hydrate it through the existing
   `get()` API and fail closed if required fields are missing. If inputs change,
   save them inline again so interrupted writes cannot change an older checkpoint's
   selection. Keep download and import-preview storage outside this change.
3. Remove the separate data with retained operations. Preserve independent
   cancellation, outcome storage, fresh reads, and verification of progress saves.

Affected PHP: operation store, history store, and retention processor. Acceptance
checks are source review of initialization/retry/cleanup paths, PHP formatting,
syntax and PHPCS, Markdownlint, graph refresh, and final diff checks. Automated
tests and benchmarks remain unrun. Stop for review before recommendation 5.

### Step 4 implementation and review

Implemented selective history indexing and separate selection storage. History
indexing reads the current summary once per operation per PHP request, then skips
database work while operation ID, creation time, owner, action, status, and note
match the confirmed row. Status/note changes and missing rows still write the
index. Failed writes and summary deletion invalidate the request cache, so a
retry reads persisted data again. Progress counters still receive their existing
durable save and readback verification.

An existing operation with unchanged, nonempty selection inputs moves `filters`
and `reviewed_rows` into a non-autoloaded `dkbce_context_<operation-id>` option.
The progress record stores the expected field names, and `get()` restores those
fields before returning the usual complete state. Fresh reads invalidate both
options' caches. Missing, incomplete, or malformed selection data raises an error
instead of dropping matching constraints or reviewed-cost checks. Empty selection
inputs, import previews, and downloads retain inline storage.

The first save remains self-contained. A later save verifies the separate data
before compacting progress; interruption before that checkpoint leaves the inline
state authoritative. Changed inputs also return to inline storage before a later
save can replace the separate option. Retention removes selection data only after
successfully deleting the parent operation. Existing inline jobs remain readable
and adopt compact storage on a subsequent qualifying save.

This changes the persisted format for compacted operations. Before downgrading to
code predating this change, expand their selection fields back into each operation
option using the current reader; older code cannot hydrate `context_fields`.

Expected savings are fewer history writes and smaller progress writes/readbacks.
Loading a compact operation adds a selection-option read, normally served from
the option cache after the first read in a request. Initial compaction also needs
one selection write and readback. No runtime speedup has been measured.

PHP formatting, syntax checks, and PHPCS passed for all three changed files using
`python3 scripts/php-quality.py all admin/class-dkbce-operation-store.php
admin/class-dkbce-history-store.php admin/class-dkbce-retention-processor.php`.
The translation template was regenerated for the missing-selection error. Source
review covered inline/compact reads, failed or interrupted saves, changed inputs,
index failure/retry/deletion, independent cancellation, selection reuse, history
display, and retention. Automated tests and product benchmarks were not run.
Markdown, graph, and final diff check results accompany the handoff. Recommendation
5 has not started; step 4 is ready for user review.
