---
name: dkbce-graphify
description: Use the Bulk COGS Editor code graph to locate unfamiliar relationships and refresh Graphify after a completed development task. Prefer direct source searches for named symbols; routine refreshes use AST extraction without LLM calls.
---

# Bulk COGS code graph

Run commands from the plugin root. `AGENTS.md` defines the task completion requirement.

## Find relevant code

Use a bounded query when the affected component or callers are unknown:

```bash
npm run graph:query -- "bulk update cancellation and progress" --budget 1000
graphify explain "DKBCE_Bulk_Processor"
graphify affected "DKBCE_COGS_Service" --depth 1
```

Prefer `rg -n` for a known file or symbol. Read the cited source before editing or asserting behavior. Do not load all of `graph.json`, `graph.html`, or the report into context.

## Refresh after work

Run once after the task's final edits and applicable formatting/checks, before the final response:

```bash
npm run graph:update
```

This uses the installed `graphify update .` CLI, cached AST extraction, and deterministic community labels. It updates `graphify-out/graph.json`, `GRAPH_REPORT.md`, the manifest, and associated graph artifacts without an LLM call. An unchanged graph is a successful no-op when the command reports success.

- Include documentation and configuration tasks in this completion step; their code graph may remain unchanged.
- Use `.graphifyignore` to keep shared skills, documentation, dependencies, generated assets, and graph outputs outside the code corpus. Read documentation directly.
- For changed code, inspect a relevant symbol or bounded query after updating. Graph presence alone does not prove that a new method was indexed.
- If extraction fails or a changed symbol is absent, report the graph as stale or incomplete and rely on source. Do not suppress the command's exit code.
- If a legitimate deletion triggers the shrink guard, inspect the removed sources before using `graphify update . --force`. Never force through unexplained extraction failures.
- Do not run semantic extraction, LLM labeling, a watcher, automatic dependency installation, or Git-hook installation as part of routine completion.

Graphify CLI missing: report the missing tool and retain the source-based result. Do not replace Graphify with a handwritten graph and label it a successful refresh.
