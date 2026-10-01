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
use local_contentfreshness\ai\reviewer;
use moodle_exception;

/**
 * Tests strict AI output parsing without calling a provider.
 *
 * @covers \\local_contentfreshness\\ai\\reviewer
 * @package local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reviewer_test extends advanced_testcase {
    /**
     * Valid JSON is normalised to the plugin schema.
     */
    public function test_valid_ai_json_is_parsed(): void {
        $reviewer = new reviewer();
        $result = $reviewer->parse_response_text(json_encode([
            'items' => [[
                'candidate_id' => 'abc123',
                'classification' => 'likely_time_sensitive',
                'reason' => 'Mentions today.',
                'evidence' => 'today',
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertCount(1, $result);
        $this->assertSame('abc123', $result[0]['candidateid']);
        $this->assertSame('likely_time_sensitive', $result[0]['classification']);
    }

    /**
     * Unknown provider classifications are downgraded to human review.
     */
    public function test_unknown_classification_is_downgraded(): void {
        $reviewer = new reviewer();
        $result = $reviewer->parse_response_text(json_encode([
            'items' => [[
                'candidate_id' => 'abc',
                'classification' => 'definitely_false',
                'reason' => 'x',
                'evidence' => 'y',
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('needs_human_review', $result[0]['classification']);
    }

    /**
     * Invalid JSON fails closed.
     */
    public function test_invalid_json_is_rejected(): void {
        $reviewer = new reviewer();
        $this->expectException(moodle_exception::class);
        $reviewer->parse_response_text('not-json');
    }
}
