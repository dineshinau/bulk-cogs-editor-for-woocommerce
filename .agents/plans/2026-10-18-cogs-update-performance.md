# Bulk COGS update performance: seven-step improvement plan

Date: 2026-10-18

## Goal and working rules

Reduce a reported 10,000-product update from 21 minutes 34 seconds toward 15 minutes, then investigate whether 10 minutes is practical. Implement one improvement at a time and let the developer benchmark each change before proceeding.

- A 15-minute target requires saving 6 minutes 34 seconds, or 30.4% of the baseline runtime.
- A 10-minute target requires saving 11 minutes 34 seconds, or 53.6% of the baseline runtime.
- These are targets, not measured or guaranteed improvements.
- Preserve WooCommerce CRUD writes, hooks, fixed operation snapshots, frozen product batch sizes, durable product outcomes, retry protection, and independent cancellation.
- Follow the current `AGENTS.md`. Do not start bulk updates, add or run tests, commit, or change hosting configuration without authorization for that action.
- Do not repeat already completed steps. Review current source and Git history before implementing the next step.

## Evidence and benchmark context

The [performance discussion](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12) reports this progression for approximately 10,000 products:

| Stage | Reported duration |
| --- | --- |
| Initial implementation | 50–60 minutes |
| Earlier processing optimizations | 40–42 minutes |
| Batch cache preparation and transient deferral | 25–28 minutes |
| Latest reported 10,000-product run | 21 minutes 34 seconds |

The latest 10,000-product run reported zero skipped and zero failed products. It was a single reported run; repeated runs and larger catalogs still need measurement. The planned 50,000-product benchmark remains unrun.

Later screenshots showed 538-product operations taking approximately 57.5 seconds through WP-Cron/HTTP and 46.4 seconds through WP-CLI. This is about 19% less operation time in those samples, but the workload and initial costs were not established as controlled equivalents. Do not extrapolate this into a confirmed 10,000-product improvement. A terminal screenshot also showed approximately 53.1 seconds of CLI command wall time, which is a different measurement from the editor's operation duration.

At review time, the source used a 50-product batch, a 20-second cooperative worker budget, and progress checkpoints every 5 products. Some project notes still described older 10-second/25-product defaults. Step 2 subsequently changed the checkpoint policy described below.

## 1. Measure and reduce gaps between workers

Status: timing instrumentation and CLI benchmark instructions implemented; repeat performance measurement pending.

Findings:

- The worker already processes multiple chunks within a 20-second callback budget.
- The installed Action Scheduler async runner sleeps 5 seconds before chaining another HTTP request.
- Scheduling, repeated WordPress startup, and deferred shutdown work can add time outside the worker callback.
- For illustration only, 60 handoffs with a 5-second sleep would contribute 5 minutes of waiting. The actual handoff count in the 10,000-product baseline was not measured.

Implementation:

- Reuse Action Scheduler's existing WP-CLI runner rather than introduce a new queue implementation.
- Log one `COGS worker timing` entry per worker that acquires the operation lock, identifying the operation, runner, start and finish timestamps, and callback duration.
- Document the CLI command, benchmark method, queue contention, and shared hosting limitations in `readme.txt`.
- Implementation commit: `edd2312`; graph commit: `a986d1d`.

After confirming an update in the editor, the developer can run:

```bash
time wp --path=/var/www/html/wcdev action-scheduler run \
  --group=bulk-cogs-editor --batch-size=1 --batches=0
```

Use the correct WordPress path for each installation. `/wcdev` and `/wpdev` have separate queues. The command processes due actions for all plugin operations on that site and exits when none are due. `--batch-size=1` means one scheduled action per claim, not one product per batch.

Running this command does not switch the site to CLI-only execution or take over an active worker. If another runner owns a claim, Action Scheduler can report too many concurrent batches. Zero processed actions means no matching due, unclaimed work was available at that moment. Retry while the operation is still active; do not use `--force` as the default workaround.

WP-CLI sets `WP_CLI` automatically inside its own process. Do not define it in `wp-config.php`.

For nontechnical users, hosting support can configure a scheduled CLI command once, with a process lock and suitable resource limits. Users then continue using Apply changes in the editor. The plugin does not configure server cron or require CLI for its normal background processing.

Validation: PHP formatting, syntax, PHPCS, and diff checks passed. No bulk benchmark was started by the agent.

## 2. Reduce progress checkpoint overhead

Status: implemented; controlled performance comparison pending.

Findings:

- The original 5-product interval generated roughly 2,000 periodic progress saves per 10,000 products, in addition to chunk boundaries and other state updates.
- Progress saving also checks cancellation using a fresh option read and cache invalidation.
- Aggregate counters can be written less often while individual outcomes remain durable.

Implementation:

- Save aggregate progress after 25 products or 2 seconds, whichever comes first between product updates.
- Poll cancellation independently, once per second between products, with fresh checks at progress checkpoints.
- Retain immediate progress handling at chunk boundaries, cooperative time limits, and deferred retries.
- Keep each product outcome durable through the existing operation store.
- Avoid an extra periodic checkpoint immediately before a completed chunk's checkpoint.
- Reset the cancellation polling timer for each acquired worker.
- Implementation commit: `62bc170`; graph commit: `8d6967d`.

A single slow product save cannot be interrupted mid-save. Timing intervals are checked between products. Moving the product threshold from 5 to 25 reduces count-triggered periodic checkpoint opportunities by 80%; the 2-second timer and boundary writes mean this is not an 80% reduction in total writes or runtime.

Validation: PHP formatting, syntax, PHPCS, and diff checks passed. No automated tests or bulk benchmark were run by the agent.

## 3. Prime WooCommerce's raw metadata cache

Status: reverted after the user reported increased total runtime. No measured duration was supplied.

Findings:

- WordPress post-cache priming does not populate WooCommerce's separate raw metadata cache with metadata IDs.
- The attempted implementation prefetched metadata for up to 50 locked products, preserved datastore filtering, and fell back to normal reads for custom datastores.
- That implementation and its early lock acquisition have been removed. The original per-product metadata reads and locking are restored.
- Do not reintroduce this approach without profiling the cause of the observed regression.

## 4. Avoid full product saves for unchanged COGS

Status: implemented; user performance comparison pending.

Implementation:

- Call WooCommerce's COGS setter before checking `get_changes()`, allowing WooCommerce to normalize the value and retain variation-specific zero/null semantics.
- If there are no pending product property changes, persist the operation marker through `save_meta_data()` instead of a full product save.
- Retain a full save whenever any product property is dirty, including changes introduced by extensions.
- Retain successful outcome counts, per-product durable outcomes, product locks, cancellation, and retry-marker checks.
- Unchanged products intentionally do not run full product-save hooks or update the product modified timestamp; metadata persistence still runs its normal hooks.

Scope: `admin/class-dkbce-bulk-processor.php`. Calculation rules remain unchanged.

Validation: PHP formatting, syntax, and PHPCS passed. No automated tests or bulk updates were run. Benchmark the same workload and report how many products already had the requested cost. This optimization helps unchanged costs; it does not avoid full saves when all costs change.

## 5. Reduce operation-state and locking persistence overhead

Status: implemented with the existing options storage; runtime comparison pending.

Findings:

- Each product used general WordPress option APIs for lock insertion/deletion and chunk persistence.
- Each durable outcome still rewrites its chunk, including IDs and accumulated results. This remains bounded by the frozen batch size.

Implementation:

- Acquire product locks with an atomic insert against the existing unique option name. Uncontended acquisition requires one SQL statement.
- Read an existing lock directly only on contention. Preserve the 900-second expiry and support legacy serialized lock records.
- Reclaim expired locks with a conditional update matching the exact observed value. Include a unique token in new records and release only the exact owned value, protecting replacement locks from expired workers.
- Persist chunk outcomes immediately through a direct update with one serialization, avoiding general option-API reads, comparisons, filters, and cache bookkeeping. Invalidate the corresponding option cache after each write.
- Accept a zero-row update only when a fresh database read confirms the identical value. Throw on failed or missing outcome persistence before advancing progress counters.
- Keep existing chunk data, product locks, cancellation, outcome durability, and WooCommerce CRUD. No new tables or data migration are required.
- General WordPress add/update/delete-option hooks do not run on these internal lock and outcome writes. Product save hooks and the normal operation-state APIs are unchanged.

Scope: `admin/class-dkbce-operation-store.php` and `admin/class-dkbce-bulk-processor.php`. See [implementation plan](../../docs/superpowers/plans/2026-10-18-persistence-overhead.md).

Validation: PHP formatting, syntax, PHPCS, and diff checks passed. No automated tests or bulk benchmark were run. Query/runtime reductions are not measured. Compare the same product mix and action before proceeding to step 6. A dedicated outcome table remains deferred until profiling justifies a migration.

## 6. Profile WooCommerce saves and extension callbacks

Status: pending.

Findings:

- A WooCommerce product save performs metadata, timestamps, lookup data, cache handling, parent synchronization, and extension hooks.
- A slow synchronous integration callback can dominate the runtime of every product save.
- Product transient deferral is already implemented, and WooCommerce already defers parent synchronization. Do not count these as wholly new optimizations.

Proposed investigation:

- Separate product loading and metadata reads from `$product->save()` time.
- Measure extension callbacks, lookup updates, transient flushing, and deferred parent synchronization.
- Include WordPress startup and shutdown in the overall benchmark.
- If an integration is responsible, investigate supported batching or deferred integration work.
- Keep required hooks and cache consistency; do not bypass the datastore or broadly disable callbacks to achieve a benchmark number.

Expected scope: measurements first. Choose the smallest responsible implementation only after identifying the dominant cost.

## 7. Evaluate safe parallel chunk workers

Status: pending; architectural change, not a concurrency setting adjustment.

Findings:

- The operation lock currently allows one worker per operation.
- Increasing Action Scheduler concurrency or adding RabbitMQ consumers does not make one operation process in parallel automatically.
- Even a perfect twofold speedup would reduce 21 minutes 34 seconds to 10 minutes 47 seconds, before parallel overhead.

Proposed change, only if earlier steps justify it:

- Introduce independently claimable chunks and atomic ownership.
- Maintain durable per-product outcomes, concurrent progress accounting, cancellation, recovery, and overlapping-product protection.
- Account for variations sharing parents and extension side effects.
- Start by evaluating two workers within measured database, CPU, memory, and PHP worker limits.

Recovery concern to address before parallelism:

The installed WooCommerce datastore saves custom product metadata before updating COGS metadata. The operation marker can therefore be persisted before the COGS write finishes. An interruption in that window may cause the retry path to treat an incomplete update as successful. Strengthen the recovery protocol before increasing concurrency; changing queue technology alone does not fix this ordering issue.

## Queue technologies and shared hosting

| Approach | Suitability | Recommendation |
| --- | --- | --- |
| Action Scheduler HTTP/WP-Cron | Works within ordinary WordPress hosting constraints | Retain as the default |
| Action Scheduler WP-CLI plus cron | Requires PHP CLI, cron access, and adequate limits | First optional runner to evaluate |
| Self-hosted RabbitMQ and persistent consumers | Requires broker hosting and permitted worker processes | Consider for VPS or managed infrastructure |
| Managed RabbitMQ or Amazon SQS | Hosts the broker externally; consumers still need execution capacity | Consider only when a separate worker architecture is justified |

RabbitMQ distributes work; workers still execute the WooCommerce save path. If external consumers call the store through HTTP, the store's PHP and database limits still apply. Provider policies vary, and some shared hosts prohibit standalone daemons. A managed broker does not remove the need to operate consumers.

References:

- [Action Scheduler performance guidance](https://actionscheduler.org/perf/)
- [Action Scheduler WP-CLI runner](https://actionscheduler.org/wp-cli/)
- [RabbitMQ PHP work queues](https://www.rabbitmq.com/tutorials/tutorial-two-php)
- [Amazon SQS consumption](https://docs.aws.amazon.com/prescriptive-guidance/latest/modernization-integrating-microservices/sqs.html)
- [Example shared hosting process restrictions](https://www.namecheap.com/support/knowledgebase/article.aspx/157/22/do-you-have-any-server-resource-restrictions/)

## Benchmark acceptance for each step

- Use the same initial product data, product and variation mix, filters, action, hosting conditions, and extension configuration.
- Record editor operation duration, CLI wall time where applicable, updated/skipped/failed counts, and runner labels.
- Group `COGS worker timing` entries by operation. Sum callback durations and calculate gaps from consecutive finish/start timestamps.
- Callback duration includes snapshot collection, processing, and batch cache flushing, but excludes WordPress startup, deferred shutdown work, and the timing log write itself.
- Identify mixed HTTP/WP-Cron/CLI runs rather than treating them as CLI-only benchmarks.
- Repeat measurements before claiming a speedup. Record the result and conditions here before advancing to the next step.
- Run only the checks authorized by the current task. Do not start a product update just to complete a development workflow.

## Source map

- [Worker, queue handoffs, checkpoints, and product writes](../../admin/class-dkbce-bulk-processor.php)
- [Operation state, chunks, outcomes, and cancellation storage](../../admin/class-dkbce-operation-store.php)
- [Filtering and COGS calculations](../../admin/class-dkbce-cogs-service.php)
- [Admin operation creation and progress endpoints](../../admin/class-dkbce-admin-functions.php)
- [CLI instructions and published benchmark context](../../readme.txt)
