# Update Plan — v1.0.0 → v1.1.0
<!-- lwdt: 202609282135 -->

## Objective
Transition Bulk COGS Editor for WooCommerce from its initial scaffolding state to a fully operational, production-grade bulk Cost of Goods Sold (COGS) editor. This update builds out the admin product list view under Products > Bulk COGS Editor, provides robust COGS meta key handling across standard WooCommerce inventory plugins (`_cogs_cost`, `_wc_cog_cost`, `_cost_of_goods`), implements an AJAX batch-saving engine with nonce and capability verification, and establishes PHPUnit test coverage.

## Version Bump
- Current: `1.0.0`
- Target:  `1.1.0` — minor bump
- Reason:  new feature

## Pre-flight Checklist
- [x] PHPCS passes with 0 errors (`phpcs --standard=phpcs.xml .`)
- [ ] All existing tests pass
- [x] `composer.json` / `package.json` versions reviewed
- [ ] Changelog entry drafted
- [ ] Deprecation notices added for any removed hooks/filters

## Tasks

### 1. Code Changes

#### 1.1 Plugin Metadata & Compatibility
- [ ] **T-001** Harmonize minimum PHP version requirement between plugin header (PHP 8.0), `readme.txt` (PHP 7.4), and `phpcs.xml` (PHP 7.4+) — `bulk-cogs-editor-for-woocommerce.php:9`, `readme.txt:7` — Priority: Med
- [ ] **T-002** Implement COGS metadata helper class/methods to detect, retrieve, and update COGS values supporting simple and variable products across popular COGS meta keys — `admin/class-dkbce-admin-functions.php` — Priority: High

#### 1.2 Admin UI & Table View
- [ ] **T-003** Implement product query pagination, product search, and category filtering in admin functions — `admin/class-dkbce-admin-functions.php` — Priority: High
- [ ] **T-004** Replace placeholder admin heading with interactive Bulk COGS editor table with editable cost inputs, margin calculation, and product thumbnails — `admin/class-dkbce-admin-functions.php` — Priority: High
- [ ] **T-005** Register and enqueue admin stylesheet and JavaScript for table interactions and quick bulk actions — `admin/class-dkbce-admin-hooks.php` — Priority: Med

#### 1.3 AJAX & Batch Operations
- [ ] **T-006** Implement AJAX batch save endpoint `dkbce_save_bulk_cogs` to persist edited COGS values with capability and nonce verification — `admin/class-dkbce-admin-hooks.php`, `admin/class-dkbce-admin-functions.php` — Priority: High
- [ ] **T-007** Add quick bulk action controls (apply fixed COGS, increase/decrease by percentage, clear COGS) — `admin/class-dkbce-admin-functions.php` — Priority: Med

### 2. Database / Migration
- [ ] **DB-001** Add option `dkbce_cogs_meta_key` with fallback detection for existing store COGS data — `admin/class-dkbce-admin-functions.php`
- [ ] Verify `update_option( 'dkbce_version', '1.1.0' )` upgrade routine

### 3. Security Hardening
- [ ] **SEC-001** Implement nonce verification (`check_ajax_referer( 'dkbce_admin_nonce', 'security' )`) and capability check (`current_user_can( 'manage_woocommerce' )`) for AJAX save handler — `admin/class-dkbce-admin-hooks.php` — Priority: High
- [ ] **SEC-002** Sanitize and validate all incoming product IDs (`absint`) and price/cost values (`wc_format_decimal`) with `wp_unslash()` — `admin/class-dkbce-admin-functions.php` — Priority: High

### 4. Test Updates
- [ ] **TST-001** Scaffold PHPUnit configuration (`phpunit.xml.dist`) and test bootstrap for WooCommerce plugin tests — `phpunit.xml.dist`, `tests/bootstrap.php` — Priority: Med
- [ ] **TST-002** Add unit test for COGS retrieval, formatting, and update logic — `tests/test-cogs-functions.php` — Priority: High
- [ ] **TST-003** Add security test verifying unauthenticated and non-admin requests are rejected by AJAX handlers — `tests/test-ajax-security.php` — Priority: High

### 5. Documentation
- [ ] Update `readme.txt` changelog section for v1.1.0 release notes
- [ ] Update inline DocBlocks for all new methods and hooks
- [ ] Update `.agents/features.md` after implementation

## Breaking Changes
| Type | Old | New | Migration Path |
|---|---|---|---|
| (none) | (none) | (none) | N/A — No breaking changes introduced |

## Rollout Notes
Standard WooCommerce plugin minor update. Requires WooCommerce 7.0+ and WordPress 6.5+. No custom database tables required. High-Performance Order Storage (HPOS) compatibility is maintained.

## Definition of Done
- [ ] All tasks above checked off
- [ ] PHPCS 0 errors (`phpcs --standard=phpcs.xml .`)
- [ ] All tests green
- [ ] Version constant updated everywhere (`DKBCE_VERSION`, plugin header, `readme.txt`)
- [ ] Tagged in git: `v1.1.0`
