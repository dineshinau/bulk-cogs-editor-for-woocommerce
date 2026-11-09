# Default rollback mode

- Add a store-wide keep/none default, retaining keep for existing installations.
- Let each bulk/import confirmation override it with a checked-to-keep checkbox and live explanatory text. Other confirmation dialogs must hide this control.
- Validate and freeze the mode in operation state. Existing operations retain history.
- No-history updates keep temporary prepared targets for retry safety, persist successful outcomes under product locks instead of confirming rollback rows, and clean temporary journals after termination.
- Keep operation summaries and outcomes; deny rollback server-side and explain unavailable product history.
- Validate with PHP formatting, syntax and PHPCS, frontend build, source review, POT generation, graph refresh and diff check. No tests or browser verification requested.
