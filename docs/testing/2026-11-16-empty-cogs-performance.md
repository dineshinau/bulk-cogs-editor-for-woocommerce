# Empty-or-zero COGS timeout diagnosis and fix

## Environment and cause

Target: local `https://wsl.com/wpdev`, MySQL 8.0.46. The catalogue contained 6,637 products and 13,611 variations, totaling 20,248 records. No catalogue data was changed during diagnosis.

The original WordPress meta query generated four postmeta joins. Three joined metadata by post ID without constraining the metadata key in the join. EXPLAIN estimated 26 metadata rows per alias. Those repeated row combinations made the grouped count expensive, despite its LIMIT 1. The user observed an nginx HTTP 504. A diagnostic SELECT capped at two seconds was interrupted by the database limit.

## Change

The service now expresses empty, zero, positive, and empty-or-zero costs as uncorrelated membership subqueries restricted to `_cogs_total_value`. The query uses the existing `meta_key` index. Positive post IDs exclude NULLs from the NOT IN input. Duplicate-row existence behavior for the zero filter is preserved. Intersected selection filters retain all cost-state conditions with AND.

The frontend also converts non-JSON HTTP failures into readable errors, with specific guidance for HTTP 408 and 504, instead of displaying a raw JSON parser exception.

This is an application query rewrite. No DDL, new indexes, server timeout settings, or product costs were changed. Existing WooCommerce product queries, permissions, ordering, variation counts, and update paths remain in place.

## Measurements

| Measurement | Result |
| --- | --- |
| Original full count SQL, two-second cap | Interrupted at 2.0048 seconds |
| Final candidate count plus product/variation breakdown | 0.5050 seconds |
| Final candidate preview page | 0.1845 seconds |
| Installed wpdev count plus breakdown | 0.6043 seconds |
| Installed wpdev preview page | 0.1897 seconds |
| Matches | 7: 5 products, 2 variations |

These are backend measurements from this local catalogue, not end-to-end browser timings or a general performance guarantee. The original unrestricted query was not allowed to run until the gateway timeout.

EXPLAIN changed from four outer postmeta joins using the post_id index to cost-key subqueries using the existing meta_key index. An initial correlated EXISTS rewrite completed but took 8.8946 seconds for count/breakdown; it was replaced by the faster uncorrelated query before installation.

The old and new queries returned identical ordered IDs for empty, zero, positive, and empty-or-zero filters on the same 17 existing product/variation IDs. That sample included all seven empty matches and ten records with positive costs. It contained no explicit numeric-zero matches. Combining empty-or-zero with a prior positive-cost selection correctly returned zero matches. No synthetic data or test suites were run.

## Validation and local installation

PHP formatting, syntax checks, and PHPCS passed for both changed PHP files. The production build, JavaScript syntax check, POT regeneration, and diff checks passed. Browser checks were not run for this task.

Four checked files were copied to the local wpdev plugin: the admin functions and COGS service PHP files, the compiled editor JavaScript, and its asset manifest. Their prior versions are backed up under `/tmp/dkbce-empty-cogs-wpdev-backup/`. Restoring those files reverses the local installation; no database rollback is required.

```bash
cp /tmp/dkbce-empty-cogs-wpdev-backup/admin/class-dkbce-admin-functions.php /var/www/html/wpdev/wp-content/plugins/bulk-cogs-editor-for-woocommerce/admin/
cp /tmp/dkbce-empty-cogs-wpdev-backup/admin/class-dkbce-cogs-service.php /var/www/html/wpdev/wp-content/plugins/bulk-cogs-editor-for-woocommerce/admin/
cp /tmp/dkbce-empty-cogs-wpdev-backup/assets/bulk-cogs-editor.js /var/www/html/wpdev/wp-content/plugins/bulk-cogs-editor-for-woocommerce/assets/
cp /tmp/dkbce-empty-cogs-wpdev-backup/assets/bulk-cogs-editor.asset.php /var/www/html/wpdev/wp-content/plugins/bulk-cogs-editor-for-woocommerce/assets/
```
