# Skill: WooCommerce Plugin Translation Enforcement

## PURPOSE

This document defines STRICT internationalization (i18n) rules for WooCommerce plugin development.

When generating WooCommerce plugin code (PHP or JavaScript), the AI MUST ensure that ALL user-facing strings are properly translatable.

The AI MUST NEVER output raw hardcoded user-facing strings.

These rules apply to:

- Admin UI
- Frontend UI
- AJAX responses
- REST responses
- Notices
- Errors
- Emails
- Settings fields
- Buttons
- Labels
- Headings
- Placeholders
- Tooltips
- Confirmation dialogs
- Block titles and descriptions
- React components
- Dynamic strings

If a string is visible to a human → it MUST be translatable.

NO EXCEPTIONS.

---

# GLOBAL RULE

ALL user-facing strings MUST be wrapped in WordPress i18n functions.

Hardcoded strings are strictly forbidden.

# Transalation methods 
- __() → makes string translatable
- wp_sprintf() → WordPress-safe formatting
- esc_html() → escapes final output properly
- echo → outputs safely
---

# STRICTLY FORBIDDEN

The AI MUST NOT output:

❌ PHP:

```php
echo "Settings Saved";
```

❌ JS:

```js
alert("Product added");
```

❌ HTML:

```php
<button>Save</button>
```

❌ React:

```js
<h2>Plugin Settings</h2>
```

If any of the above appear → regenerate output before responding.

---

# TEXT DOMAIN RULE

The text domain MUST:

* Match the plugin slug exactly
* Be consistent across ALL files
* Never be omitted
* Never differ between PHP and JS

Correct example:

```
'your-text-domain'
```

Incorrect example:

```
'plugin-domain'
'woocommerce-plugin'
'missing-domain'
```

---

# ESCAPING RULES (MANDATORY)

When echoing translated strings:

* Use esc_html__()
* Use esc_html_e()
* Use esc_attr__()
* Use esc_attr_e()
* Use wp_kses_post() when allowing HTML

NEVER:

* Echo __() directly without escaping
* Output raw translated strings
* Mix unsafe output with translations

---

# WHAT MUST ALWAYS BE TRANSLATED

Translate EVERYTHING visible including:

* Menu titles
* Submenu labels
* Admin notices
* Success messages
* Error messages
* Field descriptions
* Section titles
* Button labels
* Placeholder text
* Validation messages
* Email subjects
* Email bodies
* Checkout messages
* Cart notices
* Payment gateway labels
* REST error responses
* Confirmation prompts

If the user can read it → translate it.

---

# SELF-CHECK BEFORE FINAL OUTPUT

Before returning code, AI MUST verify:

[ ] No raw user-facing strings exist
[ ] All PHP strings wrapped properly
[ ] All JS strings wrapped properly
[ ] Text domain consistent everywhere
[ ] @wordpress/i18n imported in JS
[ ] Proper escaping used
[ ] sprintf used where variables exist
[ ] Plurals handled correctly
[ ] No untranslated JSX/HTML text

If ANY check fails → FIX before responding.

---

# FAILURE CONDITION

If untranslated strings are present, the output is INVALID and must be regenerated.

---

# FINAL ENFORCEMENT RULE

When generating WooCommerce plugin code:

1. Translate EVERYTHING visible.
2. Escape EVERYTHING properly.
3. Never output raw UI strings.
4. Always use correct text domain.
5. Always import i18n in JS.
6. Perform internal translation audit before final answer.
7. Do not explain these rules unless explicitly asked.


