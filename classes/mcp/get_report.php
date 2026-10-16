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
 * Expose a fully read-only cached and deterministic MCP freshness report.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness\mcp;

use context;
use context_course;
use local_contentfreshness\audit_service;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;

/**
 * Never create cache, contact external URLs, or consume AI credits.
 */
final class get_report extends base_tool {
    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "contentfreshness_get_report";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetreport", "local_contentfreshness");
    }

    /**
     * Return the translated MCP tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetreportdesc", "local_contentfreshness");
    }

    /**
     * Return the Moodle capability required by the tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "local/contentfreshness:audit";
    }

    /**
     * Describe the accepted MCP input properties.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(
            ["courseid" => ["type" => "integer", "minimum" => 1]]
                + report_builder::filter_properties(),
            ["courseid"]
        );
    }

    /**
     * Resolve the requested Moodle course context.
     *
     * @param array $arguments MCP input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        access::course((int)$arguments["courseid"]);
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * Execute the authorized MCP operation.
     *
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::require_course($courseid, $identity);
        $result = (new audit_service())->audit(access::course($courseid), false, false);
        return [
            "courseid" => $courseid,
            "refreshed" => false,
            "external_requests_made" => false,
            "ai_requests_made" => false,
            "course_content_modified" => false,
        ] + (new report_builder())->build($result, $arguments);
    }
}
