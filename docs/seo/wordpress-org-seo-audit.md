# WordPress.org SEO Research & README Audit

**Plugin:** Bulk COGS Editor for WooCommerce
**Audit date:** 2026-10-22
**Scope:** Repository source, `readme.txt`, plugin metadata and implementation, WordPress.org guidance and listings, WooCommerce documentation, and qualitative search/forum evidence.

> This is a research and recommendation report only. `readme.txt` and plugin code were not changed. Per the project instruction, this audit examines `readme.txt`; it intentionally does not audit `README.md`. The five existing WordPress.org tags are preserved exactly in this report and must remain unchanged.

## Executive Summary

### Strengths

- The plugin solves a narrow, understandable WooCommerce problem: bulk editing the native product Cost of Goods Sold value rather than selling price.
- The actual workflow has meaningful differentiation to explain: product/variation filters, set/fixed/percentage/clear actions, a reviewable preview, background Action Scheduler work, progress and cancellation, CSV/XLSX cost import, CSV export, operation history, and eligible rollback.
- The README already contains practical material on CSV headers, variation handling, large catalogs, retention, rollback policies, and the fact that product prices are not changed.
- The feature language overlaps with genuine merchant language. WooCommerce documentation calls the native field “Cost of Goods” and “Cost of Goods Sold (COGS),” while a WordPress.org support question describes updating “new cogs (cost price)” across thousands of products.

### Main weaknesses

- The current opening calls the plugin “the most powerful” and “Best plugin” without comparative evidence. Those claims do not explain what a merchant can do and weaken trust.
- The short description is 147 characters, close to the 150-character limit, and packs in many benefits. It can communicate the cost-update job more directly.
- `readme.txt` is 35,688 bytes, well above the Plugin Handbook’s 10 KB caution. It contains a long, highly detailed changelog and implementation-level content that makes the main workflow harder to scan; the handbook says files over 10 KB may produce errors and recommends moving older changelog entries to `changelog.txt`.
- The README explains many import details, but the early listing copy could distinguish this cost-focused workflow from WooCommerce’s built-in CSV importer. WooCommerce already supports COGS in its core product CSV import/export tool.
- Screenshot 4 presents upload controls rather than a populated, validated import preview. Screenshot 6 documents a summary-only historical operation with no retained original values, which visually weakens the rollback story. These are conversion/content issues, not claims that the screenshots are technically invalid.
- The validator’s “tags are not widely used” note is about how widely directory plugins use selected tag terms, not whether the current plugin repeats those words in its description. The report is not evidence that keyword distribution in the README caused the note or that repeated terms will clear it.

### Highest priorities

1. Before publishing the current candidate, verify the public SVN `trunk/readme.txt` and `tags/1.2.0/readme.txt` are synchronized with the intended release content and five unchanged tags. The public directory snapshot observed during this research still showed version 1.1.1 and a different tag set from the local 1.2.0 file; recheck the live page at release time.
2. Remove the unsupported “most powerful” / “Best plugin” superlatives in a future README copy pass. Lead with bulk native COGS editing and concrete safeguards instead.
3. Reduce the readme’s size and move old changelog material out, following the handbook’s guidance. Retain the current release notes and the most useful setup, feature, FAQ, and benchmark context.
4. State early that WooCommerce already supports COGS in core CSV import/export; position this plugin around filtered, previewed bulk cost changes, XLSX input, background batches, history, and rollback—not as the only way to import COGS.
5. Use evidence-based performance language. Keep the reported 10,000-product / 15:24 figure qualified as a single reported editor-operation benchmark and not a guaranteed or independently measured end-to-end CLI duration.

### Research limits

- No Google Search Console, Keyword Planner, WordPress.org author analytics, or paid keyword dataset was available. No search volumes, difficulty scores, click-through rates, or rankings are asserted.
- The [public listing snapshot](https://wordpress.org/plugins/bulk-cogs-editor-for-woocommerce/) observed during research showed fewer than 10 active installations; this is a coarse directory bucket, not an exact count or explanation of discoverability.
- Search results, plugin-page statistics, and active-install counts are time-sensitive snapshots. They describe observed listings, not search rankings or expected outcomes.
- WordPress.org does not publish a weighted plugin-search ranking formula in the sources reviewed. The official FAQ gives qualitative advice (a good readme, timely support replies, and good reviews), not a deterministic ranking model.
- A public source-code changeset identifies a `low_usage_tags` warning category, but the current official handbook does not document an exact numeric threshold or normalization algorithm. Repeated README use cannot be presented as a fix for that warning.

## Research Method and Evidence

Reviewed the current `readme.txt`, main plugin metadata, relevant implementation paths for COGS, bulk processing, CSV/XLSX import, export, and history/rollback, plus the seven screenshot captions and corresponding product screenshots. Functionality claims in this report are limited to behavior described in the readme and confirmed against the implementation. No plugin functionality was changed.

Evidence is grouped as follows:

- **Verified:** stated in a primary source or visible in the inspected repository/listing.
- **Observation:** qualitative pattern from inspected search results, directory listings, or support discussions; not a measure of demand or ranking.
- **Hypothesis:** a reasonable content or conversion opportunity that should be measured after release.

### Sources reviewed

- WordPress.org [Plugin Readmes handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) and [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/).
- WordPress.org [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) and [Readme Validator](https://wordpress.org/plugins/developers/readme-validator/).
- WordPress.org Meta Trac [changeset 13443](https://meta.trac.wordpress.org/changeset/13443), which references the `low_usage_tags` warning category.
- WooCommerce’s [Cost of Goods Sold documentation](https://woocommerce.com/document/woocommerce-cost-of-goods-sold-cogs/).
- WordPress.org competitor listings: [WPFactory Cost of Goods](https://wordpress.org/plugins/cost-of-goods-for-woocommerce/), [Ni Cost of Goods](https://wordpress.org/plugins/ni-woocommerce-cost-of-goods/), and [Flavors Commerce Intelligence](https://wordpress.org/plugins/flavors-commerce-intelligence/).
- A merchant discussion, [“Bulk update the new cost (cogs) pricing”](https://wordpress.org/support/topic/bulk-update-the-new-cost-cogs-pricing/), describing thousands of products and cost-price/COGS updates.
- The [plugin’s public WordPress.org listing](https://wordpress.org/plugins/bulk-cogs-editor-for-woocommerce/), [GitHub repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/), and benchmark discussion linked from `readme.txt`.

Searches were used to identify relevant query language and alternatives. These are qualitative observations only; they do not establish query volume, page position, or conversion performance.

## Five-Tag Preservation and Validator Note

The local `readme.txt:3` tag line is exactly:

```text
Tags: woocommerce cogs, cost of goods sold, bulk edit, product cost, csv import
```

This is the complete set to preserve. Do not add, remove, reorder, rename, or respell any of the five tags. The [WordPress.org readme handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) allows up to five comma-separated terms, says tags describe the plugin, and notes that unique tags may not display. The [developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/) also says only the first five are used/displayed and tags used only by one plugin are not shown.

### Why the low-usage note appears

The [official Meta Trac changeset](https://meta.trac.wordpress.org/changeset/13443) identifies this validator message with a `low_usage_tags` warning category. The evidence points to directory-wide usage of a tag term—how many other plugin listings use it—not the frequency of that phrase in this plugin’s description. The [official readme handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) does not specify the current count threshold or the exact tag normalization rules. A [third-party code walkthrough](https://www.lukaswojcik.com/blog/en/wordpress-plugins-tricks/readme-header-svn/) has reported a fewer-than-five threshold, but the official documentation reviewed does not confirm that number; do not treat it as a guaranteed current rule.

The supplied latest validator screenshot lists `product cost` and `woocommerce cogs`. That identifies which tags received the note in that captured result; it does not mean those phrases are missing from this readme or need to be repeated more often.

| Possible cause | Finding | Confidence |
|---|---|---|
| Tag popularity across the directory | Yes. The warning category is explicitly named `low_usage_tags`; this is the best-supported explanation. | High for category; exact threshold unknown |
| Exact tag matching | The warning is associated with selected directory tag terms, not an exact phrase match against body text. Whether aliases or normalization affect individual counts is not documented in the official sources reviewed. | Medium |
| Tag normalization | The validator/handbook sources reviewed do not define normalization rules or expose per-tag counts. | Unknown; do not speculate |
| Plugin description content | No evidence the warning checks how often the plugin itself mentions a phrase. Repeating it is not a supported fix. | High that body repetition is not the indicated criterion |
| Search relevance/ranking | Tags are directory metadata used for discovery, but the low-usage note does not establish a ranking penalty or benefit. No published ranking weights were found. | High for “not established” |
| Other directory rules | WordPress.org allows at most five tags and notes unique tags may not be shown. The screenshot’s “Notes” label makes this a note, not a reported readme error. | High |

What the plugin author can influence:

- Choose up to five relevant directory terms when the tag set is being selected.
- Keep each retained tag relevant and make the product’s relationship to those topics clear to readers in the short description and content.
- Synchronize the unchanged tags across the directory’s trunk and stable-tagged release readme files.

What the author cannot influence while retaining these exact tags:

- How many other WordPress.org plugins use the same directory tag term.
- The validator’s taxonomy-level popularity warning or any undocumented normalization/threshold logic.
- Whether the warning changes search ranking; no source reviewed establishes a ranking penalty for this note.

Repeating a tag phrase in every section is not a remedy. It can make the copy less readable and risks looking like keyword stuffing. The tags remain unchanged; the content improvements below use their concepts only where they accurately describe the plugin.

## Five-Tag Keyword Research

The phrases below are research candidates, not measured search-volume keywords. “Evidence” means observed product/documentation/forum language, not proof of query frequency.

| Existing tag | Search intent and relevant variations | Evidence observed | Current `readme.txt` coverage | Missing opportunity | Relevance |
|---|---|---|---|---|---|
| `woocommerce cogs` | Set, bulk update, or manage WooCommerce’s native Cost of Goods Sold; “WooCommerce COGS,” “update COGS,” “native Cost of Goods,” “COGS cost price.” | WooCommerce’s own documentation uses “Cost of Goods Sold (COGS)” and “Cost of Goods”; WordPress.org alternatives also use COGS terminology. A merchant support thread asks how to bulk-update new COGS across thousands of products. | Strong: the title, short description, opening, features, instructions, and FAQ repeatedly describe COGS; native WooCommerce support is explained. | State once near the opening that this edits WooCommerce’s native product/variation cost value and is not a profit-reporting product. Distinguish from the core CSV importer. | Retain. It is directly aligned with the implementation and WooCommerce’s official naming. |
| `cost of goods sold` | Understand or update direct product costs; “WooCommerce Cost of Goods Sold,” “COGS management,” “product cost,” “cost price,” “purchase cost.” | WooCommerce defines COGS as direct costs of producing or purchasing products. Competitor listing copy uses cost price, purchase price, and cost of goods. | Strong, although the longer opening overuses the topic and adds subjective superiority claims. | Define “Cost of Goods Sold (COGS)” once, then use natural variants such as product cost. Clarify the tool updates product costs, not selling prices or total accounting costs. | Retain. Exact product concept and official WooCommerce terminology. |
| `bulk edit` | Change many products at once; “WooCommerce bulk edit,” “bulk update product costs,” “bulk update COGS,” “bulk product cost management.” | The support thread describes thousands of products and inability to update one at a time. Competitors position bulk edits for cost alongside price/stock fields. | Strong: filters, fixed/percentage actions, preview, background processing, and variations are documented. | Pair the broad phrase with “product costs/COGS” rather than implying general bulk editing of every product field. Clarify price filters can scope selection but prices themselves are not changed. | Retain. The product performs bulk editing, specifically for cost values. |
| `product cost` | Add, update, or maintain product cost / supplier purchase cost; “WooCommerce product cost,” “cost price,” “purchase price,” “update product costs,” “variation cost.” | WooCommerce’s official docs support individual and variation-level cost values. Competitor descriptions use “cost price” and “purchase price”; the merchant support thread uses “cost price.” | Strong: appears in the description, product filters, feature text, CSV/XLSX workflow, and FAQs. | Explain early that this means the WooCommerce COGS field. Avoid implying supplier feeds, landed-cost accounting, inventory valuation, or historical profit analytics. | Retain. It is the plain-language synonym most likely to make COGS understandable to merchants. |
| `csv import` | Load cost values from a spreadsheet; “WooCommerce product cost CSV import,” “import COGS,” “CSV product updates,” “Excel/XLSX cost import.” | WooCommerce core documentation explicitly says its product CSV tool supports COGS. Competitor listings also discuss CSV cost import/export. General CSV-import intent is broad and crowded. | Strong: README documents UTF-8 CSV, XLSX, IDs/SKUs, `new_cogs` and legacy `cogs`, validation, preview, and CSV export. | Describe this as a COGS-focused import rather than a general catalog importer. Explain that core CSV already supports COGS and identify the additional filtered preview/background/history workflow; note XLSX input and CSV-only export. | Retain. Accurate feature and current tag; content should disambiguate it from generic importers. |

## Search Intent and Candidate Keywords

Likely merchant jobs, inferred from WooCommerce’s COGS documentation, the inspected listings, and user support language:

1. **Change supplier/product costs across a catalog.** A merchant may have cost changes for many SKUs and needs to avoid editing each product manually.
2. **Avoid accidental price edits.** “Cost,” “cost price,” and “selling price” are distinct fields. State clearly that only COGS is changed.
3. **Target the correct products.** The plugin’s filters and product/variation scope are relevant for cost changes by category, type, brand, stock state, SKU, or current values.
4. **Review the change before it writes.** A preview, validation feedback, explicit apply step, and change history reduce operational uncertainty.
5. **Work with existing spreadsheet workflows.** WooCommerce core already supports COGS through CSV. The plugin’s content should explain its more focused cost update workflow, XLSX input, selection/preview, and async processing rather than claiming CSV COGS import is otherwise unavailable.
6. **Recover from a mistaken update.** History and rollback are useful only while eligible original values remain retained; copy should explain that limit rather than promise universal undo.

| Keyword candidate | Intent | Existing tag | Evidence | Content opportunity | Priority |
|---|---|---|---|---|---|
| WooCommerce COGS / Cost of Goods Sold | Find or manage native product cost values | `woocommerce cogs`; `cost of goods sold` | Official WooCommerce naming; competing listings | Use the expansion “Cost of Goods Sold (COGS)” on first mention | P1 |
| WooCommerce bulk update COGS | Update many native cost values together | `woocommerce cogs`; `bulk edit` | Merchant support discussion about thousands of products | Put bulk cost editing in the first sentence, not behind generic superlatives | P1 |
| Bulk edit WooCommerce product costs | Apply cost changes to a filtered catalog | `bulk edit`; `product cost` | Actual filter/action/preview workflow | Name fixed, percentage, clear, preview, and batch application succinctly | P1 |
| Update supplier costs in WooCommerce | Reflect cost changes from a supplier spreadsheet | `product cost`; `csv import` | “Supplier pricing” is a stated use case; spreadsheet workflow exists | Explain the cost-only CSV/XLSX process without suggesting a supplier integration | P2 |
| WooCommerce cost price / purchase cost | Use merchant language rather than accounting acronym | `product cost`; `cost of goods sold` | Competitor copy and support question use “cost price” | Introduce “product cost” as the plain-language synonym for native COGS | P2 |
| Import WooCommerce product costs from CSV | Update costs through a spreadsheet | `csv import`; `product cost` | WooCommerce core importer supports COGS; competitors also advertise cost CSV | Compare honestly with core importer and explain the plugin’s cost-review workflow | P1 |
| Import WooCommerce COGS from Excel / XLSX | Upload an Excel workbook for cost updates | `csv import`; `woocommerce cogs` | The local implementation accepts XLSX input | Name XLSX as input only; say exports are CSV | P2 |
| WooCommerce product cost for variations | Update variation-specific COGS | `product cost`; `woocommerce cogs` | WooCommerce docs describe variation COGS; plugin supports variation records | Explain that variations are individual records and parents are not implicitly edited | P2 |
| Preview bulk product cost changes | Validate exact updates before applying | `bulk edit`; `product cost` | Current feature and UI behavior | Show preview as a concrete trust/safety benefit | P1 |
| WooCommerce product cost history / rollback | Review prior costs and reverse eligible work | `product cost`; `woocommerce cogs` | Current operation history and retention/rollback behavior | Explain retained-original-values and changed-cost policy in an FAQ | P2 |
| WooCommerce COGS background processing | Process catalog updates without a long browser request | `woocommerce cogs`; `bulk edit` | Action Scheduler implementation and progress UI | Say “background batches via Action Scheduler”; avoid “fastest” or guaranteed speed | P2 |

No search-volume or difficulty estimates are available. These are content candidates; use Search Console and actual support queries to decide which merit dedicated educational content.

## Competitor Comparison

Directory stats below are time-sensitive snapshots from inspected WordPress.org pages, not a proxy for search position, product quality, or likely ranking. No competitor copy is reused.

| Plugin | Verified positioning and observed tags | Relevant features / intent | Comparison and opportunity |
|---|---|---|---|
| [Cost of Goods: Product Cost & Profit Calculator for WooCommerce (WPFactory)](https://wordpress.org/plugins/cost-of-goods-for-woocommerce/) | Listing observed at 9,000+ active installs and 4.7/5 (41 ratings); tags: `cost`, `cost of goods`, `profit`, `profit calculator`, `woocommerce`. | Product/variation costs, profit calculations and reports, bulk editing, and CSV through WooCommerce’s standard importer/exporter. Broad intent: cost tracking plus profitability reporting. | A mature adjacent option, but its larger footprint is not evidence of directory ranking. Position this plugin as a focused COGS editing workflow, not a profit calculator. Explain previewed updates, background processing, XLSX import, history, and reviewed rollback. |
| [Ni Cost of Goods for WooCommerce](https://wordpress.org/plugins/ni-woocommerce-cost-of-goods/) | Listing observed at 200+ active installs and 3.5/5 (4 ratings); tags: `COGS`, `cost of goods`, `profit margin`, `woocommerce reports`. | Cost price/purchase price fields, gross-profit reporting, and quick/bulk cost editing in its product list; Pro description also offers bulk updates and CSV report export. | It targets both cost entry and reporting. Differentiate around the dedicated filter → preview → apply flow, cost import/export workflow, operation outcomes, and rollback policy—without claiming competitor workflows are unsafe or inferior. |
| [Flavors Commerce Intelligence](https://wordpress.org/plugins/flavors-commerce-intelligence/) | Listing observed at fewer than 10 active installs and no reviews; tags: `analytics`, `cost of goods`, `inventory`, `profit`, `woocommerce`. | CSV cost import by SKU/ID with matched/unmatched/invalid preview, background batches, cost history, native WooCommerce COGS interoperability, and broad profit/inventory reports. | This is a close workflow comparator despite being a broader analytics plugin. Content should make the target distinction clear: focused bulk COGS editing and rollback versus a wider reporting/catalog suite. Its low observed installs also show why install counts cannot be read as rankings. |

### Competitor FAQ and screenshot observations

- Ni’s listing FAQ defines COGS in plain language and answers product cost entry, bulk/category editing, and variation questions. Its screenshot captions emphasize profit analysis, cost entry, and reporting.
- Flavors’ FAQ specifies CSV columns and preview behavior, explains where WooCommerce native COGS is entered, and answers whether cost changes affect historical profit. Its screenshot captions focus on profit, inventory, and catalog reports.
- WPFactory’s FAQ covers bulk cost editing by category and points to the default WooCommerce export workflow. Its listing also promotes cost/profit analytics and bulk cost editing.
- These are useful examples of questions and positioning to address—not evidence of search volume or a reason to copy competitor language. For this plugin, answer cost-import/core-import questions and show its preview/history workflow.

### Competitive gaps and differentiation

- **Core import confusion:** WooCommerce already imports/exports COGS via its product CSV tools. The content opportunity is not “CSV COGS import exists only here”; it is a dedicated workflow that combines filtered product selection, preview, asynchronous preparation/application, XLSX input, operation history, and reviewed rollback.
- **Avoid competing on breadth:** Other products offer profit reports, stock valuation, margins, or order-level costs. These are not this plugin’s function. Do not target generic “profit calculator,” “inventory system,” or accounting terms as if implemented.
- **Make the outcome visible:** Show a realistic bulk operation from filtering through preview and results, and a rollback review with retained original values. These are easier to understand than internal batch/cache details.
- **Educational opportunity:** Publish a concise developer-owned guide answering “How to bulk update WooCommerce COGS safely,” covering native field prerequisites, CSV versus XLSX, SKU/ID mapping, preview, and rollback limitations. Link it from the directory listing. This is a recommendation, not a claim that an existing guide has been rewritten.

## `readme.txt` Audit

### Short description (`readme.txt:11`)

- **Current:** “Fast WooCommerce COGS bulk editing with CSV/XLSX import, live progress, safe previews, history, and rollback for efficient product cost management.”
- **Assessment:** Clear feature inventory, but it is 147 characters and crowded with speed/efficiency language. It does not lead as directly with the task (“bulk edit product costs”) and makes the key benefit compete with six secondary features.
- **Opportunity:** Make the product and action explicit, then use two or three proof-oriented capabilities. Short description maximum is 150 characters; the handbook says longer copy is truncated.

### Opening description (`readme.txt:13–19`)

- **Strengths:** Correctly names WooCommerce’s native COGS feature, says the plugin does not change product prices, and mentions CSV/XLSX, progress, cancellation, history, and rollback.
- **Issues:** “The most powerful” and “Best plugin” are unsubstantiated comparative claims. They are not needed to match search intent and read as advertising. The second sentence can be more useful by stating what this plugin does beyond the core COGS CSV importer.
- **Opportunity:** Lead with the merchant task and cost/price distinction. In the next sentence, describe preview and background application; then explain spreadsheet support and conditional rollback. Note that original values must still be retained for rollback.

### Features and use cases (`readme.txt:27–197`)

- **Strengths:** Detailed feature coverage is unusually transparent: filters, set/fixed/percentage/clear actions, product and variation counts, stale preview behavior, CSV/XLSX requirements, cancellation, privacy, retention, and WP-CLI operation.
- **Issues:** The README contains deep operational and implementation notes before its core feature summary is complete, and several ideas recur across “Why use,” “How it works,” “Features,” FAQs, and changelog. Some details (such as PHP extensions, staging expiration, worker identity, and queue flags) are more appropriate for linked documentation than directory discovery copy.
- **Opportunity:** Preserve accurate behavior but prioritize a short feature list in user language. Move advanced CLI and environment troubleshooting into the existing documentation link. Avoid repeating exact phrases to pursue a density target.
- **Accuracy boundary:** The plugin edits WooCommerce product/variation COGS through WooCommerce product APIs. It does not implement profit analytics, supplier feeds, inventory valuation, or general product/pricing management.

### CSV/XLSX and core WooCommerce distinction (`readme.txt:78–83`, `132–145`)

- Current coverage is extensive and factually differentiating: import accepts UTF-8 CSV and XLSX, requires an ID or SKU plus `new_cogs` (legacy `cogs` accepted), validates before apply, and export produces CSV.
- [WooCommerce’s official documentation](https://woocommerce.com/document/woocommerce-cost-of-goods-sold-cogs/) says its core product CSV importer/exporter supports the native COGS field. Copy should acknowledge this and explain the plugin’s focused alternative workflow instead of implying core import lacks COGS support.
- Keep the critical input/output distinction: CSV and XLSX imports; CSV export. Do not say XLSX export.

### FAQ (`readme.txt:207–276`)

- **Existing coverage:** Bulk updates, native COGS requirements, percentage changes, preview, operation history, rollback and retention, variations, large catalogs, price preservation, and external-service/privacy questions.
- **Useful addition or consolidation:** Add one short FAQ stating that WooCommerce core can import/export COGS through its standard product CSV tool, then explain when this plugin is useful: filtered cost-only updates with preview/background batches, XLSX input, operation history, and rollback while originals are retained.
- **Useful clarification:** Add a compact file-format FAQ only if existing inline instructions are shortened: `id` or `sku` plus `new_cogs` (legacy `cogs` accepted); XLSX first worksheet; CSV export only. Avoid duplicating the full import documentation in the FAQ.
- **Do not add unsupported FAQs** about supplier synchronization, product profit reports, all-purpose product imports, automatic cost updates, or guaranteed processing times.

### Screenshots (`readme.txt:278–286`)

The seven captions cover bulk editing, preview, CSV export, CSV/XLSX import controls, operation history, operation details, and retention/rollback settings. They align broadly with actual functionality.

- Screenshot 1 shows the main filter/action/preview workflow and is the best candidate for the first visual. Keep it legible at directory display size; consider a clearer crop if the long page makes the important actions hard to read.
- Screenshot 2 reinforces exact current/proposed values and selection. It supports the “review before apply” proposition.
- Screenshot 3 demonstrates filtered CSV export but is a secondary capability; do not let it lead the gallery over the main COGS workflow.
- Screenshot 4 shows import controls but not a populated, validated preview. A future genuine screenshot of validation/preview would better communicate the differentiator. Do not write a caption that says validation results are visible in the current image.
- Screenshot 5 shows searchable operation history and statuses.
- Screenshot 6 explains that the shown historical record has no original values and cannot be rolled back. This is accurate, but it undercuts the rollback benefit visually. If available, replace it with a genuine test operation that has retained originals and show the rollback review; never fabricate data or conceal a limitation.
- Screenshot 7 shows retention and overwrite/skip-changed settings. This supports an important rollback limitation and can remain later in the gallery.

Suggested captions appear in Proposed Copy below. Captions describe only what is visible; any replacement image is a separate future task.

### Upgrade notice and changelog (`readme.txt:288–372`)

- The 1.2.0 Upgrade Notice identifies import/export, history, rollback, and automatic database updates. This is useful only for users upgrading to that release; keep it concise and verify it matches the actual release contents.
- The local changelog begins with `1.2.0 (2026-10-24)`, two days after this audit date. Treat this as a **pre-publication release-date check**, not a request to change version numbers or alter historical dates. Publish only when the date is accurate for the release.
- The complete file is 35,688 bytes. The [Plugin Readmes handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) cautions that readmes above 10 KB may cause errors, recommends a brief, direct readme, and recommends keeping the current release in the readme while moving older changelog data to `changelog.txt`. This is the clearest structural risk found and the highest-value readability task.
- Changelog entries contain many internal implementation details. Keep user-visible fixes and features, but move older/redundant detail to the recommended separate changelog file as a future documentation change.

### Plugin header and implementation

- Main plugin metadata at `bulk-cogs-editor-for-woocommerce.php:3–18` says `Version: 1.2.0`, requires WordPress 6.7, PHP 7.4, WooCommerce 10.3, and declares WooCommerce as a plugin dependency; it reports WooCommerce tested through 11.2. The readme states `Stable tag: 1.2.0`, `Requires at least: 6.7`, `Requires PHP: 7.4`, and `Tested up to: 7.1`.
- The plugin header `Description` (“Allow bulk Cost of Goods Editing for products in WooCommerce.”) is generic. WordPress.org’s [plugin readme documentation](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) says the readme supplies the public directory description; the header description is metadata, not a substitute for that copy. A future metadata-copy improvement can make it more readable, but no header was modified here.
- Code inspection supports the core claims: product COGS is read/written through WooCommerce product methods; Action Scheduler enqueues asynchronous update/import work; CSV and XLSX parsing/validation is implemented; history/rollback has explicit review, retention, and eligibility checks. Do not claim that every historical operation is reversible: summary-only or expired operations without retained original values are not.
- No Donate link is present. It is optional in the handbook and is not an SEO prerequisite; none is recommended for now.

## WordPress.org Directory SEO

| Element | Developer influence | Evidence / relevance | Recommendation |
|---|---|---|---|
| Plugin display name and permalink | The approved permalink/slug is fixed; a display-name change is a branding decision, not a keyword workaround. The current name has five words. | Official FAQ says neither URL nor display name is weighted above the other and recommends a memorable, descriptive display name under five words. | Keep the established identity; do not pursue a rebrand/slug change for SEO. The under-five guidance is not evidence that changing this established name would improve rankings. |
| Short description and long description | Editable through the stable-tagged `readme.txt`. | The [handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) says the readme controls the public directory content, short description is capped at 150 characters, and the description should explain what the plugin does rather than read like a sales pitch. | Lead with native WooCommerce product cost/COGS and concise real workflow differentiators. |
| Tags | Developer controls up to five terms; directory controls whether terms are widely used/displayed. | Official handbook and FAQ document five-tag limit, relevance, and unique-tag display limits; `low_usage_tags` appears in Meta Trac source. | Preserve the five existing tags exactly. Do not stuff the body to try to clear a usage note. |
| Screenshots, icon, banner | Developer controls plugin assets and captions. | These are directly visible on the listing and can clarify UI and use cases; this audit found current screenshot evidence for core features. | Use a clear main workflow first, then preview/import/history. Replace the summary-only rollback example only with an authentic eligible operation. |
| Installation, FAQ, changelog, upgrade notice | Developer controls copy in `readme.txt`. | Handbook says install directions should be direct, FAQ should answer real issues, and changelog should be brief/current. | Reduce overlong technical detail; answer core import distinction and rollback eligibility. |
| Support and reviews | Developer can provide timely, useful forum support; reviews must be genuine and voluntary. | The [official FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/) qualitatively recommends a good readme, promptly answered support, and good reviews. It gives no numerical weights. | Answer actual support questions, invite feedback neutrally where appropriate, never solicit fake reviews or use sockpuppeting. |
| Compatibility and release metadata | Developer reports tested/required versions and release information; they must be accurate. | Directory displays compatibility/version information and uses stable-tagged readme data. Search data may take time to refresh after SVN commit. | Keep requirements and `Tested up to` truthful. Align main-file version, trunk readme, stable tag, and tagged release copy. |
| Directory search and caching | WordPress.org controls indexing/caching. | The [official FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/) says changes usually appear in search 6–14 days after SVN commit; it does not reveal ranking weights. | Allow time for propagation and compare post-update data; do not expect immediate search changes. |
| Google search discovery | The developer can publish useful public documentation and measure owned-site performance. | This audit did not have Search Console or keyword-volume data. Directory SEO and Google ranking are separate measurement questions. | Publish a useful workflow guide only if it adds real help; use Search Console for the owned domain if configured. |

### Public listing synchronization observation

The local checkout has version/stable tag 1.2.0, while the [public listing](https://wordpress.org/plugins/bulk-cogs-editor-for-woocommerce/) snapshot surfaced version 1.1.1 and a tag set that differed from the local line. This may simply reflect the unreleased local candidate or directory cache. Before release, verify `trunk/readme.txt` and `tags/1.2.0/readme.txt` on WordPress.org SVN, ensure the current content and exact five required tags are present, and then allow the documented propagation delay. Do not alter the local tag set to match an older public snapshot.

## Promotional Language Assessment

| Term / claim | Assessment | Recommended treatment |
|---|---|---|
| “Best” / “top” | Subjective, comparative, and unsupported by comparative testing or user evidence in the README. It does not clarify the feature. | Remove from directory copy. State the concrete use case and workflow instead. |
| “Most powerful” | Unverifiable and broad; invites comparison with plugins that do more than COGS editing. | Remove. Describe what the plugin actually does. |
| “Fast” / “fastest” | A speed benefit can be relevant, but the available benchmark is one reported run and its completion screenshot measures editor operation elapsed time rather than independently measured end-to-end CLI runtime. | Avoid “fastest” and unqualified guarantees. If included, preserve the benchmark context and variables exactly. |
| “Easy,” “efficient,” “advanced” | Generic unless tied to a concrete behavior. | Prefer “preview before applying,” “background batches,” “CSV/XLSX import,” “operation history,” and “reviewed rollback.” |
| “Safe” | Could overpromise if left unqualified; previews reduce risk but do not guarantee every store outcome. | Use “review changes before applying” and explain cancellation/rollback limits accurately. |

The `readme.txt` performance section is comparatively responsible: it calls the result reported, distinguishes the editor-operation elapsed time from end-to-end CLI runtime, notes it is a single run, and lists environmental factors. The [linked benchmark discussion](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/discussions/12) is the developer’s report, not independent validation. Preserve those qualifications and all historical figures if the benchmark remains. Do not convert the result to “10,000 products in 15 minutes guaranteed” or “fastest WooCommerce COGS plugin.”

## Prioritized Recommendations

Confidence describes confidence in the content/policy rationale, not confidence that rankings will change. Expected benefits are directional; no ranking or install outcome is promised.

| Priority | Recommendation and exact location | Current → proposed direction | Target / intent | Why useful and expected benefit | Confidence | Risk |
|---|---|---|---|---|---|---|
| **P0** | Verify 1.2.0 release date and synchronized SVN readmes before publishing; `readme.txt:295`, metadata lines 3–7, stable-tagged SVN copy. | Local changelog date is 2026-10-24 versus audit date 2026-10-22; public snapshot appeared older. → Publish only when release/date are real and trunk/tag copy agrees. | Release accuracy, directory content freshness | Prevent inaccurate release metadata and a directory listing that does not reflect intended content. | High | Low if treated as a release gate; do not edit dates/version speculatively. |
| **P1** | Shorten and clarify the opening; `readme.txt:11–19`. | 147-character packed short description and “most powerful”/“Best plugin.” → Identify bulk product cost/COGS first; state preview/background and spreadsheet/history benefits factually. | `woocommerce cogs`, `cost of goods sold`, `bulk edit`, `product cost` | Better first-screen relevance and user comprehension; likely conversion benefit, but no ranking guarantee. | High | Low; preserve under 150 characters and actual feature set. |
| **P1** | Reduce readme size; `readme.txt:27–197`, `207–276`, `295–372`. | 35,688-byte file with deep implementation details and long old changelog. → Keep high-value overview and current release notes; move older changelog to `changelog.txt` and advanced operational detail to the existing docs page. | All five tag concepts; directory readability | Follows official guidance; reduces the risk of readme parsing errors and improves scanability. | High | Medium: do not remove important requirements, warnings, or rollback limitations. |
| **P1** | Explain the core CSV distinction early; `readme.txt:13–19` and FAQ near `readme.txt:212`. | Import feature is described without an early comparison to WooCommerce’s own COGS CSV support. → Acknowledge core importer and state the plugin’s focused filtered preview/background/XLSX/history/rollback workflow. | `csv import`, `product cost`, `woocommerce cogs` | Prevents misleading claims and answers “why use this if core imports COGS?” | High | Low if phrased precisely; do not imply core lacks COGS support. |
| **P1** | Replace subjective superlatives; `readme.txt:15–17`. | “Most powerful” and “Best plugin.” → Concrete capabilities and clear cost-versus-price wording. | Conversion/trust; all tag concepts | More credible, useful copy; avoids unsupported comparisons and repetitive promotional language. | High | Low. |
| **P2** | Condense feature list into user outcomes; `readme.txt:27–123`. | Several repeated concepts and internal details. → Group into filter/action, preview, batch processing, import/export, history/rollback, and price-preservation sections. | Bulk cost management, preview, CSV/XLSX | Lets readers scan the distinct product value quickly. | High | Medium if feature caveats are accidentally omitted. |
| **P2** | Add one core-import/format FAQ; `readme.txt:207–276`. | Current FAQs cover the plugin’s functions but do not directly answer how its CSV workflow relates to core. → Add the proposed concise FAQ below. | CSV cost import, XLSX, COGS | Addresses a likely evaluation question based on official WooCommerce docs. | High | Low; avoid duplicating long inline instructions. |
| **P2** | Improve screenshot evidence; `readme.txt:278–286`, screenshot 4 and 6. | Import screenshot shows controls without populated validation; history detail shows no retained originals. → Capture authentic import preview and a genuine rollback-eligible review if test data permits. | Import conversion, rollback trust | Demonstrates differentiators visually and makes capabilities easier to evaluate. | Medium-high | Medium; screenshots must remain authentic and avoid implying universal rollback. |
| **P2** | Create/update an educational guide on owned documentation; link at `readme.txt:19` or existing documentation location. | Current docs/video links are present, but one self-contained COGS bulk update guide could answer core-import, SKU/ID, XLSX, preview, and rollback questions. → Publish only if materially useful and maintained. | Long-tail WooCommerce COGS/product cost queries | Can attract and assist users outside the directory; measurable through Search Console if configured. | Medium | Medium ongoing maintenance; no guaranteed organic traffic. |
| **P3** | Improve the main-file `Description` metadata; `bulk-cogs-editor-for-woocommerce.php:5`. | Generic “Allow bulk Cost of Goods Editing…” → Clear sentence aligned with the directory copy. | COGS bulk editing | Improves metadata clarity in contexts that display the plugin header; secondary to stable readme content. | Medium | Low; do not mistake it for the public directory long description. |
| **P3** | Keep periodic support/compatibility maintenance visible. | Respond to actual forum questions and keep tested/required versions accurate. | User trust and directory conversion | Official FAQ qualitatively recommends prompt support and good reviews; maintenance helps user confidence. | High for trust; unknown for ranking effect | Low; never solicit or manufacture reviews. |

## Proposed Copy (Not Applied)

These are ready-to-review copy candidates only. They have not been applied to `readme.txt`.

### Short description

```text
Bulk edit WooCommerce product costs (COGS) with CSV/XLSX import, previews, background updates, operation history, and rollback.
```

This is under 150 characters and leads with the actual job. “Rollback” remains qualified in the long description and FAQ by retention and eligibility.

### Opening description

```text
Bulk COGS Editor helps you bulk edit product and variation costs in WooCommerce. Filter the products you want, set or adjust their native Cost of Goods Sold (COGS) value, and review the proposed changes before applying them. Updates run in background batches with progress and cancellation. Product prices are not changed.

Import product costs from CSV or XLSX, or export matching cost data to CSV. Review completed operations in History and review a rollback when the original values are still retained and eligible.

WooCommerce already supports COGS in its standard product CSV importer and exporter. This plugin provides a focused workflow for filtered cost updates, previews, background processing, XLSX input, operation history, and eligible rollback.
```

### Feature descriptions

```text
* Filter products and variations by product details, category, brand, stock status, price, or current COGS.
* Set a cost, increase or decrease it by a fixed amount or percentage, or clear it.
* Preview current and proposed costs, select products, and confirm before applying changes.
* Process updates in Action Scheduler background batches with progress and cancellation.
* Import cost updates from CSV or XLSX using a product ID or SKU; export matching costs to CSV.
* Review operation outcomes and available original values in History; rollback is limited by retention and eligibility.
* Change WooCommerce COGS only; product prices are not modified.
```

These descriptions intentionally omit internal persistence/worker implementation detail and the “best/fastest” claims. Keep existing specific caveats in linked docs or relevant FAQs.

### FAQ addition

```text
Does WooCommerce already support importing COGS?

Yes. WooCommerce’s standard product CSV importer and exporter support the native COGS field. Bulk COGS Editor is an alternative focused on filtering products, reviewing cost changes before applying them, processing updates in background batches, importing XLSX files, and keeping operation history with eligible rollback. It does not replace the full WooCommerce product importer.
```

Optional concise format FAQ, if the existing inline import instructions are shortened:

```text
What columns does a cost import file need?

Provide `id` or `sku` and `new_cogs`. Legacy CSV files may use `cogs` when `new_cogs` is absent. XLSX import reads the first worksheet. The plugin exports CSV files; it does not export XLSX.
```

### Screenshot captions

1. **Bulk editor:** Filter products and preview COGS actions before confirming an update.
2. **Product preview:** Compare current and proposed costs and select the products to update.
3. **CSV export:** Choose product cost columns and filters for a CSV export.
4. **CSV/XLSX import:** Upload a cost file and review validated rows before applying changes. Use only after replacing the current controls-only screenshot with a real preview image.
5. **Operation history:** Find past bulk edits, imports, exports, and rollbacks by status and other filters.
6. **Operation details:** Review recorded outcomes and retained original costs. Prefer a genuine rollback-eligible test operation image over the current summary-only record, if available.
7. **Settings:** Choose history retention and the behavior for products changed after an update.

Do not alter or create screenshot images as part of this research-only phase.

## Measurement Plan

Record a baseline before publishing any approved copy/assets, then annotate the date, SVN revision/release, and any other changes. Use 30-, 60-, and 90-day comparisons as directional checkpoints—not experiments that prove ranking causality.

| Checkpoint | Actions and metrics | Available data / limitations |
|---|---|---|
| **30 days** | Verify the live WordPress.org listing shows the intended 1.2.0 readme, exact five tags, screenshots, and compatibility metadata. Record listing version, active-install bucket, downloads if available to the plugin author, support topics, and genuine reviews. In Google Search Console (if configured), capture impressions, clicks, CTR, queries, and landing pages for the developer-owned documentation site. | The public listing exposes only coarse active-install ranges and visible reviews/support; it does not provide directory search impressions or query-level click data to the public. Search Console covers only the owned/verified site, not WordPress.org’s internal search results. WordPress.org may take 6–14 days after SVN commit to reflect search changes. |
| **60 days** | Compare query themes (COGS, product cost, bulk update, spreadsheet import) and page engagement on owned documentation. Track installation/download trend, new support questions, unresolved/resolved support topics, and whether users understand the difference between cost and price. Note any changed compatibility/plugin release during the period. | If available in the author account, WordPress.org download statistics are helpful but do not reveal the search phrase or listing impression that preceded an install. Do not claim a listing conversion rate without impression and install attribution data. |
| **90 days** | Compare rolling 30/60/90-day directional trends with the baseline. Keep a query log from Search Console and support topics; record installs/downloads, reviews, support response status, and top user questions. Decide whether the copy or docs need another change based on observed confusion and relevant search impressions—not a desire to repeat more keywords. | Small install counts and external algorithm/caching changes make short-term percentages noisy. Separate listing changes from release, support, screenshot, and compatibility changes where possible. No single observed change proves the cause of a ranking/install change. |

### Measurement definitions

- **Search impressions/clicks:** Google Search Console data for owned documentation, if verified. These are not WordPress.org plugin-directory search metrics.
- **WordPress.org listing visits:** No author-accessible visit count was verified in this audit. Do not infer visits from downloads or active-install buckets.
- **Install/download change:** Track developer-visible WordPress.org statistics if available and public active-install ranges, with dates. Ranges are coarse and not exact install counts.
- **Conversion indicators:** Without listing impressions and attributable installs, use indirect indicators only: downloads/installs where available, support questions, voluntary reviews, documentation click-through if analytics are already configured, and successful onboarding feedback.
- **Query relevance:** Search Console query terms for owned pages and the language users use in support posts. WordPress.org does not provide public query-level listing search data in the sources reviewed.
- **Privacy:** Do not add tracking or telemetry to the plugin as an SEO measurement workaround. Use only appropriately disclosed, consent/privacy-compliant analytics on developer-owned pages.

## Final Tag Confirmation

The required five tags remain exactly:

```text
woocommerce cogs
cost of goods sold
bulk edit
product cost
csv import
```

No tag changes are recommended. The validator’s low-usage note should be treated as a directory-wide tag adoption notice; content can improve relevance and comprehension but cannot make other plugins adopt these terms.

## Sources and Further Reading

- [Plugin Readmes – WordPress Plugin Handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Plugin Developer FAQ – WordPress Plugin Handbook](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress.org Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Meta Trac changeset 13443: `low_usage_tags` validator warning](https://meta.trac.wordpress.org/changeset/13443)
- [Readme tag popularity and SVN behavior walkthrough](https://www.lukaswojcik.com/blog/en/wordpress-plugins-tricks/readme-header-svn/) (secondary source; its numeric threshold is not confirmed in the official handbook)
- [WooCommerce Cost of Goods Sold documentation](https://woocommerce.com/document/woocommerce-cost-of-goods-sold-cogs/)
- [WPFactory Cost of Goods plugin listing](https://wordpress.org/plugins/cost-of-goods-for-woocommerce/)
- [Ni Cost of Goods plugin listing](https://wordpress.org/plugins/ni-woocommerce-cost-of-goods/)
- [Flavors Commerce Intelligence plugin listing](https://wordpress.org/plugins/flavors-commerce-intelligence/)
- [Merchant support discussion: bulk update COGS for thousands of products](https://wordpress.org/support/topic/bulk-update-the-new-cost-cogs-pricing/)
- [Bulk COGS Editor public listing](https://wordpress.org/plugins/bulk-cogs-editor-for-woocommerce/)
- [Bulk COGS Editor GitHub repository](https://github.com/dineshinau/bulk-cogs-editor-for-woocommerce/)
