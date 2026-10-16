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
 * Filter and paginate course freshness findings for MCP.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_contentfreshness\mcp;

use invalid_parameter_exception;

/**
 * Render the existing audit-service rows without modifying their classifications.
 */
final class report_builder {
    /** Maximum number of detailed rows in one response. */
    public const MAX_LIMIT = 50;

    /** Allowed deterministic severity labels. */
    public const SEVERITIES = ["", "low", "medium", "high"];

    /**
     * Filter results consistently for cache-only reads and confirmed audits.
     *
     * @param array $result Native audit_service output.
     * @param array $arguments Optional query filters.
     * @return array
     */
    public function build(array $result, array $arguments): array {
        $severity = (string)($arguments["severity"] ?? "");
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new invalid_parameter_exception(get_string("mcpinvalidseverity", "local_contentfreshness"));
        }
        $sourcekey = (string)($arguments["sourcekey"] ?? "");
        if (strlen($sourcekey) > 255) {
            throw new invalid_parameter_exception(get_string("mcpinvalidsource", "local_contentfreshness"));
        }
        $sectionnum = (int)($arguments["sectionnum"] ?? -1);
        if ($sectionnum < -1) {
            throw new invalid_parameter_exception(get_string("mcpinvalidsection", "local_contentfreshness"));
        }
        $offset = max(0, (int)($arguments["offset"] ?? 0));
        $limit = max(1, min(self::MAX_LIMIT, (int)($arguments["limit"] ?? 20)));

        $severitycounts = ["high" => 0, "medium" => 0, "low" => 0];
        $matching = [];
        foreach ($result["rows"] as $row) {
            if (($sourcekey !== "" && $row["sourcekey"] !== $sourcekey) ||
                    ($sectionnum >= 0 && (int)$row["sectionnum"] !== $sectionnum) ||
                    ($severity !== "" && $row["severity"] !== $severity)) {
                continue;
            }
            if (isset($severitycounts[$row["severity"]])) {
                $severitycounts[$row["severity"]]++;
            }
            $matching[] = $row;
        }

        $total = count($matching);
        return [
            "itemcount" => (int)$result["itemcount"],
            "total_findings" => $total,
            "severity_counts" => $severitycounts,
            "errors" => $result["errors"],
            "warnings" => $result["warnings"],
            "filters" => [
                "severity" => $severity,
                "sourcekey" => $sourcekey,
                "sectionnum" => $sectionnum,
            ],
            "offset" => $offset,
            "limit" => $limit,
            "has_more" => $offset + $limit < $total,
            "findings" => array_slice($matching, $offset, $limit),
        ];
    }

    /**
     * Expose consistent input fields to both READ and WRITE tools.
     *
     * @return array
     */
    public static function filter_properties(): array {
        return [
            "sourcekey" => ["type" => "string", "maxLength" => 255],
            "sectionnum" => ["type" => "integer", "minimum" => 0],
            "severity" => ["type" => "string", "enum" => self::SEVERITIES],
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => self::MAX_LIMIT],
        ];
    }
}
