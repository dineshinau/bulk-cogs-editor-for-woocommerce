# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-10-20)

## Corpus Check
- 29 files · ~45,260 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 2, .xml 1, .scss 1)

## Summary
- 338 nodes · 678 edges · 19 communities (9 shown, 10 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 63 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `00149c0a`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- DKBCE_CSV_Service
- bulk-cogs-editor.spec.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- DKBCE_Import_Store
- blueprint.json
- DKBCE_Autoloader
- ref_anthropic_ai_sdk
- scripts
- DKBCE_Retention_Processor
- DKBCE_Admin_Functions
- bulk-cogs-editor.js
- DKBCE_COGS_Service
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 37 edges
2. `DKBCE_Operation_Store` - 29 edges
3. `DKBCE_Bulk_Processor` - 23 edges
4. `DKBCE_Import_Store` - 22 edges
5. `DKBCE_COGS_Service` - 21 edges
6. `setNotice()` - 16 edges
7. `updateOperationControls()` - 16 edges
8. `preview()` - 16 edges
9. `csvMode()` - 15 edges
10. `request()` - 14 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php
- `dkbce_maybe_upgrade_database()` --calls--> `DKBCE_Database_Installer`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-database-installer.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php

## Import Cycles
- None detected.

## Communities (19 total, 10 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.16
Nodes (14): argparse, os, pathlib, changed_files(), main(), php_files(), Include staged, unstaged, and untracked PHP paths, excluding deletions., Format and check selected project PHP files without running tests. (+6 more)

### Community 1 - "DKBCE_CSV_Service"
Cohesion: 0.12
Nodes (3): DKBCE_CSV_Service, DKBCE_Download_Processor, DKBCE_Import_Reader

### Community 2 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.06
Nodes (32): ref_node_child_process, ref_node_crypto, ref_node_fs, ref_node_os, ref_node_path, @playwright/test, fs, getProducts() (+24 more)

### Community 6 - "DKBCE_Import_Store"
Cohesion: 0.15
Nodes (3): DKBCE_Database_Installer, DKBCE_Import_Processor, DKBCE_Import_Store

### Community 7 - "blueprint.json"
Cohesion: 0.22
Nodes (8): features, networking, landingPage, preferredVersions, php, wp, $schema, steps

### Community 10 - "scripts"
Cohesion: 0.06
Nodes (31): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, scripts (+23 more)

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.14
Nodes (53): actionLabel(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), applyChanges(), cancelOperation(), clearFilters() (+45 more)

### Community 135 - "DKBCE_Bulk_Processor"
Cohesion: 0.16
Nodes (4): DKBCE_Bulk_Processor, dkbce_load_plugin_files(), dkbce_maybe_upgrade_database(), dkbce_wc_log()

### Community 188 - "DKBCE_Operation_Store"
Cohesion: 0.15
Nodes (3): DKBCE_Operation_Store, {closure#1}(), dkbce_e2e_cleanup()

## Knowledge Gaps
- **53 isolated node(s):** `$schema`, `landingPage`, `php`, `wp`, `networking` (+48 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 118 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Operation_Store` connect `DKBCE_Operation_Store` to `DKBCE_CSV_Service`, `DKBCE_Retention_Processor`, `DKBCE_COGS_Service`, `DKBCE_Import_Store`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_CSV_Service`, `DKBCE_Retention_Processor`, `DKBCE_COGS_Service`?**
  _High betweenness centrality (0.077) - this node is a cross-community bridge._
- **Why does `DKBCE_COGS_Service` connect `DKBCE_COGS_Service` to `DKBCE_CSV_Service`, `DKBCE_Retention_Processor`?**
  _High betweenness centrality (0.072) - this node is a cross-community bridge._
- **Are the 9 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `.cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 9 INFERRED edges - model-reasoned connections that need verification._
- **Are the 10 inferred relationships involving `DKBCE_Import_Store` (e.g. with `.ajax_apply()` and `.ajax_import_status()`) actually correct?**
  _`DKBCE_Import_Store` has 10 INFERRED edges - model-reasoned connections that need verification._
- **Are the 4 inferred relationships involving `DKBCE_COGS_Service` (e.g. with `.get_instance()` and `.__construct()`) actually correct?**
  _`DKBCE_COGS_Service` has 4 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `landingPage`, `php` to the rest of the system?**
  _53 weakly-connected nodes found - possible documentation gaps or missing edges._