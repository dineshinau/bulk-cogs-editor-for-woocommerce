---
name: dkbce-planning
description: Plan changes across the Bulk COGS Editor components, diagnose uncertain failures, and check completion using a concise Superpowers workflow. Use for new behavior, queue or persistence changes, and investigations; skip formal plans for straightforward edits.
---

# Bulk COGS planning

This is a project adaptation of [Superpowers](https://github.com/obra/superpowers), using the locally available 6.3.0 reference skills. The upstream references retain their [MIT license](references/SUPERPOWERS-LICENSE).
Apply repository `AGENTS.md` before generic workflow defaults.

## Choose the smallest useful workflow

- Known small fix: locate the responsible method, state the intended behavior, edit, and run the permitted checks.
- Uncertain failure: collect current evidence, distinguish measured behavior from hypotheses, identify the responsible path, then patch. Use `investigate-first` or the [Superpowers debugging reference](references/systematic-debugging.md) if deeper diagnosis is needed; do not load both routinely.
- New behavior or several components: write a short plan with the goal, acceptance criteria, affected files, dependencies, implementation steps, and checks. Save a plan in `docs/superpowers/plans/` only when it will help continuation or review.
- Completion: review the diff and report commands actually run. For an ambiguous completion claim, consult the [Superpowers verification reference](references/verification-before-completion.md).

For a substantial plan that needs more structure, read the [Superpowers planning reference](references/writing-plans.md). Adapt its template to the task; do not copy implementation code into a second document.

## Plugin invariants to include when relevant

- Product matching and arithmetic belong to `DKBCE_COGS_Service`; queue execution belongs to `DKBCE_Bulk_Processor`; durable state belongs to `DKBCE_Operation_Store`.
- Preserve a fixed snapshot before updates when filters depend on COGS values.
- Keep each outcome durable, counters recoverable, batch size fixed for a job, and cancellation independent of progress writes.
- Resume partial chunks without repeating percentage or fixed increases. Keep WooCommerce CRUD and existing public hooks.
- Scope performance claims to the observed environment and product mix. Separate action execution from the time between actions.

## Keep planning economical

- Use Caveman lite for user updates; write normal prose in persisted documentation and code.
- Search a named symbol directly. For unknown relationships, use one bounded Graphify query and read the relevant source.
- Load one process skill and only the domain guidance needed for the current step. Reuse prior findings with file references.
- Existing authorization carries through execution. Ask only when missing information prevents a correct implementation.
- Generic TDD, commits, worktrees, mandatory design approval, and agent dispatch are not automatic steps here. Follow `AGENTS.md` and the user's task scope.
- When the user explicitly requests a commit, inspect and include changed tracked `languages/*.pot` files with the related product changes. Regenerate the POT first when translatable PHP strings changed.
- Use `npm run php:quality -- <changed PHP files>` during PHP work. Tests require a user request.
- Finish with `npm run graph:update`; report graph status and checks without claiming unmeasured speedups or unrun tests.
