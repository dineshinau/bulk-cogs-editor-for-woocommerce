# History recovery fixes

Fix the six findings in the manual report without changing product scope or
performing updates on existing catalog products during verification.

1. Reconcile ambiguous product saves against persisted operation markers and
   targets before confirming journals or recording failures.
2. Allow reviewed recovery attempts for remaining rollback products, excluding
   products already restored by any successful attempt.
3. Serialize rollback startup with source cleanup, verify state persistence,
   and repair missing-operation claims safely under the same expiring lock.
4. Recheck retention and active rollback dependencies before destructive cleanup;
   distinguish cleanup that has already begun from a merely queued cleanup.
5. Preserve editor preview and selection when navigating History or Settings.
6. Poll historical details independently and route refresh through stale-worker
   recovery without replacing the editor's operation state.

Affected: history store/controller, operation store, bulk/retention processors,
frontend source and generated assets, manual/review reports. Existing schema
should be reused where possible. No release bump or commits are requested.

Acceptance: each reported failure has a passing focused manual reproduction,
including partial rollback recovery without overwriting already restored products.
Checks: PHP quality helper, frontend build, scoped browser and disposable-fixture
checks already authorized by the user, Markdown lint, diff review, Graphify.

Completion: all six fixes and the count-free snapshot read are implemented.
Focused browser and controlled-failure checks passed; the manual report records
exact results and remaining validation limits. All disposable fixture data was
removed, settings restored, and no existing catalog product costs changed.
