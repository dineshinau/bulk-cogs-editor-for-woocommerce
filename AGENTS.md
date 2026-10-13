# Codex project instructions

## Project map

Bulk COGS Editor for WooCommerce, version 1.0.0; HPOS compatible.
Read version declarations from the plugin header and `phpcs.xml` before changing compatibility.
The current header requires PHP 7.4, WordPress 6.7, and WooCommerce 10.3.
PHPCS currently checks PHP 7.4+ and WordPress 6.5; use the stricter applicable constraint.

| Responsibility | Source |
| --- | --- |
| Bootstrap, compatibility, logging | `bulk-cogs-editor-for-woocommerce.php` |
| Admin UI, AJAX, authorization | `admin/class-dkbce-admin-functions.php` |
| Hook registration | `admin/class-dkbce-admin-hooks.php` |
| Filters, queries, COGS calculations | `admin/class-dkbce-cogs-service.php` |
| Action Scheduler, snapshots, updates | `admin/class-dkbce-bulk-processor.php` |
| Checkpoints, outcomes, cancellation | `admin/class-dkbce-operation-store.php` |
| Frontend source / generated assets | `src/` / `assets/` |

## Scope and correctness

- Preserve user changes, public hooks, behavior, `DKBCE_` / `dkbce_` prefixes, and text domain `bulk-cogs-editor-for-woocommerce`.
- Follow `phpcs.xml`; ignore conflicting prefixes, compatibility floors, paths, and commands in generic skills.
- Sanitize unslashed input, escape output, check capabilities and nonces, and prepare dynamic SQL.
- Use WooCommerce CRUD for product COGS. Preserve per-product outcomes, retry protection, frozen batch sizes, and independent cancellation.
- Keep changes focused. Do not edit dependencies or generated assets by hand. Refreshing `graphify-out/` under the lifecycle below is explicitly authorized.
- Do not add or run tests unless requested. PHP formatting, syntax checks, PHPCS, and graph validation are normal completion checks.
- Do not commit, push, change global agent configuration, or start a bulk product update merely to complete a development workflow.
- Update version declarations and release notes together only when preparing a release.

## Skills and token use

- Use Caveman **lite** for concise updates and results. Keep code, documentation, exact errors, constraints, and evidence clear and complete.
- Read only the skill needed for the current step. Reuse already-read instructions unless their files changed. Do not load the entire `.agents/` pack.
- Use [dkbce-planning](.agents/skills/dkbce-planning/SKILL.md) for changes spanning components, new behavior, or uncertain failures. It adapts Superpowers planning, debugging, and verification to this project.
- Apply straightforward, authorized edits directly. For larger work, write a short plan with acceptance criteria, affected files, and supported checks; do not duplicate implementation code in plans.
- Use Superpowers references only when their method is needed. This project's limits on tests, commits, approval pauses, and scope override generic skill defaults.
- Prefer inline work for small tasks. Delegate only when requested or when an applicable skill explicitly calls for it and independent work justifies the extra context.
- For PHP, read [phpcs-coding-standards](.agents/skills/phpcs-coding-standards/SKILL.md); use the relevant WooCommerce backend guidance as needed.
- For performance investigations, use `wp-performance`; for frontend design or accessibility, load the corresponding existing skill only when the task needs it.
- Use `rg` for named files/symbols. For unfamiliar relationships, use [dkbce-graphify](.agents/skills/dkbce-graphify/SKILL.md) with bounded output, then confirm findings in source.

## PHP editing loop

Write to the active ruleset from the first edit. After each coherent PHP change, run:

```bash
npm run php:quality -- admin/class-dkbce-bulk-processor.php
```

Pass all PHP files touched by the task. Without paths, the helper selects changed and untracked PHP files.
It runs PHPCBF, PHP syntax checks, then PHPCS. `npm run phpcbf -- <files>` formats; `npm run phpcs -- <files>` checks.
Review formatter changes. Fix introduced findings; report unrelated existing warnings. Never weaken the ruleset to hide a finding.
The helper resolves local, PATH, or Composer user tools; `DKBCE_PHPCBF_BIN` and `DKBCE_PHPCS_BIN` can specify explicit binaries.

## Graphify lifecycle: every completed task

1. Read the relevant source; treat graph edges as navigation hints, not proof.
2. Complete edits and applicable formatting/checks.
3. Run `npm run graph:update` once after the final edit, including documentation or configuration tasks. The installed CLI uses cached AST extraction without LLM calls and leaves unchanged graph/report content untouched.
4. For changed code, inspect one relevant symbol or bounded query to confirm coverage. On failure, report the graph as stale and continue using source; do not claim a refresh succeeded.
5. Review `git diff --check` and the final diff. Report changes, checks, and graph status briefly.

The graph covers project code and tooling. `.graphifyignore` excludes shared agent material, documentation, dependencies, generated assets, and graph outputs.
Read documentation directly; routine refreshes do not perform semantic extraction. Do not automatically run `graphify extract`, LLM labeling, watch processes, or install Git hooks.
If the shrink guard blocks a legitimate deletion, inspect the removed sources before choosing `--force`; never hide extraction failures with it.

## Supported checks

- PHP: commands above with `phpcs.xml` (WordPress, WooCommerce, security, PHPCompatibility).
- Frontend: `npm run build` when source assets change; inspect generated output.
- Browser tests exist under `tests/e2e/` (`npm run test:e2e`); run only when requested. No Composer/PHPUnit configuration is present.
- Worker defaults: 50 products, 25-product progress checkpoints, 10-second cooperative update budget. Batch size is configurable through `dkbce_batch_size`.
- Throughput and timeout resilience require measurement. The planned 50,000-product Playground benchmark remains unrun.
