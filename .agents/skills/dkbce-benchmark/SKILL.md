---
name: dkbce-benchmark
description: Design and compare reproducible Bulk COGS Editor performance benchmarks, or interpret its worker logs. Use for bulk-update timing, throughput, query-count, memory, and runner comparisons. Installing or invoking this skill alone does not authorize product updates or data resets.
---

# Bulk COGS benchmark

Measure one implementation change at a time using the same starting catalog and operation. Follow the project's AGENTS.md; existing authorization applies, and tests or bulk updates require a user request. Read-only log analysis can proceed independently.

## Establish the comparison

Record the baseline and candidate commits, including uncommitted differences. Preserve pending user changes; use isolated checkouts if a baseline environment is needed. Do not switch or reset the active checkout to stage a comparison.

Record:

- Exact WordPress path/site URL, PHP, WordPress, WooCommerce, Action Scheduler, and MySQL/MariaDB versions.
- Active plugins relevant to product saves, object-cache backend, database location, and competing workload.
- Snapshot product count and simple/variable/variation mix; matching filters, action, value, price decimals, and initial COGS distribution, including null versus zero.
- Counts of products whose normalized COGS should change versus remain unchanged. Successful outcomes include unchanged products, so the success count cannot provide this split.
- Runner, configured and frozen batch size, worker budget, progress checkpoint policy, and cache conditions. Read current source and operation state; do not assume older notes describe the running code.

Use equivalent disposable/staging datasets for baseline and candidate. Repeating a set operation can turn the second run into an unchanged-cost benchmark; repeating a percentage increase changes its input. Restore equivalent starting data only within the user's authorized environment and reset scope. If equivalent starting data cannot be established, label the comparison observational.

Keep changed-cost and unchanged-cost workloads separate. Cover variation inheritance and zero/null semantics when relevant; let WooCommerce normalization determine whether a value changes.

## Choose the runner deliberately

Read `admin/class-dkbce-bulk-processor.php` for the current Action Scheduler hook/group, worker timings, and budget. Inspect the installed Action Scheduler CLI help before issuing a command.

For an authorized benchmark after the operation is queued, a typical command is:

```bash
time wp --path=/absolute/path/to/site action-scheduler run \
  --group=bulk-cogs-editor --batch-size=1 --batches=0
```

- Verify the target path; separate WordPress installations have separate queues.
- CLI batch size counts scheduled actions, not products. The plugin freezes its own product batch size.
- The group can include other COGS operations; check what will be executed before running it.
- This command does not automatically take ownership from WP-Cron/HTTP workers, wait for delayed actions, or install a recurring runner.
- Zero actions is not proof that an operation completed. A concurrency refusal is not a reason to add `--force`.
- Keep runner and scheduling configuration the same for code comparisons. Compare runners as a separate experiment. Label operations with multiple runner types as mixed.
- WP-CLI defines `WP_CLI`; do not define it in wp-config.php to influence runner labels.

## Capture evidence

Use the operation UUID to join its snapshot, worker timing, and terminal logs. Match the terminal processed/succeeded/skipped/failed totals to the expected snapshot. Missing terminal records mean completion is unconfirmed. Retain failures and cancellations in the report instead of discarding them as timing outliers.

Distinguish these measurements:

| Measurement | Meaning |
| --- | --- |
| Operation duration | User-facing completion interval reported by the plugin; verify its start timestamp in source |
| Worker elapsed | Individual callback interval from worker timing logs, not necessarily request bootstrap/shutdown |
| CLI wall time | Entire command duration, potentially including startup, scheduler work, or multiple operations |
| Between-worker gap | Next callback start minus previous finish, where callbacks are sequential |
| Query count/time and peak memory | Only the instrumented scope; name the measurement tool and boundaries |

Sum worker durations only for the same operation and scope. Overlapping callbacks require interval-union analysis; negative gaps should be investigated, not silently clamped. Operation duration minus worker elapsed is residual time, not proof of scheduler delay alone. Snapshot construction and initialization can be present in worker timings.

Do not infer whole-job SQL counts or memory from one web request. Query Monitor/SAVEQUERIES/profilers can change runtime: use the same instrumentation in both samples and separate diagnostic runs from uninstrumented timing runs. Use available profiling tools; do not install extensions, flush caches, or change hosting configuration merely to complete a report.

## Compare and report

When repeated runs are authorized, aim for at least three equivalent runs per version and alternate baseline/candidate order where practical. Report each sample, median, and range. A single run is preliminary. Record cold/warm conditions; cache flushing requires appropriate scope and authorization.

- Time reduction: `(baseline_seconds - candidate_seconds) / baseline_seconds * 100`.
- Throughput: `processed_products / operation_seconds`; report changed-product throughput separately when measured.
- Compare correctness totals and end-state COGS as well as speed. State whether end-state validation was performed and how; do not infer it from success logs alone.
- Do not extrapolate a small catalog result to 10,000 or 50,000 products as a measured result.

Deliver a compact table with revision, operation UUID, workload, runner, product counts, operation time, worker time, SQL/memory evidence if available, outcome totals, and data-reset method. Explain confounders and whether the result supports keeping the change. Save requested reports under `docs/benchmarks/`; use aggregate or sanitized data rather than customer records or credentials.

For database diagnosis use the installed `sql-optimization-patterns` skill and the actual database engine. For broader WordPress profiling use `wp-performance`. These references do not authorize unrelated database or hosting changes.
