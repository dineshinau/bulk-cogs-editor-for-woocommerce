# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-11-12)

## Corpus Check
- 38 files · ~96,686 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 18 file(s) not represented in the graph (top: .csv 13, (none) 3, .xml 1)

## Summary
- 540 nodes · 1127 edges · 35 communities (21 shown, 14 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 116 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d3d3bb75`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- php-quality.py
- DKBCE_CSV_Service
- bulk-cogs-editor.spec.cjs
- ref_bulk_cogs_editor_css
- ref_node_process
- DKBCE_Import_Store
- release-workflows.spec.cjs
- DKBCE_Autoloader
- ref_anthropic_ai_sdk
- scripts
- DKBCE_COGS_Service
- DKBCE_Admin_Functions
- Frequently asked questions
- global-setup.cjs
- DKBCE_History_Store
- global-teardown.cjs
- bulk-cogs-editor.js
- playwright.config.cjs
- DKBCE_Preset_List_Table
- Bulk COGS Editor for WooCommerce
- Bulk editing workflow
- CSV export
- CSV and Excel import
- History and rollback
- Features
- Saved filter presets
- Settings
- DKBCE_Range_Query_Profiler
- DKBCE_Bulk_Processor
- DKBCE_Operation_Store

## God Nodes (most connected - your core abstractions)
1. `DKBCE_Admin_Functions` - 44 edges
2. `DKBCE_Operation_Store` - 38 edges
3. `DKBCE_COGS_Service` - 29 edges
4. `DKBCE_Bulk_Processor` - 28 edges
5. `DKBCE_History_Store` - 28 edges
6. `renderOperation()` - 28 edges
7. `setNotice()` - 26 edges
8. `DKBCE_Import_Store` - 25 edges
9. `loadHistoryDetails()` - 24 edges
10. `request()` - 23 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Bulk_Processor`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-bulk-processor.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_COGS_Service`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-cogs-service.php
- `dkbce_maybe_upgrade_database()` --calls--> `DKBCE_Database_Installer`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-database-installer.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_History_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-history-store.php
- `dkbce_e2e_cleanup()` --calls--> `DKBCE_Operation_Store`  [INFERRED]
  tests/e2e/fixtures.php → admin/class-dkbce-operation-store.php

## Import Cycles
- None detected.

## Communities (35 total, 14 thin omitted)

### Community 0 - "php-quality.py"
Cohesion: 0.09
Nodes (21): argparse, json, os, pathlib, re, changed_files(), main(), php_files() (+13 more)

### Community 2 - "bulk-cogs-editor.spec.cjs"
Cohesion: 0.18
Nodes (8): fs, getProducts(), os, path, preview(), setFilters(), stateDirectory, { test, expect }

### Community 6 - "DKBCE_Import_Store"
Cohesion: 0.15
Nodes (4): DKBCE_Import_Processor, DKBCE_Import_Store, {closure#1}(), dkbce_e2e_cleanup()

### Community 7 - "release-workflows.spec.cjs"
Cohesion: 0.15
Nodes (12): ajax(), drive(), { execFileSync }, fixture(), fs, os, path, remember() (+4 more)

### Community 10 - "scripts"
Cohesion: 0.06
Nodes (34): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, devDependencies, @playwright/cli, @playwright/test, name, scripts (+26 more)

### Community 11 - "DKBCE_COGS_Service"
Cohesion: 0.07
Nodes (6): DKBCE_Admin_Hooks, DKBCE_COGS_Service, DKBCE_Health_Notice, DKBCE_History_Controller, DKBCE_Retention_Processor, dkbce_load_plugin_files()

### Community 13 - "Frequently asked questions"
Cohesion: 0.18
Nodes (11): Can I preview without changing my store?, Can I update variations without changing their parents?, Can rollback overwrite a later cost edit?, Does the plugin send store data to external services?, Does this replace WooCommerce’s native COGS feature?, Frequently asked questions, Is cancellation the same as rollback?, Why are some preview controls disabled? (+3 more)

### Community 14 - "global-setup.cjs"
Cohesion: 0.18
Nodes (10): ref_node_crypto, { chromium }, crypto, { execFileSync }, fixtureScript, fs, os, path (+2 more)

### Community 15 - "DKBCE_History_Store"
Cohesion: 0.13
Nodes (3): DKBCE_Database_Installer, DKBCE_History_Store, DKBCE_Preset_Store

### Community 16 - "global-teardown.cjs"
Cohesion: 0.29
Nodes (6): ref_node_child_process, ref_node_fs, { execFileSync }, fs, os, path

### Community 17 - "bulk-cogs-editor.js"
Cohesion: 0.08
Nodes (91): actionLabel(), addOperationHint(), appendCell(), appendChangeCell(), appendProductCell(), appendProductLinkCell(), appendReviewNotice(), applyChanges() (+83 more)

### Community 18 - "playwright.config.cjs"
Cohesion: 0.29
Nodes (6): ref_node_os, ref_node_path, { defineConfig }, os, path, stateDirectory

### Community 23 - "Bulk COGS Editor for WooCommerce"
Cohesion: 0.20
Nodes (9): 1. Bulk edit overview, 2. Enable WooCommerce Cost of Goods Sold, Background processing and recovery, Bulk COGS Editor for WooCommerce, Contents, Development and support, Optional WP-CLI runner, Performance (+1 more)

### Community 24 - "Bulk editing workflow"
Cohesion: 0.22
Nodes (9): 10. Update completed: 1,000 of 1,000, 3. Choose filters and a cost action, 4. Review 1,000 selected products, 5. Confirm with rollback history, 6. Confirm without rollback history, 7. Update started: 0 of 1,000, 8. Update halfway: 500 of 1,000, 9. Confirm stopping remaining updates (+1 more)

### Community 25 - "CSV export"
Cohesion: 0.29
Nodes (7): 11. Configure a CSV export, 12. Confirm the CSV export, 13. Follow CSV generation progress, 14. Download the completed CSV, 15. Inspect the exported spreadsheet, 16. Enter replacement costs in new_cogs, CSV export

### Community 26 - "CSV and Excel import"
Cohesion: 0.29
Nodes (7): 17. Upload and prepare an import, 18. Confirm cancelling import preparation, 19. Review validated import rows, 20. Confirm selected imported costs, CSV and Excel import, File format, Validate, review, then apply

### Community 27 - "History and rollback"
Cohesion: 0.33
Nodes (6): 21. Find an operation in History, 22. Inspect recorded product changes, 23. Review and confirm a rollback, 24. Inspect a completed rollback, History and rollback, Rollback steps and retry behavior

### Community 28 - "Features"
Cohesion: 0.33
Nodes (6): Features, Find the products you need, Guidance and responsive controls, Preview and choose your update scope, Product and variation support, Set, adjust, or clear costs

### Community 29 - "Saved filter presets"
Cohesion: 0.40
Nodes (5): 25. Manage saved filter presets, 26. Edit a preset, 27. Confirm deleting one preset, 28. Confirm bulk deletion of presets, Saved filter presets

### Community 30 - "Settings"
Cohesion: 0.67
Nodes (3): 29. Configure history and rollback settings, 30. Confirm force clearing a stuck operation, Settings

### Community 135 - "DKBCE_Bulk_Processor"
Cohesion: 0.15
Nodes (3): DKBCE_Bulk_Processor, dkbce_maybe_upgrade_database(), dkbce_wc_log()

## Knowledge Gaps
- **106 isolated node(s):** `name`, `version`, `update`, `npm_install`, `start` (+101 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 209 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **14 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DKBCE_Admin_Functions` connect `DKBCE_Admin_Functions` to `DKBCE_Bulk_Processor`, `DKBCE_CSV_Service`, `DKBCE_COGS_Service`, `DKBCE_History_Store`?**
  _High betweenness centrality (0.063) - this node is a cross-community bridge._
- **Why does `DKBCE_COGS_Service` connect `DKBCE_COGS_Service` to `DKBCE_Admin_Functions`, `DKBCE_Range_Query_Profiler`, `DKBCE_Import_Store`, `DKBCE_Bulk_Processor`?**
  _High betweenness centrality (0.048) - this node is a cross-community bridge._
- **Why does `DKBCE_Operation_Store` connect `DKBCE_Operation_Store` to `DKBCE_CSV_Service`, `DKBCE_Import_Store`, `DKBCE_Bulk_Processor`, `DKBCE_COGS_Service`, `DKBCE_Admin_Functions`, `DKBCE_History_Store`?**
  _High betweenness centrality (0.046) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `DKBCE_Admin_Functions` (e.g. with `.__construct()` and `.summary()`) actually correct?**
  _`DKBCE_Admin_Functions` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 15 inferred relationships involving `DKBCE_Operation_Store` (e.g. with `.get_instance()` and `.cleanup()`) actually correct?**
  _`DKBCE_Operation_Store` has 15 INFERRED edges - model-reasoned connections that need verification._
- **Are the 11 inferred relationships involving `DKBCE_COGS_Service` (e.g. with `.get_instance()` and `.__construct()`) actually correct?**
  _`DKBCE_COGS_Service` has 11 INFERRED edges - model-reasoned connections that need verification._
- **Are the 5 inferred relationships involving `DKBCE_Bulk_Processor` (e.g. with `.get_instance()` and `.ajax_history_detail()`) actually correct?**
  _`DKBCE_Bulk_Processor` has 5 INFERRED edges - model-reasoned connections that need verification._