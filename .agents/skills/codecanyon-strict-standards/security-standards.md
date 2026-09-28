---
name: codecanyon-security-standards
description: Strict WordPress security rules for input handling, ensuring all superglobals are unslashed and sanitized before use.
---

# CodeCanyon & WordPress Security Standards

Every WordPress plugin submitted to CodeCanyon or the official repository must follow strict data validation and sanitization protocols.

## 1. The `wp_unslash()` Protocol (CRITICAL)

WordPress may add slashes to `$_POST`, `$_GET`, and `$_REQUEST` data (legacy `magic_quotes` behavior). Accessing these globals directly without unslashing leads to data corruption (double-slashes) and is flagged by security scanners.

### Rule: Always Always Unslash
Before ANY sanitization or type casting, you MUST call `wp_unslash()`.

- **WRONG**: `sanitize_text_field( $_POST['key'] )`
- **WRONG**: `(int) $_POST['id']`
- **CORRECT**: `sanitize_text_field( wp_unslash( $_POST['key'] ) )`
- **CORRECT**: `(int) wp_unslash( $_POST['id'] )`
- **CORRECT**: `absint( wp_unslash( $_POST['id'] ) )`

## 2. Combined Sanitization & Unslashing

For different data types, use the following patterns:

| Data Type | Correct Pattern |
| :--- | :--- |
| **String** | `sanitize_text_field( wp_unslash( $_POST['...'] ) )` |
| **Textarea** | `sanitize_textarea_field( wp_unslash( $_POST['...'] ) )` |
| **Email** | `sanitize_email( wp_unslash( $_POST['...'] ) )` |
| **Integer** | `absint( wp_unslash( $_POST['...'] ) )` or `(int) wp_unslash( $_POST['...'] )` |
| **Array** | `array_map( 'sanitize_text_field', wp_unslash( $_POST['...'] ) )` |

## 3. Nonce Verification

Sanitization alone is not security. Every state-changing action (AJAX, POST) MUST be preceded by a nonce check.

```php
if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'action_name' ) ) {
    wp_send_json_error( 'Invalid nonce' );
}
```

## 4. Avoiding Direct Trust

Never use a superglobal as a key directly inside another superglobal without checking existence and unslashing.

- **Bad**: `$_POST[ $_GET['key'] ]`
- **Good**: 
  ```php
  $key = sanitize_key( wp_unslash( $_GET['key'] ) );
  $val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
  ```
