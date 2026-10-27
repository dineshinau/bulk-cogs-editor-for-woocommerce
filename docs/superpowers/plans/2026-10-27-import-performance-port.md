# Import performance port for 1.2.1

## Scope

Port the confirmed improvements 2, 3, 5, 6, and 7 from the ux branch's performance
review and add the agreed usage details to readme.txt. Exclude improvements 1
and 4. Preserve the existing 1.2.1 rounding, Stop confirmation, tab navigation,
and selection reuse changes.

## Implementation

1. Port bounded import validation and staging from 3a5d76a to the CSV service,
   import reader, and import store.
2. Port worker budget reuse from 31bdaa0 and XLSX stream reuse from 73cbd12 to
   the import reader and processor.
3. Port selective export getters, parent cache priming, and memory yielding from
   69497ad to the download processor. Omit its selection sidecar because it depends
   on excluded improvement 4. Reuse the COGS service cache primer.
4. Port Apply preparation (5) from 73115fd, including batched exclusions,
   reviewed-product reads, and bounded preflight continuations in the admin
   controller, COGS service, import store, and frontend source.
5. Document rounding, recovery, selection reuse, stopping and navigation, blank
   import validation, the upgrade notice, and the selected performance work.

## Acceptance and checks

- Preserve ownership and capability checks, ID/SKU agreement, duplicate rejection
  across batches and retries, CSV quoting, XLSX security, and store precision.
- Advance input cursors only after staging succeeds. Reuse XLSX streams only at
  the exact durable cursor and close them before leaving a worker.
- Keep independent cancellation, frozen batch sizes, bounded memory/time limits,
  existing WooCommerce getters and CRUD, and existing persisted job compatibility.
- Run PHP formatting, syntax checks, and PHPCS on all modified PHP files using
  scripts/php-quality.py (the npm alias is absent). Regenerate the POT for changed
  translatable PHP and rebuild frontend assets if source JavaScript changes.
- Review source and diffs, refresh Graphify once after final edits, and inspect
  relevant symbols. Do not run tests, benchmarks, product updates, or commits.
- Report source-level reductions separately from throughput; runtime gains remain
  unmeasured.

## Port result

Implemented the confirmed imports, Apply checks, and export changes. The CSV
service, import reader, import processor, import store, and COGS service match
their ux versions. The download processor retains dev's inline-state cleanup
because selection sidecars belong to excluded improvement 4. The operation
store, history store, bulk processor, and retention processor are unchanged.

Source review covered replayed staging, duplicate rows across batches, partial
validation, durable cursor advancement, cancellation between batches, XML stream
closure, selected-column getters, and the owned-preview continuation before its
single-use claim. The worker rechecks reviewed costs when processing updates.

PHP formatting, syntax checks, and PHPCS passed for all seven modified PHP files
using `python3 scripts/php-quality.py all` with explicit paths. `npm run build`
passed. Tests and performance benchmarks were not run, and no product updates
were started. These checks do not establish a runtime throughput improvement.
