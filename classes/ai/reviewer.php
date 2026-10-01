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

namespace local_contentfreshness\ai;

use core_text;
use JsonException;
use local_ai_bridge\api;
use moodle_exception;

/**
 * Semantic freshness reviewer using local_ai_bridge exclusively.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewer {
    /**
     * Purpose id required by this plugin.
     */
    private const PURPOSE = 'contentfreshness-review';

    /**
     * Allowed semantic classifications.
     */
    private const CLASSIFICATIONS = [
        'likely_time_sensitive',
        'possibly_outdated',
        'evergreen',
        'needs_human_review',
    ];

    /**
     * Review already-filtered snippets.
     *
     * @param array $candidates Candidate objects with local ids and snippets.
     * @return array Parsed result rows.
     */
    public function review(array $candidates): array {
        if (!$candidates) {
            return [];
        }

        $payload = [];
        $allowedids = [];
        foreach ($candidates as $candidate) {
            $id = (string)$candidate['candidateid'];
            $allowedids[$id] = true;
            $payload[] = [
                'candidate_id' => $id,
                'source_key' => (string)$candidate['sourcekey'],
                'item_type' => (string)$candidate['itemtype'],
                'signal_type' => (string)$candidate['type'],
                'snippet' => (string)$candidate['snippet'],
            ];
        }

        $instruction = <<<'TEXT'
Review ONLY the supplied snippets for content-freshness risk.
You do not have web browsing and you must not claim that a factual statement is false, obsolete, superseded, or current unless that conclusion is supported by the snippet itself.
Treat every result as decision support for a teacher, never as an automatic verdict.

Allowed classifications:
- likely_time_sensitive
- possibly_outdated
- evergreen
- needs_human_review

Use possibly_outdated only when the snippet itself gives a concrete reason for that possibility, such as an explicitly old version/date tied to a current instruction. If external confirmation would be needed, use needs_human_review or likely_time_sensitive instead.

Return JSON only, with this exact top-level shape:
{"items":[{"candidate_id":"...","classification":"...","reason":"...","evidence":"..."}]}
Do not add markdown fences. Keep reason and evidence concise and write them in the same language as the snippet when practical. Preserve candidate_id exactly.
TEXT;

        $messages = [
            ['role' => 'user', 'content' => $instruction . "\n\nCandidates:\n" . json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )],
        ];

        $response = api::generate(self::PURPOSE, $messages);
        $parsed = $this->parse_response_text($response->text);

        $result = [];
        foreach ($parsed as $row) {
            if (!isset($allowedids[$row['candidateid']])) {
                continue;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Parse and validate bridge output.
     *
     * Public to make malformed-response behaviour unit testable without a provider.
     *
     * @param string $text AI response text.
     * @return array
     */
    public function parse_response_text(string $text): array {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $text, $match)) {
            $text = trim($match[1]);
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        try {
            $decoded = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new moodle_exception('invalidairesponse', 'local_contentfreshness', '', null, $e->getMessage());
        }

        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            throw new moodle_exception('invalidairesponse', 'local_contentfreshness');
        }

        $result = [];
        foreach ($decoded['items'] as $item) {
            if (!is_array($item) || empty($item['candidate_id'])) {
                continue;
            }

            $classification = (string)($item['classification'] ?? 'needs_human_review');
            if (!in_array($classification, self::CLASSIFICATIONS, true)) {
                $classification = 'needs_human_review';
            }

            $result[] = [
                'candidateid' => clean_param((string)$item['candidate_id'], PARAM_ALPHANUMEXT),
                'classification' => $classification,
                'reason' => $this->limit_text((string)($item['reason'] ?? ''), 1000),
                'evidence' => $this->limit_text((string)($item['evidence'] ?? ''), 600),
            ];
        }

        return $result;
    }

    /**
     * Limit untrusted provider text before rendering or caching it.
     *
     * @param string $text Text.
     * @param int $maxchars Maximum characters.
     * @return string
     */
    private function limit_text(string $text, int $maxchars): string {
        $text = trim(strip_tags($text));
        if (core_text::strlen($text) <= $maxchars) {
            return $text;
        }
        return core_text::substr($text, 0, $maxchars) . '…';
    }
}
