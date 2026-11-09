# Preset management

Add Presets before Settings, rendering a native WP_List_Table for the current
user with created date, edit/delete row actions and bulk delete. Store new
presets in a site-prefixed custom table with user_id, stable IDs, unique names
per owner, rule JSON, timestamps and edit revisions. Extend the existing database
installer. Per user instruction, do not migrate usermeta or browser presets.

Reuse the product filter controls to edit rules in the Presets tab. Loaded
presets cannot be saved as duplicates in Bulk edit or Import/export; only new
unique names can be created there. Delete controls belong exclusively to Presets.

Acceptance: ownership and nonce/capability checks on all operations, unique-name
constraint, created date preserved on update, stale edits rejected, no product
updates caused by managing presets. Run formatting, syntax, PHPCS, build, POT,
diff and graph checks. No tests or Playwright unless explicitly requested.

## Completion checks

PHP formatting, syntax and PHPCS, JavaScript syntax, production build and diff
checks passed. Assets and POT were regenerated. No tests, Playwright, or live preset saves/deletes were run. The table will be created by
the existing schema installer after deployment; schema version advances to 1.2.1.
Existing usermeta and browser presets are not read, migrated, or deleted.

The subsequent 409 investigation confirmed an early wcdev table still used
`owner_name (user_id, name_key)`. The installer repairs this to `(user_id, name)`
and removes the obsolete derived column while preserving names and rules. This
repair was applied on wcdev and the resulting index verified with a read-only
schema query. No usermeta or browser preset migration was added.
