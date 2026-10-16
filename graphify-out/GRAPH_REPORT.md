# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-10-15)

## Corpus Check
- 19 files · ~22,602 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 2, .xml 1, .scss 1)

## Summary
- 220 nodes · 375 edges · 16 communities (6 shown, 10 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 19 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `b936834b`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- scripts
- bulk-cogs-editor.spec.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- DKBCE_Admin_Hooks
- ref_anthropic_ai_sdk
- package.json
- DKBCE_Admin_Functions
- bulk-cogs-editor.js
- DKBCE_COGS_Service
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 26 edges
2. `DKBCE_Bulk_Processor` - 20 edges
3. `DKBCE_COGS_Service` - 17 edges
4. `DKBCE_Operation_Store` - 17 edges
5. `scripts` - 13 edges
6. `preview()` - 13 edges
7. `renderPreview()` - 11 edges
8. `setNotice()` - 10 edges
9. `request()` - 9 edges
10. `getProducts()` - 9 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php

## Import Cycles
- None detected.

## Communities (16 total, 10 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.16
Nodes (14): argparse, os, pathlib, changed_files(), main(), php_files(), Include staged, unstaged, and untracked PHP paths, excluding deletions., Format and check selected project PHP files without running tests. (+6 more)

### Community 1 - "scripts"
Cohesion: 0.15
Nodes (13): scripts, build, check-engines, make-pot, npm_install, packages-update, phpcbf, phpcs (+5 more)

### Community 2 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.06
Nodes (31): ref_node_child_process, ref_node_crypto, ref_node_fs, ref_node_os, ref_node_path, fs, getProducts(), os (+23 more)

### Community 10 - "package.json"
Cohesion: 0.10
Nodes (19): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, version (+11 more)

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.17
Nodes (35): actionLabel(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), applyChanges(), cancelOperation(), fetchPreviewPage() (+27 more)

### Community 188 - "DKBCE_Operation_Store"
Cohesion: 0.15
Nodes (3): DKBCE_Operation_Store, {closure#1}(), dkbce_e2e_cleanup()

## Knowledge Gaps
- **47 isolated node(s):** `name`, `version`, `update`, `npm_install`, `start` (+42 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 89 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_Operation_Store`, `DKBCE_Admin_Hooks`?**
  _High betweenness centrality (0.076) - this node is a cross-community bridge._
- **Why does `@playwright/test` connect `package.json` to `bulk-cogs-editor.spec.cjs`?**
  _High betweenness centrality (0.057) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `DKBCE_COGS_Service` (e.g. with `.get_instance()` and `.__construct()`) actually correct?**
  _`DKBCE_COGS_Service` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `dkbce_e2e_cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `version`, `update` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `bulk-cogs-editor.spec.cjs` be split into smaller, more focused modules?**
  _Cohesion score 0.0620782726045884 - nodes in this community are weakly interconnected._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._