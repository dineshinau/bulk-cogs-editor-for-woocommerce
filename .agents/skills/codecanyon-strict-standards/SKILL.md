---
name: codecanyon-strict-standards
description: Enforces strict WordPress and CodeCanyon coding rules covering class prefixing, strict file naming conventions, namespacing autoloaders, custom user roles, and JS scoping.
---

# CodeCanyon Strict Anti-Collision & File Standards

> **Context:** This skill documents critical mistakes commonly made when assembling standard Object-Oriented PHP or WordPress plugins for CodeCanyon/Envato distribution, specifically avoiding soft-rejection triggers related to file naming and global namespace pollution.

## 1. The Global Namespace Class Rule
**MISTAKE:** Defining universal, generic class names even when namespaced (e.g., `class DeliveryEngine`, `class AdminMenu`).
**THE FIX:** CodeCanyon's aggressive automated scanners will flag generic class names. **Every single class MUST be prefixed** with a string unique to the plugin, separated by an underscore (`PluginPrefix_`).
*   **Bad:** `class AdminMenu`
*   **Good:** `class Wksdc_Admin_Menu`
*   *Note:* Methods and internal variables do not require global prefixing as long as they live entirely inside a properly prefixed Class.

## 2. WPCS File and Directory Naming
**MISTAKE:** Storing files using PascalCase, matching their class names exactly (e.g., `src/Admin/AdminMenu.php`), which triggers the `WordPress.Files.FileName.InvalidClassFileName` PHPCS rule.
**THE FIX:** CodeCanyon mandates strict WPCS file naming, even for PSR-4 autoloaded directories.
*   **Classes:** The filename must be fully lowercase, replace underscores (`_`) with hyphens (`-`), and be prefixed with `class-`.
    *   *Example:* `class Wksdc_Admin_Menu` MUST reside in `class-wksdc-admin-menu.php`.
*   **Directories:** All path directories within the plugin (e.g., `src/admin`) must strictly be lowercase to circumvent exact-match string errors on various operating systems.

## 3. The Custom Autoloader Class
**MISTAKE:** Standard PSR-4 autoloaders blindly map `Namespaced\Admin_Menu` to `Admin_Menu.php`, or using procedural spl_autoload_register in the main file.
**THE FIX:** Package the autoloader into a dedicated class within `includes/` and use a static `register()` method. This keeps the main file clean and follows OOP standards.

```php
// File: includes/class-wksdc-autoload.php
class Wksdc_Autoload {
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload($class) {
        $prefix = 'Wksdc\\';
        if (0 !== strpos($class, $prefix)) return;

        $relative_class = substr($class, strlen($prefix));
        $path_parts     = explode('\\', $relative_class);
        $file_name      = array_pop($path_parts);

        $folder_path = strtolower(implode('/', $path_parts));
        $file_name   = 'class-' . strtolower(str_replace('_', '-', preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $file_name))) . '.php';

        $file = WKSDC_PLUGIN_DIR . 'src/' . $folder_path . '/' . $file_name;
        if (file_exists($file)) require $file;
    }
}
```

## 4. Main Plugin File (Class-Based Architecture)
**MISTAKE:** Writing procedural code (defines, hooks, includes, standalone functions) directly in the root plugin file.
**THE FIX:** Wrap the entire bootstrap logic in a `final class` with a singleton pattern. This prevents global namespace pollution and allows for better lifecycle management.

*   **Rules:**
    1.  The root file should only contain the Plugin Header and a single class instantiation — **zero** standalone functions, **zero** bare `define()` calls, **zero** bare `add_action()` calls outside the class.
    2.  Use a `define_constants()` private method for all `define()` calls, guarded with `if ( ! defined() )`.
    3.  Use an `includes()` private method to load the autoloader.
    4.  Use an `init_hooks()` private method to register **all** `add_action`, `register_activation_hook`, and `register_deactivation_hook` calls.
    5.  The WooCommerce dependency check and admin notice must be **class methods**, not standalone functions.
    6.  Use singleton pattern with private constructor and `::instance()` to prevent double-instantiation.
    7.  The class MUST be prefixed with the plugin prefix (e.g., `WKCPF_Bootstrap`).

*   **Critical Anti-Patterns to Avoid:**
    *   `function wkcpf_bootstrap() { ... } add_action('plugins_loaded', 'wkcpf_bootstrap');` — **WRONG**: standalone function.
    *   `define('WKCPF_VERSION', '1.0.0');` at file root — **WRONG**: bare define outside class.
    *   `register_activation_hook(__FILE__, ...);` at file root — **WRONG**: bare hook outside class.

```php
<?php
/**
 * Plugin Name: My Plugin
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Prefix_Bootstrap {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        if ( ! defined( 'PREFIX_VERSION' ) ) {
            define( 'PREFIX_VERSION', '1.0.0' );
        }
        if ( ! defined( 'PREFIX_FILE' ) ) {
            define( 'PREFIX_FILE', __FILE__ );
        }
        if ( ! defined( 'PREFIX_PATH' ) ) {
            define( 'PREFIX_PATH', plugin_dir_path( __FILE__ ) );
        }
        // ... all other constants ...
    }

    private function includes() {
        require_once PREFIX_PATH . 'includes/class-prefix-autoloader.php';
        Prefix_Autoloader::register();
    }

    private function init_hooks() {
        register_activation_hook( PREFIX_FILE, array( 'Prefix_Installer', 'activate' ) );
        register_deactivation_hook( PREFIX_FILE, array( 'Prefix_Installer', 'deactivate' ) );
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
    }

    public function on_plugins_loaded() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
            return;
        }
        Prefix_Plugin::instance()->init();
    }

    public function wc_missing_notice() {
        // Admin notice as a class method, NOT a standalone function.
    }
}
Prefix_Bootstrap::instance();
```

## 5. Custom User Roles and Database Entries
**MISTAKE:** Registering a raw generic user role string like `add_role('delivery_manager', ...)` or using raw table names.
**THE FIX:** Any custom user role or capability injected globally into the WP Database must physically contain the unique plugin prefix.
*   **Bad Role:** `delivery_manager`
*   **Good Role:** `wksdc_delivery_manager`
*   **Good Capabilities:** `wksdc_manage_settings`, `wksdc_update_delivery`
*   **Tables:** Always use `{$wpdb->prefix}wksdc_your_table_name`.

## 6. Frontend JavaScript Constraints (IIFE + No Inline Scripts)
**MISTAKE 1:** Declaring global variables or functions in Javascript (e.g., `let currentYear;` or `function renderCalendar() {}`) inside assets.
**MISTAKE 2:** Writing `<script>` tags directly in PHP templates instead of enqueueing external `.js` files.
**THE FIX:**
*   Every JS file must be fully enclosed within an IIFE using strict mode.
*   **Zero** `<script>` tags in PHP templates — all JavaScript must live in `.js` files under `assets/js/` and be enqueued via `wp_enqueue_script()`.
*   Dynamic data must be passed from PHP to JS via `wp_localize_script()` or `wp_add_inline_script()`, **never** via inline `<script>` blocks in templates.
*   jQuery must be passed as a parameter to the IIFE, never referenced as global `$`.

```javascript
(function ( $ ) {
    'use strict';
    // All variables and functions must reside inside this protective envelope.
    function renderCalendar() { ... }
})( jQuery );
```

## 7. Plugin Dependencies (Modern Header Usage)
**MISTAKE:** Writing manual `class_exists('WooCommerce')` checks and custom admin notices to detect if WooCommerce is active.
**THE FIX:** Use the modern WordPress `Requires Plugins` header. As of WordPress 7.0, listing `woocommerce` in this header automatically prevents activation and informs the user if the dependency is missing or inactive.

*   **Rule:** If the plugin header contains `Requires Plugins: woocommerce`, DO NOT add manual dependency checks in the PHP logic. This reduces boilerplate and leverages native WordPress UI for better UX.

*   **Header Example:**
```php
/**
 * Plugin Name: My Cool Extension
 * Requires Plugins: woocommerce
 */
```

## 8. Development Standards (Maintenance)

To maintain 100% compliance with zero-violation PHPCS and security audits, follow these specialized sub-guidelines:

1.  **[PHPCS Linting Standards](coding-standard.md)**: Zero-violation patterns for docblocks, punctuation, and operator handling.
2.  **[Security & Input Protocols](security-standards.md)**: Strict `wp_unslash()`, sanitization, and nonce verification requirements for all superglobals.
3.  **[Admin Menu & Orchestration](admin-orchestration-standards.md)**: Patterns for centralized menu registration and lazy-loading of admin sub-modules.
4.  **[Date & Timezone Safety](date-timezone-standards.md)**: Enforces `wp_date()` over `date()`/`date_i18n()`, `wp_timezone()` in all DateTime constructors, and timezone-aware cron scheduling.
5.  **[Translation & i18n Enforcement](translation-check.md)**: All user-facing strings must be wrapped in WordPress i18n functions — no raw hardcoded strings allowed.

## Final Validation Protocol Focus
Before considering any plugin architectural shift "complete":
1. Have I audited `class-` file prefixes?
2. Does every `class Foo` begin with `class Prefix_Foo`?
3. Are all string identifiers explicitly registered in WordPress (Roles, Capabilities, Cron Tags, Shortcodes) natively prefixed?
4. **Input Handling**: Have I ensured every instance of `$_POST`/`$_GET` is unslashed and sanitized?
5. **Orchestration**: Is there only one class handling the `admin_menu` registration?
6. **Date/Timezone**: Is every `date()` replaced with `wp_date()`? Is every `date_i18n()` replaced with `wp_date()`? Is every `gmdate()` replaced with `wp_date( $format, $timestamp, new \DateTimeZone( 'UTC' ) )`? Does every `new \DateTime` / `new \DateTimeImmutable` pass `wp_timezone()`? Are cron timestamps built with `wp_timezone()` instead of bare `strtotime()`?
7. **Main Plugin File**: Is the root plugin file fully class-based? Are there **zero** standalone functions, **zero** bare `define()` calls, **zero** bare `add_action()` calls outside the bootstrap class? Is the WooCommerce missing notice a class method, not a standalone function?
8. **Inline JavaScript**: Are there **zero** `<script>` tags in PHP templates? Is all JS externalized into `.js` files and enqueued via `wp_enqueue_script()`? Is dynamic data passed via `wp_localize_script()` or `wp_add_inline_script()`?
9. **Output Escaping**: Does every `sprintf( __() )` use `esc_html__()` instead? Is every injected variable escaped with `esc_html()`, `esc_attr()`, or `esc_url()` inside `sprintf()`?
