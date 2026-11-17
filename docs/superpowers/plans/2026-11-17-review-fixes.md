# 1.3.1 review fixes

Goal: preserve valid import previews and serialize inline edits with Apply; keep delayed health scans valid and document import editing.

Acceptance criteria:
- Saving an unchanged staged cost succeeds without deleting the preview.
- Edit and Apply cannot execute concurrently for the same preview. Lock ownership ends with the request, including error responses.
- Health scan identity survives queue delays beyond one hour; completed counts retain their one-hour cache.
- The FAQ describes import editing and distinguishes bulk-only limits and reset controls.

Files: admin functions, health notice, readme, translation template if strings change.

Checks: PHP formatting, syntax and PHPCS; source review of lock lifetime and transient no-op handling; final graph refresh and diff check. No tests or browser sessions.
