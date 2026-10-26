# Moodle Content Freshness (`local_contentfreshness`)

`local_contentfreshness` is a Moodle local plugin that helps teachers find course content that deserves review
because it may be time-sensitive. It deliberately does **not** declare material false or outdated and it never edits
course content automatically.

## What is inspected

the plugin inspects teacher-authored text from:

- Page;
- Book descriptions and Book chapters;
- course section summaries;
- Text and media areas (`mod_label`);
- Assignment description/activity instructions;
- Forum descriptions;
- Quiz descriptions.

Student submissions, student answers and forum posts are intentionally not inspected.

## Deterministic analysis first

Before any AI call the plugin locally detects candidate snippets containing:

- older year references;
- explicit dates;
- temporal wording such as “currently”, “today”, “atualmente” and “hoje”;
- software/platform version references;
- absolute external HTTP/HTTPS links.

Only semantic candidates are sent to the AI bridge, and only as bounded snippets with local identifiers. The AI never
receives every course text by default.

The allowed semantic classifications are:

- `likely_time_sensitive`;
- `possibly_outdated`;
- `evergreen`;
- `needs_human_review`.

The prompt explicitly tells the model that it has no browsing and must not claim external factual freshness without
evidence in the supplied snippet.

## Safe external link checks

External links are checked only during a manual audit. The checker:

1. considers only absolute HTTP/HTTPS URLs whose host differs from the Moodle site;
2. rejects credentials, non-standard ports, localhost/internal suffixes and literal IP addresses before networking;
3. performs a HEAD request through Moodle's `curl` wrapper;
4. never sets `ignoresecurity`, so Moodle's curl security helper validates resolved addresses and redirects;
5. limits request count and timeout through plugin settings.

A failed or blocked request is reported as “could not be verified”, not as proof that the referenced content is
outdated.

## Cache

Each source has a stable `sourcekey` and a SHA-256 hash of its current text. Deterministic candidates and semantic AI
output are reused only while the hash matches. Editing one activity invalidates that source only.

Link results are stored separately from semantic output because a URL can change while the Moodle text remains
byte-for-byte identical. Running the audit again refreshes the network checks.

## Capability

The report requires:

```
local/contentfreshness:audit
```

It is granted by default to editing teachers and managers at course context.

## Report

The course navigation link opens a report containing:

- item and section;
- candidate snippet;
- reason;
- risk type;
- severity;
- semantic classification;
- Moodle last-modified date;
- edit link.

Filters are available for section, content age, source type and severity.

## Privacy

The plugin does not store user-specific data. Its cache contains only analysis related to teacher-authored course
content. Student submissions and private learner content are out of scope.

## Optional MCP integration (ChatGPT)

When [local_mcp](https://eduardokrausme.github.io/moodle-local_mcp/) is installed,
it automatically discovers `local_contentfreshness\mcp\provider` in
`classes/mcp/provider.php`. No `lib.php` callback, hard dependency, separate server
or manual tool registration is required; without `local_mcp` this plugin works normally.

| Tool | Scope | Purpose |
| --- | --- | --- |
| `contentfreshness_list_sources` | READ | List the teacher-authored content sources in a course, with current-hash cache status and pagination. |
| `contentfreshness_get_report` | READ | Inspect deterministic findings and still-valid cached AI/link results with pagination and optional source, section and severity filters. |
| `contentfreshness_run_audit` | WRITE | Perform a confirmed refresh using the existing `audit_service`, including configured AI review, safe HEAD checks and cache updates. |

Both READ tools require the existing `local/contentfreshness:audit` Moodle capability
in the course context and the MCP READ scope. They never send HTTP requests, consume AI
credits, write cache rows or modify course content. To enforce this, `audit_service::audit()`
accepts an optional third argument `$storelocal`; `get_report` passes `false` while the
existing Moodle interface retains its original cache-writing behavior.

The WRITE tool also requires `local/contentfreshness:audit` and an enabled MCP WRITE
scope. It presents a side-effect-free preview before the MCP confirmation token is
issued. A confirmed refresh may check external links, send bounded snippets through
`local_ai_bridge` and save cache results; it never changes course activities.
Settings `maxurlchecks`, `linktimeout` and `maxaicandidates` remain authoritative.

Filtering options: `sourcekey`, `sectionnum` (Moodle course section number), and
`severity` (`low`, `medium`, `high`). Each response includes counts and no more
than 50 detail rows; use `offset` and `limit` to retrieve additional pages.
Items are observations requiring human review, **not** authoritative claims
that the content is outdated. Update the Moodle plugin and refresh the MCP
connector to discover the tools.
