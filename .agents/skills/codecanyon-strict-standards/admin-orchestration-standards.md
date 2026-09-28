---
name: admin-orchestration-standards
description: Standards for centralized admin menu registration and lazy-loading of admin modules to ensure high-performance and clean hook management.
---

# Admin Menu & Orchestration Standards

Use this skill to maintain a centralized and efficient admin architecture. Avoid fragmented hook registrations across multiple classes.

## 1. Centralized Hook Management (The Orchestrator Pattern)

All `admin_menu` and `admin_init` hooks that relate to structural registration must be managed by a single orchestrator class (e.g., `Wksdc_Admin_Menu`).

- **DO NOT** allow multiple classes to call `add_action( 'admin_menu', ... )`.
- **DO NOT** let sub-modules (like Manifest or Blackout Date managers) register their own menus.
- **RATIONALE**: Fragmented registrations make it impossible to reorder menus from one place and lead to "hook bloat" where the system executes unnecessary class constructors during every admin request.

## 2. Lazy-Loading Admin Modules

Sub-modules should only be instantiated when their specific page is visited. Use anonymous functions or dedicated callbacks in `add_submenu_page`.

### Preferred Pattern:
```php
add_submenu_page(
    'parent-slug',
    'Page Title',
    'Menu Title',
    'capability',
    'page-slug',
    function () {
        ( new \Namespace\To\Module() )->render_page();
    }
);
```

### Avoid Pattern:
```php
// Fragmented hook in Manifest class
add_action( 'admin_menu', [ $this, 'register' ] );

// Global instantiation in Plugin bootstrapper
( new \Namespace\To\Manifest() ); 
```

## 3. Separation of Action vs. Render

- **Render**: Callback should only handle UI rendering.
- **Actions**: Handle form submissions (`$_POST`) on `admin_init` or `load-{$page}` hooks, NOT within the render callback.
- Ensure `admin_init` handlers check the `page` parameter to avoid running logic on every admin screen.

## 4. Menu Consistency

- Always include a "Settings" page as the final item in the plugin's menu group.
- Provide shortcuts under the main `woocommerce` menu for critical operational features (e.g., "Scheduled Deliveries") to improve UX discoverability.

## 5. Script/Style Enqueueing

- Always use the `$hook` parameter in `admin_enqueue_scripts`.
- Only enqueue assets when the current `$hook` matches the plugin's registered page slugs.
