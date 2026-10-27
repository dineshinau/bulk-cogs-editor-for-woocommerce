# Time-boxed browser QA — 23 October 2026

## Scope and outcome

Approximately ten-minute exploratory and regression pass against `https://wsl.com/wcdev`, starting 11:29:58 UTC. Chrome ran visibly through Playwright. Repository baseline: `f5c8df7`. WordPress displayed version 7.1.3. Local development HTTPS certificate validation was disabled in the test browser.

Overall: **31 documented scenarios: 29 passed and 2 failed (one UX finding and one test-infrastructure finding).** All 16 existing regression tests passed, with no skips or retries, in 138 seconds including setup and cleanup. Additional interactive checks covered CSV export/import, history, rollback review, settings validation, and mobile layout. This is a rapid smoke assessment, not exhaustive feature or release certification.

## Existing regression scenarios

Each row refers to the implemented assertions in `tests/e2e/bulk-cogs-editor.spec.cjs`; some authorization checks use browser-context HTTP requests rather than clicks. Full machine-readable results: [suite-results.json](2026-10-23-qa/suite-results.json).

| ID | Scenario | Result |
| --- | --- | --- |
| R01 | loads admin page with six actions and accessible filter controls | PASS |
| R02 | keeps the apply panel in document flow on mobile | PASS |
| R03 | search matches product title and SKU and reset clears filters | PASS |
| R04 | filters by category, price range, COGS range, and combined AND conditions | PASS |
| R05 | filters simple, variable, variation, grouped, and external products | PASS |
| R06 | filters all stock states | PASS |
| R07 | filters by supported brand taxonomy when available | PASS |
| R08 | previews all six actions without modifying the current COGS value | PASS |
| R09 | preview shows images, edit links, increase green and decrease red | PASS |
| R10 | skips relative operations for empty COGS and WooCommerce-normalized zero | PASS |
| R11 | rounds calculated COGS to store precision | PASS |
| R12 | paginates large previews and reports total matching products | PASS |
| R13 | renders malicious product names as text | PASS |
| R14 | requires valid filters, action values, nonce, and capability | PASS |
| R15 | cancellation request is scoped to an existing authorized operation | PASS |
| R16 | confirms apply, processes selected disposable product, and rejects replay | PASS |

The six preview calculations started at 10.00: set 15 → 15.00; +10% → 11.00; −10% → 9.00; +2 → 12.00; −2 → 8.00; clear → Empty. The actual bulk-write test applied 15.00 to one disposable product, verified the value after reload, and rejected preview replay. Cancellation coverage confirms request authorization and the cancellation flag; it does not prove interruption under sustained worker load.

## Interactive scenarios

| ID | Steps and expected result | Actual result | Status |
| --- | --- | --- | --- |
| E01 | Open Settings and History; controls and records should load | Both loaded successfully | PASS |
| E02 | Export search `apparel-01`; verify all matching parent/variation rows | Five rows exported, IDs 654/656/657/658/659; blank final `new_cogs` column | PASS |
| E03 | Download import template | CSV includes `id,sku,cogs,new_cogs` and example row | PASS |
| E04 | Validate with no selected file | Actionable file/upload-limit message | PASS |
| E05 | Upload `sku,cost` headers | Background validation rejects missing required cost header | PASS |
| E06 | Validate mixed CSV containing one valid row, unknown SKU, and blank cost | Only valid row previewed: 7.12 → 8.50; other two rows excluded | PASS |
| E07 | Download validation error report | CSV contains row numbers 3/4 and reasons; negative value is spreadsheet-escaped | PASS |
| E08 | Compare blank-cost behavior against File requirements | Help says blank costs are skipped, but row is counted as invalid | FAIL — F01 |
| E09 | Open details for completed update | Original 10.00 and updated 15.00, timestamps, counters, and filters displayed | PASS |
| E10 | Review rollback for deleted test product | Missing-product warning and explicit overwrite acknowledgement displayed; confirmation initially disabled | PASS |
| E11 | Enter zero custom retention days and attempt Save | Native validity rejects zero; no valid settings update submitted | PASS |
| E12 | Resize Bulk edit to 390 × 844 | Document width 390; no horizontal page overflow | PASS |
| E13 | Import CSV for disposable product 2141, cost 10.00 → 12.50 | Preview and one-product confirmation correct; completion shown; WooCommerce CRUD read returns 12.5 | PASS |
| E14 | Inspect cleanup after regression run | Product removed but administrator-owned history remains | FAIL — F02 |
| E15 | Confirm rollback of disposable import, then verify stored cost | Completed in 34 seconds including worker wait; WooCommerce CRUD read returned 10.00 | PASS |

## Findings

### F01 — Low severity: blank cost is presented as an error despite skip guidance

Reproduce: select CSV import; upload [mixed.csv](2026-10-23-qa/mixed.csv); validate. Row 4 has an existing SKU and empty `new_cogs`. File requirements says “Blank costs are skipped”, while the UI reports “Invalid rows: 2” and includes the blank row in [import-errors.csv](2026-10-23-qa/import-errors.csv). The valid row remains usable. No cost corruption observed.

Expected: distinguish intentionally skipped blank costs from invalid data, or make the help explicitly state they are counted as validation errors. Evidence: [mixed import screenshot](2026-10-23-qa/mixed-import.png).

### F02 — Medium severity, test infrastructure: teardown leaves administrator-owned operation history

Reproduce: run the existing headed suite and open History after “Temporary Playwright fixtures removed.” Operation `5271df47-25d5-4b8c-97ee-c0e63fbd85a5` remains, showing a deleted product and original/new costs 10.00/15.00. Setup signs in as administrator, but `dkbce_e2e_cleanup()` only deletes discovered operation records when their owner equals the temporary manager ID. This creates persistent test pollution across runs.

Expected: cleanup should track and remove only operations created by that run, regardless of which authorized fixture actor created them. Evidence: [rollback review of leftover operation](2026-10-23-qa/rollback.png), `tests/e2e/global-setup.cjs`, `tests/e2e/fixtures.php`. No cleanup code was changed in this assessment.

## Limitations and follow-up coverage

Not exercised: XLSX parsing/formulas/multiple sheets; large or oversized imports; live cancellation mid-batch; crash/retry recovery; concurrent administrators; expiry/retention deletion; rollback skip-changed policy; settings persistence after a valid change; all selection-range/column-preference combinations; all history filter/pagination combinations; export column subsets; keyboard-only and screen-reader audit; Firefox/Safari; large-catalog performance. No 50,000-product benchmark was run.

A few exploratory Playwright commands used overly broad locators or attached download listeners incorrectly. These were corrected using observed controls and downloaded-file inspection; automation-command errors are not counted as product failures. The plugin page console check returned zero errors at the time inspected.

## Evidence

[Export CSV](2026-10-23-qa/export.csv) · [Template CSV](2026-10-23-qa/template.csv) · [Import errors](2026-10-23-qa/import-errors.csv) · [Settings](2026-10-23-qa/settings.png) · [History](2026-10-23-qa/history.png) · [Mobile](2026-10-23-qa/mobile.png).

## Data handling and completion

The suite reported removal of temporary products/users. Its administrator-owned operation history remained (F02). The additional disposable product ID 2141 was imported from 10.00 to 12.50, rolled back to 10.00, verified through WooCommerce CRUD, and deleted. Existing catalog products were used only for export and unapplied import previews. Settings were not saved with a valid change. QA-created import/rollback history and temporary upload/export jobs remain for normal retention cleanup; no unrelated history was removed.

[Completed rollback](2026-10-23-qa/rollback-complete.png). Browser left open for inspection at the end of testing. Graph refresh completed successfully without topology changes, and the whitespace check passed.
