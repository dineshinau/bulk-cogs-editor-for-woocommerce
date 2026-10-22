# History and rollback

Add a third History tab matching the supplied reference and existing UI. Reuse product cells, change badges, progress, cancellation, and result rendering. Keep bulk edit and import/export behavior intact.

Acceptance: paginated history with status, action, user, date and text filters; operation details and paginated recorded changes; explicit rollback review and confirmation; background rollback with durable original values, product locks, retry protection, frozen batches and independent cancellation. Preserve unset costs. Legacy operations without recorded values remain readable but cannot roll back. Add a fourth Settings tab with 30-day, 90-day and custom-day retention, plus overwrite or skip-changed rollback behavior. Default to 30 days and overwrite; freeze the reviewed conflict policy for each rollback.

Implementation: add indexed history storage and schema upgrade; capture original/target values before product writes; run rollback through the existing worker; expose authorized AJAX endpoints; add History UI using shared rendering helpers; connect retention cleanup.

Affected: database installer/bootstrap, operation store, bulk processor, admin functions/hooks, new history storage/controller, frontend source and rebuilt assets.

Checks: project PHP formatting/syntax/PHPCS (use existing Python helper if the npm alias is absent), frontend build, final diff check, Graphify refresh and bounded inspection. No tests, commits or product updates are authorized for implementation verification.
