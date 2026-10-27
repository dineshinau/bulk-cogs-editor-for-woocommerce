# Release 1.2.1 verification

Verification targets `dev` at `8524a79`. Product source is unchanged by this verification task. Test harness changes use temporary shop-manager and customer accounts rather than the shared administrator, and clean up operations belonging to the temporary manager.

No release-blocking product defect was found in the tested environment. The remaining runtime coverage limits are listed below.

## Environment

- Local site: `https://wsl.com/wcdev`.
- WordPress 7.1.3; WooCommerce 11.2.1; plugin 1.2.1 active; COGS enabled.
- PHP CLI 8.4.27; web requests use the configured PHP 8.3 FPM socket.
- Chromium through installed Google Chrome; desktop and 390px mobile checks.
- Store currency precision: two decimal places. Import validation also exercises zero and three decimal places using a request-local filter.
- Disposable release fixtures: 260 additional simple products, initially COGS 10 and regular price 20. Updates and rollback are restricted to these fixtures.

## Completed checks

| Check | Result |
| --- | --- |
| Existing browser regression suite | 16 passed in 3.9 minutes, including fixture cleanup |
| Runtime PHP syntax and PHPCS | Passed for bootstrap and all 15 admin classes |
| Release regression scenarios | 15 distinct scenarios passed across focused runs |
| Test fixture PHP syntax and PHPCS | Passed for both fixture files |
| Production build | Passed; generated assets unchanged |
| Release archive | 25 intended plugin files; ZIP integrity check passed |
| Version declarations | Plugin header, constant, package version, stable tag, and current changelog agree on 1.2.1 |

The existing suite covers filters, product types, search, calculation modes, rounding, pagination and selection, export previews, invalid input, nonces, customer capability rejection, stored-content escaping, mobile layout, and a confirmed fixture update with replay rejection.

## Focused release scenarios

All 15 distinct release scenarios have passing results across the initial run and targeted reruns. Together with the 16 existing regressions, this gives 31 distinct passing scenarios; this was not a single uninterrupted green run.

| Scenario | Result |
| --- | --- |
| Import rounding at 0, 2, and 3 decimal places | Passed |
| Mixed ID/SKU CSV batches, cross-batch duplicates, invalid rows, unchanged costs, and error report | Passed |
| XLSX with 3,002 shared strings, 1,079 empty worksheet rows, and 260 valid products | Passed on targeted rerun in 1.3 minutes |
| XLSX formulas and invalid CSV headers rejected before updates | Passed |
| Cancel preparation confirmation, Escape/Cancel behavior, and accessible tabs | Passed |
| Stop confirmation, Escape/Cancel behavior, and return from Settings to both editor tabs | Passed |
| Force clear confirms, requests cancellation, clears references, and retains operation data | Passed |
| Stale force-clear references rejected without cancelling a newer operation | Passed |
| Exclude 255 CSV rows, update exactly five, reject replay, hide empty reports, and roll back | Passed |
| Render all 260 reviewed products, continue Apply past 250, update, preserve prices, reuse changed-cost selection, check mobile layout, and roll back all | Passed on final focused run in 2.1 minutes |
| Pending/started retention rejects new previews, refreshed previews, pages, range selection, and Apply | Passed |
| Cleanup starting during a preview query rejects the result using fresh state | Passed |
| Cleanup marker saved before outcome deletion and retained across bounded retries | Passed |
| Retention recheck preserves still-retained outcomes and allows reuse | Passed |
| Selected-column export across batches, correct CSV contents, and unchanged costs | Passed on final focused run in 55.9 seconds |

Initial harness failures were resolved without changing product source: the old heading and shared-admin login dependency, short XLSX wait, force-clear reload synchronization, formatted-price and quoted-header expectations, pagination waits, and settling confirmed updates between tests. A subsequent empty-array parser error was introduced and corrected in the fixture helper. Early failed test attempts remain in the local JSON reports; the final update/rollback and export rerun is green.

## Cleanup

The fixture teardown cancels only operations owned by its temporary manager, waits for worker ownership locks, removes staged rows, outcomes, history, scheduled fixture work, products, terms, users, and temporary login state. A private manifest permits removal of test uploads in PHP-FPM's private temporary directory. The final read-only audit covered 1,304 recorded products across four runs and found zero remaining products, matching SKUs, test users, import rows, history rows, outcomes, or owned operation records. PHP-FPM cleanup reported no failed files.

## Reproduction

```bash
DKBCE_BASE_URL=https://wsl.com/wcdev DKBCE_WP_PATH=/var/www/html/wcdev npm run test:e2e -- tests/e2e/bulk-cogs-editor.spec.cjs
DKBCE_E2E_FPM_SOCKET=/run/php/php8.3-fpm.sock DKBCE_E2E_RELEASE=1 DKBCE_BASE_URL=https://wsl.com/wcdev DKBCE_WP_PATH=/var/www/html/wcdev npm run test:e2e -- tests/e2e/release-workflows.spec.cjs
npm run build
```

The optional `DKBCE_E2E_FPM_SOCKET` setting lets teardown remove only recorded fixture uploads through their owning web runtime when PHP-FPM uses a private temporary directory. It requires access to the local socket. Cleanup waits for fixture worker locks and retains state if a worker remains active.

PHP checks use `python3 scripts/php-quality.py check` with explicit runtime paths and `all` with the two test fixture paths. The documented `npm run php:quality` alias is absent from this checkout; the underlying helper is available.

## Limits

This run does not cover the declared PHP 7.4 / WordPress 6.7 / WooCommerce 10.3 minimum-version matrix, Firefox/Safari, multisite, or an installed ZIP upgrade. PHPCS includes compatibility checks but cannot replace runtime matrix testing. No large-catalog performance benchmark or claim about production throughput follows from these fixture tests. The planned 50,000-product benchmark remains unrun.
