# COGS health notice

Add a dismissible catalog health notice using `wp_admin_notice`. Default to
the editor page; offer all admin screens in Settings. Restrict visibility
to users who can manage product COGS.

Count published sellable products and variations through WooCommerce APIs
in bounded Action Scheduler batches. Respect inherited variation costs and
explicit variation zero values (WooCommerce normalizes ordinary zero costs to unset). Missing costs take priority over costs above the
current selling price. Cache complete counts for one hour, including zero
counts. Hide dismissed notices per user for one hour.

Affected files: new health notice class and dismissal script, admin hooks,
settings controller and UI, webpack entries, generated assets and translations.
Preserve the pending readme edits.

Checks: PHP quality on changed PHP, frontend build, translation generation,
graph refresh with symbol coverage, diff review. No tests or bulk updates.
