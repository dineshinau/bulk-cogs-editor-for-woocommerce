# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-10-13)

## Corpus Check
- 19 files · ~19,639 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 2, .xml 1, .scss 1)

## Summary
- 206 nodes · 336 edges · 17 communities (7 shown, 10 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `ff51fd41`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- DKBCE_Admin_Hooks
- global-setup.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- scripts
- bulk-cogs-editor.spec.cjs
- ref_anthropic_ai_sdk
- package.json
- DKBCE_Admin_Functions
- bulk-cogs-editor.js
- DKBCE_COGS_Service
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 23 edges
2. `DKBCE_Bulk_Processor` - 15 edges
3. `DKBCE_Operation_Store` - 15 edges
4. `DKBCE_COGS_Service` - 14 edges
5. `scripts` - 13 edges
6. `preview()` - 11 edges
7. `setNotice()` - 10 edges
8. `renderPreview()` - 10 edges
9. `request()` - 8 edges
10. `getProducts()` - 8 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php

## Import Cycles
- None detected.

## Communities (17 total, 10 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.16
Nodes (14): argparse, os, pathlib, changed_files(), main(), php_files(), Include staged, unstaged, and untracked PHP paths, excluding deletions., Format and check selected project PHP files without running tests. (+6 more)

### Community 2 - "global-setup.cjs"
Cohesion: 0.09
Nodes (23): ref_node_child_process, ref_node_crypto, ref_node_fs, ref_node_os, ref_node_path, adminCredentialsFile, { chromium }, crypto (+15 more)

### Community 6 - "scripts"
Cohesion: 0.15
Nodes (13): scripts, build, check-engines, make-pot, npm_install, packages-update, phpcbf, phpcs (+5 more)

### Community 7 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.18
Nodes (8): fs, getProducts(), os, path, preview(), setFilters(), stateDirectory, { test, expect }

### Community 10 - "package.json"
Cohesion: 0.10
Nodes (19): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, version (+11 more)

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.16
Nodes (33): actionLabel(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), applyChanges(), cancelOperation(), fetchPreviewPage() (+25 more)

### Community 188 - "DKBCE_Operation_Store"
Cohesion: 0.19
Nodes (3): DKBCE_Operation_Store, {closure#1}(), dkbce_e2e_cleanup()

## Knowledge Gaps
- **47 isolated node(s):** `name`, `version`, `update`, `npm_install`, `start` (+42 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 85 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_Admin_Hooks`, `DKBCE_COGS_Service`?**
  _High betweenness centrality (0.072) - this node is a cross-community bridge._
- **Why does `@playwright/test` connect `package.json` to `global-setup.cjs`, `bulk-cogs-editor.spec.cjs`?**
  _High betweenness centrality (0.066) - this node is a cross-community bridge._
- **Why does `DKBCE_Operation_Store` connect `DKBCE_Operation_Store` to `DKBCE_COGS_Service`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `dkbce_e2e_cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `version`, `update` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `global-setup.cjs` be split into smaller, more focused modules?**
  _Cohesion score 0.08923076923076922 - nodes in this community are weakly interconnected._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._