# Empty COGS query performance

The Empty or zero filter generates four postmeta joins, three unconstrained by meta_key in their join conditions. On wpdev's 20,248 product/variation records, a SELECT capped at two seconds times out. EXPLAIN estimates 26 metadata rows per joined alias. The resulting repeated metadata combinations make the paginated count expensive.

Replace stored COGS state meta-query groups with uncorrelated IN/NOT IN predicates filtered by the cost meta key in the existing product-query WHERE hook. Preserve empty, numeric zero, positive, variation, range, and intersected selection semantics. Carry all state constraints through the WooCommerce query bridge. Keep product reads and writes through existing APIs; no schema changes, indexes, product mutations, or server configuration changes.

Also report a useful error for non-JSON HTTP failures, including 504 responses.

Acceptance: bounded count and first-page reads complete on the existing catalogue; old and new predicates agree on bounded real product subsets; existing selection constraints remain combined with AND; count and page order stay intact. Record plans and elapsed times without claiming broad benchmark results.

Checks: PHP formatting/syntax/PHPCS, frontend build, POT generation, read-only backend measurements and query-plan comparison, graph refresh, final diff review. No Playwright or test suite execution is authorized for this task.
