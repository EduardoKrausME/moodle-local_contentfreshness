<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local_contentfreshness.php
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


$string['action'] = 'Action';
$string['age'] = 'Content age';
$string['aifailed'] = 'AI review could not be completed: {$a}';
$string['allage'] = 'Any age';
$string['allsections'] = 'All sections';
$string['allseverities'] = 'All severities';
$string['alltypes'] = 'All types';
$string['assigntype'] = 'Assignment instructions';
$string['auditcomplete'] = 'Audit completed. Unchanged text reused cached AI analysis.';
$string['bookchaptertype'] = 'Book chapter';
$string['booktype'] = 'Book';
$string['cached'] = 'Cached';
$string['candidatecap'] = 'The audit found more semantic candidates than the configured limit. {$a} candidate(s) were left for human review without an AI call.';
$string['classification'] = 'Classification';
$string['contentfreshness:audit'] = 'Audit course content freshness';
$string['edit'] = 'Edit';
$string['evergreen'] = 'Evergreen';
$string['explicit_date'] = 'Explicit date';
$string['external_link'] = 'External link';
$string['filter'] = 'Apply filters';
$string['forumtype'] = 'Forum description';
$string['high'] = 'High';
$string['intro'] = 'Find course text that deserves human review because it contains temporal references, software versions, dates or external links. The report does not automatically declare content false or outdated.';
$string['invalidairesponse'] = 'The AI bridge returned a response that could not be parsed safely.';
$string['item'] = 'Item';
$string['labeltype'] = 'Text and media area';
$string['likely_time_sensitive'] = 'Likely time-sensitive — review required';
$string['link_http_error'] = 'External link HTTP error';
$string['link_not_checked'] = 'External link not checked';
$string['link_redirect'] = 'External link redirects';
$string['link_server_error'] = 'External link server error';
$string['link_unreachable'] = 'External link could not be verified';
$string['linkcap'] = 'The audit found more external links than the configured network-check limit. Remaining links were listed without a request.';
$string['linktimeout'] = 'External link timeout';
$string['linktimeout_desc'] = 'Timeout in seconds for each safe external link HEAD request. Values below 1 are treated as 1 second.';
$string['low'] = 'Low';
$string['maxaicandidates'] = 'Maximum AI candidates per audit';
$string['maxaicandidates_desc'] = 'Maximum number of locally detected freshness snippets sent through local_ai_bridge in one manual audit.';
$string['maxurlchecks'] = 'Maximum external link checks per audit';
$string['maxurlchecks_desc'] = 'Maximum number of external HTTP/HTTPS links checked during one manual audit. Checks use Moodle curl security protections and never bypass the security helper.';
$string['medium'] = 'Medium';
$string['modified'] = 'Last Moodle modification';
$string['navtitle'] = 'Content freshness';
$string['needs_human_review'] = 'Needs human review';
$string['noresults'] = 'No freshness signals matched the current filters.';
$string['old_year'] = 'Old year reference';
$string['olderthan1'] = 'Older than 1 year';
$string['olderthan2'] = 'Older than 2 years';
$string['olderthan3'] = 'Older than 3 years';
$string['olderthan5'] = 'Older than 5 years';
$string['pagetype'] = 'Page';
$string['pendingai'] = 'Semantic review pending';
$string['pluginname'] = 'Content freshness';
$string['possibly_outdated'] = 'Possibly outdated — review required';
$string['privacy:metadata'] = 'The Content freshness plugin does not store personal data. It caches analysis of teacher-authored course content and does not inspect student submissions.';
$string['quiztype'] = 'Quiz description';
$string['reason'] = 'Reason';
$string['reason_explicit_date'] = 'The text contains an explicit date that may require contextual review.';
$string['reason_external_link'] = 'External links can change independently of Moodle content and should be reviewed periodically.';
$string['reason_link_http_error'] = 'The safe HEAD request returned HTTP {$a}. Review the destination before changing course content; some sites restrict HEAD requests.';
$string['reason_link_not_checked'] = 'The URL was detected but has not been requested yet. Run the audit to verify it safely.';
$string['reason_link_not_checked_limit'] = 'The URL was detected but was not requested because the configured audit limit was reached.';
$string['reason_link_redirect'] = 'The external link redirects. Verify that the final destination is still the intended reference.';
$string['reason_link_server_error'] = 'The safe HEAD request returned HTTP {$a}. This may be temporary, so human review is required.';
$string['reason_link_unreachable'] = 'The URL could not be safely verified. No conclusion about the target content was made.';
$string['reason_old_year'] = 'The text contains an older year reference. The year alone does not prove the statement is outdated.';
$string['reason_software_version'] = 'The text mentions a software or platform version that may change over time.';
$string['reason_temporal_expression'] = 'The wording depends on the moment in which the material is read and therefore deserves review.';
$string['requiresreview'] = 'Requires review';
$string['resetfilters'] = 'Reset';
$string['risktype'] = 'Risk type';
$string['runaudit'] = 'Run freshness audit';
$string['section'] = 'Section';
$string['sectiontype'] = 'Section summary';
$string['severity'] = 'Severity';
$string['snippet'] = 'Snippet';
$string['software_version'] = 'Software version';
$string['summary'] = '{$a->items} course item(s) inspected; {$a->candidates} freshness signal(s) shown.';
$string['temporal_expression'] = 'Temporal expression';
$string['type'] = 'Type';
$string['unknownmodified'] = 'Unknown';
