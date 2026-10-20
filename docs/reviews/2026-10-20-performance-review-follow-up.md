# Performance review follow-up

Reviewed on 20 October 2026 against HEAD `4a676ff` plus the working tree. This supplements [the original review](2026-10-20-performance-review.md) and [the range-query investigation](2026-10-20-range-query-profiling.md).

Several earlier recommendations are implemented, but some are only partially addressed. The highest priorities are cleanup continuation and XLSX restart behavior. Further opportunities remain in product hydration, import validation, retained state, and download workers.

This is a source review, not a benchmark. Complexity and query-count estimates below describe code paths, not measured latency or speed gains. No tests, database operations, queue execution, or product updates were performed. Runtime source and existing local edits were preserved.

## Scope and revision

Read every runtime PHP class, the bootstrap and autoloader, JavaScript and SCSS sources, build configuration, database schema, PHP quality helper, range profiler, and previous performance reports. Inspected local WordPress/WooCommerce source to confirm ID-query caching, product metadata loading, and Action Scheduler uniqueness. Dependencies were reference material, not part of the application audit.

Local edits in the admin controller, COGS service, JavaScript, and later the bulk processor changed during the review. Findings include the selection-preservation changes and a subsequent reviewed-row freshness check. The following fingerprints identify the earlier evidence snapshot; subsequent concurrent edits moved line numbers and added finding 11. This report does not certify edits made after its review cutoff.

| Source | SHA-256 |
| --- | --- |
| `admin/class-dkbce-admin-functions.php` | `c78212697110e231517640cd5a0907c31fce4b5ed9f2b3caa17b5b2a3c73f5dc` |
| `admin/class-dkbce-cogs-service.php` | `7a896832f596b66b94c834a94ff4d00a13eea87bae445db86839aa073badda4c` |
| `src/bulk-cogs-editor.js` | `f3d42a67f4f7023a68eb469b6095ea6c3e914e9c7f0b1897aa7aa096848c6a4f` |

Line references identify this working tree and may move with further edits. Plugin header and runtime constant both report 1.1.2. Preserve PHP 7.4, WordPress 6.7, WooCommerce 10.3, CRUD, public hooks, frozen batch sizes, durable outcomes, retry protection, and independent cancellation.

## Findings

P1 identifies the first work to address for the affected workload. P2 identifies substantive follow-up; ordering should depend on workload measurements.

### 1. P1: Cleanup continuation is rejected while its current action runs

**Evidence:** [bulk processor](../../admin/class-dkbce-bulk-processor.php), `enqueue_cleanup()` lines 105–110 and `cleanup_operation()` lines 133–150; [retention processor](../../admin/class-dkbce-retention-processor.php), lines 85–90. Related to original recommendations 9 and 11.

Both cleanup paths enqueue their next batch with the same hook, arguments, and group and `unique = true`. The installed Action Scheduler DB store rejects a unique insert when an identical action is pending **or running**. The callback itself is still running, so it suppresses its own continuation. The bulk helper then treats `as_has_scheduled_action()` as success because it finds that same running action.

Normal cleanup removes at most 100 snapshot options and, once snapshots are exhausted, 500 outcomes. A 50,000-product operation has 1,000 default chunks; cleanup can stop after the first 100 without a pending successor. Later progress polling can rescue another batch, and retention can revisit old terminal jobs, but neither provides autonomous completion. Retention likewise processes one 100-option page instead of immediately draining its backlog; its recurring action provides a later daily opportunity.

**Recommendation:** Give continuations a scheduling contract that permits one pending successor while the current action runs. Options include cursor-bearing arguments or guarded non-unique scheduling with the existing operation lock. Distinguish a pending successor from the current running callback. Keep cleanup idempotent and retain recovery for failed enqueue attempts.

**Acceptance:** A multi-batch cleanup and a retention backlog over 100 options drain without an open admin page or waiting for the next daily sweep. Repeated callbacks preserve ownership and bounded deletion.

### 2. P1: XLSX still reparses every completed prefix

**Evidence:** [import reader](../../admin/class-dkbce-import-reader.php), `xlsx_batch()` lines 170–238; [import processor](../../admin/class-dkbce-import-processor.php), lines 119–128. Original recommendation 3 remains incomplete.

Increasing the effective batch to `batch_size * 4` reduces the number of passes, but each call reopens XML at the beginning, resets `$index`, and reads through the persisted cursor. This affects shared strings and worksheet rows. The deadline is checked only after a new node is processed, leaving the entire old prefix outside the cooperative time checks.

For 50,000 row elements and full 1,000-element batches, the 50 main passes visit approximately 1,275,000 row starts, with another pass potentially required to discover EOF. Earlier cells and text are traversed too. This is an algorithmic estimate, not a timing result; time-limited smaller batches increase repeated work.

**Recommendation:** Implement a resumable intermediate representation or safely indexed parser state. Merely increasing batch size or adding a deadline inside the skipped prefix does not solve cross-request progress. Preserve private storage, XML safety, sparse cells, shared strings, duplicates, cancellation, and interruption recovery.

**Acceptance:** Late batches do not perform work proportional to all earlier rows. Measure first, middle, and final request duration and bytes traversed on representative XLSX files.

### 3. P2: Cache priming remains incomplete, including a separate worker metadata read

**Evidence:** [COGS service](../../admin/class-dkbce-cogs-service.php), `scan_matches()` lines 464–472 and `preview_row()` lines 339–344; [admin controller](../../admin/class-dkbce-admin-functions.php), `csv_preview_page()` line 931; [download processor](../../admin/class-dkbce-download-processor.php), lines 176–179; [bulk processor](../../admin/class-dkbce-bulk-processor.php), lines 484–497 and 742. Partly original recommendation 6; the worker's separate metadata cache is an additional opportunity.

Ordinary previews query IDs and immediately construct products without priming their posts, terms, or metadata. Local WooCommerce `query()` primes automatically only for its object-return path, and WordPress returns early for `fields = ids`. Import previews and exports prime product IDs but omit explicit parent priming. Preview attachment posts and attachment metadata are also read individually when images are distinct.

The update worker primes WordPress metadata, but its retry-marker `get_meta()` uses WooCommerce's separate raw metadata cache, which includes metadata IDs. On a cold WooCommerce cache, `WC_Data::read_meta_data()` still calls a per-product `read_meta()` SELECT. WordPress post-meta priming does not satisfy that cache. This is not permission to remove the retry marker or bypass WooCommerce persistence.

**Recommendation:** Add bounded product/parent/attachment priming where actually needed. Separately profile the worker's raw metadata reads; consider WooCommerce's `prime_raw_meta_data_cache()` with correct raw rows, cache groups, data-store filtering, and capability checks for supported implementations. Preserve fresh ownership/retry reads under product locks; do not introduce a stale marker through speculative caching.

**Acceptance:** Cold-cache previews avoid linear product/parent/image reads. Worker profiling distinguishes WordPress metadata from WooCommerce raw metadata, with correct retry behavior and extension filters intact.

### 4. P2: Import validation and large exclusions still issue per-row queries

**Evidence:** [CSV service](../../admin/class-dkbce-csv-service.php), `validate_row()` lines 47–96 and `sku_ids()` lines 133–166; [import reader](../../admin/class-dkbce-import-reader.php), lines 60–62 and 211–217; [import store](../../admin/class-dkbce-import-store.php), `put()` and `exclude()` lines 182–210. Original recommendations 4 and 6 are partly implemented.

SKU lookup now uses the indexed WooCommerce table when eligible, but still executes separately for every input row. Both readers validate and stage each row immediately, without first collecting a bounded set for SKU resolution and product/parent priming. Each staged product also performs a duplicate-target lookup after its write.

`exclude()` validates each selected ID through a scalar SELECT, then updates each ID separately in the Apply HTTP request. Excluding 10,000 distinct valid products therefore requests 10,000 validation SELECTs plus 10,000 UPDATEs before its final count. The UI can accumulate exclusions across pages; there is no corresponding small server-side bound.

**Recommendation:** Decode bounded batches, resolve SKUs together, prime products/parents, and preserve per-row errors. Batch staging and duplicate detection only with equivalent cross-batch/retry guarantees. Validate exclusions as bounded sets, then update those sets; retain the preview claim and fail-closed selection validation.

**Acceptance:** Validation reads scale by batches rather than rows. A large exclusion set avoids two queries per ID while missing IDs, duplicates, permissions, and interrupted validation remain correct.

### 5. P2: Import expiry bypasses bounded outcome deletion

**Evidence:** [import processor](../../admin/class-dkbce-import-processor.php), lines 192–200; [operation store](../../admin/class-dkbce-operation-store.php), `delete_outcomes()` lines 449–460. Remaining gap in original recommendation 9.

Normal operation cleanup limits outcome deletion to 500 rows. Applied imports preserve outcomes for reports, but expiry then calls `delete_outcomes()` with an unrestricted DELETE for the whole operation before deleting 1,000 staging rows. Every staging-cleanup action repeats the outcome deletion call. A large retained import therefore has a monolithic database cleanup step despite the bounded staging loop.

**Recommendation:** Persist separate outcome/staging cleanup phases and delete both in bounded batches. Coordinate expiry with active report generation so cleanup cannot remove data still needed by a download.

**Acceptance:** Expiring a large applied import avoids a single full-operation DELETE and resumes both phases after interruption. Active report generation retains its source dependency until completion or a deliberate expiry failure.

### 6. P2: Cancelling before the first outcome triggers a legacy full-chunk scan

**Evidence:** [operation store](../../admin/class-dkbce-operation-store.php), `summarize()` lines 346–373; [bulk processor](../../admin/class-dkbce-bulk-processor.php), `finish_operation()` line 648. Remaining gap in original recommendation 9.

Zero indexed outcomes plus any snapshot chunks is treated as a legacy operation. A current operation cancelled during snapshotting or before its first outcome satisfies that condition. Reconciliation reads every chunk option, although current chunks have empty legacy results. A 50,000-product snapshot can consequently require 1,000 option reads and retain those arrays in the request cache during cancellation. This occurs before terminal state and asynchronous cleanup are saved.

**Recommendation:** Persist the outcome-storage format in new operation state and use legacy reconciliation only for genuinely legacy operations. Keep legacy reconciliation resumable if large old jobs must remain supported.

**Acceptance:** Current jobs with no outcomes finalize without walking all snapshot chunks. Legacy jobs still recover their recorded results accurately.

### 7. P2: Large search and visited-ID sets still amplify state reads and writes

**Evidence:** [admin controller](../../admin/class-dkbce-admin-functions.php), preview `row_ids` merges at lines 1075, 1228, and 1251, and top-level search removal at line 1430; [COGS service](../../admin/class-dkbce-cogs-service.php), `matching_args()`; [download processor](../../admin/class-dkbce-download-processor.php), lines 117–130. Remaining gap in original recommendation 5, plus visited-page growth.

Search results are reused, but they remain complete ID arrays in preview transients and download filters. Each preview page reloads and rewrites the transient and merges all previously visited IDs. Visiting an entire large catalog produces cumulative state serialization proportional to the sum of visited-prefix sizes, rather than just the current page.

Download checkpoints rewrite their complete state, including frozen search IDs. The new selection-preservation code also stores historical `search_ids` inside `selection_filters`; Apply removes only the top-level `search_ids`, leaving those historical arrays inside frequently checkpointed worker state. Subsequent matching still emits large inclusion predicates. Later freshness edits also copy every visited row's `reviewed_rows` signature into worker state, adding another growing map to each progress write.

**Recommendation:** Store large frozen match sets and visited authorization sets separately from counters and page state, with indexed membership and bounded access. Preserve the new selection-intersection semantics and the existing search freshness policy; do not cap matches or loosen ID authorization.

**Acceptance:** Checkpoint payload sizes remain bounded as search matches and visited pages grow. Measure serialized bytes, SQL predicate size, and peak memory, including repeated filter refreshes with broad historical searches.

### 8. P2: Download request memory is not bounded by its 100-row batch size

**Evidence:** [download processor](../../admin/class-dkbce-download-processor.php), `process()` line 101 and `batch()` lines 176–198. Additional gap in the new download implementation.

The download loop can process an arbitrary number of pages within ten seconds. Each page primes posts, metadata, and terms, which remain in the request object cache. Discarding the local rows array does not release those cache entries. Fast catalogs with large descriptions or metadata can retain many more than 100 products before the deadline. Importantly, this is a memory-risk mechanism, not a measured exhaustion incident.

**Recommendation:** Add a conservative maximum pages/products per request and a measured memory watermark, with headroom before fetching the next page. Checkpoint and enqueue the next worker at either boundary. Avoid reinstating per-row global cache flushing.

**Acceptance:** Peak memory stays within a documented envelope for products with large metadata, while exports retain row parity and cooperative cancellation.

### 9. P2: New preview selection counting repeats an unchanged total query

**Evidence:** [admin controller](../../admin/class-dkbce-admin-functions.php), `ajax_preview()` lines 1054–1065. Applies to current local selection-preservation edits.

The ordinary page query already returns the full matching count. The controller then runs another counted query for `selected_count`, even when there are no exclusions and no selected range. Both totals describe the same matching set in that case. With metadata ranges, this repeats the expensive filter/count work during action changes and initial previews; the page-level runtime cache flush further limits reuse in the default cache.

Historical selection filters can also repeat identical metadata/taxonomy predicates across refreshes. The history is capped at 12, but repeated clauses can still add joins and category subqueries.

**Recommendation:** Reuse the page count when selection adds no restriction. For historical constraints, deduplicate only provably identical clauses while preserving intersections and extension hooks. Continue counting restricted selections and rejecting changed catalogs where required.

**Acceptance:** An unrestricted preview needs no second identical counted query. Excluded/ranged selections retain accurate totals; repeated filter refreshes do not add identical SQL clauses indefinitely.

### 10. P2: Interrupted downloads have no stale-worker recovery

**Evidence:** [download processor](../../admin/class-dkbce-download-processor.php), `process()` lines 77–135; [admin controller](../../admin/class-dkbce-admin-functions.php), `ajax_download_status()` lines 823–859; [JavaScript](../../src/bulk-cogs-editor.js), `waitForDownload()` lines 236–248. Additional lifecycle gap in the new download implementation.

The worker saves state and enqueues its successor only at the end of a request. A hard termination before that point leaves `status = processing` with no successor. Unlike bulk operations, download status does not recover stale workers. The browser continues polling until an error or the two-day expiry. The file-offset recovery mechanism exists, but there is no normal recovery path to invoke it.

**Recommendation:** Add a download ownership lock and stale-action recovery before enabling automatic retries. Checkpoint complete batches and recover only when no pending/running worker owns the job. Reuse file truncation to the last durable offset to avoid duplicate output.

**Acceptance:** A terminated download resumes without user restart, duplicate rows, or concurrent file truncation. Transient polling failures back off and retry without creating duplicate workers.

### 11. P2: New freshness preflight scales with every visited row in one Apply request

**Evidence:** [admin controller](../../admin/class-dkbce-admin-functions.php), `reviewed_rows()`, `revalidate_reviewed_rows()` lines 1295–1323, and its call from `ajax_apply()`; [bulk processor](../../admin/class-dkbce-bulk-processor.php), `process_product()` lines 733–736. These local edits arrived during the final review pass.

Preview paging accumulates freshness signatures. Apply then constructs and recalculates each reviewed product in a single HTTP request, before claiming the token or starting the queue. There is no product-count, deadline, or memory bound and no explicit cache priming. Staged imports additionally reread each value through `calculate()`'s `source_id` branch. Exclusion checks use `in_array()` inside that loop, adding work proportional to reviewed rows times exclusions when both sets are large.

The worker intentionally clears WordPress metadata for each reviewed product after acquiring its lock. That protects freshness but means earlier batch metadata priming cannot eliminate those reviewed-product reads. This is a deliberate correctness tradeoff to measure, not an instruction to remove the lock-time check.

**Recommendation:** Use an ID-keyed exclusion set and batch trusted staged values and product/parent reads. For large reviewed sets, design resumable preflight with durable review ownership before product writes; retain rejection of stale reviewed values and the final check under the product lock. Store signatures separately from frequently rewritten counters as described in finding 7.

**Acceptance:** Apply can validate a large reviewed selection within bounded requests and memory while preserving the promise that stale reviewed values prevent that update from starting. Locked worker freshness checks remain effective.

## Smaller remaining opportunities

These were already identified in the original report and remain visible in current source. They should follow the larger server-side work.

| Area | Current evidence | Remaining work |
| --- | --- | --- |
| Taxonomy UI | Admin `get_terms()` loads all categories/brands; JavaScript creates a checkbox for each native option | Bounded searchable term loading for stores with large taxonomies |
| Browser formatting | `money()` and `appendChangeCell()` construct `Intl.NumberFormat` repeatedly | Reuse currency/percentage formatters |
| Progress rendering | `renderOperation()` replaces the panel and timer on every poll; time rendering also rebuilds nodes | Update existing text/progress nodes and retain one timer |
| Preferences | `savePreferences()` serializes every save through a promise chain | Coalesce pending preference changes |
| Polling | Import, bulk, and download loops use fixed visible/hidden intervals; bulk polling stops after a fetch failure | Visibility-aware backoff, bounded retries, immediate refresh on return, responsive cancellation |
| Preview cache flushing | `scan_matches()` flushes the entire runtime cache once per page | Profile removing this page-level flush; per-row flushing is fixed, and pages are already bounded to 100 rows |
| Worker memory | Bulk worker caps ten chunks but permits up to 1,000 products per chunk | Measure peak memory and add a watermark before increasing batch sizes |

The existing range-query report remains the right constraint: no blanket lookup substitution, cursor rewrite, or generic postmeta index is justified by the small-catalog results. Further SQL changes need representative large-catalog plans and result parity, including historical selection intersections.

## Earlier improvements confirmed

- Preview cache flushing moved outside the per-product loop.
- Counts use bounded ID retrieval with WooCommerce pagination totals rather than loading every ID.
- SKU resolution has an indexed lookup path with verification and a metadata fallback.
- Import previews reuse staged values, and update chunks load them in one query.
- Shared-string caching is bounded.
- Downloads now use private server files and cursor-based background processing.
- Outcomes use indexed immutable rows rather than rewriting serialized chunks per product.
- Normal finalization reuses its computed summary and schedules bounded cleanup.
- Product locks no longer invalidate the shared negative-option cache.
- Terminal operation/claim retention exists.
- Class loading uses an autoloader, and version declarations agree.
- Range-query SQL plans and semantics were investigated and documented.

These are useful changes. The findings above address remaining paths and incomplete lifecycle guarantees, not a reason to discard those improvements.

## Suggested order and permitted verification

1. Repair cleanup continuation so large jobs reliably release temporary data.
2. Fix XLSX cross-request restart complexity for large spreadsheet workloads.
3. Complete read-path priming and batch import validation/exclusion handling.
4. Bound import expiry and distinguish current zero-outcome jobs from legacy storage.
5. Bound download memory and add safe stale-worker recovery.
6. Separate large membership sets from frequently written state and eliminate redundant preview counts.
7. Apply the small browser optimizations after measuring their contribution.

Future acceptance work should capture query counts, peak memory, serialized bytes, per-action execution time, queue waiting, and end-to-end duration separately. Compare cold/warm cache, CSV/XLSX, ID/SKU inputs, variation parents, large metadata, broad searches, cancellation before the first outcome, and interrupted cleanup/downloads. These are proposed checks; tests and product-update benchmarks still require authorization under project instructions.

This review changes documentation only. PHP formatting/PHPCS and frontend builds are unnecessary for this report; running their fix modes would touch unrelated work. Markdown lint, graph refresh status, and final diff checks are reported with the review result. The documented `npm run graph:update` script is still absent; the installed `graphify update .` CLI supplies the refresh without LLM extraction.
