# Changelog

This changelog covers **only the two most recent published GitHub releases** of `local_contentfreshness`. Each entry describes verified Git differences between its published tag and the immediately preceding **published release** tag. Commits added to `master` after `1.0.6` are not included.

## [1.0.6] - 2026-10-19

Published: 2026-10-19 14:56:30 UTC.

Comparison: [1.0.5...1.0.6](https://github.com/EduardoKrausME/moodle-local_contentfreshness/compare/1.0.5...1.0.6)

### Added: optional Moodle MCP tools

- Added an optional provider in `classes/mcp/provider.php` that registers **three** tools when `local_mcp` discovers the plugin. The integration does not add a required `local_mcp` dependency in `version.php` or require a separate MCP server.
- Added two read tools:
  - `contentfreshness_list_sources`: list teacher-authored course content sources, cache validity metadata and paginated results without exposing raw source HTML.
  - `contentfreshness_get_report`: retrieve deterministic freshness findings and still-valid cached AI/link results, with source, section and severity filters and pagination.
- Added the confirmed write tool `contentfreshness_run_audit`: refresh a course audit through the existing audit service after a side-effect-free preview. Execution may perform protected external link checks, use `local_ai_bridge` for AI review and update plugin cache, but does not change course activity content.
- Enforced `local/contentfreshness:audit` in the requested course context using the authenticated Moodle user. Read and write tools also use their respective MCP permissions and confirmation flow.
- Added bounded results (up to 50 detail rows per response), input validation and filtering by `sourcekey`, `sectionnum` and `severity` (`low`, `medium` or `high`).

### Changed: read-only audit isolation

- Added a backwards-compatible third argument, `$storelocal = true`, to `audit_service::audit()`. MCP report reads pass `false` to avoid writing deterministic results to cache; existing callers retain the previous default behavior.
- Implemented the read tools without network requests, AI calls, or mutations to course content or plugin cache. Confirmed refresh operations remain separately exposed as MCP writes.
- Added `tests/mcp_tools_test.php` with coverage for read-only cache behavior, authorization, filters/pagination and MCP tool operations.

### Added: administrator integration notice

- Added a dismissible MCP integration notice on the Content Freshness report page, visible only to site administrators when `local_mcp` is not installed and the user has not dismissed the notice.
- Added a persistent per-user dismissal preference protected by an authenticated POST with Moodle sesskey.
- Added the associated Mustache template, responsive and dark-mode styles, and English/Brazilian Portuguese strings for the notice and tool descriptions.

### Documentation, release automation and metadata

- Expanded `README.md` with the MCP tool list, authorization model, read-versus-write effects, pagination, configurable audit limits and the warning that freshness findings require human review.
- Added concurrency settings to `.github/workflows/release.yml` using `group: release-${{ github.ref }}`, `cancel-in-progress: false` and `queue: max`.
- Updated `version.php` from plugin version `2026101500` / release `1.0.5` to `2026101900` / release `1.0.6`. The required Moodle build (`2024100700`) and `local_ai_bridge` dependency version (`2026093001`) were unchanged.

### Scope

The published-tag comparison contains eight commits. It adds optional MCP integration classes, the administrative notice and tests, and updates the existing audit service, report page, translations, styles, README, release workflow and version metadata. These are code changes in the published release; this changelog does not imply successful runtime execution or installation testing.

## [1.0.5] - 2026-10-15

Published: 2026-10-15 20:27:56 UTC.

Comparison: [1.0.4...1.0.5](https://github.com/EduardoKrausME/moodle-local_contentfreshness/compare/1.0.4...1.0.5)

### Changed: release metadata

- Updated `version.php` from `$plugin->version = 2026101400` and `$plugin->release = '1.0.4'` to `2026101500` and `1.0.5`, respectively.

### Scope

The published-tag comparison contains only commit `b7964c2b555bb18857e18989b6c8d895eda88299` and modifies **only `version.php`**. The minimum Moodle build (`2024100700`) and `local_ai_bridge` dependency version (`2026093001`) remained unchanged. No runtime source files, features, fixes or workflows changed between these tags.

---

These notes are based solely on published Git tag diffs; they exclude unreleased commits and do not claim that tests or deployments were executed while writing this file.
