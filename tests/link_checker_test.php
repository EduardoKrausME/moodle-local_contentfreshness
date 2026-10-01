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

/**
 * Tests pre-network URL safety validation.
 *
 * @covers \\local_contentfreshness\\link_checker
 * @package local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_checker_test extends advanced_testcase {
    /**
     * Public hostname URLs with standard ports are accepted for Moodle curl.
     */
    public function test_public_http_urls_are_accepted(): void {
        $checker = new link_checker();
        $this->assertTrue($checker->validate_url('https://example.org/docs?id=1'));
        $this->assertTrue($checker->validate_url('http://example.org:80/path'));
    }

    /**
     * Absolute links back to the same Moodle host are not external-link findings.
     */
    public function test_same_site_url_is_not_external(): void {
        global $CFG;

        $oldwwwroot = $CFG->wwwroot;
        $CFG->wwwroot = 'https://moodle.example.org';
        try {
            $checker = new link_checker();
            $this->assertFalse($checker->is_external_url('https://moodle.example.org/course/view.php?id=2'));
            $this->assertTrue($checker->is_external_url('https://docs.example.org/guide'));
        } finally {
            $CFG->wwwroot = $oldwwwroot;
        }
    }

    /**
     * URL forms commonly used for SSRF are rejected before networking.
     */
    public function test_unsafe_url_shapes_are_rejected(): void {
        $checker = new link_checker();
        $this->assertFalse($checker->validate_url('file:///etc/passwd'));
        $this->assertFalse($checker->validate_url('http://localhost/admin'));
        $this->assertFalse($checker->validate_url('http://127.0.0.1/'));
        $this->assertFalse($checker->validate_url('http://[::1]/'));
        $this->assertFalse($checker->validate_url('http://user:pass@example.org/'));
        $this->assertFalse($checker->validate_url('https://example.org:8443/'));
        $this->assertFalse($checker->validate_url('http://service.internal/'));
    }
}
