# PHP 8.1 compatibility review

Date: 24 October 2026. Reviewed plugin: Bulk COGS Editor for WooCommerce 1.2.1, commit `0af996b`, initially clean working tree.

## Assessment

No PHP 8.1 fatal incompatibility was identified in the reviewed plugin code. There is one confirmed source-level deprecation issue in XLSX metadata parsing, affecting three attribute-handling paths. It is triggered by incomplete workbook metadata rather than normal bulk COGS editing. There is also a significant limitation in the installed compatibility checker.

This is a source review with static checks, not a PHP 8.1 runtime certification. PHP 8.1 is not installed in the review environment. No product code, compatibility declarations, dependencies, or store data were changed. No tests or product update operations were run.

WooCommerce's [29 September announcement](https://developer.woocommerce.com/2026/09/29/php-8-1-requirement/) confirms that WooCommerce 11.6, planned for February 2027, will require PHP 8.1+. WooCommerce 11.5 remains available to stores on PHP 7.4 or 8.0. The announcement recommends PHP 8.3+ for hosting upgrades. Raising WooCommerce's minimum does not itself make PHP 7.4-compatible plugin syntax invalid on PHP 8.1.

## Scope and current requirements

The review inventoried all 23 project PHP files outside dependencies and shared agent material. Static syntax checks included the generated asset manifest and the existing browser fixture file without executing them. Manual review focused on bootstrap and hook registration, class declarations, request validation, COGS arithmetic, CSV/XLSX parsing, worker execution, persistence, history, rollback, downloads, and retention.

| Declaration | Current value | Location |
| --- | --- | --- |
| Plugin version | 1.2.1 | `bulk-cogs-editor-for-woocommerce.php:6` |
| Minimum PHP | 7.4 | `bulk-cogs-editor-for-woocommerce.php:15`, `readme.txt:6` |
| Minimum WordPress | 6.7 | `bulk-cogs-editor-for-woocommerce.php:14` |
| Minimum WooCommerce | 10.3 | `bulk-cogs-editor-for-woocommerce.php:16` |
| WooCommerce tested up to | 11.2 | `bulk-cogs-editor-for-woocommerce.php:17` |
| PHPCS compatibility range | `7.4-` | `phpcs.xml:20` |

The existing `7.4-` range is appropriate while retaining PHP 7.4 support. It does not impose an upper PHP limit. The checker must know about newer PHP changes for that range to provide useful forward-compatibility coverage.

## Findings

### F1 — Medium: nullable XML attributes reach non-nullable string parameters

Location: `DKBCE_Import_Reader::prepare_xlsx()`, in `admin/class-dkbce-import-reader.php`.

`XMLReader::getAttribute()` returns `null` when an attribute is absent. The parser passes some of these values directly to internal string functions. Passing `null` to a non-nullable scalar parameter of an internal function is deprecated starting in PHP 8.1. See the [XMLReader contract](https://www.php.net/manual/en/xmlreader.getattribute.php) and [PHP 8.1 deprecations](https://www.php.net/manual/en/migration81.deprecated.php).

| Path | Source evidence | Trigger |
| --- | --- | --- |
| Relationship type | `getAttribute( 'Type' )` at line 115; `substr( $type, ... )` at lines 117, 128, 130 | A relationship element omits `Type`. Depending on its ID, the first affected call is line 117 or line 128. |
| Relationship target | `getAttribute( 'Target' )` at line 123; `substr( $target, 0, 1 )` at line 124 | A selected worksheet or shared-string relationship omits `Target`. |
| Worksheet dimension | `getAttribute( 'ref' )` at line 147; `preg_match( ..., $dimension, ... )` at line 148 | A worksheet contains a `dimension` element without `ref`. A worksheet with no `dimension` element does not enter this branch. |

These are three instances of the same input-contract issue. The affected functions remain supported; replacing `substr()` or `preg_match()` is unnecessary.

**Impact:** Under ordinary PHP 8.1 error handling, these calls emit deprecation notices and coerce `null` to an empty string. A deprecation alone does not stop execution. Displayed diagnostics can contaminate a response if this path runs in an HTTP request. A site error handler that converts deprecations to exceptions can cause the import preview to fail; `DKBCE_Import_Processor::process()` catches `Throwable` and records a failed preview at lines 138–140. That exception behavior depends on the site's error handler and was not reproduced here.

Well-formed XLSX files supply these attributes, so this finding does not establish a failure in ordinary XLSX imports, CSV imports, COGS calculations, or product updates.

**Replacement direction for later planning:** Validate required relationship attributes before string operations and reject incomplete relationships with the existing import error mechanism. Treat absent optional dimension metadata as unavailable before matching it. Prefer deliberate validation over silently casting every missing attribute to a string. Both approaches can remain compatible with PHP 7.4; a minimum-version change is not needed to fix this issue.

**Evidence level:** Confirmed by source data flow and PHP's documented contracts. No malformed-workbook reproduction was run.

### F2 — Medium: installed compatibility checker cannot establish PHP 8.1 readiness

Location: the environment's Composer installation, `/home/dinesh/.config/composer/composer.lock`, package `phpcompatibility/php-compatibility`; project integration at `phpcs.xml:20` and its `PHPCompatibility` rule.

The installed PHPCompatibility version is **9.3.5**, with a package timestamp of 27 December 2019. It predates PHP 8.0 and PHP 8.1. PHP_CodeSniffer itself is version **3.13.6**. Passing `testVersion=8.1` selects a target for the checks the installed standard knows; it does not add missing migration rules. The [upstream 9.3.5 release](https://github.com/PHPCompatibility/PHPCompatibility/releases/tag/9.3.5) identifies the installed release.

**Impact:** Both compatibility scans returned zero findings, but their clean results cannot rule out PHP 8.1 deprecations or behavioral changes. F1 illustrates a gap that manual review must cover. Even a newer static checker will not prove all input-dependent behavior.

**Direction for later planning:** Use a maintained PHPCompatibility release that explicitly covers the intended PHP versions, in an isolated or project-managed tool installation. Keep the project's existing rules and PHP 7.4 coverage until support policy changes. Do not update global tools as part of this report.

**Evidence level:** Confirmed installed package metadata and inspection of the installed standard. This is a tooling limitation, not a plugin runtime defect.

## Older constructs that do not require replacement

The review found no use of removed legacy APIs such as `each()`, `create_function()`, `__autoload()`, or `mysql_*()`. It found no calls to PHP 8.1-deprecated `strftime()`, `gmstrftime()`, `strptime()`, `mhash*()`, or `FILTER_SANITIZE_STRING` / `FILTER_SANITIZE_STRIPPED`. These searches covered project PHP, excluding dependencies and shared agent files.

| Area checked | Assessment |
| --- | --- |
| `array()` syntax, untyped properties and parameters, PHPDoc annotations | Supported in PHP 8.1. Converting them is optional modernization. |
| Autoloading | Uses `spl_autoload_register()` and a declared static loader in `admin/class-dkbce-autoloader.php:19`. No legacy `__autoload()` function. |
| Constructors and signatures | Uses `__construct()` where needed. No required parameter following an optional parameter was found. |
| Internal class inheritance and interfaces | No project class extends an internal PHP class or implements `Serializable` or internal collection interfaces. PHP 8.1's tentative return-type changes have no identified project target here. |
| `ctype_digit()` | XLSX input is initialized as a string and filled from DOM text at `admin/class-dkbce-import-reader.php:322–336`. AJAX count inputs are sanitized strings at `admin/class-dkbce-admin-functions.php:1510` and `:1866`. No non-string call was identified in these paths. |
| CSV parsing | `SplFileObject::fgetcsv()` and `str_getcsv()` explicitly receive an empty escape argument at `admin/class-dkbce-import-reader.php:40` and `:53`. They do not rely on the default escape value deprecated in PHP 8.4. |
| CSV column combination | `admin/class-dkbce-csv-service.php:55–60` checks matching field counts before `array_combine()`, avoiding PHP 8's unequal-count `ValueError` in this path. |
| COGS arithmetic and clearing | Decimal validation and explicit casts are present. `null` remains the intentional unset COGS value and is passed to WooCommerce CRUD, not automatically to a PHP string function. See `admin/class-dkbce-cogs-service.php:197–218` and `admin/class-dkbce-bulk-processor.php:880–890`. |
| PHP 8.2 dynamic properties | Direct writes to properties of plugin instances matched declared properties. No replacement target was identified. Dynamic-property deprecation starts in PHP 8.2, not 8.1. |

References: [PHP 8.0 incompatible changes](https://www.php.net/manual/en/migration80.incompatible.php), [PHP 8.1 incompatible changes](https://www.php.net/manual/en/migration81.incompatible.php), [PHP 8.2 deprecations](https://www.php.net/manual/en/migration82.deprecated.php), and [PHP 8.4 deprecations](https://www.php.net/manual/en/migration84.deprecated.php). These observations are bounded source findings, not certification of every supported PHP release.

## Checks performed

| Check | Result | Limit |
| --- | --- | --- |
| Full project PHPCS ruleset with `testVersion=8.1`, one worker | 22 files; zero errors and warnings | Uses the old compatibility standard described in F2; excludes generated assets by project rules. |
| PHPCompatibility-only scan with `testVersion=8.1` | 23 files; zero errors and warnings | Includes the generated asset manifest; same version limitation. |
| `php8.3 -d error_reporting=32767 -l` on all 23 files | All passed; no declaration deprecations reported | Syntax and declarations only; no code paths executed. |
| `php -d error_reporting=32767 -l` on all 23 files | All passed; default CLI is PHP 8.4.27 | Syntax and declarations only; no code paths executed. |
| PHP 8.1 runtime, browser flows, worker operations | Not run | PHP 8.1 unavailable; tests and store operations were outside this review. |

Successful PHPCS commands:

```bash
phpcs --standard=phpcs.xml --runtime-set testVersion 8.1 --parallel=1 --report=json --report-file=/tmp/dkbce-php81-project-phpcs.json .
phpcs --standard=PHPCompatibility --runtime-set testVersion 8.1 --parallel=1 --extensions=php --ignore='*/node_modules/*,*/vendor/*,*/.agents/*,*/.git/*,*/graphify-out/*' --report=full --report-json=/tmp/dkbce-php81-compatibility.json .
```

The report files above are temporary local evidence. Syntax checks enumerated project `*.php` files while excluding `node_modules`, `vendor`, `.agents`, `.git`, and `graphify-out`, then invoked each PHP binary separately for each file.

## Boundaries for the next planning discussion

The identified code correction belongs in XLSX attribute validation. A broad rewrite to newer PHP syntax is not supported by these findings. The compatibility-tool update and runtime evidence should be separate work items.

Before declaring PHP 8.1 compatibility verified, future authorized validation should cover previews, standard updates, retry/cancellation, CSV and XLSX import/export, history, rollback, and cleanup on a disposable PHP 8.1 environment with deprecations recorded. Include incomplete XLSX metadata as well as valid files. Add a PHP 8.3+ environment for the hosting target WooCommerce recommends. None of that validation has been performed by this review.

Changing the plugin's PHP minimum is a support-policy and release decision. Keeping PHP 7.4 compatibility can remain useful for stores on supported older WooCommerce versions. Any later minimum-version change must align declarations, documentation, release notes, and quality-tool configuration.

XLSX also requires ZIP, XMLReader, and DOM extensions. The plugin already checks them at `admin/class-dkbce-import-reader.php:79`. Recheck extension availability when changing a server's PHP installation; a missing extension is an environment issue rather than deprecated plugin syntax. WordPress, WooCommerce, Action Scheduler, other plugins, and themes were not audited here. The plugin's guarded use of WooCommerce's internal `ProductTransientsDeferrer` at `admin/class-dkbce-bulk-processor.php:465` is a separate WooCommerce API compatibility concern, not an identified PHP 8.1 deprecation.
