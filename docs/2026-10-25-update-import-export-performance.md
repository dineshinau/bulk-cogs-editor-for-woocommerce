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

Implemented and awaiting user review. Steps 2 through 7 have not started.

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

Graph refresh and final diff checks are reported with the handoff. Automated
tests and product-update benchmarks have not been run.
