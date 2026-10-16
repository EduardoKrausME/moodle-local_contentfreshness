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
 * Shared Moodle permissions and course resolution for MCP tools.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness\mcp;

use context_course;
use local_mcp\security\authenticated_identity;
use stdClass;

/**
 * The connected user's existing course-audit capability is always authoritative.
 */
final class access {
    /**
     * Resolve a real Moodle course for a tool input.
     *
     * @param int $courseid Course identifier.
     * @return stdClass
     */
    public static function course(int $courseid): stdClass {
        return get_course($courseid);
    }

    /**
     * Require the plugin's existing capability in the exact course context.
     *
     * @param int $courseid Moodle course identifier.
     * @param authenticated_identity $identity Connected Moodle user.
     * @return void
     */
    public static function require_course(int $courseid, authenticated_identity $identity): void {
        self::course($courseid);
        require_capability(
            "local/contentfreshness:audit",
            context_course::instance($courseid, MUST_EXIST),
            $identity->userid
        );
    }
}
