# Inline New COGS editing

Add a pencil editor to bulk review rows after a Step 2 action is chosen. Save non-negative costs using store precision; zero clears COGS. Cancel leaves the draft unchanged; Reset restores the Step 2 calculation. Mark custom values and recalculate row differences. Editing does not update products.

Persist validated product overrides in the owned preview operation so pagination, preflight checks, and queued workers use the same values. Restrict editing to reviewed products and permitted users; consumed previews cannot be edited. Changing filters/actions creates a new preview and discards overrides. Existing selection and retry behavior remain in place. CSV previews and browsing without an action remain read-only.

Affected files: admin functions/hooks, COGS service, frontend JS/SCSS, rebuilt assets, translations. No worker changes are needed because workers use the shared calculation method.

Checks: PHP formatting/syntax/PHPCS, production build, POT generation, Graphify refresh and relevant symbol inspection, final diff review. Tests and browser checks are outside the requested scope.
