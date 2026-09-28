# Unique Prefixing Audit Report

**Plugin:** `cd-recurring-subscriptions`  
**Expected Prefix:** `wksdc` (`Wksdc_` for classes, `wksdc_` for hooks/options/functions, `WKSDC_` for constants)  
**Audit Date:** 5 March 2026  

---

---

## Violations Found

### Violation 1 — Generic WC Account endpoint slug `'subscriptions'` (HIGH)

**Problem:** The My Account endpoint uses the generic, unprefixed slug `'subscriptions'`, which will collide with WooCommerce Subscriptions or any plugin that registers the same endpoint.

| # | File | Line | Current Code |
|---|------|------|--------------|
| 1 | `src/customer/class-wksdc-account-endpoint.php` | L31 | `public const SLUG = 'subscriptions';` |
| 2 | `src/customer/class-wksdc-account-endpoint.php` | L198 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 3 | `src/customer/class-wksdc-wallet-endpoint.php` | L48 | `if ( 'subscriptions' === $key …)` |
| 4 | `src/util/class-wksdc-mailer.php` | L61 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 5 | `templates/customer/subscription-detail.php` | L18 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 6 | `templates/customer/subscription-detail.php` | L54 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 7 | `templates/customer/subscription-wizard.php` | L13 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 8 | `templates/customer/subscriptions-list.php` | L21 | `wc_get_account_endpoint_url( 'subscriptions' )` |
| 9 | `templates/customer/subscriptions-list.php` | L118 | `wc_get_account_endpoint_url( 'subscriptions' )` |

**Fix:** Change `SLUG` constant to `'wksdc-subscriptions'`. Update all 9 locations to use the new slug or reference the constant.

---

### Violation 2 — Wallet endpoint slug uses `cd-` prefix (MEDIUM)

**Problem:** The wallet endpoint uses `'cd-wallet'` instead of the standard `wksdc` prefix.

| # | File | Line | Current Code |
|---|------|------|--------------|
| 1 | `src/customer/class-wksdc-wallet-endpoint.php` | L21 | `public const SLUG = 'cd-wallet';` |

**Fix:** Change to `'wksdc-wallet'`.

---

### Violation 3 — Admin menu slug uses `cd-` prefix (MEDIUM)

**Problem:** The Delivery Blackouts admin submenu uses `'cd-delivery-blackouts'` instead of a `wksdc`-prefixed slug.

| # | File | Line | Current Code |
|---|------|------|--------------|
| 1 | `src/admin/class-wksdc-admin-menu.php` | L128 | `'cd-delivery-blackouts'` (submenu slug in `add_submenu_page`) |
| 2 | `src/admin/class-wksdc-blackout-menu.php` | L49 | `if ( 'cd-delivery-blackouts' !== $page )` |
| 3 | `src/admin/class-wksdc-blackout-menu.php` | L232 | `'page' => 'cd-delivery-blackouts'` (URL parameter) |

**Fix:** Change all three from `'cd-delivery-blackouts'` to `'wksdc-blackouts'`.

---

### Violation 4 — Stale admin hook with `cd-` prefix + unprefixed HTML id (LOW)

**Problem:** A hardcoded WordPress admin hook uses the `cd-subscriptions` prefix, and an HTML `div` element uses the `cd-` prefix for its id attribute.

| # | File | Line | Current Code |
|---|------|------|--------------|
| 1 | `src/admin/class-wksdc-admin-menu.php` | L174 | `'cd-subscriptions_page_wksdc-settings'` in `$allowed_hooks` array |
| 2 | `src/wallet/class-wksdc-wallet-checkout-handler.php` | L102 | `id="cd-wallet-checkout-section"` (HTML div id) |

**Fix for L174:** The top-level menu slug is `wksdc`, so WordPress generates submenu hooks as `wksdc_page_{submenu-slug}`. Replace with `'wksdc_page_wksdc-settings'` or remove it (the `str_contains($hook, 'wksdc')` fallback already covers it).  
**Fix for L102:** Rename HTML id to `wksdc-wallet-checkout-section`.

---

## Summary

| Severity | Violation | Locations | Files Affected |
|----------|-----------|-----------|----------------|
| HIGH | Generic `'subscriptions'` slug | 9 | 5 |
| MEDIUM | `'cd-wallet'` endpoint slug | 1 | 1 |
| MEDIUM | `'cd-delivery-blackouts'` menu slug | 3 | 2 |
| LOW | Stale `cd-` admin hook + HTML id | 2 | 2 |
| **Total** | **4 distinct issues** | **15** | **8** |
