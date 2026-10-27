# Benchmark preparation and existing timing evidence

Controlled runs are pending dataset selection. These existing logs are
observational evidence, not an equivalent baseline/candidate comparison.

## Local environment

- Site: `https://wsl.com/wcdev`, path `/var/www/html/wcdev`.
- PHP 8.4.27, WordPress 7.1.3, WooCommerce 11.2.1, MySQL 8.0.46.
- Catalog: 168 product posts and 371 variation posts, including all statuses.
- Active plugins: Bulk COGS Editor, Quick Plugin Switcher, WooCommerce, WP Mail Log.
- Candidate revision: `019200a`; proposed aggregate baseline: `89a6818`
  (before improvement 1). Individual improvement comparisons need their own
  before/after revisions.
- Current source defaults: 50 products/batch, 20-second cooperative update budget,
  25-product checkpoints. Frozen job settings must be recorded per run.

## Existing local operations

Extracted from local WooCommerce `dkbce` logs dated October 24–26, 2026.
Times are seconds; worker time is the sum of logged callback elapsed durations,
not PHP bootstrap or full request time. Successful outcomes do not establish
how many costs actually changed. End-state costs have not been verified.

| Operation UUID | Products | Operation time | Worker sum | Runner | Success/skipped/failed |
| --- | ---: | ---: | ---: | --- | --- |
| `515c5dfa-821d-46cb-805f-f945b9400e34` | 500 | 88.9238 | 59.5105 | HTTP + WP-Cron | 500/0/0 |
| `7aa70798-4032-48c4-a850-1446c58276e9` | 538 | 111.5469 | 73.8360 | HTTP | 538/0/0 |
| `15f00ca8-69d9-47c3-b854-01ff97bc7359` | 500 | 56.8056 | 36.4994 | HTTP + WP-Cron | 500/0/0 |
| `0ac80251-5dcb-4afe-8f6c-c973790ff285` | 500 | 43.8334 | 37.5359 | HTTP + WP-Cron | 500/0/0 |
| `c984d497-04bf-422b-a4db-9854910f1e2f` | 538 | 79.0591 | 48.8411 | HTTP + WP-Cron | 538/0/0 |
| `336c9744-06c9-4470-80ff-4035414c81f7` | 538 | 90.9432 | 45.6490 | HTTP + WP-Cron | 538/0/0 |

No revision, equivalent starting costs, or cache conditions were recorded for
these historical runs. Residual operation time is not necessarily scheduler
delay. These rows establish neither speedup nor regression.

## Controlled run design

Use a disposable database and filesystem, isolated from the existing site's
queues, uploads, outgoing email, and external callbacks. Do not run its queued
actions or reset its products. Preserve identical initial datasets for baseline
and candidate. Record product types, initial costs including zero/null, settings,
Action Scheduler version, cache backend, and runner before timing.

Run equivalent changed-cost and unchanged-cost update workloads separately.
Measure CSV export with the same selected columns and scope, and CSV/XLSX preview
with the same input rows. Compare output and persisted outcomes as well as time.
Use three samples per revision where practical, alternating execution order.
Record operation duration, worker duration, command wall time, query counts, and
peak memory with identical instrumentation. Report medians/ranges and limitations.
A small-catalog result must not be extrapolated as a measured 10,000-product result.

## Stopped run: 2026-10-26

The user stopped the first controlled run before any sample completed. The
harness expected JSON from each WP-CLI worker, but the plugin wrote its normal
worker timing log to stdout first; parsing failed. No benchmark result was
recorded. The runner exited and no Python or PHP benchmark process remains. The
original `wpdev` database was only exported; catalog values and its pending
Action Scheduler operation were not changed or executed.

An isolated temporary database (`dkbce_benchmark_20261026`) was created from the
wpdev snapshot. Automatic review rejected its deletion because dropping a
database is irreversible and could not verify the target while MySQL was
unavailable. The temporary SQL dump, benchmark checkout, and credentials were
removed from `/tmp`; that isolated database still needs explicit approval and a
working database connection before cleanup.

The screenshot's import error was traced to CSV amounts with more than six
fractional digits. The importer now accepts up to 18 fractional digits and
rounds to the WooCommerce store precision before validation. On wpdev, precision
is two decimals; read-only checks confirmed rounding examples. PHP quality passed.
The benchmark was not resumed.
