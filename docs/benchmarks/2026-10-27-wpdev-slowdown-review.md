# wpdev bulk-update slowdown review

## Finding

The latest 10,000-product run completed in 2,041.4914 seconds (34 minutes
1 second), with 10,000 successful outcomes, zero skipped, and zero failed.
The slowdown has several contributors: a different runner, slower work inside
callbacks, an older deployed plugin, and diagnostic instrumentation. Table size
alone is not established as the cause.

This was a read-only investigation of the existing operation. No new bulk
update, benchmark, cache flush, database change, plugin deployment, or test was
performed. Successful outcomes do not independently verify final product costs.

## Comparison from existing logs

Source: `wpdev/wp-content/uploads/wc-logs/dkbce-2026-10-20-*.log` and
`dkbce-2026-10-27-*.log`. These are observational runs, not equivalent
baseline/candidate benchmarks. Original catalog values, save hooks, diagnostic
settings, and the exact deployed revision of the older run are not established.

| Operation | Runner | Operation elapsed | Sum of callback time | Between callbacks |
| --- | --- | ---: | ---: | ---: |
| `9882fbdc-d9ff-4514-9dbb-909aa84b34be`, October 20 | WP-CLI | 925.6248 s | 920.7040 s | 3.8931 s |
| `1f5a3fe7-479b-4bc5-a47f-d54c8bee449f`, October 27, earlier run | HTTP / WP-Cron | 2551.2553 s | 1709.6966 s | 824.7689 s |
| `d665a83a-e1a7-4204-8cd6-c2b9b693fcb5`, October 27, screenshot run | HTTP / WP-Cron | 2041.4914 s | 1298.7382 s | 720.2079 s |

All three terminal records report 10,000 successful outcomes, zero skipped,
and zero failed. Their frozen product batch sizes were 50. Callback totals
include snapshot construction. There were no overlapping callback intervals
in these samples. Operation elapsed also includes time outside the first and
last logged callback boundaries, so the columns do not sum exactly.

The 925.6248-second run closely matches the previously reported 15-minute
result; another October 20 WP-CLI run completed in 912.1556 seconds.

Compared with the 925.6248-second sample, the latest run has approximately
716 seconds more between callbacks and 378 seconds more inside callbacks.
The latest run's median inter-callback gap is 5.2839 seconds; its maximum is
42.7574 seconds. These gaps include request startup, shutdown, scheduler work,
and dispatch delays; they cannot all be classified as idle scheduler time.

The installed WooCommerce Action Scheduler async runner explicitly sleeps
five seconds before chaining another request:
`packages/action-scheduler/classes/ActionScheduler_AsyncRequest_QueueRunner.php`,
`handle()` and `get_sleep_seconds()`. WP-CLI does not use that HTTP chaining
path. This explains a recurring component of the measured gaps. The longer
gaps require further request/queue tracing to attribute precisely.

## Deployment differences

At inspection, the repository was at `ecce65e`, including implementation
commit `eeb654a`. The installed plugin at
`/var/www/html/wpdev/wp-content/plugins/bulk-cogs-editor-for-woocommerce`
reported **1.2.1**, not the repository's **1.2.2**.

Direct source comparisons confirmed these missing optimizations on wpdev:

- `DKBCE_Bulk_Processor::process_product()` does not pass its already-read
  journal row to `DKBCE_History_Store::prepare()`. The installed `prepare()`
  repeats the lookup and reads the inserted row back. The repository reuses
  the existing row and returns the successfully inserted values, avoiding two
  redundant journal reads for a newly prepared product.
- `DKBCE_History_Store::index()` replaces the summary at every save on wpdev.
  The repository avoids replacements when the indexed summary is unchanged.
- `DKBCE_Operation_Store::save()` on wpdev persists stable selection data
  inline on each checkpoint. The repository separates unchanged selection
  context from progress. For this particular operation the serialized state
  was only 2,294 bytes, with 10 reviewed signatures totaling 777 bytes, so
  oversized selection checkpoints are not the leading explanation here.

History and rollback add durable journal work absent from the earlier feature
set. Repository history dates the history/rollback feature commit `b17c366`
to October 21, after the October 20 timing samples. That chronology is a
comparison limitation; it does not establish the deployed revision of each run.
Required journaling, retry protection, and WooCommerce CRUD must be preserved.

## Database observations

Environment: PHP 8.4.27, WordPress 7.2-beta1, WooCommerce 11.2.1,
MySQL 8.0.46. No persistent object-cache drop-in was present.

Approximate table sizes from `information_schema.TABLES`:

| Table suffix | Approximate rows | Data + indexes |
| --- | ---: | ---: |
| `postmeta` | 519,989 | 76.64 MiB |
| `actionscheduler_actions` | 43,186 | 41.75 MiB |
| `dkbce_history_products` | 130,761 | 23.12 MiB |
| `actionscheduler_logs` | 128,993 | 19.31 MiB |
| `dkbce_operation_outcomes` | 103,820 | 9.47 MiB |
| `options` | 2,366 | 5.30 MiB |

InnoDB row estimates are not exact counts. Autoloaded options totaled
101,611 bytes across 568 options. Required primary keys were present on
history-product rows, and the sampled outcome lookup used the primary key.
No missing index was demonstrated by the inspected lookups.

Performance Schema's cumulative statement digests showed material write cost:

| Statement family | Count | Total time | Average |
| --- | ---: | ---: | ---: |
| Update option by name | 29,447 | 235.35 s | 7.992 ms |
| Confirm journal status | 10,000 | 79.04 s | 7.904 ms |
| Insert original/target journal | 10,000 | 75.61 s | 7.561 ms |
| Insert durable outcome | 10,000 | 75.56 s | 7.556 ms |

These are cumulative schema-level statistics, not instrumentation scoped to
the operation. They must not be summed and presented as an exact per-job SQL
total. Indexed option updates and journal confirmations examined one row per
execution on average. The evidence points to write cost rather than a
demonstrated table-scan bottleneck for those statement families.

The buffer pool was 128 MiB; `innodb_flush_log_at_trx_commit` and `sync_binlog`
were both 1. Durability-related write latency is a plausible contributor,
but no fsync profile or controlled storage comparison was collected. The
cumulative buffer-pool counters recorded 5,876 physical reads against
29,238,599 logical read requests, and no current row-lock waits at the sample.
These global counters do not prove the absence of earlier cache or lock stalls.
Changing durability settings is not supported by this investigation.

The scheduler had roughly 2,687 pending actions at inspection. The largest
groups were future product sale events, not overdue bulk-update work. That
pending count by itself does not demonstrate queue starvation.

## Diagnostic overhead

Query Monitor 4.1.1 was active. Its database drop-in symlink existed and had
an October 26 modification timestamp; that timestamp does not establish when
instrumentation first became active. Its source enables query recording and
uses `QM_DB` for eligible web requests, while explicitly bypassing CLI and
cron. Therefore HTTP workers and the old WP-CLI timing are not instrumented
equivalently. Its contribution needs a controlled comparison; it is not
quantified here. The CLI inspection did not have `SAVEQUERIES` enabled.

## Recommended next steps

1. Deploy the existing 1.2.2 build to wpdev before assessing the merged code's
   performance. Do this after the current operation is terminal; this run is
   now terminal. No new optimization patch is required for the deployment
   differences listed above.
2. Compare the same runner and equivalent starting product data. Keep changed
   costs separate from repeated exact-cost updates that no longer change costs.
   The latest operation was a 5% increase, not an unchanged-cost operation.
3. Run a separately authorized comparison with Query Monitor disabled, including
   its database instrumentation. Preserve the same runner and workload.
4. If HTTP dispatch remains the dominant added cost, evaluate a dedicated
   scheduler runner as an operational change. Do not remove Action Scheduler's
   built-in sleep or increase concurrency without measuring effects.
5. If callback processing remains slow, collect operation-scoped write and
   storage timings. Investigate repeated option writes and product-save hooks
   before proposing table cleanup, new indexes, or database configuration changes.

The previous 15-minute result is not a supported promise for the current
feature set and HTTP runner. The available evidence explains important parts
of the difference but does not quantify every contributor.
