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

namespace local_contentfreshness;

use local_contentfreshness\ai\reviewer;
use local_contentfreshness\analysis\local_analyser;
use local_contentfreshness\content\collector;
use local_contentfreshness\content\item;
use stdClass;
use Throwable;

/**
 * Coordinates deterministic collection, safe link checks, caching and AI review.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_service {
    /** AI batch size to keep payloads bounded. */
    private const AI_BATCH_SIZE = 20;

    /** @var collector */
    private collector $collector;

    /** @var local_analyser */
    private local_analyser $analyser;

    /** @var cache_manager */
    private cache_manager $cache;

    /** @var reviewer */
    private reviewer $reviewer;

    /** @var link_checker */
    private link_checker $linkchecker;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->collector = new collector();
        $this->analyser = new local_analyser();
        $this->cache = new cache_manager();
        $this->reviewer = new reviewer();
        $this->linkchecker = new link_checker();
    }

    /**
     * Audit a course.
     *
     * When $refresh is false no provider call and no network request is made;
     * the method only combines deterministic findings with matching cache data.
     *
     * @param stdClass $course Course record.
     * @param bool $refresh Whether to refresh changed semantic candidates and links.
     * @return array{rows:array,errors:array,warnings:array,itemcount:int}
     */
    public function audit(stdClass $course, bool $refresh): array {
        $items = $this->collector->collect($course);
        $errors = [];
        $warnings = [];
        $states = [];
        $aicandidates = [];
        $candidateindex = [];
        $queuedsourcekeys = [];
        $urlcheckcount = 0;
        $maxurlconfig = get_config('local_contentfreshness', 'maxurlchecks');
        $timeoutconfig = get_config('local_contentfreshness', 'linktimeout');
        $maxaiconfig = get_config('local_contentfreshness', 'maxaicandidates');
        $maxurlchecks = $maxurlconfig === false ? 50 : max(0, (int)$maxurlconfig);
        $timeout = $timeoutconfig === false ? 5 : max(1, (int)$timeoutconfig);
        $maxaicandidates = $maxaiconfig === false ? 100 : max(0, (int)$maxaiconfig);
        $candidateoverflow = 0;
        $linkoverflow = false;

        foreach ($items as $contentitem) {
            $hash = $contentitem->content_hash();
            $cached = $this->cache->get($contentitem->courseid, $contentitem->sourcekey, $hash);
            if ($cached !== null && $cached->localresultjson !== null) {
                $local = $this->decode_local_result($cached->localresultjson);
            } else {
                $local = $this->analyser->analyse($contentitem->content);
                $this->cache->store_local($contentitem->courseid, $contentitem->sourcekey, $hash, $local);
                $cached = $this->cache->get($contentitem->courseid, $contentitem->sourcekey, $hash);
            }

            $local['urls'] = array_values(array_filter(
                $local['urls'],
                fn(string $url): bool => $this->linkchecker->is_external_url($url)
            ));

            foreach ($local['candidates'] as &$candidate) {
                $candidate['candidateid'] = substr(hash(
                    'sha256',
                    $contentitem->sourcekey . '|' . $candidate['candidateid']
                ), 0, 20);
            }
            unset($candidate);

            $aicached = $cached !== null && $cached->airesultjson !== null;
            $linkcached = $cached !== null && $cached->linkresultjson !== null;
            $airesults = $this->decode_json_array($cached->airesultjson ?? null);
            $linkresults = $this->decode_json_array($cached->linkresultjson ?? null);

            if (!$refresh && !$linkcached && $local['urls']) {
                $linkresults = [];
                foreach ($local['urls'] as $url) {
                    $linkresults[] = [
                        'url' => $url,
                        'status' => 'not_checked',
                        'httpcode' => 0,
                        'error' => '',
                    ];
                }
            }

            if ($refresh && $local['urls']) {
                $linkresults = [];
                foreach ($local['urls'] as $url) {
                    if ($urlcheckcount >= $maxurlchecks) {
                        $linkoverflow = true;
                        $linkresults[] = [
                            'url' => $url,
                            'status' => 'not_checked_limit',
                            'httpcode' => 0,
                            'error' => '',
                        ];
                        continue;
                    }
                    $linkresults[] = $this->linkchecker->check($url, $timeout);
                    $urlcheckcount++;
                }
                $this->cache->store_links(
                    $contentitem->courseid,
                    $contentitem->sourcekey,
                    $hash,
                    $linkresults
                );
                $linkcached = true;
            }

            if (!$aicached && $refresh && $local['candidates']) {
                $remaining = max(0, $maxaicandidates - count($aicandidates));
                if (count($local['candidates']) <= $remaining) {
                    $queuedsourcekeys[$contentitem->sourcekey] = true;
                    foreach ($local['candidates'] as $candidate) {
                        $candidate['sourcekey'] = $contentitem->sourcekey;
                        $candidate['itemtype'] = $contentitem->type;
                        $aicandidates[] = $candidate;
                        $candidateindex[$candidate['candidateid']] = $contentitem->sourcekey;
                    }
                } else {
                    $candidateoverflow += count($local['candidates']);
                }
            }

            $states[$contentitem->sourcekey] = [
                'item' => $contentitem,
                'hash' => $hash,
                'local' => $local,
                'airesults' => $airesults,
                'linkresults' => $linkresults,
                'aicached' => $aicached,
                'linkcached' => $linkcached,
            ];
        }

        if ($refresh && $aicandidates) {
            $newresults = [];
            try {
                foreach (array_chunk($aicandidates, self::AI_BATCH_SIZE) as $batch) {
                    foreach ($this->reviewer->review($batch) as $result) {
                        $sourcekey = $candidateindex[$result['candidateid']] ?? null;
                        if ($sourcekey === null) {
                            continue;
                        }
                        $newresults[$sourcekey][] = $result;
                    }
                }

                foreach ($states as $sourcekey => &$state) {
                    if (!isset($queuedsourcekeys[$sourcekey])) {
                        continue;
                    }
                    $state['airesults'] = $newresults[$sourcekey] ?? [];
                    $this->cache->store_ai(
                        $state['item']->courseid,
                        $sourcekey,
                        $state['hash'],
                        $state['airesults']
                    );
                    $state['aicached'] = true;
                }
                unset($state);
            } catch (Throwable $e) {
                $errors[] = get_string('aifailed', 'local_contentfreshness', $e->getMessage());
            }
        }

        if ($refresh) {
            $this->cache->prune_course((int)$course->id, array_keys($states));
        }

        if ($candidateoverflow > 0) {
            $warnings[] = get_string('candidatecap', 'local_contentfreshness', $candidateoverflow);
        }
        if ($linkoverflow) {
            $warnings[] = get_string('linkcap', 'local_contentfreshness');
        }

        $rows = [];
        foreach ($states as $state) {
            $rows = array_merge($rows, $this->rows_for_state($state));
        }

        usort($rows, static function (array $a, array $b): int {
            $weight = ['high' => 3, 'medium' => 2, 'low' => 1];
            $severity = ($weight[$b['severity']] ?? 0) <=> ($weight[$a['severity']] ?? 0);
            if ($severity !== 0) {
                return $severity;
            }
            return $a['sectionnum'] <=> $b['sectionnum'];
        });

        return [
            'rows' => $rows,
            'errors' => $errors,
            'warnings' => $warnings,
            'itemcount' => count($items),
        ];
    }

    /**
     * Convert one source state into report rows.
     *
     * @param array $state State.
     * @return array
     */
    private function rows_for_state(array $state): array {
        /** @var item $item */
        $item = $state['item'];
        $aiindex = [];
        foreach ($state['airesults'] as $result) {
            if (!empty($result['candidateid'])) {
                $aiindex[$result['candidateid']] = $result;
            }
        }

        $rows = [];
        foreach ($state['local']['candidates'] as $candidate) {
            $ai = $aiindex[$candidate['candidateid']] ?? null;
            $classification = $ai['classification'] ?? 'needs_human_review';
            $reason = $candidate['localreason'];
            if (!empty($ai['reason'])) {
                $reason .= ' ' . $ai['reason'];
            } else if (!$state['aicached']) {
                $reason .= ' ' . get_string('pendingai', 'local_contentfreshness') . '.';
            }

            $severity = $this->semantic_severity($candidate['severity'], $classification);
            $rows[] = $this->base_row(
                $item,
                $candidate['snippet'],
                $reason,
                $candidate['type'],
                $severity,
                $classification,
                $state['aicached']
            );
        }

        foreach ($state['linkresults'] as $linkresult) {
            $rows[] = $this->link_row($item, $linkresult, $state['linkcached']);
        }

        return $rows;
    }

    /**
     * Derive report severity deterministically from local signal + AI class.
     *
     * @param string $local Local severity.
     * @param string $classification AI classification.
     * @return string
     */
    private function semantic_severity(string $local, string $classification): string {
        if ($classification === 'possibly_outdated') {
            return 'medium';
        }
        if ($classification === 'likely_time_sensitive' && $local === 'low') {
            return 'medium';
        }
        if ($classification === 'evergreen') {
            return 'low';
        }
        return in_array($local, ['low', 'medium', 'high'], true) ? $local : 'low';
    }

    /**
     * Build one deterministic external-link row.
     *
     * @param item $item Content item.
     * @param array $result Link result.
     * @param bool $cached Whether matching cache exists.
     * @return array
     */
    private function link_row(item $item, array $result, bool $cached): array {
        $status = (string)($result['status'] ?? 'not_checked_limit');
        $httpcode = (int)($result['httpcode'] ?? 0);
        $url = (string)($result['url'] ?? '');
        $type = 'external_link';
        $severity = 'low';
        $classification = 'likely_time_sensitive';
        $reason = get_string('reason_external_link', 'local_contentfreshness');

        if ($status === 'http_error') {
            $type = 'link_http_error';
            $severity = in_array($httpcode, [404, 410], true) ? 'high' : 'medium';
            $classification = 'needs_human_review';
            $reason = get_string('reason_link_http_error', 'local_contentfreshness', $httpcode);
        } else if ($status === 'server_error') {
            $type = 'link_server_error';
            $severity = 'medium';
            $classification = 'needs_human_review';
            $reason = get_string('reason_link_server_error', 'local_contentfreshness', $httpcode);
        } else if ($status === 'blocked_or_unreachable' || $status === 'not_checked_unsafe') {
            $type = 'link_unreachable';
            $severity = 'medium';
            $classification = 'needs_human_review';
            $reason = get_string('reason_link_unreachable', 'local_contentfreshness');
        } else if ($status === 'redirect') {
            $type = 'link_redirect';
            $severity = 'low';
            $reason = get_string('reason_link_redirect', 'local_contentfreshness');
        } else if ($status === 'not_checked_limit') {
            $type = 'link_not_checked';
            $severity = 'low';
            $classification = 'needs_human_review';
            $reason = get_string('reason_link_not_checked_limit', 'local_contentfreshness');
        } else if ($status === 'not_checked') {
            $type = 'link_not_checked';
            $severity = 'low';
            $classification = 'needs_human_review';
            $reason = get_string('reason_link_not_checked', 'local_contentfreshness');
        }

        return $this->base_row(
            $item,
            $url,
            $reason,
            $type,
            $severity,
            $classification,
            $cached
        );
    }

    /**
     * Build a report row.
     *
     * @param item $item Content item.
     * @param string $snippet Snippet.
     * @param string $reason Reason.
     * @param string $risktype Risk type.
     * @param string $severity Severity.
     * @param string $classification Classification.
     * @param bool $cached Cache state.
     * @return array
     */
    private function base_row(
        item   $item,
        string $snippet,
        string $reason,
        string $risktype,
        string $severity,
        string $classification,
        bool   $cached
    ): array {
        return [
            'sourcekey' => $item->sourcekey,
            'itemname' => $item->name,
            'itemtype' => $item->type,
            'sectionnum' => $item->sectionnum,
            'snippet' => $snippet,
            'reason' => $reason,
            'risktype' => $risktype,
            'severity' => $severity,
            'classification' => $classification,
            'timemodified' => $item->timemodified,
            'editurl' => $item->editurl->out(false),
            'cached' => $cached,
        ];
    }

    /**
     * Decode cached deterministic analysis defensively.
     *
     * @param string|null $json JSON.
     * @return array{candidates:array,urls:array}
     */
    private function decode_local_result(?string $json): array {
        if ($json === null || $json === '') {
            return ['candidates' => [], 'urls' => []];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['candidates' => [], 'urls' => []];
        }
        return [
            'candidates' => is_array($decoded['candidates'] ?? null) ? $decoded['candidates'] : [],
            'urls' => is_array($decoded['urls'] ?? null) ? $decoded['urls'] : [],
        ];
    }

    /**
     * Decode a cached JSON list defensively.
     *
     * @param string|null $json JSON.
     * @return array
     */
    private function decode_json_array(?string $json): array {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
