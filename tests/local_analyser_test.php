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

use advanced_testcase;
use local_contentfreshness\analysis\local_analyser;

/**
 * Tests deterministic freshness signals.
 *
 * @package local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_analyser_test extends advanced_testcase {
    /**
     * Old dates and years are review candidates, not factual verdicts.
     */
    public function test_dates_and_years_are_detected(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse('Manual revised on 10/02/2020. Policy originally published in 2018.', 2026);
        $types = array_column($result['candidates'], 'type');

        $this->assertContains('explicit_date', $types);
        $this->assertContains('old_year', $types);
    }

    /**
     * Software-looking versions require surrounding software context.
     */
    public function test_software_versions_are_detected_with_context(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse('This course currently uses Moodle 4.1 and PHP 8.1.', 2026);
        $types = array_column($result['candidates'], 'type');

        $this->assertContains('software_version', $types);
        $this->assertContains('temporal_expression', $types);
    }

    /**
     * Ordinary decimal values should not become version findings without context.
     */
    public function test_decimal_without_software_context_is_not_version(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse('The result was 3.14 metres.', 2026);
        $types = array_column($result['candidates'], 'type');

        $this->assertNotContains('software_version', $types);
    }

    /**
     * Temporal wording is detected independently of dates and versions.
     */
    public function test_temporal_expressions_are_detected(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse('Atualmente este procedimento é usado pela equipe.', 2026);
        $types = array_column($result['candidates'], 'type');

        $this->assertContains('temporal_expression', $types);
    }

    /**
     * Snippets remain valid UTF-8 when accented characters precede a match.
     */
    public function test_snippets_preserve_utf8_boundaries(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse(str_repeat('ação ', 150) . 'Moodle 4.1', 2026);
        $version = array_values(array_filter(
            $result['candidates'],
            static fn(array $candidate): bool => $candidate['type'] === 'software_version'
        ));

        $this->assertNotEmpty($version);
        $this->assertSame(1, preg_match('//u', $version[0]['snippet']));
    }

    /**
     * External URLs are extracted without contacting them.
     */
    public function test_external_urls_are_extracted(): void {
        $analyser = new local_analyser();
        $result = $analyser->analyse('<p>See <a href="https://example.org/docs">docs</a>.</p>', 2026);

        $this->assertContains('https://example.org/docs', $result['urls']);
    }
}
