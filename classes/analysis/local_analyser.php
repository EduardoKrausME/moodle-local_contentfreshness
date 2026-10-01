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

namespace local_contentfreshness\analysis;

use core_text;

/**
 * Deterministic freshness heuristics.
 *
 * These rules only identify review candidates. They never claim that the
 * underlying statement is false or outdated.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_analyser {
    /**
     * Maximum snippet length sent to the AI bridge.
     */
    private const SNIPPET_LENGTH = 600;

    /**
     * Analyse content locally.
     *
     * @param string $html HTML or text.
     * @param int|null $currentyear Current year, injectable for tests.
     * @return array{candidates: array, urls: array}
     */
    public function analyse(string $html, ?int $currentyear = null): array {
        $currentyear ??= (int)date('Y');
        $text = $this->plain_text($html);
        $candidates = [];

        $this->find_old_years($text, $currentyear, $candidates);
        $this->find_dates($text, $candidates);
        $this->find_temporal_expressions($text, $candidates);
        $this->find_versions($text, $candidates);

        return [
            'candidates' => $this->deduplicate($candidates),
            'urls' => $this->extract_urls($html),
        ];
    }

    /**
     * Convert HTML to stable plain text without executing anything.
     *
     * @param string $html Input.
     * @return string
     */
    public function plain_text(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text ?? '');
    }

    /**
     * Extract absolute HTTP/HTTPS links only.
     *
     * @param string $html Input.
     * @return string[]
     */
    public function extract_urls(string $html): array {
        $urls = [];

        if (preg_match_all('~https?://[^\s<>"\']+~iu', html_entity_decode($html, ENT_QUOTES | ENT_HTML5), $matches)) {
            foreach ($matches[0] as $url) {
                $url = rtrim($url, ".,;:!?)]}'\"");
                if ($url !== '') {
                    $urls[$url] = true;
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * Find old year references.
     *
     * @param string $text Plain text.
     * @param int $currentyear Current year.
     * @param array $out Findings.
     */
    private function find_old_years(string $text, int $currentyear, array &$out): void {
        if (!preg_match_all('/\b(?:19|20)\d{2}\b/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[0] as [$yeartext, $offset]) {
            $year = (int)$yeartext;
            if ($year >= $currentyear - 1 || $year > $currentyear) {
                continue;
            }

            $out[] = $this->candidate(
                'old_year',
                $yeartext,
                $offset,
                $text,
                $year <= $currentyear - 5 ? 'medium' : 'low',
                get_string('reason_old_year', 'local_contentfreshness')
            );
        }
    }

    /**
     * Find explicit date forms.
     *
     * @param string $text Plain text.
     * @param array $out Findings.
     */
    private function find_dates(string $text, array &$out): void {
        $patterns = [
            '/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-](?:19|20)\d{2}\b/u',
            '/\b(?:19|20)\d{2}-\d{2}-\d{2}\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as [$match, $offset]) {
                $out[] = $this->candidate(
                    'explicit_date',
                    $match,
                    $offset,
                    $text,
                    'medium',
                    get_string('reason_explicit_date', 'local_contentfreshness')
                );
            }
        }
    }

    /**
     * Find temporal wording.
     *
     * @param string $text Plain text.
     * @param array $out Findings.
     */
    private function find_temporal_expressions(string $text, array &$out): void {
        $pattern = '/\\b(?:atualmente|hoje|agora|no momento|neste momento|recentemente|última versão|' .
            'ultima versão|nova versão|versão atual|currently|today|now|recently|latest version|new version|' .
            'current version)\\b/iu';
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $out[] = $this->candidate(
                'temporal_expression',
                $match,
                $offset,
                $text,
                'medium',
                get_string('reason_temporal_expression', 'local_contentfreshness')
            );
        }
    }

    /**
     * Find version numbers only when nearby text looks software-related.
     *
     * @param string $text Plain text.
     * @param array $out Findings.
     */
    private function find_versions(string $text, array &$out): void {
        $pattern = '/\b(?:v(?:ersion|ersão)?\s*)?\d+\.\d+(?:\.\d+)?(?:[-+][a-z0-9.\-]+)?\b/iu';
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $context = $this->snippet($text, $offset, strlen($match), 180);
            $softwarepattern = '/\\b(?:versão|version|software|moodle|php|python|java|node(?:\\.js)?|' .
                'react|angular|laravel|windows|ubuntu|android|ios|chrome|firefox|postgresql|mysql|mariadb|api)\\b/iu';
            if (!preg_match($softwarepattern, $context)) {
                continue;
            }

            $out[] = $this->candidate(
                'software_version',
                $match,
                $offset,
                $text,
                'medium',
                get_string('reason_software_version', 'local_contentfreshness')
            );
        }
    }

    /**
     * Build one structured candidate.
     *
     * @param string $type Signal type.
     * @param string $match Matched text.
     * @param int $offset Byte offset.
     * @param string $text Plain text.
     * @param string $severity Local severity.
     * @param string $reason Local reason.
     * @return array
     */
    private function candidate(
        string $type,
        string $match,
        int $offset,
        string $text,
        string $severity,
        string $reason
    ): array {
        $id = substr(hash('sha256', $type . '|' . $match . '|' . $offset), 0, 20);
        return [
            'candidateid' => $id,
            'type' => $type,
            'match' => $match,
            'snippet' => $this->snippet($text, $offset, strlen($match), self::SNIPPET_LENGTH),
            'severity' => $severity,
            'localreason' => $reason,
            '_offset' => $offset,
            '_length' => strlen($match),
        ];
    }

    /**
     * Extract UTF-8-safe context around a PCRE byte offset without exceeding a safe payload size.
     *
     * @param string $text Text.
     * @param int $offset Byte offset.
     * @param int $matchlength Match length.
     * @param int $maxlength Maximum snippet characters.
     * @return string
     */
    private function snippet(string $text, int $offset, int $matchlength, int $maxlength): string {
        // PCRE offsets are bytes, while slicing arbitrary byte ranges can split a UTF-8 character.
        // Convert the match position to character offsets before extracting the snippet.
        $prefix = substr($text, 0, $offset);
        $matchbytes = substr($text, $offset, $matchlength);
        $charoffset = core_text::strlen($prefix === false ? '' : $prefix);
        $matchchars = core_text::strlen($matchbytes === false ? '' : $matchbytes);
        $half = (int)floor(($maxlength - $matchchars) / 2);
        $start = max(0, $charoffset - max(0, $half));
        return trim(core_text::substr($text, $start, $maxlength));
    }

    /**
     * Remove duplicate candidates caused by overlapping regexes.
     *
     * @param array $candidates Candidates.
     * @return array
     */
    private function deduplicate(array $candidates): array {
        $seen = [];
        $result = [];
        $overlaptypes = ['old_year', 'explicit_date', 'software_version'];
        $priority = ['old_year' => 1, 'software_version' => 2, 'explicit_date' => 3];

        foreach ($candidates as $candidate) {
            $key = $candidate['type'] . '|' . $candidate['_offset'] . '|' . $candidate['match'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $replaced = false;
            if (in_array($candidate['type'], $overlaptypes, true)) {
                $candidateend = $candidate['_offset'] + $candidate['_length'];
                foreach ($result as $index => $existing) {
                    if (!in_array($existing['type'], $overlaptypes, true)) {
                        continue;
                    }
                    $existingend = $existing['_offset'] + $existing['_length'];
                    $overlaps = $candidate['_offset'] < $existingend && $existing['_offset'] < $candidateend;
                    if (!$overlaps) {
                        continue;
                    }
                    if (($priority[$candidate['type']] ?? 0) > ($priority[$existing['type']] ?? 0)) {
                        $result[$index] = $candidate;
                    }
                    $replaced = true;
                    break;
                }
            }

            if (!$replaced) {
                $result[] = $candidate;
            }
        }

        foreach ($result as &$candidate) {
            unset($candidate['_offset'], $candidate['_length']);
        }
        unset($candidate);
        return array_values($result);
    }
}
