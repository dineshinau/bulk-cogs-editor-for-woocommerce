# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-10-26)

## Corpus Check
- 31 files · ~62,809 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 2, .xml 1, .scss 1)

## Summary
- 425 nodes · 911 edges · 21 communities (8 shown, 13 thin omitted)
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 100 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `18eeb62e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- DKBCE_CSV_Service
- bulk-cogs-editor.spec.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- DKBCE_Import_Store
- DKBCE_Range_Query_Profiler
- DKBCE_Autoloader
- ref_anthropic_ai_sdk
- scripts
- DKBCE_History_Controller
- DKBCE_Admin_Functions
- Bulk COGS Editor for WooCommerce
- DKBCE_History_Store
- bulk-cogs-editor.js
- DKBCE_COGS_Service
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 39 edges
2. `DKBCE_Operation_Store` - 38 edges
3. `DKBCE_Bulk_Processor` - 26 edges
4. `DKBCE_COGS_Service` - 26 edges
5. `DKBCE_History_Store` - 24 edges
6. `DKBCE_Import_Store` - 24 edges
7. `loadHistoryDetails()` - 21 edges
8. `setNotice()` - 21 edges
9. `renderOperation()` - 20 edges
10. `request()` - 19 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php
- `dkbce_maybe_upgrade_database()` --calls--> `DKBCE_Database_Installer`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-database-installer.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_History_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-history-store.php

## Import Cycles
- None detected.

## Communities (21 total, 13 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.16
Nodes (14): argparse, os, pathlib, changed_files(), main(), php_files(), Include staged, unstaged, and untracked PHP paths, excluding deletions., Format and check selected project PHP files without running tests. (+6 more)

### Community 2 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.06
Nodes (32): ref_node_child_process, ref_node_crypto, ref_node_fs, ref_node_os, ref_node_path, @playwright/test, fs, getProducts() (+24 more)

### Community 10 - "scripts"
Cohesion: 0.06
Nodes (33): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, scripts (+25 more)

### Community 11 - "DKBCE_History_Controller"
Cohesion: 0.13
Nodes (3): DKBCE_Admin_Hooks, DKBCE_History_Controller, DKBCE_Retention_Processor

### Community 13 - "Bulk COGS Editor for WooCommerce"
Cohesion: 0.06
Nodes (32): Background processing and recovery, Bulk COGS Editor for WooCommerce, Bulk editing workflow, Can I preview without changing my store?, Can I update variations without changing their parents?, Can rollback overwrite a later cost edit?, Contents, CSV and Excel import (+24 more)

### Community 15 - "DKBCE_History_Store"
Cohesion: 0.16
Nodes (4): DKBCE_Database_Installer, DKBCE_History_Store, {closure#1}(), dkbce_e2e_cleanup()

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.11
Nodes (70): actionLabel(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), applyChanges(), cancelOperation(), clearFilters() (+62 more)

### Community 135 - "DKBCE_Bulk_Processor"
Cohesion: 0.16
Nodes (4): DKBCE_Bulk_Processor, dkbce_load_plugin_files(), dkbce_maybe_upgrade_database(), dkbce_wc_log()

## Knowledge Gaps
- **75 isolated node(s):** `name`, `version`, `update`, `npm_install`, `start` (+70 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 150 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **13 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Operation_Store` connect `DKBCE_Operation_Store` to `DKBCE_History_Controller`, `DKBCE_COGS_Service`, `DKBCE_Import_Store`, `DKBCE_History_Store`?**
  _High betweenness centrality (0.070) - this node is a cross-community bridge._
- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_CSV_Service`, `DKBCE_History_Controller`, `DKBCE_COGS_Service`?**
  _High betweenness centrality (0.064) - this node is a cross-community bridge._
- **Why does `DKBCE_COGS_Service` connect `DKBCE_COGS_Service` to `DKBCE_History_Controller`, `DKBCE_Operation_Store`, `DKBCE_Range_Query_Profiler`?**
  _High betweenness centrality (0.062) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `DKBCE_Admin_Functions` (e.g. with `.__construct()` and `.summary()`) actually correct?**
  _`DKBCE_Admin_Functions` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 15 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `.cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 15 INFERRED edges - model-reasoned connections that need verification._
- **Are the 4 inferred relationships involving `DKBCE_Bulk_Processor` (e.g. with `.get_instance()` and `.ajax_history_detail()`) actually correct?**
  _`DKBCE_Bulk_Processor` has 4 INFERRED edges - model-reasoned connections that need verification._
- **Are the 8 inferred relationships involving `DKBCE_COGS_Service` (e.g. with `.get_instance()` and `.__construct()`) actually correct?**
  _`DKBCE_COGS_Service` has 8 INFERRED edges - model-reasoned connections that need verification._