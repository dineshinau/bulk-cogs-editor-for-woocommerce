# Graph Report - bulk-cogs-editor-for-woocommerce  (2026-09-28)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 392 nodes · 514 edges · 23 communities (20 shown, 3 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 2 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `243c523d`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19

## God Nodes (most connected - your core abstractions)
1. `main()` - 15 edges
2. `main()` - 12 edges
3. `scripts` - 11 edges
4. `buildReport()` - 9 edges
5. `readFileSafe()` - 9 edges
6. `existsFile()` - 8 edges
7. `installTarget()` - 7 edges
8. `main()` - 7 edges
9. `copyDir()` - 7 edges
10. `DKBCE_Admin_Functions` - 6 edges

## Surprising Connections (you probably didn't know these)
- `dkbce_load_plugin_files()` --calls--> `DKBCE_Admin_Hooks`  [INFERRED]
  bulk-cogs-editor-for-woocommerce.php → admin/class-dkbce-admin-hooks.php

## Import Cycles
- None detected.

## Communities (23 total, 3 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (44): description, type, description, $id, required, type, description, items (+36 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (33): additionalProperties, items, type, $id, enum, type, items, type (+25 more)

### Community 2 - "Community 2"
Cohesion: 0.11
Nodes (25): assert(), main(), usage(), validateSkillName(), DEFAULT_IGNORES, existsDir(), findFilesRecursive(), main() (+17 more)

### Community 3 - "Community 3"
Cohesion: 0.07
Nodes (27): type, type, type, additionalProperties, properties, type, enum, type (+19 more)

### Community 4 - "Community 4"
Cohesion: 0.08
Nodes (24): description, enum, type, description, type, description, type, description (+16 more)

### Community 5 - "Community 5"
Cohesion: 0.08
Nodes (24): additionalProperties, type, additionalProperties, type, additionalProperties, type, additionalProperties, type (+16 more)

### Community 6 - "Community 6"
Cohesion: 0.10
Nodes (20): minimum, type, minimum, type, minimum, type, minimum, type (+12 more)

### Community 7 - "Community 7"
Cohesion: 0.10
Nodes (20): additionalProperties, properties, type, additionalProperties, properties, type, core, gutenberg (+12 more)

### Community 8 - "Community 8"
Cohesion: 0.26
Nodes (19): buildRecommendations(), DEFAULT_IGNORES, detectConfigConstants(), detectKinds(), detectPackageManager(), detectPluginHeaderFromPhpFile(), detectThemeHeaderFromStyleCss(), existsDir() (+11 more)

### Community 9 - "Community 9"
Cohesion: 0.19
Nodes (18): buildAnalysisPrompt(), buildUpdatePrompt(), callClaude(), detectChanges(), getUpstreamStateHash(), loadJson(), loadLastSyncState(), loadSkillContent() (+10 more)

### Community 10 - "Community 10"
Cohesion: 0.11
Nodes (18): dependencies, @woocommerce/dependency-extraction-webpack-plugin, @wordpress/scripts, name, scripts, build, check-engines, make-pot (+10 more)

### Community 11 - "Community 11"
Cohesion: 0.27
Nodes (14): assert(), copyDir(), copyFileSyncPreserveMode(), getDestDir(), getSourceDir(), installTarget(), isSymlink(), listAvailableSkills() (+6 more)

### Community 12 - "Community 12"
Cohesion: 0.15
Nodes (3): DKBCE_Admin_Functions, DKBCE_Admin_Hooks, dkbce_load_plugin_files()

### Community 13 - "Community 13"
Cohesion: 0.32
Nodes (11): decodeHtml(), fetchJson(), fetchText(), main(), mkdirp(), normalizeGutenbergReleases(), normalizeWpVersionCheckPayload(), parseWpGutenbergMapFromHtml() (+3 more)

### Community 14 - "Community 14"
Cohesion: 0.17
Nodes (12): type, type, type, type, hasJest, hasPhpUnit, hasPlaywright, hasWpEnv (+4 more)

### Community 15 - "Community 15"
Cohesion: 0.17
Nodes (12): additionalProperties, properties, type, type, paths, pluginsDir, repoRoot, themesDir (+4 more)

### Community 16 - "Community 16"
Cohesion: 0.38
Nodes (10): assert(), buildTarget(), copyDir(), copyFileSyncPreserveMode(), isSymlink(), listSkillDirs(), main(), parseArgs() (+2 more)

### Community 17 - "Community 17"
Cohesion: 0.29
Nodes (9): canRun(), existsFile(), main(), parseArgs(), runWp(), main(), parseArgs(), runWp() (+1 more)

### Community 18 - "Community 18"
Cohesion: 0.35
Nodes (10): buildConfigHints(), buildReport(), extractStubPackageReferences(), findPhpstanScripts(), isFile(), main(), normalizeComposerScript(), readJsonSafe() (+2 more)

### Community 19 - "Community 19"
Cohesion: 0.20
Nodes (10): const, type, name, tool, version, additionalProperties, properties, required (+2 more)

## Knowledge Gaps
- **158 isolated node(s):** `description`, `type`, `description`, `$id`, `required` (+153 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 173 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `properties` connect `Community 1` to `Community 3`, `Community 19`, `Community 7`?**
  _High betweenness centrality (0.098) - this node is a cross-community bridge._
- **Why does `properties` connect `Community 5` to `Community 1`, `Community 15`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Why does `signals` connect `Community 1` to `Community 5`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **What connects `description`, `type`, `description` to the rest of the system?**
  _158 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.048484848484848485 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.06417112299465241 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.10804597701149425 - nodes in this community are weakly interconnected._