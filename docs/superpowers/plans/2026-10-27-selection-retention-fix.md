# Protect reused selections during retention cleanup

## Goal

Reject an expired selection instead of presenting a subset of its products when
retention cleanup deletes the source operation's durable outcomes.

## Changes

- Admin controller: validate reusable sources with fresh operation state before
  and after product reads, covering initial and refreshed previews, pagination,
  range selection, and every Apply preflight request. Return a refresh error with
  Reset filters guidance if cleanup is pending or started.
- Bulk processor: recheck retention before irreversible cleanup, then persist
  history_cleanup_started before removing any outcomes. Retried cleanup retains
  this marker so later requests cannot mistake a partially deleted selection for
  a complete one. Ordinary temporary cleanup continues to preserve reusable
  outcomes.
- Keep existing ownership checks, one-use preview claims, frozen snapshots, and
  the worker's expected-total check before product updates. The snapshot check
  prevents partial updates if source outcomes expire after confirmation.
- Document the fix in the 1.2.1 changelog and regenerate translation references.

## Acceptance and checks

No reused preview or scope derived from partial outcome deletion may be accepted.
Normal retained selections must remain usable. A retention extension before
cleanup starts must preserve outcomes. Cleanup must remain bounded and retryable.

Review the request and cleanup paths in source; run PHP formatting, syntax checks,
and PHPCS on both PHP files; regenerate the POT; refresh Graphify and inspect
affected symbols; and review git diff --check and the final diff. Tests, product
updates, benchmarks, and commits are outside the requested work.

## Source review

- Initial reuse checks source ownership before returning expiry guidance. Stored
  previews and operation pages retain their existing access checks.
- Missing sources, nonterminal sources, and pending or started history cleanup
  reject reuse. Ordinary temporary cleanup is allowed because it preserves the
  reusable outcomes. The new checks return immediately for unrelated filters.
- A cleanup that starts during product reads is caught by the fresh check after
  those reads. The started marker is durable and remains set through retries and
  after history cleanup finishes, until retention deletes the operation itself.
- Retention is checked before starting deletion. Once irreversible cleanup has
  started, retries finish it instead of exposing partially deleted data after a
  settings change. Bounds and cancellation storage are unchanged.
- If cleanup begins after confirmation of a query-backed selection, the existing
  worker compares the complete frozen snapshot count with the confirmed scope
  before processing any products. Explicit selections already have frozen IDs
  and do not need the source outcomes to build their snapshot.

This is source review; behavioral and concurrency tests have not been run.
