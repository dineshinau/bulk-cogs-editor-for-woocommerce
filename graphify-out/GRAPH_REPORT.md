# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-10-19)

## Corpus Check
- 21 files · ~27,202 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 2, .xml 1, .scss 1)

## Summary
- 246 nodes · 436 edges · 15 communities (7 shown, 8 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 24 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8e9b294a`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- bulk-cogs-editor.spec.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- blueprint.json
- ref_anthropic_ai_sdk
- scripts
- DKBCE_Admin_Functions
- bulk-cogs-editor.js
- DKBCE_COGS_Service
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 29 edges
2. `DKBCE_Bulk_Processor` - 20 edges
3. `DKBCE_Operation_Store` - 19 edges
4. `DKBCE_COGS_Service` - 17 edges
5. `preview()` - 14 edges
6. `scripts` - 13 edges
7. `renderPreview()` - 13 edges
8. `setNotice()` - 12 edges
9. `request()` - 11 edges
10. `previewCSV()` - 10 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php

## Import Cycles
- None detected.

## Communities (15 total, 8 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.16
Nodes (14): argparse, os, pathlib, changed_files(), main(), php_files(), Include staged, unstaged, and untracked PHP paths, excluding deletions., Format and check selected project PHP files without running tests. (+6 more)

### Community 2 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.06
Nodes (32): ref_node_child_process, ref_node_crypto, ref_node_fs, ref_node_os, ref_node_path, @playwright/test, fs, getProducts() (+24 more)

### Community 7 - "blueprint.json"
Cohesion: 0.22
Nodes (8): features, networking, landingPage, preferredVersions, php, wp, $schema, steps

### Community 10 - "scripts"
Cohesion: 0.06
Nodes (31): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, scripts (+23 more)

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.16
Nodes (40): actionLabel(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), applyChanges(), cancelOperation(), csvMode() (+32 more)

### Community 117 - "DKBCE_COGS_Service"
Cohesion: 0.13
Nodes (3): DKBCE_Admin_Hooks, DKBCE_COGS_Service, dkbce_load_plugin_files()

### Community 188 - "DKBCE_Operation_Store"
Cohesion: 0.13
Nodes (3): DKBCE_Operation_Store, {closure#1}(), dkbce_e2e_cleanup()

## Knowledge Gaps
- **53 isolated node(s):** `$schema`, `landingPage`, `php`, `wp`, `networking` (+48 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 99 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **8 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_Operation_Store`, `DKBCE_COGS_Service`?**
  _High betweenness centrality (0.080) - this node is a cross-community bridge._
- **Why does `DKBCE_Bulk_Processor` connect `DKBCE_Bulk_Processor` to `DKBCE_Operation_Store`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `dkbce_e2e_cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `DKBCE_COGS_Service` (e.g. with `.get_instance()` and `.__construct()`) actually correct?**
  _`DKBCE_COGS_Service` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `landingPage`, `php` to the rest of the system?**
  _53 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `bulk-cogs-editor.spec.cjs` be split into smaller, more focused modules?**
  _Cohesion score 0.06282051282051282 - nodes in this community are weakly interconnected._
- **Should `scripts` be split into smaller, more focused modules?**
  _Cohesion score 0.0625 - nodes in this community are weakly interconnected._