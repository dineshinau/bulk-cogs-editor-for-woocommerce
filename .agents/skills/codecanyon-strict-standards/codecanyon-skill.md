---
name: CodeCanyon WordPress Plugin Compliance Skill
description: A strict AI instruction layer and rule engine to ensure all generated WordPress plugins are 100% compliant with Envato / CodeCanyon marketplace requirements.
---

# CodeCanyon & WooCommerce Plugin Quality Standards

This document defines the strict quality, security, and coding standards required for WordPress and WooCommerce plugins to be accepted on CodeCanyon and the official WooCommerce store. **Any AI or LLM generating code for this project MUST strictly adhere to these rules.** Failure to do so will result in plugin rejection.

## 1. Security (CRITICAL)
*   **Sanitization:** All user input (`$_POST`, `$_GET`, `$_REQUEST`, etc.) MUST be sanitized as soon as it is received before any processing. Use specific WordPress sanitization functions: `sanitize_text_field()`, `sanitize_email()`, `absint()`, `sanitize_textarea_field()`, etc.
*   **Escaping (Late Escaping):** All dynamic output MUST be escaped immediately before being echoed to the browser or returned. Use: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`, `esc_js()`, `wp_kses_post()`, etc. Never assume data in the database is safe to output without escaping.
*   **Validation:** Validate data to ensure it matches the expected format (whitelist approach).
*   **Nonces:** Every form submission, AJAX request, or sensitive action (e.g., deleting a record) MUST use WordPress nonces for CSRF protection. Generate with `wp_create_nonce()` and verify with `wp_verify_nonce()` or `check_ajax_referer()`.
*   **SQL Injection Prevention:** NEVER access the database directly or use direct SQL strings with variables. All custom queries MUST use `$wpdb->prepare()`. Alternatively, use WordPress APIs like `WP_Query`, `get_posts()`, `update_option()`, etc.
*   **Capability Checks:** Protect all admin pages, custom REST API endpoints, and sensitive AJAX actions by checking user capabilities using `current_user_can()` with the appropriate capability (e.g., `manage_options`).
*   **No Unsafe Remote Calls:** Do not use `curl` or `file_get_contents`; use `wp_remote_get`/`wp_remote_post`.
*   **No Obfuscated Code:** Do not put executable code in `eval()`, `base64_decode()`, or `gzinflate()`.

## 2. Coding Standards & Best Practices
*   **Unique Prefixing:** Every custom function, class, hook, variable (global/public), database table, and option name MUST be prefixed with a unique identifier (e.g., `your_plugin_prefix_`). This prevents fatal errors from naming conflicts.
*   **No Deprecated Functions:** Do not use any deprecated WordPress or WooCommerce functions. Check the developer documentation for the current standard functions.
*   **File Organization & Logic Separation:** Admin-specific code (settings pages, admin scripts) MUST be logically separated from public-facing code. Use `is_admin()` conditionals or separate files included only when needed to optimize performance on the frontend.
*   **Strict Typing & Error Handling:** Use `===` for comparisons. Write code that does not generate any PHP notices, warnings, or errors when `WP_DEBUG` is set to `true`. Plugin activation must generate zero issues.
*   **Database Management:** Minimize creating new custom tables. Utilize Custom Post Types (CPTs), standard post meta (`update_post_meta`), options (`update_option`), and taxonomies. If custom tables are absolutely necessary, ensure they are created correctly on activation and removed upon uninstallation (if the user opts-in).
*   **Fully Namespaced Classes / OOP Structure:** Use fully namespaced classes and Object-Oriented design. No procedural spaghetti code outside the main bootstrap.
*   **No Global Pollution:** Limit use of global variables. Use class properties or singletons to manage state.

## 3. Asset Loading (Scripts & Styles)
*   **Standard Enqueuing:** All CSS and JavaScript MUST be loaded using `wp_enqueue_script()` and `wp_enqueue_style()`. NEVER hardcode `<script>` or `<link>` tags into the header or footer.
*   **No Duplicate Libraries:** Do not bundle or load duplicate versions of libraries that are already included in WordPress (e.g., jQuery, React, Backbone). Use the core-provided versions (`wp_enqueue_script('jquery')`).
*   **Never Deregister Core Scripts:** Never deregister or dequeue default WordPress scripts like jQuery to load your own version.

## 4. Internationalization (i18n) & Translation
*   **Translatable Strings:** Every single user-facing text string MUST be wrapped in WordPress translation functions (e.g., `__( 'String', 'text-domain' )`, `_e( 'String', 'text-domain' )`, `_n()`, `_x()`).
*   **Escaped Translation:** Use combined escape and translate functions for output: `esc_html__()`, `esc_attr__()`, `esc_html_e()`, `esc_attr_e()`.
*   **Text Domain:** Use a consistent text domain that exactly matches the plugin slug (lowercase, hyphen-separated).
*   **No Variables in Translation Strings:** Do not pass PHP variables directly into translation functions (e.g., `__($variable, 'text-domain')` is invalid). Use `printf` or `sprintf` instead: `sprintf( esc_html__( 'Hello %s', 'text-domain' ), $name )`.

## 5. Performance & Data Handling
*   **Caching:** Complex queries or external API calls must be cached using WordPress Transients API (`set_transient()`, `get_transient()`) to prevent slow page loads and API throttling.
*   **Query Optimization:** Avoid `SELECT *` in database queries; fetch only the required columns.
*   **Clean Uninstallation:** When a plugin is uninstalled (via `uninstall.php` or the uninstall hook), it should clean up its data (options, custom tables), but ONLY if the user has explicitly chosen a "delete all data on uninstall" setting. Do not unexpectedly wipe user data upon deactivation or standard uninstallation without warning.

## 6. General Requirements & CodeCanyon Specifics
*   **WooCommerce Compatibility (If applicable):** If building for WooCommerce, explicitly declare compatibility in the main plugin file and ensure it works with the High-Performance Order Storage (HPOS) feature.
*   **No Disruptive Advertising:** Do not put aggressive upsells, non-dismissible admin notices, or advertisements in the WordPress dashboard. All notices must be dismissible and stay dismissed. No admin UI spam.
*   **No Unauthorized Tracking:** Do not transmit user data or telemetry to third-party servers without an explicit, clear opt-in from the user.
*   **Gutenberg Compatibility:** If building blocks, follow standard React/Gutenberg practices. Blocks must not cause validation errors if the page is saved and reloaded.
*   **Branding & Licensing restrictions:** 
    *   No trademarked terms like "WordPress" in the plugin name (use "Plugin Name for WordPress" instead).
    *   No trademark violations (e.g., Facebook, WooCommerce logos) without permission.
    *   Must be 100% GPL compatible or use split-licensing compliant with Envato rules.
*   **Demo & Asset Rules:** Images and assets in the demo must be fully licensed for redistribution. No external generic placeholders that load over HTTP.
*   **Documentation:** A comprehensive offline documentation folder (HTML or PDF) must be included, with clear installation/usage instructions and a changelog.

---

## A. Structured Checklist
- [ ] Plugin header is correctly formatted (Name, Description, Version, Author, Text Domain).
- [ ] All functions, classes, options, hooks, and constants are uniquely prefixed.
- [ ] No hardcoded paths (`plugin_dir_path()` and `plugin_dir_url()` used).
- [ ] Translation functions (`__()`, `_e()`) wrap all text strings with appropriate text domains.
- [ ] JS/CSS enqueued properly (`'use strict';` for JS). No core libraries bundled.

## B. Pre-Submission Validation Checklist
- [ ] WP_DEBUG mode tests run with zero warnings/notices.
- [ ] Demo tested and functioning on the latest WordPress version.
- [ ] Offline documentation generated and included.
- [ ] Activation/Deactivation hooks work without generating output or errors.
- [ ] Envato requirements verified manually against the official requirements page.

## C. Code Generation Ruleset
1.  **Prefixing:** Assign a standard prefix. All standard structures must follow this prefix.
2.  **OOP Only:** Procedural code is limited to the main plugin bootstrap file.
3.  **Hooks First:** All output generated via WordPress hooks. Zero direct output in core classes.
4.  **No Echoing in Logic:** Controller logic never echoes output. Views/templates handle rendering.

## D. Security Enforcement Ruleset
1.  **Input:** `$_POST`, `$_GET`, `$_REQUEST` accesses must immediately be wrapped in sanitization functions before evaluation.
2.  **Output:** All `echo` statements MUST be wrapped in `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, etc.
3.  **Nonces:** Every form needs `wp_nonce_field`. Every handler needs `wp_verify_nonce`.
4.  **Capabilities:** Before processing any admin action, call `current_user_can('manage_options')` (or equivalent).
5.  **No Hardcoded Links:** Use `admin_url()`, `home_url()`, etc.


## F. Plugin Structure Requirements
*   A secure starter plugin boilerplate must utilize the exact separation of concerns shown in the File Structure Template.
*   Include standard activation/deactivation classes for initial table setups or cleanup.
*   Implement a loader class to register all hooks and define the text domain centrally.

## G. Rejection-Prevention Checklist
*   [ ] Checked for missing late escaping on dynamic data output.
*   [ ] Checked for custom queries lacking `$wpdb->prepare()`.
*   [ ] Verified no `curl()` calls; replaced with `wp_remote_*()`.
*   [ ] Checked for hidden external server pings without explicit consent.
*   [ ] Verified there is no Envato trademark infringement in names/folders.

## H. Strict “DO NOT” Violation List
*   **DO NOT** use `$_POST['action']` without sanitizing and checking the nonce.
*   **DO NOT** enqueue scripts outside `wp_enqueue_scripts` or `admin_enqueue_scripts`.
*   **DO NOT** write output during the `plugins_loaded` hook.
*   **DO NOT** put executable code in `eval()`, `base64_decode()`, or `gzinflate()`.
*   **DO NOT** disable WordPress core functionality or remove core scripts.
*   **DO NOT** use default names or variables like `$plugin`, `$data`, or `$options` in global scope.
*   **DO NOT** write files arbitrarily; use WP_Filesystem.
*   **DO NOT** create messy admin menus (no UI spam).

## I. Final Pre-Upload Audit System
1. **PHPCS Audit:** Run `phpcs --standard=WordPress .` across the codebase.
2. **Review DB Calls:** Grep for `$wpdb->query` and ensure `prepare` is present.
3. **Escaping Check:** Search for `echo $` and eliminate them with late escaping functions.
4. **WP_DEBUG Audit:** Install plugin on a fresh WP installation with `WP_DEBUG=true`. View all pages to capture errors.
5. **Asset Check:** Verify no 404s for scripts/images.

---

**CRITICAL INSTRUCTION FOR AI AGENTS/LLMs:** 
When asked to write, refactor, or review code for this plugin, you **MUST** verify your output against every single rule in this document. **Prioritize Security (Sanitization, Escaping, Nonces, DB Prepare) and Prefixing above all else.** The code will be rejected by CodeCanyon if any of these standards are violated. Every time you write a line of code outputting data or taking data in from a user, stop and ask yourself: "Is this sanitized? Is this escaped? Is this request verified by a nonce?"

> **AI SYSTEM GOAL:** You are a senior CodeCanyon WordPress plugin reviewer and developer. Your generated code must never trigger a soft or hard rejection from Envato.
> 
> **MANDATORY AI RULES:**
> 1. Wrap ALL external or user data in sanitization functions (`sanitize_text_field`, etc.).
> 2. Wrap ALL output in escaping functions (`esc_html`, `esc_attr`, `esc_html__`).
> 3. Add `wp_verify_nonce()` to all custom endpoints, forms, and AJAX.
> 4. Check capabilities (`current_user_can`) before completing sensitive executions.
> 5. Never use `curl` or `file_get_contents`; always use `wp_remote_get`/`wp_remote_post`.
> 6. Prefix all classes, functions, handlers, and variables consistently.
> 7. Organize logic using Object-Oriented Programming, avoiding the global namespace.
> 8. Ensure total translation readiness with the correct domain string.
> 9. Follow WordPress Coding Standards for spacing, indentation, and structure.
> 10. Default to the strictest interpretation of security if ambiguity exists.
