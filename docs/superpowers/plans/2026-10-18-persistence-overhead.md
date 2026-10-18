# Step 5: reduce persistence overhead

## Scope and acceptance

- Replace general option-API work on the per-product lock path with atomic SQL against the existing non-autoloaded plugin options.
- Preserve the 900-second product lock expiry, operation identifiers, and legacy lock records. Give each new lock a unique owner token; reclaim and release only the exact stored value observed or acquired.
- Write each chunk outcome immediately with one serialization and a direct update. Preserve the existing chunk format, fixed snapshots, checkpoint counters, and cancellation path.
- Fail closed on outcome persistence errors; never advance counters after a failed write.
- Keep WooCommerce product writes unchanged. No schema migration, tests, product updates, or commits in this task.

## Files and checks

Update `admin/class-dkbce-operation-store.php` and `admin/class-dkbce-bulk-processor.php`. Review both normal and contended/expired lock paths, same-value chunk writes, missing rows, cache invalidation, and resume behavior. Run PHP formatting, syntax, PHPCS, diff checks, and one graph refresh.

## Measurement limits

This removes option reads, repeated serialization/comparison, and option-API cache bookkeeping from the hot path. Chunk payloads still grow and remain durable per product. No measured speedup or SQL-query reduction is claimed until the developer benchmarks the same workload. A dedicated results table remains a later profiling-driven option.
