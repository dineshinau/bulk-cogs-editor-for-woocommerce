# Refresh cached COGS health counts

Add a Refresh now link styled as a WordPress button-link to the existing health notice. Clicking starts a fresh background scan and polls for completion. Retain the displayed count while scanning, announce progress, replace the notice message when complete, and remove the notice when both counts reach zero. Errors restore the refresh control and show readable feedback.

Use the existing bounded health scanner; large catalogues must not be scanned in a single AJAX request. Assign each fresh scan a UUID marker and pass it through scheduled batches so obsolete scans cannot publish stale counts. Preserve hourly cache expiry, dismiss behavior, published-product scope, variation inheritance, capabilities, and nonce validation.

Affected files: health notice PHP, notice JavaScript, generated notice assets, translation template, and the current Added health-notice entry in both changelogs. Existing uncommitted skill changes remain intact.

Checks: PHP formatting/syntax/PHPCS, JavaScript syntax, production build, translation generation, graph refresh and symbol inspection, final diff review. No tests or browser sessions requested for this task.
