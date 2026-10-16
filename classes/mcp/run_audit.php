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
 * Run a confirmed, potentially networked freshness audit via MCP.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness\mcp;

use context;
use context_course;
use local_contentfreshness\audit_service;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/**
 * Separate optional AI/network refresh from read-only content inspection.
 */
final class run_audit extends base_tool {
    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "contentfreshness_run_audit";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcprunaudit", "local_contentfreshness");
    }

    /**
     * Return the translated MCP tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcprunauditdesc", "local_contentfreshness");
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
     * Confirm a refresh without doing any external request during the preview.
     *
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::require_course($courseid, $identity);
        // Check filters without running or persisting a real audit.
        (new report_builder())->build(
            ["rows" => [], "errors" => [], "warnings" => [], "itemcount" => 0], $arguments
        );
        $maxurl = get_config("local_contentfreshness", "maxurlchecks");
        $maxai = get_config("local_contentfreshness", "maxaicandidates");
        return [
            "operation" => $this->get_name(),
            "courseid" => $courseid,
            "max_external_link_requests" => $maxurl === false ? 50 : max(0, (int)$maxurl),
            "max_ai_candidates" => $maxai === false ? 100 : max(0, (int)$maxai),
            "may_make_external_requests" => true,
            "may_consume_ai_credits" => true,
            "will_update_plugin_cache" => true,
            "will_modify_course_content" => false,
            "confirmation_required" => true,
        ];
    }

    /**
     * Execute only after local_mcp has consumed its confirmation token.
     *
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        // Validate course permissions and all filters before any network or AI work.
        $this->preview($arguments, $identity);
        $courseid = (int)$arguments["courseid"];
        $result = (new audit_service())->audit(access::course($courseid), true);
        return [
            "courseid" => $courseid,
            "refreshed" => true,
            "may_have_made_external_requests" => true,
            "may_have_called_ai" => true,
            "course_content_modified" => false,
        ] + (new report_builder())->build($result, $arguments);
    }
}
