# Account filter presets

Move presets to site-scoped WordPress user metadata for the authenticated user.
Preserve the compact UI and ten-preset limit. Authenticate AJAX with the existing
nonce/capability checks, validate names and filter fields, and reject stale writes
instead of overwriting another device's changes. Load server presets when the
panel opens. Migrate browser presets only after a successful server save; retain
local data on conflicts or failures. Never apply filters or update products during
migration.

Touch admin functions/hooks, frontend JS, generated assets, and translations.
Check PHP formatting/syntax/PHPCS, JS syntax/build, focused Playwright behavior
where the instance supports the new endpoint, and Graphify coverage. No commit.

## Verification

- PHP formatting, syntax, PHPCS, JavaScript syntax, and production build passed.
- Playwright CLI on wpdev with local assets and isolated endpoint fixtures checked
  load failure, migration, removal of browser data only after successful saving,
  account save/reload, and deletion. No real preset metadata was changed on wpdev.
- The updated PHP endpoint has not been deployed to wpdev. Actual database
  persistence, cross-device/user isolation, and concurrent-write integration
  checks remain pending deployment. Source enforces current-user ownership,
  site scope, nonce/capability checks, and revision comparison.
