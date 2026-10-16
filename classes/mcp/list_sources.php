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
 * Read available content sources and matching cache states via MCP.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness\mcp;

use context;
use context_course;
use local_contentfreshness\cache_manager;
use local_contentfreshness\content\collector;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;

/**
 * Return metadata only, never the raw teacher-authored HTML.
 */
final class list_sources extends base_tool {
    /**
     * @return string
     */
    public function get_name(): string {
        return "contentfreshness_list_sources";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcplistsources", "local_contentfreshness");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcplistsourcesdesc", "local_contentfreshness");
    }

    /**
     * @return string
     */
    public function get_required_capability(): string {
        return "local/contentfreshness:audit";
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 50],
        ], ["courseid"]);
    }

    /**
     * @param array $arguments MCP input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        access::course((int)$arguments["courseid"]);
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::require_course($courseid, $identity);
        $offset = max(0, (int)($arguments["offset"] ?? 0));
        $limit = max(1, min(50, (int)($arguments["limit"] ?? 20)));
        $items = (new collector())->collect(access::course($courseid));
        $cache = new cache_manager();
        $sources = [];
        foreach (array_slice($items, $offset, $limit) as $item) {
            $cached = $cache->get($courseid, $item->sourcekey, $item->content_hash());
            $sources[] = [
                "sourcekey" => $item->sourcekey,
                "name" => $item->name,
                "type" => $item->type,
                "cmid" => $item->cmid,
                "sectionnum" => $item->sectionnum,
                "timemodified" => $item->timemodified,
                "has_local_cache" => $cached !== null && $cached->localresultjson !== null,
                "has_ai_cache" => $cached !== null && $cached->airesultjson !== null,
                "has_link_cache" => $cached !== null && $cached->linkresultjson !== null,
            ];
        }
        return [
            "courseid" => $courseid,
            "total" => count($items),
            "offset" => $offset,
            "limit" => $limit,
            "has_more" => $offset + $limit < count($items),
            "sources" => $sources,
        ];
    }
}
