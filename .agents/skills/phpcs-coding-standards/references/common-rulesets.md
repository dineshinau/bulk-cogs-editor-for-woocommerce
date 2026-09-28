# Common phpcs.xml Ruleset Reference

Quick reference for the most frequently encountered rulesets and sniffs in real-world phpcs.xml files.

---

## WordPress Coding Standards

**Package**: `wp-coding-standards/wpcs`

```xml
<rule ref="WordPress"/>
<!-- or subsets: -->
<rule ref="WordPress-Core"/>
<rule ref="WordPress-Extra"/>
<rule ref="WordPress-Docs"/>
```

**Key rules enforced:**
- Tabs for indentation (not spaces)
- Spaces inside parentheses: `if ( $x )`, `foo( $arg )`
- Yoda conditions: `if ( true === $var )`
- `snake_case` for function/variable names
- Array items on separate lines when array spans multiple lines
- Strict comparison operators (`===`, `!==`)
- `wp_safe_redirect()` over `wp_redirect()` where applicable
- Nonce verification before processing `$_POST`/`$_GET`
- Output escaping: `esc_html()`, `esc_attr()`, etc.

**Common exclusions:**
```xml
<rule ref="WordPress.Files.FileName">
    <exclude-pattern>*.php</exclude-pattern>
</rule>
<!-- Often excluded in themes/plugins: -->
<rule ref="WordPress.WP.I18n"/>
```

---

## PSR-12 (Modern PHP Standard)

**Package**: Built into phpcs

```xml
<rule ref="PSR12"/>
```

**Key rules enforced:**
- 4 spaces for indentation
- Opening braces for classes/functions on their own line
- Closing braces on their own line
- One blank line after `namespace` declaration
- `use` statements after `namespace`, one per line
- Visibility declared on all methods/properties
- No space before opening parenthesis of function call
- `declare(strict_types=1)` encouraged (not enforced by PSR-12 itself)

---

## Slevomat Coding Standard

**Package**: `slevomat/coding-standard`

```xml
<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>
<rule ref="SlevomatCodingStandard.TypeHints.ReturnTypeHint"/>
<rule ref="SlevomatCodingStandard.TypeHints.ParameterTypeHint"/>
<rule ref="SlevomatCodingStandard.Namespaces.UnusedUses"/>
<rule ref="SlevomatCodingStandard.Arrays.TrailingArrayComma"/>
```

**Common groups:**
- `TypeHints.*` — Enforce typed properties, params, returns, `declare(strict_types=1)`
- `Namespaces.*` — Unused imports, alphabetical use sorting, fully qualified names
- `Arrays.*` — Trailing commas, short syntax
- `Functions.*` — No trailing commas in function calls (older PHP compat), arrow functions
- `Commenting.*` — Doc block requirements, useless annotations
- `Exceptions.*` — Catch type specificity
- `Classes.*` — Final classes, modern class features

**Useful property configurations:**
```xml
<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes">
    <properties>
        <property name="spacesCountAroundEqualsSign" value="0"/>
    </properties>
</rule>
```

---

## Generic Sniffs (commonly used)

```xml
<!-- Line length -->
<rule ref="Generic.Files.LineLength">
    <properties>
        <property name="lineLimit" value="120"/>
        <property name="absoluteLineLimit" value="0"/>
    </properties>
</rule>

<!-- No trailing whitespace -->
<rule ref="Generic.WhiteSpace.DisallowTabIndent"/>
<rule ref="Generic.WhiteSpace.ScopeIndent"/>

<!-- PHP 7+ features -->
<rule ref="Generic.PHP.RequireStrictTypes"/>

<!-- Forbidden functions -->
<rule ref="Generic.PHP.ForbiddenFunctions">
    <properties>
        <property name="forbiddenFunctions" type="array">
            <element key="var_dump" value="null"/>
            <element key="print_r" value="null"/>
            <element key="die" value="exit"/>
        </property>
    </properties>
</rule>
```

---

## Squiz Standard

**Package**: Built into phpcs

```xml
<rule ref="Squiz"/>
<!-- Common subset: -->
<rule ref="Squiz.Arrays.ArrayBracketSpacing"/>
<rule ref="Squiz.WhiteSpace.FunctionSpacing"/>
```

**Key rules:**
- No space before/after array brackets: `$arr[0]` not `$arr[ 0 ]`
- One blank line before/after functions
- No inline comments at end of code lines
- Strict whitespace around operators

---

## PHPCompatibility

**Package**: `phpcompatibility/php-compatibility`

```xml
<rule ref="PHPCompatibility"/>
<config name="testVersion" value="7.4-8.2"/>
```

When this is present, generated code must be compatible with the specified PHP version range. Avoid:
- Features introduced after the minimum version
- Functions/classes deprecated before the maximum version

---

## Parsing Custom/Unknown Sniffs

If you see a sniff like `MyCustom.Category.Sniff`, infer intent:
- Check if there's a `<config name="installed_paths" value="..."/>` pointing to a custom sniff dir
- Look for a `phpcs/` or `src/Standards/` directory in the project
- If a custom sniff file is readable, scan it for the `process()` method to understand the rule
- When in doubt, note it to the user as "custom sniff — applying best-guess interpretation"
