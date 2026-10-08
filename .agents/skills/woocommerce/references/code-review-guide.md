
# WooCommerce Code Review

Review code changes against WooCommerce coding standards and conventions.

## Critical Violations to Flag

### Backend PHP Code

Consult the `woocommerce-backend-dev` skill for detailed standards. Using these standards as guidance, flag these violations and other similar ones:

**Architecture & Structure:**

- ❌ **Standalone functions** - Must use class methods ([file-entities.md](file-entities.md))
- ❌ **Using `new` for DI-managed classes** - Classes in `src/` must use `$container->get()` ([dependency-injection.md](dependency-injection.md))
- ❌ **Classes outside `src/Internal/`** - Default location unless explicitly public ([file-entities.md](file-entities.md))

**Naming & Conventions:**

- ❌ **camelCase naming** - Must use snake_case for methods/variables/hooks ([code-entities.md](code-entities.md))
- ❌ **Yoda condition violations** - Must follow WordPress Coding Standards ([coding-conventions.md](coding-conventions.md))

**Documentation:**

- ❌ **Missing `@since` annotations** - Required for public/protected methods and hooks ([code-entities.md](code-entities.md))
- ❌ **Missing docblocks** - Required for all hooks and methods ([code-entities.md](code-entities.md))
- ❌ **Verbose docblocks** - Keep concise, one line is ideal ([code-entities.md](code-entities.md))

**Data Integrity:**

- ❌ **Missing validation** - Must verify state before deletion/modification ([data-integrity.md](data-integrity.md))

**Testing:**

- ❌ **Using `$instance` in tests** - Must use `$sut` variable name ([unit-tests.md](unit-tests.md))
- ❌ **Missing `@testdox`** - Required in test method docblocks ([unit-tests.md](unit-tests.md))
- ❌ **Test file naming** - Must follow convention for `includes/` vs `src/` ([unit-tests.md](unit-tests.md))

### UI Text & Copy

Consult the `woocommerce-copy-guidelines` skill. Flag:

- ❌ **Title Case in UI** - Must use sentence case ([sentence-case.md](sentence-case.md))
    - Wrong: "Save Changes", "Order Details", "Payment Options"
    - Correct: "Save changes", "Order details", "Payment options"
    - Exceptions: Proper nouns (WooPayments), acronyms (API), brand names

## Review Approach

1. **Scan for critical violations** listed above
2. **Cite specific skill files** when flagging issues
3. **Provide correct examples** from the skill documentation
4. **Group related issues** for clarity
5. **Be constructive** - explain why the standard exists when relevant

## Output Format

> 🔴 **MANDATORY**: Follow the AUDIT OUTPUT PROTOCOL defined in `GEMINI.md`.

Use the structured table format for all violations:

```markdown
| Severity | File | Line | Standard | Issue | Fix |
|----------|------|------|----------|-------|-----|
| 🔴 CRITICAL | `path/file.php` | L45 | [security-patterns.md] | Missing nonce | Add `wp_verify_nonce()` |
| 🟠 HIGH | `path/file.php` | L102 | [code-entities.md] | camelCase method | Rename to snake_case |
| 🟡 MEDIUM | `path/file.php` | L78 | [sentence-case.md] | Title Case in UI | Use sentence case |
| 🔵 LOW | `path/file.php` | L200 | [code-entities.md] | Missing @since | Add annotation |
```

### Severity Mapping for WooCommerce

| Violation Category | Default Severity |
|--------------------|------------------|
| Security (nonces, capabilities, SQL injection) | 🔴 CRITICAL |
| Data Integrity (CRUD without validation) | 🔴 CRITICAL |
| Architecture (DI violations, standalone functions) | 🟠 HIGH |
| Naming conventions (camelCase, file naming) | 🟠 HIGH |
| Testing violations (missing @testdox, wrong variable) | 🟡 MEDIUM |
| Documentation (missing @since, verbose docblocks) | 🟡 MEDIUM |
| UI Copy (Title Case instead of sentence case) | 🟡 MEDIUM |
| Minor style issues | 🔵 LOW |

### Report Summary (Required)

Always end with a summary:

```markdown
## Audit Summary

| Severity | Count |
|----------|-------|
| 🔴 CRITICAL | X |
| 🟠 HIGH | X |
| 🟡 MEDIUM | X |
| 🔵 LOW | X |

**Verdict**: ✅ PASS / ⚠️ CONDITIONAL / ❌ BLOCKED
**Standards**: woocommerce/references/[files consulted]
```

## Notes

- All detailed standards are in the `woocommerce-backend-dev`, `woocommerce-dev-cycle`, and `woocommerce-copy-guidelines` skills
- Consult those skills for complete context and examples
- When in doubt, refer to the specific skill documentation linked above
