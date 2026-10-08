# Codex project instructions

## Project

- WordPress plugin: Bulk COGS Editor for WooCommerce.
- Main entry point: `bulk-cogs-editor-for-woocommerce.php`.
- Admin implementation: `admin/class-dkbce-admin-functions.php` and `admin/class-dkbce-admin-hooks.php`.
- PHP compatibility declared by the plugin header: PHP 8.0+.
- WordPress compatibility declared by the plugin header: WordPress 6.0+; WooCommerce 7.0+.
- Current plugin version: 1.0.0. Update all version declarations and release notes together when preparing a release.
- The plugin declares WooCommerce HPOS compatibility.

## Rules for changes

- Follow the existing procedural bootstrap, prefixed classes/functions (`DKBCE_`, `dkbce_`), and text domain `bulk-cogs-editor-for-woocommerce`.
- Treat `phpcs.xml` as the authoritative PHP coding and compatibility ruleset. Do not copy conflicting prefixes, minimum versions, or lint instructions from bundled `.agents/skills/` documents; some are generic skill-pack content and do not match this repository.
- For PHP changes, preserve WordPress and WooCommerce conventions: sanitize unslashed input, escape output, verify nonces for state-changing requests, check capabilities, and use `$wpdb->prepare()` for dynamic SQL.
- Preserve existing public hooks and behavior unless the task requires a change. Keep HPOS compatibility in mind for WooCommerce data access.
- Do not edit generated or dependency directories such as `vendor/`, `node_modules/`, or `build/` unless explicitly required.
- Keep changes focused. Do not create tests or run test suites unless requested; when asked to verify, choose checks supported by this repository and report their results.

## Repository notes

- PHP rules: `phpcs.xml` (WordPress Core/Extra/Docs, WooCommerce, security, and PHPCompatibility).
- JavaScript build scripts use `@wordpress/scripts`; `npm run build` is available when frontend assets are involved.
- There is no `tests/` directory or Composer/PHPUnit configuration in the current repository.
- `package.json` contains PHPCS scripts that rely on environment-specific `$VAR` and `.config/composer` paths. Inspect those prerequisites before using the scripts; do not assume a generic `npm test`, `npm run lint`, or PHPStan command exists.
- `.agents/` contains shared skill-pack material and project planning/feature notes. Consult only relevant files, and prefer this file plus repository configuration when instructions conflict.
- The bulk COGS processor uses Action Scheduler with batches of 50. The planned performance check is to create 50,000 products in WordPress Playground and measure the update speed; treat timeout resilience and throughput as unverified until that benchmark is run.
