# Date & Timezone Safety Standards

> **Context:** WordPress sites can run on servers whose `date.timezone` differs from the site's configured timezone (Settings → General). Raw PHP date functions silently use the server timezone, producing wrong dates for cutoff checks, cron schedules, delivery-day matching, and customer-facing output. This document enforces WordPress timezone-aware date handling throughout plugin code.

---

## 1. Forbidden → Required Replacements

| Forbidden | Replacement | Why |
|-----------|-------------|-----|
| `date( 'Y-m-d' )` | `wp_date( 'Y-m-d' )` | `wp_date()` respects `wp_timezone()` since WP 5.3 |
| `date( 'l', $ts )` | `wp_date( 'l', $ts )` | Weekday names must reflect site timezone, not server TZ |
| `date_i18n( $fmt, $ts )` | `wp_date( $fmt, $ts )` | `date_i18n()` is the legacy function; `wp_date()` is the modern replacement |
| `new \DateTime()` | `new \DateTime( 'now', wp_timezone() )` | Bare constructor uses server default TZ |
| `new \DateTime( $date_string )` | `new \DateTime( $date_string, wp_timezone() )` | Same — always pass `wp_timezone()` as 2nd arg |
| `new \DateTimeImmutable( $expr )` | `new \DateTimeImmutable( $expr, wp_timezone() )` | Same rule for immutable variant |
| `strtotime( 'tomorrow 02:00:00' )` (for cron) | `( new \DateTimeImmutable( 'tomorrow 02:00:00', wp_timezone() ) )->getTimestamp()` | `strtotime()` relative expressions use server TZ; crons must fire at the site-configured time |

---

## 2. Acceptable Patterns — Do NOT Change

| Pattern | Why It's OK |
|---------|-------------|
| `gmdate( 'c', $ts )` | Intentionally UTC — used for `<time datetime="">` ISO attributes |
| `gmdate( 'Y-m-d' )` | UTC date for admin `<input type="date">` defaults — keep as-is |
| `gmdate()` in tests for UTC fixtures | Intentionally UTC |
| `current_time( 'mysql' )` | Returns site-local MySQL datetime — acceptable for DB `created_at` columns |
| `current_time( 'Y-m-d' )` | Returns site-local formatted date — acceptable |
| `$datetime_obj->format( 'Y-m-d' )` | Object already carries timezone from construction — safe if constructed with `wp_timezone()` |
| `strtotime()` inside `wp_date( $fmt, strtotime( $str ) )` | `wp_date()` handles TZ conversion from the UTC timestamp — acceptable pattern |
| `time()` for Action Scheduler delay offsets | Returns UTC Unix timestamp — correct for `time() + HOUR_IN_SECONDS` style scheduling |

---

## 3. The `wp_timezone()` Rule

> **Every `new \DateTime` or `new \DateTimeImmutable` constructor in plugin code MUST receive `wp_timezone()` as the second parameter**, unless the intent is explicitly UTC (in which case use `new \DateTimeZone( 'UTC' )` and document why with a comment).

---

## 4. Quick Reference — Correct vs Incorrect

```php
// ✅ Get current site-local date string.
$today = wp_date( 'Y-m-d' );

// ✅ Get weekday name from a date string.
$day_name = wp_date( 'l', strtotime( $delivery_date ) );

// ✅ Display a formatted date to the user.
echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $some_date ) ) );

// ✅ Compare dates in site timezone.
$now = new \DateTimeImmutable( 'now', wp_timezone() );

// ✅ Parse a Y-m-d string in site timezone.
$date_obj = new \DateTime( $date_string, wp_timezone() );

// ✅ Schedule cron at 2 AM site time.
$timestamp = ( new \DateTimeImmutable( 'tomorrow 02:00:00', wp_timezone() ) )->getTimestamp();
as_schedule_recurring_action( $timestamp, DAY_IN_SECONDS, 'my_hook' );

// ✅ UTC for HTML datetime attribute — gmdate is correct here.
echo gmdate( 'c', strtotime( $created_at ) );

// ❌ NEVER do this:
$today = date( 'Y-m-d' );                    // Server TZ — WRONG.
$day   = date( 'l', strtotime( $date ) );    // Server TZ — WRONG.
$obj   = new \DateTime();                     // Server TZ — WRONG.
$ts    = strtotime( 'tomorrow 02:00:00' );    // Server TZ for cron — WRONG.
echo date_i18n( $fmt, $ts );                  // Legacy function — WRONG.
```

---

## 5. Concrete Fixes Applied in This Plugin

These are the exact corrections made to eliminate timezone bugs:

| File | Line(s) | Before | After |
|------|---------|--------|-------|
| `templates/customer/subscription-detail.php` | 38 | `date_i18n( get_option('date_format'), strtotime(...) )` | `wp_date( get_option('date_format'), strtotime(...) )` |
| `templates/customer/subscription-detail.php` | 41 | `date( 'l', strtotime(...) )` | `wp_date( 'l', strtotime(...) )` |
| `templates/customer/subscription-detail.php` | 261 | `date_i18n( get_option('date_format'), strtotime(...) )` | `wp_date( get_option('date_format'), strtotime(...) )` |
| `tests/test-wksdc-delivery-engine.php` | 106 | `date( 'l', strtotime('2026-03-05') )` | `wp_date( 'l', strtotime('2026-03-05') )` |
| `src/admin/class-wksdc-blackout-menu.php` | 73 | `new \DateTime()` | `new \DateTime( 'now', wp_timezone() )` |
| `src/api/class-wksdc-rest-router.php` | 722 | `new \DateTime( $first_delivery_date )` | `new \DateTime( $first_delivery_date, wp_timezone() )` |
| `src/service/class-wksdc-blackout-service.php` | 161 | `new \DateTime( $start_date )` | `new \DateTime( $start_date, wp_timezone() )` |
| `src/core/class-wksdc-installer.php` | 312 | `strtotime( 'tomorrow 02:00:00' )` | `( new \DateTimeImmutable( 'tomorrow 02:00:00', wp_timezone() ) )->getTimestamp()` |
| `src/core/class-wksdc-installer.php` | 322 | `strtotime( 'tomorrow 02:00:00' )` | `( new \DateTimeImmutable( 'tomorrow 02:00:00', wp_timezone() ) )->getTimestamp()` |

---

## 6. Validation Checklist

Before marking any date-related change complete:

1. **Zero `date()` calls** — `grep -rn '\bdate\s*(' src/ templates/ tests/` must return only `vendor/` hits or JS `new Date()`.
2. **Zero `date_i18n()` calls** — fully replaced by `wp_date()`.
3. **Every `new \DateTime` / `new \DateTimeImmutable`** passes `wp_timezone()` (or explicitly documents UTC intent).
4. **Cron timestamps** use `DateTimeImmutable + wp_timezone()`, never bare `strtotime()` with relative expressions.
5. **`gmdate()` untouched** — confirmed intentionally UTC.
6. **All tests pass** — `vendor/bin/phpunit` returns zero failures.
