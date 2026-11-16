# Product browsing and bulk editor layout

## Goal
Show selling prices and current COGS immediately after Find products, and stack the bulk editor steps in full-width sections.

## Acceptance criteria
- Selling price appears beside current COGS and supports column preferences without shifting existing column identifiers.
- Matching products support pagination and selection before a cost action is entered.
- Browsing displays no proposed cost or change and cannot queue an update.
- Adding or clearing an action refreshes the table and preserves the product selection.
- Cost action controls share a compact desktop row and stack on smaller screens.
- Existing CSV, snapshot, selection, and confirmation protections remain in place.

## Files and approach
- COGS service: current selling price and read-only browse rows.
- Admin functions: browse preview validation, apply guard, column preferences, and UI copy.
- Frontend source: browse state, refresh and selection flow, selling price column, vertical layout.
- Readme, generated assets, translation template, and code graph.

## Checks
Run PHP formatting, syntax checks, and PHPCS on changed PHP files; build assets; regenerate translations; refresh the graph and inspect changed symbols; review the final diff and whitespace. Do not run tests or browser sessions without a request.
