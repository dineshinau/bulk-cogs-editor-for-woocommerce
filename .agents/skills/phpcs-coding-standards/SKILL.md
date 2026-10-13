---
name: phpcs-coding-standards
description: Apply this plugin's phpcs.xml while writing or reviewing PHP. Use PHPCBF for formatting, PHP syntax checks, and PHPCS for WordPress, WooCommerce, security, and compatibility findings on the affected PHP files.
---

# Project PHP standards

Read `AGENTS.md`, `phpcs.xml`, and the relevant source. The repository configuration is authoritative; do not substitute generic WooCommerce core paths, namespaces, or version floors.

## Write compliant code

- Preserve `DKBCE_` / `dkbce_`, text domain `bulk-cogs-editor-for-woocommerce`, tabs, WordPress spacing, Yoda conditions, and existing class/file organization.
- The current ruleset targets PHP 7.4+ and WordPress 6.5. Check the plugin header too; preserve the stricter applicable requirement.
- Document functions and hooks. Sanitize unslashed request values, verify capabilities and nonces, escape output, and use prepared dynamic SQL.
- Keep WooCommerce CRUD, public hooks, and retry/cancellation behavior unless the task changes them.
- Do not edit dependencies or generated files, weaken `phpcs.xml`, or add broad suppressions to make checks pass.

## Format and check during editing

After each coherent edit, run the combined command on every PHP file changed by the task:

```bash
npm run php:quality -- admin/class-dkbce-bulk-processor.php
```

It runs PHPCBF, PHP syntax checks, and PHPCS with `phpcs.xml` and one worker. Review the formatting diff, fix introduced findings, then repeat only when edits or failures require it.

Separate commands are available:

```bash
npm run phpcbf -- admin/class-dkbce-cogs-service.php
npm run phpcs -- admin/class-dkbce-cogs-service.php
```

Without paths, the helper selects staged, unstaged, and untracked PHP files. It excludes dependencies, generated output, and shared skills. Explicit paths are best when unrelated user changes exist.

The helper finds project-local binaries, PATH commands, or Composer user binaries. Set `DKBCE_PHPCBF_BIN` and `DKBCE_PHPCS_BIN` for another existing installation. Missing tools or standards are reported; do not silently install dependencies or substitute a different ruleset.

PHPCBF exit code 1 means fixes were applied. PHPCS warnings still cause a nonzero result: distinguish existing warnings from introduced findings and never claim a clean report unless it is clean.
Report the commands and results. Tests are separate and require a user request. Refresh Graphify after the final edit as required by `AGENTS.md`.
