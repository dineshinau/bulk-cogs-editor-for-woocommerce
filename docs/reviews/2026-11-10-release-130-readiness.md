# Release 1.3.0 readiness review

Review date: 2026-11-10 UTC. Reviewed commit: `a35e086`. Recommendation: hold publication until the cancellation issue and regression-suite gaps below are addressed. No product source was changed, committed, pushed, or published during this review.

## Findings and mitigations

### P2: Import cancellation is delayed until the next status poll

Location: `src/bulk-cogs-editor.js:2865`, with polling at lines 22 and 882; server handling in `admin/class-dkbce-admin-functions.php:955`.

Confirming Cancel preparation sets `state.importCancel`, disables the button, and displays cancellation text. It does not send a request. Only the next `pollImport()` call sends `cancel=1`. Version 1.3.0 uses a 25-second interval when the total is unknown or at least 1,000 rows, extending this existing coupling between cancellation and polling.

Reproduced through Playwright CLI with a pending import owned by the disposable QA account. The cancellation request was not sent within five seconds; it was sent 24,859 ms after confirmation. The server eventually recorded cancellation. Closing the page before the poll would lose the local flag. Import preparation does not change product costs, so this finding is about cancellation reliability and avoidable background work, not demonstrated cost corruption.

Mitigation: send cancellation immediately on confirmation, handle request failure, and retain normal status polling separately. Verify that cancellation reaches the server before the next scheduled status poll, including when the page is closed afterward. Cover Escape and preparation finishing while confirmation is open.

### P2: The release suite does not currently provide a green release gate

Command: `DKBCE_BASE_URL=https://wsl.com/wcdev DKBCE_WP_PATH=/var/www/html/wcdev DKBCE_E2E_RELEASE=1 DKBCE_E2E_FPM_SOCKET=/run/php/php8.4-fpm.sock npm run test:e2e`.

Result: **26 passed, 5 failed in 15.0 minutes**, including setup and teardown. The initial sandbox-denied attempt did not execute tests; this result is from the permitted rerun. The site actually uses PHP 8.3 FPM. Fixture-file cleanup was subsequently checked through the correct socket and reported no failed files.

| Failing scenario | Evidence and classification | Required mitigation |
| --- | --- | --- |
| Category/price/COGS combined filters | Positive match assertion passed. The empty-result assertion still expects text in `#dkbce-product-count`, which 1.3.0 intentionally clears. | Assert the current empty-result panel and absence of obsolete counts. |
| Confirm one-product update and reject replay | The test checks the hidden selected-only control but never selects a product. Explicit selection is now required, leaving Apply disabled. | Select the fixture row and update confirmation-copy assertions. |
| Cancel import preparation | The five-second cancellation assertion fails; CLI independently measured a 24.9-second delay. | Fix immediate cancellation and retain the prompt-cancellation assertion. |
| Apply five CSV rows and roll back | Five updates and replay rejection passed. Rollback eligibility failed because the existing site default was `none`, and the test omitted `rollback_mode`. | Explicitly request `keep` for rollback scenarios; add independent `none` scenarios. Do not depend on shared site settings. |
| Review/update 260 products | The preceding failed rollback left five fixture costs changed. Filtering for COGS 10 therefore returned 255 products, with 55 on the final page. This is a cascading fixture-state failure. | Restore fixture state between scenarios. Explicitly select products in this test too. Rerun the complete 260-product preflight/update/rollback scenario. |

The current suite contains no `rollback_mode` assertions and no dedicated preset scenarios. This review added temporary CLI checks, but these do not replace maintained regression coverage for the new features. Do not describe this run as 31 passing scenarios or as validation of the full 260-product update path.

### P3: One project PHPCS error remains

Location: `admin/class-dkbce-admin-functions.php:355`.

The production PHP scan reports one error, zero warnings: `Squiz.Commenting.BlockComment.NoNewLine`, “Block comment text must start on a new line.” Adjacent translator comments precede `lastProgress`. Consolidate the obsolete translator annotation and run PHPCBF, syntax checks, and PHPCS. This is a standards failure, not a demonstrated runtime defect.

The documented `npm run php:quality` alias is absent from `package.json`. `scripts/php-quality.py` exists and can run the quality loop directly. Repair the documented command or its alias so release checks are reproducible.

## Environment and scope

- WordPress 7.1.4; WooCommerce 11.2.2; plugin 1.3.0; native COGS enabled.
- PHP CLI 8.4.27; Nginx web requests use `/run/php/php8.3-fpm.sock`.
- Chromium through Google Chrome, using both the existing Playwright test runner and the explicitly requested Playwright CLI.
- Production source review focused on changes since tag `1.2.1`: authorization, preset ownership/revisions, operation-context persistence, history journals/outcomes, rollback mode, retention continuation, download cleanup, and selection recovery. Existing import/export paths were also exercised and inspected.
- Automated fixtures: 326 products and temporary shop-manager/customer accounts. Supplemental CLI workflows used a separate shop manager and two simple products, initially COGS 10 and 20, both regular price 40.
- No shared catalog product was intentionally updated. The existing site default `dkbce_rollback_mode=none` was read, not changed.

## Checks and observed results

| Check | Result |
| --- | --- |
| Release version declarations | Header, `DKBCE_VERSION`, package version, lockfile root version, stable tag, and current changelog agree on 1.3.0. |
| Database version | `DKBCE_DB_VERSION` is 1.2.1, newer than the 1.1.0 schema constant in release 1.2.1. Schema and plugin versions are independent. The active site already has the preset table. |
| Production build | `npm run build` passed; all four generated assets matched the committed files. |
| PHP syntax | All 22 packaged PHP files passed syntax checks under CLI PHP 8.4. Bootstrap also passed PHP 8.3 syntax checking. |
| Project PHPCS | Bootstrap, index, and admin code scanned with `phpcs.xml`, one worker. One formatting error described above; zero warnings. |
| Translation catalog | Generated a temporary runtime POT with WP-CLI. `msgcmp --use-untranslated` found no missing runtime messages in the tracked POT. Tracked translations were not regenerated. |
| Release package | Built a temporary ZIP matching the package script's runtime allowlist: 27 files. ZIP integrity check passed. No tests, agent files, dependencies, graph files, or QA artifacts included. |
| Official Plugin Check 2.2.0 | Repository category, update mode, on the extracted allowlisted package: **0 errors, 129 warnings**. This was not an all-category/runtime Plugin Check pass. |
| Existing browser suite | 26/31 passed; failures classified above. |

Plugin Check warnings consist of 59 direct-query notices, 56 uncached-query notices, eight SQL-parameter warnings, five input-sanitization warnings, and one schema-change notice. The eight SQL warnings involve prepared UNION branches, generated placeholder lists, or fixed internal clauses; the flagged inputs are passed to service validation. No SQL-injection defect was confirmed in these reviewed paths. Private tables, durable worker state, locks, and schema upgrades account for many notices. Preserve an explicit warning triage; do not claim the scanner was warning-free.

A preliminary scan of the development checkout reported six packaging errors and 132 warnings, including a nested ZIP, hidden development files, and PHPCS reports. Those files are absent from the reviewed package. Publish only the allowlisted runtime contents, not the entire workspace. WordPress.org's [plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) and [readme guidance](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) remain the external release references.

## Supplemental Playwright CLI workflows

| Workflow | Observed result |
| --- | --- |
| Find two QA products and preview exact cost | Two matches, current/proposed costs displayed, zero products selected, Apply disabled until explicit selection. |
| Account preset | Saved filters to the temporary account, opened the native Presets table, hovered the row, and renamed the preset successfully. |
| Preset persistence and deletion | Renamed preset survived reload. Delete confirmation initially focused Cancel; Escape dismissed it. Confirmed deletion removed the preset. |
| Exact update with rollback history | Explicitly selected the two QA products, enabled Keep rollback history, confirmed the UI update, and verified both costs became 15. Prices remained 40. |
| UI rollback | Opened History, reviewed rollback, checked acknowledgment, and confirmed. Backend reads verified restoration to 10 and 20; prices remained 40. |
| No rollback history | Confirmed a 10% increase with history unchecked. Costs became 11 and 22; prices remained 40. Operation completed with two successes, two durable outcomes, and zero temporary journal rows after cleanup. |
| No-history rollback denial | History review returned `can_rollback=false`, zero product-change rows, and a specific explanation that history was disabled. |
| Import cancellation timing | Confirmed through the dialog; server-bound cancellation request measured at 24,859 ms. |
| Mobile layout | At 390 and 320 CSS pixels, document width equalled viewport width. Screenshot inspection at 320 pixels showed current and new costs together. Desktop and mobile screenshots remain under `/tmp`. |

Temporary CLI scripts used actual UI interactions for save/edit/update/rollback/confirmation. Direct backend reads verified cost and persistence results. Some initial CLI attempts used stale labels, ambiguous hidden locators, or unhovered native row actions; those harness errors were corrected and are not counted as plugin failures.

## Remaining release evidence

Before publication, obtain a green maintained regression run after the mitigations above. Also verify a clean install and an actual upgrade from 1.2.1 using the final release ZIP; the current site already had the new schema, so it cannot prove migration correctness by itself.

This review does not establish runtime compatibility across PHP 7.4, WordPress 6.7, and WooCommerce 10.3, nor across Firefox/Safari, multisite, or persistent-object-cache configurations. Static compatibility checks do not replace that matrix. No large-catalog throughput benchmark was run; neither the planned 50,000-product benchmark nor production performance is established by these fixtures.

## Cleanup

The suite removed all 326 recorded fixture products and both temporary users; a separate read-only audit found zero remaining products or users for that run. The manual CLI cleanup removed five owned operations, both supplemental products, and its temporary user, and confirmed zero presets for that user. The browser was closed. Shared site rollback settings were preserved. Generated browser artifacts were moved outside the checkout. No production PHP or frontend source was edited.

## Local evidence

Raw outputs are retained under `/tmp/dkbce-130-*`, including `e2e.log`, `phpcs.log`, `build.log`, `package-check.json`, `package-check-parsed.json`, `none-audit.json`, and CLI workflow logs. The temporary release ZIP is `/tmp/dkbce-1.3.0-review.zip`. These local artifacts are not published and may disappear when temporary storage is cleared. Credential-bearing fixture state and login scripts are intentionally excluded from this report and from repository artifacts.
