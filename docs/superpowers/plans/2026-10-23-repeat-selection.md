# Repeat operations on the completed selection

Keep completed bulk selections available for another operation without reloading.

Acceptance criteria:
- Completion automatically requests a fresh preview and requires confirmation again.
- Explicit, range, and all-except selections reuse the completed product scope, including products moved outside their old COGS filter.
- Only the operation owner can reuse a completed bulk selection. Preview claims remain single-use.
- Failed refreshes remain retryable; CSV and rollback behavior remains unchanged.
- Retained selection outcomes follow existing history retention.

Implementation: retain outcomes for new bulk operations; authorize reuse in the preview endpoint; constrain matching queries to that operation's outcomes; refresh the frontend once on completion. Preserve original filter values as a baseline so later filter edits narrow the reused selection.

Affected files: admin functions, COGS service, bulk processor, frontend JavaScript, generated assets.

Checks: PHP formatting, syntax and PHPCS; frontend build and syntax; source review of authorization, duplicate claims, cleanup and refresh retry paths; graph update and diff checks. Do not run tests or product updates.
