# WooCommerce performance review

Review date: 20 October 2026. Reviewed revision: `b38deea`, with a clean working tree before this report.

The largest opportunities are in product previews and large imports. The bulk update worker already includes several useful optimizations. Improve the repeated reads and avoidable scans first; consider persistence changes only after measuring their contribution.

This is a source-based review, not a benchmark. The code patterns below are confirmed by inspection. Their production latency, query counts, memory consumption, and potential speed gains have not been measured. No tests, product updates, database changes, or queue runs were performed for this review.

## Scope and current implementation

Reviewed all runtime PHP classes, the bootstrap and hooks, the complete JavaScript and SCSS sources, and build configuration. Inspected generated asset sizes, development tooling, test setup and coverage declarations, and existing performance documentation. Dependencies and generated bundles were not treated as editable application source.

Relevant WordPress and WooCommerce implementation details were checked against the locally installed source. Its WooCommerce header reports **11.2.1**; that does not establish compatibility with every supported release.

The current plugin header and package report **1.1.2**, while `DKBCE_VERSION` is **1.1.1**. Runtime code uses a **20-second** cooperative bulk worker budget, **50-product** default chunks, **25-product or 2-second** progress checkpoints, and a **1-second** cancellation polling interval. Import preparation uses **250-record** batches and a **10-second** budget. These facts supersede older version and timing descriptions in project instructions.

The existing README reports 10,000 updates in 21 minutes 34 seconds. This review does not independently verify that run or use it to predict savings. The planned 50,000-product benchmark remains unverified here.

## Priorities

P1 means the first optimization to pursue for the affected workload. P2 means a substantive follow-up whose order should depend on measured workload. Effort describes relative implementation complexity, not a delivery estimate.

| ID | Priority | Opportunity | Main workload | Effort |
| --- | --- | --- | --- | --- |
| 1 | P1 | Stop flushing the runtime cache after each preview row | Every product preview | Small |
| 2 | P1 | Count matches without loading every matching ID | Counts and pagination | Small–medium |
| 3 | P1 | Eliminate repeated XLSX prefix parsing | Large XLSX imports | Large |
| 4 | P1 | Resolve exact SKUs through indexed lookup storage | SKU-only imports | Medium |
| 5 | P2 | Avoid repeating broad search and carrying large ID lists | Search-filtered previews and exports | Medium–large |
| 6 | P2 | Reuse staged values and batch import reads | Import preview and processing | Medium |
| 7 | P2 | Generate large downloads outside the browser request loop | CSV exports and error reports | Large |
| 8 | P2 | Reduce durable outcome write amplification | Large bulk updates | Large |
| 9 | P2 | Bound final summaries and cleanup | Completion and cancellation | Medium |
| 10 | P2 | Avoid global negative-option cache eviction per product | Bulk updates | Small–medium |
| 11 | P2 | Add retention for normal operations and preview claims | Long-running installations | Medium |
| 12 | P2 | Profile numeric metadata filters and deep pagination | Large filtered catalogs | Medium–large |

## Findings and recommendations

### 1. Preserve the preview request's runtime cache

**Evidence:** [COGS service](../../admin/class-dkbce-cogs-service.php), lines 499–510 and 570–575.

`scan_matches()` loads each product, creates its preview row, then calls `free_memory()`. That method flushes the entire runtime object cache after every row. A 100-row page therefore requests 100 runtime cache flushes.

This discards reusable product, parent, term, attachment, and option entries. Without a persistent cache, later accesses can repeat database work. With a persistent cache that implements runtime flushing, later accesses can repeat cache-server round trips. Actual behavior depends on the cache implementation; merely finding the function does not prove a drop-in supports it. WordPress documents the function as clearing the runtime cache. [WordPress API reference](https://developer.wordpress.org/reference/functions/wp_cache_flush_runtime/).

**Recommendation:** Remove per-row flushing from this bounded, maximum-100-row response. Prime the requested products and variation parents before constructing rows, using the same bounded approach already present in the bulk worker. Prime attachment posts and metadata for thumbnails as a separate bounded set where useful. Retain WooCommerce getters.

Also stop clearing `$wpdb->queries` in this read-only UI path: it erases diagnostic evidence when query recording is enabled.

**Acceptance:** Identical preview data, lower repeated reads on 20- and 100-row pages, and acceptable peak memory with both default caching and a supported persistent cache.

### 2. Replace full ID materialization with bounded count queries

**Evidence:** [COGS service](../../admin/class-dkbce-cogs-service.php), lines 395–403 and 449–493; [admin functions](../../admin/class-dkbce-admin-functions.php), lines 601–605, 680–682, 966, and 1082–1085.

For ordinary counts and previews, `scan_matches()` first calls `wc_get_products()` with `limit => -1` and `return => ids`, then counts the returned array. Preview requests subsequently run another query for the requested page. Every page repeats the full count path.

This makes PHP memory and ID transfer proportional to all matching products even when the response displays only 20. CSV export preview also explicitly counts before calling a helper that counts again.

**Recommendation:** Use a bounded WooCommerce paginated query and its returned `total`. A count-only request can request one ID; a preview can request its actual page and reuse that query's total. WooCommerce documents both bounded limits and the paginated total. [Product query documentation](https://developer.woocommerce.com/docs/features/products/wc-get-products/).

This removes full ID materialization; it does not make database counting constant-time. Preserve the existing count-change rejection and separate product/variation totals. Keep background snapshots on their existing count-free cursor path. Handle out-of-range pages explicitly because an empty page must not silently become a zero-total catalog.

**Acceptance:** A 50,000-match count does not return 50,000 IDs into PHP. Page counts and variation breakdowns remain correct for combined filters and changing catalogs.

### 3. Make XLSX preparation resume without reparsing earlier rows

**Evidence:** [import reader](../../admin/class-dkbce-import-reader.php), lines 163–179, 211–225, and 237–244; [import processor](../../admin/class-dkbce-import-processor.php), lines 124–128.

Every `xlsx_batch()` opens the ZIP member from the beginning, resets its local index to zero, and reads past the persisted cursor. This applies separately to shared strings and worksheet rows. Previously processed nodes are not expanded, but their XML is still traversed.

For approximately 50,000 rows in 250-row batches, the worksheet passes visit roughly five million row starts, excluding additional EOF traversal and shared-string processing. That is an algorithmic estimate, not a timing measurement. Smaller effective batches increase the repeated work.

The deadline check occurs only after processing a new node. Scanning the old prefix is therefore outside the cooperative deadline checks and can dominate late requests.

**Recommendation:** Introduce a resumable intermediate representation with independently readable chunks, or use a parser/indexing design that supports safe restart positions. A plain byte offset into compressed XML is not sufficient: XML namespace and document context must remain valid. Keep intermediate files private and include them in cleanup.

As an interim improvement, process multiple bounded batches within a request using one open reader. This reduces reopen overhead but does not solve cross-request prefix rescanning. Adding a deadline check while skipping, by itself, can repeatedly stop before reaching new data; it is not a complete solution.

**Acceptance:** Late batches avoid work proportional to all earlier rows. Shared strings, sparse cells, duplicates, malformed XML, cancellation, and interruption recovery preserve existing behavior.

### 4. Replace repeated postmeta SKU searches with indexed resolution

**Evidence:** [CSV service](../../admin/class-dkbce-csv-service.php), lines 69–78 and 133–136.

Each SKU-only row runs an exact `_sku` lookup by joining posts to postmeta and comparing `meta_value`. On a standard WordPress schema, the metadata value has no ordinary index suitable for this equality search. `LIMIT 2` bounds returned rows, not the work needed to find them.

The installed WooCommerce product data store already uses `wc_product_meta_lookup.sku` for exact SKU resolution, and its schema declares a SKU index.

**Recommendation:** Resolve a bounded set of incoming SKUs together through the indexed lookup storage behind a compatibility-aware adapter. Preserve exact matching, status checks, ID/SKU agreement, escaped-SKU handling, and ambiguity rejection.

Do not simply substitute `wc_get_product_id_by_sku()`: that API returns one ID, while this importer deliberately detects duplicates. Define behavior for missing or stale lookup records before replacing the current source of truth; verify results against product objects and provide a deliberate fallback where needed.

**Acceptance:** Duplicate SKUs still reject every ambiguous target. `EXPLAIN` confirms useful lookup-index access on the supported schema. Compare SKU-only and ID-based imports separately.

### 5. Avoid repeating broad search for every page and export batch

**Evidence:** [COGS service](../../admin/class-dkbce-cogs-service.php), lines 383–385 and 413–415; [bulk processor](../../admin/class-dkbce-bulk-processor.php), lines 314–320; [admin functions](../../admin/class-dkbce-admin-functions.php), lines 895–899.

`search_product_ids()` calls WooCommerce search without a result limit. The installed search implementation performs substring matching and returns all matches. The plugin then supplies that entire set as the next product query's `include` list.

The bulk snapshot correctly resolves search once and stores it separately. Ordinary previews and export batches do not share that optimization. A broad search can therefore repeat for every 100-record export request. Even the cached bulk path still carries the full match array and large inclusion predicate through subsequent queries.

**Recommendation:** First reuse search results within one logical request. For large exports or snapshots, consider staging a frozen search result set in indexed rows and paging through it. Reusing a result set across requests needs an explicit freshness policy; do not silently replace live matching with stale results.

Preserve WooCommerce search hooks, numeric-ID behavior, parent inclusion, SKU and global-identifier matching. An arbitrary search result cap would change which products are updated and is not an acceptable optimization.

**Acceptance:** A search-based export does not rerun the same broad search per batch. Record search duration, result-set size, SQL length, and total memory.

### 6. Batch import reads and reuse values already fetched

**Evidence:** [admin functions](../../admin/class-dkbce-admin-functions.php), lines 823–831 and 903–915; [COGS service](../../admin/class-dkbce-cogs-service.php), lines 234–239; [import store](../../admin/class-dkbce-import-store.php), lines 119–125 and 138–156; [import reader](../../admin/class-dkbce-import-reader.php), lines 55 and 324–325.

The import preview fetches complete staged rows, retains only product IDs, then `calculate()` rereads each staged value individually. Bulk import processing also performs a staged-value query for each product. XLSX shared strings are fetched individually for every referenced cell, including repeated references.

Import validation, import previews, and CSV exports construct product objects one at a time without the explicit batch priming already used by the update worker.

**Recommendation:** Keep the values returned with staged preview rows. Load pending import values in one bounded query per update chunk and pass a trusted map into calculation without triggering the `source_id` reread. Parse bounded validation input first so IDs, SKU resolutions, products, and parents can be prepared together. Use bounded shared-string caching or batch lookups.

Maintain per-row validation and duplicate detection across batches and retries. Do not weaken durable staging or product permissions to reduce reads.

**Acceptance:** Eliminate the additional staged-value SELECT per previewed or processed product. Repeated shared-string references reuse fetched values. Query count falls while peak memory remains bounded.

### 7. Generate large exports and reports as private server files

**Evidence:** [admin functions](../../admin/class-dkbce-admin-functions.php), lines 788–801 and 895–930; [JavaScript](../../src/bulk-cogs-editor.js), lines 129–137, 218–229, and 345–440.

The browser requests 100 export records at a time, sequentially, and stores every CSV fragment in `parts`. It creates the download Blob only after all requests finish. Error-report downloads use the same accumulation pattern.

An export of exactly 50,000 returned IDs requires 500 populated requests plus a final empty request under the current completion condition. Each request repeats WordPress initialization, authorization, query work, and network latency. Browser memory grows with the complete export.

**Recommendation:** Add an asynchronous export job that writes bounded batches to a private file and exposes an authorized download when complete. Apply the same approach to large error reports. Preserve cancellation, column selection, formula protection, ownership checks, expiry, and cleanup. Freeze the selection deliberately if the export is intended to represent one consistent product set.

For a smaller interim change, prime export product caches and reuse search results. Increasing the batch size alone does not solve browser memory growth and requires measured request-time limits.

**Acceptance:** Large downloads do not require the browser to retain all CSV text or orchestrate hundreds of product requests. No unauthenticated public export URL is created.

### 8. Reduce outcome serialization and write amplification

**Evidence:** [bulk processor](../../admin/class-dkbce-bulk-processor.php), lines 466–469 and 503–506; [operation store](../../admin/class-dkbce-operation-store.php), lines 158–175.

Each product outcome serializes and rewrites the whole chunk, including its IDs and all earlier results. For a full chunk of size `B`, result entries are serialized `B × (B + 1) / 2` times across the writes. At `B = 50`, that is 1,275 result-entry appearances for 50 outcomes, plus repeated IDs. Total work is proportional to `N × B` as chunk size varies, rather than just `N`.

Imported products additionally write their outcome to the staging table. This duplication is deliberate today: the chunk supports recovery and the staging row supports reporting.

**Recommendation:** Profile persistence separately from WooCommerce saves. If material, store one durable result per operation/product in indexed storage, with immutable snapshot membership and recoverable counters. For imports, evaluate whether one authoritative outcome record can support both recovery and reporting.

This is a schema and recovery change, not a small batching tweak. Preserve compatibility with in-flight option-backed jobs, retry markers, ownership, ordering, and failure handling. Do not buffer all outcomes until the end of a chunk.

**Acceptance:** Exactly one durable logical outcome per product after retries, with lower serialized bytes and database write volume. Validate interruption between product save, result persistence, and counter checkpoint.

### 9. Bound final reconciliation and cleanup

**Evidence:** [bulk processor](../../admin/class-dkbce-bulk-processor.php), lines 395–397, 487–489, 567–584, and 608–609; [operation store](../../admin/class-dkbce-operation-store.php), lines 243–275 and 285–290.

Normal completion calls `summarize()` to choose the final status. `finish_operation()` immediately summarizes again. Request caching can reduce repeated SQL, but both passes still traverse every stored outcome.

Finalization then deletes every chunk in one request without a cooperative deadline. A 50,000-product job at 50 products per chunk has 1,000 chunks. These final passes can retain many chunk arrays in the request cache and create a long completion or cancellation tail.

If execution stops after terminal state is saved but before cleanup finishes, ordinary terminal worker invocations return early; there is no separate normal-operation cleanup job to finish that deletion.

**Recommendation:** Pass the already-computed summary into finalization. Make large reconciliation and deletion resumable with durable cursors, or use indexed aggregates after an outcome-store redesign. Queue idempotent cleanup after the terminal result is safely persisted, and ensure cleanup itself can recover.

**Acceptance:** Finalization fits the worker budget as job size grows. Cancellation still reconciles durable outcomes accurately. Interrupted cleanup eventually removes only the finished job's data.

### 10. Avoid deleting the entire negative-option cache per product lock

**Evidence:** [operation store](../../admin/class-dkbce-operation-store.php), lines 186–217, especially line 216.

Every successful product lock acquisition deletes the shared `options:notoptions` cache entry. This entry records missing option names for WordPress and other plugins, not just this operation.

Under the ordinary successful path, 50,000 product locks request 50,000 such deletions. Later missing-option lookups can repeat database queries, and persistent-cache installations incur extra cache traffic.

**Recommendation:** Establish one access contract for these private lock records. Because acquisition, contention reads, and release already use direct SQL, determine whether they need to participate in the generic option negative cache at all. If option API readers must remain supported, invalidate only the affected entry using a concurrency-aware approach.

Do not remove invalidation without auditing all lock readers and ownership transitions. Preserve atomic acquisition, expiry reclamation, and conditional release.

**Acceptance:** Normal lock acquisition no longer invalidates unrelated negative-option entries. Contended, expired, and replacement locks behave identically.

### 11. Add retention for completed operations and normal preview claims

**Evidence:** [admin functions](../../admin/class-dkbce-admin-functions.php), lines 1140–1152 and 1375–1392; [bulk processor](../../admin/class-dkbce-bulk-processor.php), lines 608–609; [import processor](../../admin/class-dkbce-import-processor.php), lines 166–202.

Normal bulk updates retain their `dkbce_operation_*` options and one-use `dkbce_preview_claim_*` options indefinitely. Clearing the visible operation removes only user metadata. Import-preview cleanup exists, but it does not provide general retention for ordinary bulk operation records or the separate applied-operation state.

These options are non-autoloaded, which is good. This is long-term table, storage, backup, and maintenance growth rather than an automatic per-request autoload penalty. Failed queue starts can also leave snapshots that never reach normal finalization.

**Recommendation:** Add a bounded cleanup process with a documented history-retention period. Retain active jobs, in-flight claims, and import/report dependencies. Link claims to operations or maintain an indexed cleanup registry so retention does not require scanning every option.

A claim must remain effective for the full lifetime of any reusable preview token and concurrent Apply request.

**Acceptance:** Repeated completed and failed jobs do not grow retained state indefinitely. Active work, recent reports, and replay protection survive cleanup.

### 12. Profile range-query plans before changing storage or indexes

**Evidence:** [COGS service](../../admin/class-dkbce-cogs-service.php), lines 433–446, 477–493, 545–562, and 586–602; [admin functions](../../admin/class-dkbce-admin-functions.php), lines 1434–1440; [import store](../../admin/class-dkbce-import-store.php), line 154.

Price and COGS ranges use numeric casts over postmeta. Multiple ranges add metadata joins. Parent-category matching adds a correlated `EXISTS` predicate. Preview pages and range-boundary selection use offsets; deep pages require the database to traverse earlier matches.

These query shapes are confirmed. Their relative cost requires actual SQL plans, cardinalities, and installed indexes. The existing category `EXISTS` also prevents duplicate product rows, so replacing it blindly could worsen correctness or performance.

**Recommendation:** Capture generated SQL and inspect `EXPLAIN` for broad and selective filter combinations. Investigate lookup-backed price filtering while preserving exact parent/variation and current-price semantics. Use cursor navigation or persisted page boundaries for sequential browsing where the UI permits it; retain a deliberate strategy for arbitrary page jumps.

The installed WooCommerce COGS controller creates a lookup COGS column as `DECIMAL(19,4)`, while this plugin supports six-decimal imports and casts filter values to `DECIMAL(20,6)`. Do not substitute that lookup column without resolving precision and supported-version differences.

Do not add a generic postmeta index or a second COGS lookup table before plans demonstrate the need and synchronization costs are understood.

**Acceptance:** Matching IDs, ordering, monetary boundary behavior, and category inheritance remain identical. Query plans and measured latency improve for the targeted cases.

## Smaller opportunities

| Area | Evidence | Suggested improvement |
| --- | --- | --- |
| Large taxonomy lists | Admin functions, lines 291–303 and 378–405; JavaScript, lines 620–679 | Fetch searchable category/brand options in bounded pages for stores with thousands of terms. Currently every term becomes both a native option and a custom checkbox. |
| Formatting and progress rendering | JavaScript, lines 820–834, 925–927, 1402–1415, and 1418–1632 | Reuse currency/percentage `Intl.NumberFormat` instances. Update existing progress nodes rather than rebuilding the panel and timer on each poll. |
| Polling and preference traffic | JavaScript, lines 309, 336, 481–484, and 1634–1655 | Coalesce preference saves. Back off status polling in hidden tabs and after transient failures, with immediate refresh on return. Keep cancellation responsive. |
| Worker memory and scheduling | Bulk processor, lines 189–241; import processor, lines 124–128 and 152 | Measure peak memory before increasing batches. The bulk worker runs up to ten chunks per action; this is a product-count bound, not a memory-watermark check. Import preparation still enqueues after one reader batch. |
| Bootstrap loading | Bootstrap, lines 75–87; admin hooks, lines 24–47 | Consider loading UI-only classes only where needed. Worker and cleanup hooks must remain registered for cron, CLI, and asynchronous requests. Likely lower value than query work. |

The generated JavaScript is 39,075 bytes and the main CSS is 17,510 bytes, uncompressed on disk. Assets are already restricted to the plugin admin page, and product images use lazy loading. A frontend framework rewrite is not justified by this review.

## Optimizations already present and worth preserving

- Snapshot queries use an increasing product-ID cursor and avoid recounting every batch.
- All matching IDs are snapshotted before cost updates begin, preserving membership when filters depend on COGS.
- The worker primes product and variation-parent caches.
- WooCommerce product transient invalidation is deferred when the supported internal implementation is available, with cleanup in `finally`.
- Normalized no-change updates use metadata persistence rather than a full product save.
- Product locks already use direct atomic SQL and conditional ownership checks.
- Outcomes are durable per product; aggregate counters use checkpoints.
- Cancellation has independent persisted state and throttled fresh checks.
- Search IDs are separated from frequently rewritten bulk-operation progress.
- CSV reading uses a durable byte offset, and staged imports have indexed storage and bounded cleanup.
- Error lists and ordinary preview pages are bounded.

These mechanisms explain why removing hooks, writing COGS directly with SQL, dropping retry markers, or replacing durable state with a transient would be a poor performance tradeoff.

## Suggested implementation order

1. Fix preview cache flushing and full-ID counting. Add bounded priming to read paths.
2. Improve SKU resolution and reuse staged values. Prioritize XLSX restart behavior if large XLSX files are a common workflow.
3. Reduce repeated search and implement durable large exports.
4. Measure worker time split among WooCommerce saves, private persistence, cache operations, and queue waiting. Then address outcome storage and finalization.
5. Add lifecycle retention and make targeted query-plan improvements.
6. Apply small browser optimizations after server bottlenecks are addressed.

## Measurement and acceptance plan

This is proposed follow-up work, not a record of checks already run. Any product-update benchmark should use a disposable or explicitly authorized catalog.

| Scenario | Compare | Record |
| --- | --- | --- |
| Product counts and previews | 1,000, 10,000, and 50,000 matches; 20/100-row pages | Median and p95 response time, SQL count/time, IDs returned, peak PHP memory |
| Catalog filters | Broad search, exact SKU, combined price/COGS/category filters, deep pages | SQL plans, rows examined, result parity, query size |
| Read-path caching | Default object cache and a supported persistent cache; cold/warm runs | Repeated reads, cache round trips, peak memory |
| Import preparation | CSV and XLSX; IDs and SKUs; repeated and unique shared strings | Rows/second, first/middle/last batch times, SQL count, temporary storage |
| Export and reports | 10,000 and 50,000 rows, long names, large error reports | Request count, total duration, server/browser peak memory, output parity |
| Product updates | Simple products, variations sharing parents, unchanged values | Actual saves/second, persistence time, memory, cache invalidations |
| Completion and cancellation | Large snapshot, interruption during reconciliation/cleanup | Finalization duration, cancellation latency, accurate totals, retained records |
| Retention | Repeated completed, failed, cancelled, and active jobs | Option/staging row counts and bytes, dependency-safe expiry |

Hold hosting, product mix, active extensions, caching, logging, and batch size constant when comparing implementations. Include repeated runs.

Separate total elapsed time into initialization, snapshotting, actual processing, finalization, and waiting between workers. Existing worker timing logs provide timestamps and counter deltas, but recovered outcomes can increase `processed_delta` without representing new product writes.

Action Scheduler documents WP-CLI as a higher-throughput queue-running option and warns that increased concurrent HTTP runners can overload a server. Evaluate runner changes after measuring queue gaps; do not confuse Action Scheduler's actions-per-claim setting with this plugin's products-per-chunk setting. [Action Scheduler performance guidance](https://actionscheduler.org/perf/).

Preserve all public hooks, WooCommerce CRUD, PHP 7.4 / WordPress 6.7 / WooCommerce 10.3 compatibility, frozen batch sizes, per-product outcomes, retry protection, and independent cancellation.

## Review limitations and tooling notes

- No live database plans, profiler traces, or new throughput measurements were collected. Recommendations requiring them are identified above.
- Existing tests were inspected for context, not run or extended. Future optimization acceptance includes correctness and interruption scenarios, subject to the project's test authorization rule.
- No production source or generated asset changes are part of this report.
- The current `package.json` lacks the documented `php:quality` and `graph:update` scripts. The standalone PHP quality helper and Graphify CLI exist. `graphify update .` completed successfully with no code-graph topology changes. The missing npm scripts are tooling drift to repair separately.
- The version-constant mismatch is maintenance drift, not a measured performance problem. Normal asset versioning already uses the generated asset manifest.
