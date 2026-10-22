# Range-query profiling — recommendation 12

## Delivered scope and decision

Added a WP-CLI profiler that captures the plugin's generated WooCommerce SELECTs, installed indexes, lookup columns, plain EXPLAIN plans, warm SQL timings, and ordered page IDs. The profiler is a development tool; the plugin does not load it.

The local measurements do not support a blanket cursor or lookup substitution. Runtime queries, storage, indexes, and pagination remain unchanged. This completes the requested query-plan investigation; a deployed latency improvement is not claimed. The recommendation's performance acceptance criterion still requires a targeted change supported by a representative large catalog.

## Reproduce

From the plugin directory, against a local development catalog:

```bash
wp --path=/var/www/html/wcdev eval-file scripts/class-dkbce-range-query-profiler.php '{"samples":9}' > /tmp/range-profile.json
```

To profile a specific filter combination:

```bash
wp --path=/var/www/html/wcdev eval-file scripts/class-dkbce-range-query-profiler.php '{"samples":9,"page":10,"page_size":20,"filters":{"price_min":"100","price_max":"150","cogs_min":"0","cogs_max":"999999999999","category":[15]}}' > /tmp/range-custom.json
```

Choose category IDs from the target catalog. `filters` replaces the default matrix and goes through `DKBCE_COGS_Service::validate_filters()`. Supported page sizes are 10, 20, 50, and 100. Samples are bounded to 1–20. The selected page is capped at the scenario's last page so selective filters still have a meaningful comparison; both the requested and actual page are recorded. The default category is the first nonempty category by term ID.

The tool executes product SELECTs and metadata inspection, without product writes, schema changes, cache flushes, or queue execution. Temporary capture callbacks are removed in `finally`. Product query result caching is disabled only inside the capture callbacks. Repeated SQL measurements bypass the WordPress result cache without clearing it. The existing plugin bootstrap still runs normally under WP-CLI.

## Method and environment

Full SQL, plans, individual timings, schema metadata, and bounded page IDs are recorded in [the machine-readable capture](2026-10-20-range-query-profile.json).

- Captured at 2026-10-20T21:04:44+00:00.
- MySQL 8.0.46, WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.3.6.
- 167 published products and 371 published variations; one additional product has `importing` status and is outside the ordinary query.
- No persistent object cache reported; store filter precision is two decimals.
- Nine measured samples per query after one unreported warm execution. Capture itself also executes the generated query.
- The profiler records traditional EXPLAIN estimates, not actual rows examined. It does not use EXPLAIN ANALYZE.
- SQL latency includes the database call and ID fetch. Counted queries include their immediate `SELECT FOUND_ROWS()` call. These are SQL measurements, not AJAX, product hydration, browser, or queue throughput timings.
- Query shapes run consecutively rather than interleaved. Cache warming and scheduling noise can affect comparisons. With nine samples, the recorded nearest-rank p95 is the maximum sample, not a reliable population p95.

`preview` retains the ordinary preview's `SQL_CALC_FOUND_ROWS` behavior while returning IDs. `offset` removes total calculation to isolate ID retrieval. `cursor` uses the plugin's existing `after_id` path with the same filters and page size. Counted and uncounted queries are reported separately so removing a total is not mistaken for a cursor speedup.

The cursor boundary comes from the preceding offset page. Its SQL, plan, and time are recorded under `boundary`. That acquisition cost is not included in cursor latency. Sequential browsing could reuse a previous page's final ID, but an arbitrary jump would still need the offset query or another validated boundary source.

## Measurements

Median warm SQL latency, milliseconds:

| Scenario | Matches | Actual page | Preview + total | Offset IDs | Cursor IDs |
| --- | ---: | ---: | ---: | ---: | ---: |
| `all` | 538 | 10 | 15.061 | 4.805 | 1.280 |
| `price_broad` | 534 | 10 | 47.224 | 44.169 | 37.256 |
| `price_narrow` | 31 | 2 | 4.058 | 3.873 | 10.609 |
| `cogs_narrow` | 3 | 1 | 1.951 | 1.970 | 1.753 |
| `combined` | 31 | 2 | 27.587 | 26.218 | 18.496 |
| `price_exact` | 0 | 1 | 2.042 | 1.747 | 1.986 |
| `category` | 204 | 10 | 5.677 | 5.886 | 1.300 |
| `combined_category` | 13 | 1 | 13.502 | 12.991 | 13.386 |

Default ranges are recorded alongside every scenario. `price_narrow` is 100–150; `cogs_narrow` is 10–20. `combined` uses price 100–150 and COGS 0–999999999999; the broad COGS range keeps both metadata joins while producing a nonempty result on this catalog. Category scenarios use term 15. `price_exact` is 100–100 and has no matches here.

Ordered IDs matched between preview, offset, and cursor in all eight scenarios. This establishes parity for the recorded pages only. It does not establish exhaustive monetary-boundary or taxonomy behavior on every supported WooCommerce version.

## Plans and interpretation

- **Unfiltered page 10:** offset uses an ordered `PRIMARY` index scan on posts; cursor uses a `PRIMARY` range scan. Median ID retrieval improved from 4.805 to 1.280 ms, excluding boundary acquisition and totals.
- **Category page 10:** cursor also selects a posts primary-key range and retains the parent-category `EXISTS`. Median ID retrieval improved from 5.886 to 1.300 ms. Obtaining the preceding page independently costs another 4.811 ms median.
- **Selective price page 2:** offset starts from the postmeta `meta_key` index, estimates 801 candidate metadata rows, and uses a temporary table and filesort. Cursor changes the join order to a posts primary-key range, followed by postmeta `post_id` lookups estimating 22 metadata rows per post. Median latency regressed from 3.873 to 10.609 ms. Removing a filesort did not make this query faster.
- **Broad price:** both paths begin with the metadata key and retain a temporary table and filesort. A cursor does not eliminate the numeric metadata filtering work.
- **Combined price/COGS:** offset starts with the COGS metadata alias, then joins posts and price metadata; the cursor changes the join order. This case improves in the local sample, while the selective price-only case regresses.
- **Category inheritance:** EXPLAIN shows primary-key access to the category product and indexed taxonomy relationships. The existing `EXISTS` prevents duplicate products from multiple category assignments. The measurements do not justify replacing it.

The installed postmeta indexes are `PRIMARY(meta_id)`, `post_id(post_id)`, and `meta_key(meta_key(191))`. The report includes the other participating indexes. No index was added or removed.

## Lookup-backed price investigation

The installed lookup has `min_price` and `max_price` as `DECIMAL(19,4)`, and no `cogs_total_value` column. The installed WooCommerce COGS controller can add that optional column as `DECIMAL(19,4)`; its presence cannot be assumed.

The installed variable-product data store's `sync_price()` stores every distinct child price in a separate parent `_price` row. Thus the current metadata predicate can match any of those prices. A lookup minimum or an overlapping min/max interval does not preserve that behavior.

A separate read-only comparison on published products and variations for 100–150 found seven current metadata matches lost by a `min_price BETWEEN 100 AND 150` replacement, and nine additional matches introduced by an overlapping lookup interval. Examples:

| Product ID | Lookup min/max | Current metadata match | Minimum match | Interval overlap |
| --- | --- | --- | --- | --- |
| 839 | 15.9200–220.7500 | No | No | Yes |
| 853 | 69.9000–718.7600 | Yes | No | Yes |

Reproduce that comparison using the local table names below; adjust prefixes for another installation:

```sql
SELECT p.ID, p.post_type, l.min_price, l.max_price,
       EXISTS (
           SELECT 1 FROM wc_postmeta AS m
           WHERE m.post_id = p.ID AND m.meta_key = '_price'
             AND CAST(m.meta_value AS DECIMAL(20,6)) BETWEEN '100' AND '150'
       ) AS meta_matches,
       (l.min_price BETWEEN '100' AND '150') AS min_matches,
       (l.min_price <= '150' AND l.max_price >= '100') AS overlap_matches
FROM wc_posts AS p
INNER JOIN wc_wc_product_meta_lookup AS l ON l.product_id = p.ID
WHERE p.post_type IN ('product', 'product_variation') AND p.post_status = 'publish'
HAVING meta_matches <> min_matches OR meta_matches <> overlap_matches
ORDER BY p.ID;
```

This comparison examines price-predicate semantics on published lookup-backed rows, rather than replacing the full WooCommerce query. Missing/stale lookup rows and extension hooks would require additional compatibility work for any future adapter.

Precision also prevents a direct COGS substitution: MySQL returns `10.0000` from `CAST('10.000001' AS DECIMAL(19,4))` and `10.000001` from the existing six-decimal cast. Six-decimal imports are supported, while filter validation uses store price precision. The profiler adds its six-decimal filter scenario only when the store allows at least six decimals; it was skipped on this two-decimal store. The empty exact-price scenario does not prove populated boundary behavior. No precision setting was changed.

## Follow-up implementation criteria

1. Repeat the profiler on a representative large catalog with populated exact boundaries, sparse price intervals, variation parents, category descendants, and relevant extensions. Use the custom filter configuration to select representative values.
2. Consider persisted sequential page boundaries only for query shapes with a repeatable benefit. Bind boundaries to filters, page size, ordering, and a deliberate catalog-freshness policy. The current preview is live; reusing old boundaries after catalog changes could skip or repeat matches.
3. Retain offset access for arbitrary jumps unless a current, matching boundary is available. Measure boundary acquisition and count costs with total request latency.
4. A future lookup adapter must preserve each parent `_price` value, missing/stale-row behavior, current-price semantics, six-decimal boundaries, and public query hooks. Min/max substitution already fails on this catalog.
5. Adopt a query rewrite or index only after ordered IDs, populated monetary boundaries, category inheritance, and supported-version behavior agree and targeted latency improves. A generic postmeta index or a second COGS table is not justified by this small-catalog profile.

## Completion checks

`python3 scripts/php-quality.py all scripts/class-dkbce-range-query-profiler.php` passed PHPCBF, PHP syntax checking, and PHPCS. Read-only profiling and lookup comparisons were run; no automated tests or product-update benchmarks were run. Graph status is reported with the task result.
