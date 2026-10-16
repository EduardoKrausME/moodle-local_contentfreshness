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
 * MCP integration security, pagination and cache-isolation tests.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness;

use advanced_testcase;
use context_course;
use local_contentfreshness\mcp\report_builder;
use local_contentfreshness\mcp\run_audit;
use local_contentfreshness\mcp\get_report;
use local_contentfreshness\mcp\list_sources;
use required_capability_exception;

/**
 * Verify that MCP reads are side-effect free and tool permissions stay scoped.
 *
 * @covers \local_contentfreshness\audit_service
 * @covers \local_contentfreshness\mcp\report_builder
 * @covers \local_contentfreshness\mcp\get_report
 * @covers \local_contentfreshness\mcp\list_sources
 * @covers \local_contentfreshness\mcp\run_audit
 */
final class mcp_tools_test extends advanced_testcase {
    /**
     * Generate an activity containing a deterministic freshness signal.
     *
     * @return array Course and page.
     */
    private function fixture(): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page", [
            "course" => $course->id,
            "name" => "Temporal document",
            "content" => "<p>In 2018, this material described Moodle.</p>",
        ]);
        return [$course, $page];
    }

    /**
     * A cache-only read must not insert, invalidate or update cache rows.
     *
     * @return void
     */
    public function test_read_only_audit_does_not_write_cache(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $page] = $this->fixture();

        $service = new audit_service();
        $result = $service->audit($course, false, false);
        $this->assertGreaterThan(0, $result["itemcount"]);
        $this->assertNotEmpty($result["rows"]);
        $this->assertSame(0, $DB->count_records("local_contentfreshness_cache", [
            "courseid" => $course->id,
        ]));

        $original = $service->audit($course, false, true);
        $this->assertSame(count($result["rows"]), count($original["rows"]));
        $count = $DB->count_records("local_contentfreshness_cache", ["courseid" => $course->id]);
        $this->assertGreaterThan(0, $count);
        $before = $DB->get_records("local_contentfreshness_cache", ["courseid" => $course->id]);

        $DB->set_field("page", "content", "<p>Today this material describes Moodle.</p>", [
            "id" => $page->id,
        ]);
        $service->audit($course, false, false);
        $after = $DB->get_records("local_contentfreshness_cache", ["courseid" => $course->id]);
        $this->assertEquals($before, $after);
    }

    /**
     * The same severity filter and pagination apply to both read and write output.
     *
     * @return void
     */
    public function test_report_builder_filters_and_paginates(): void {
        $rows = [
            ["sourcekey" => "page:1:content", "sectionnum" => 1, "severity" => "high"],
            ["sourcekey" => "page:1:content", "sectionnum" => 1, "severity" => "medium"],
            ["sourcekey" => "page:2:content", "sectionnum" => 2, "severity" => "high"],
        ];
        $result = (new report_builder())->build(
            ["rows" => $rows, "errors" => [], "warnings" => [], "itemcount" => 2],
            ["sourcekey" => "page:1:content", "offset" => 1, "limit" => 1]
        );
        $this->assertSame(2, $result["total_findings"]);
        $this->assertSame(1, count($result["findings"]));
        $this->assertSame("medium", $result["findings"][0]["severity"]);
        $this->assertFalse($result["has_more"]);
        $this->assertSame(1, $result["severity_counts"]["high"]);
    }

    /**
     * Unrecognized severities must not silently broaden the requested report.
     *
     * @return void
     */
    public function test_invalid_severity_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new report_builder())->build(
            ["rows" => [], "errors" => [], "warnings" => [], "itemcount" => 0],
            ["severity" => "critical"]
        );
    }

    /**
     * A WRITE preview must require permission and never execute the audit.
     *
     * @return void
     */
    public function test_mcp_write_preview_is_side_effect_free(): void {
        global $DB;
        $this->resetAfterTest();
        if (!class_exists(\local_mcp\security\authenticated_identity::class)) {
            $this->markTestSkipped("The optional local_mcp plugin is not installed.");
        }

        $this->setAdminUser();
        [$course] = $this->fixture();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$this->getUser()->id, "oauth", ["read", "write"]
        );
        $tool = new run_audit();
        $preview = $tool->preview(["courseid" => $course->id], $identity);
        $this->assertTrue($preview["confirmation_required"]);
        $this->assertTrue($preview["will_update_plugin_cache"]);
        $this->assertSame(0, $DB->count_records("local_contentfreshness_cache"));

        $read = (new get_report())->execute(["courseid" => $course->id], $identity);
        $this->assertFalse($read["refreshed"]);
        $this->assertNotEmpty($read["findings"]);
        $this->assertSame(0, $DB->count_records("local_contentfreshness_cache"));

        $sources = (new list_sources())->execute(["courseid" => $course->id], $identity);
        $this->assertGreaterThan(0, $sources["total"]);
        $this->assertSame(0, $DB->count_records("local_contentfreshness_cache"));
    }

    /**
     * A connected user without the Moodle capability cannot audit the course.
     *
     * @return void
     */
    public function test_mcp_denies_unauthorized_user(): void {
        $this->resetAfterTest();
        if (!class_exists(\local_mcp\security\authenticated_identity::class)) {
            $this->markTestSkipped("The optional local_mcp plugin is not installed.");
        }
        $this->setAdminUser();
        [$course] = $this->fixture();
        $student = $this->getDataGenerator()->create_user();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$student->id, "oauth", ["read", "write"]
        );
        $this->assertFalse(has_capability(
            "local/contentfreshness:audit", context_course::instance($course->id), $student
        ));
        $this->expectException(required_capability_exception::class);
        (new get_report())->execute(["courseid" => $course->id], $identity);
    }
}
