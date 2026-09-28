---
name: codecanyon-standard-rules
description: Strict WordPress and WooCommerce coding standards for PHP, enforcing patterns required for zero-violation PHPCS compliance.
---

# CodeCanyon & WordPress Linting Standards

Use this skill to ensure all PHP code complies with the strict ruleset defined in the project's `phpcs.xml`. These rules are mandatory for avoiding common linting errors in WordPress plugin development.

## 1. Docblocks & Property Documentation

### Property `@var` Tags
Never use single-line `@var` tags. Always use a multi-line format with a short description.
- **WRONG**: `/** @var Wksdc_Manager */`
- **CORRECT**:
  ```php
  /**
   * Manager dependency.
   *
   * @var Wksdc_Manager
   */
  ```

### Function `@param`, `@return`, and `@throws` Tags
All parameters and return types must have a description. If a parameter's type is union or complex, ensure the docblock matches the signature exactly.
- **Throws**: If the code uses a `try-catch` block and re-throws or generates exceptions, `@throws` is mandatory.
- **CORRECT**:
  ```php
  /**
   * Short description.
   *
   * @param int    $id   Object ID.
   * @param string $meta Meta value.
   * @return bool True on success.
   * @throws \Exception If operation fails.
   */
  ```

## 2. Comments & Punctuation

### Inline Comments
All inline comments (`//`) must start with a space and end with terminal punctuation (`.`, `!`, `?`).
- **WRONG**: `// Fix the bug`
- **CORRECT**: `// Fix the bug.`

### WooCommerce Hook Documentation
When using `do_action` or `apply_filters`, use the standard WordPress documentation block above the hook if it's a custom hook. Use `/**` style comments for descriptions.
- **CORRECT**:
  ```php
  /**
   * Action triggered after order generation fails.
   *
   * @since 1.0.0
   */
  do_action( 'wksdc_order_generation_failed', $sub_id, $date, $reason );
  ```

### Empty Catch Blocks
Do not leave `catch` blocks empty. If an exception is intentionally ignored, add an explanatory comment and a `phpcs:ignore` tag on the `catch` line.
- **CORRECT**:
  ```php
  } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
      // Intentional empty catch for calculation fallback.
  }
  ```

### Commented-out Code
Avoid leaving commented-out logic. If comments trigger "commented-out code" warnings but are meant to be descriptive, rephrase them to avoid code-like syntax (e.g., avoid ending with semicolons inside comments).
- **Suppression**: Use `// phpcs:ignore Squiz.PHP.CommentedOutCode.Found` ONLY if absolutely necessary.

## 3. Control Structures

### Yoda Conditions
Always use Yoda conditions for comparisons with literals or constants (`'literal' === $variable`).
- **WRONG**: `if ( $type === 'subscription' )`
- **CORRECT**: `if ( 'subscription' === $type )`

### Lonely IF Statements
Avoid `else { if (...) }` patterns. Flatten them into `elseif`.
- **CORRECT**:
  ```php
  if ( $cond1 ) {
      // ...
  } elseif ( $cond2 ) {
      // ...
  }
  ```

### Multi-line IF Conditions
Each line in a multi-line `if` statement must begin with a boolean operator. Alternatively, collapse simple conditions into a single line.

## 4. Operators & Expressions

### Short Ternaries
WordPress standards discourage short ternaries (`?:`). Use the explicit format (`$a ? $a : $b`) or add suppression for complex expressions.
- **Suppression**: `// phpcs:ignore Universal.Operators.DisallowShortTernary.Found`

## 5. Parameter Handling

### Unused Parameters
For hook callbacks (actions/filters) where a parameter is required by the signature but unused:
1. Prefix the variable with an underscore (e.g., `$_order`).
2. Add `// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed` to the function signature line.

## 6. Spacing & Alignment

Ensure equals signs (`=`) in sequential assignment blocks are vertically aligned to improve readability.
- **CORRECT**:
  ```php
  $id     = 10;
  $status = 'active';
  $note   = 'none';
  ```

## 7. Type Hints

Always use the most accurate type hint in both signature and docblocks.
- Use `\WP_Post` for general WordPress post objects.
- Use `\WC_Order`, `\WC_Product`, etc., for specific WooCommerce objects.
- Ensure type hints stay updated (e.g., use `\WP_Post|\WC_Product` if mixed).
