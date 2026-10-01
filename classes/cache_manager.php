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

use stdClass;

/**
 * Persistent cache keyed by source and content hash.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_manager {
    /**
     * Get a cache record only when the content hash still matches.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Source key.
     * @param string $contenthash Current content hash.
     * @return stdClass|null
     */
    public function get(int $courseid, string $sourcekey, string $contenthash): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_contentfresh_cache', [
            'courseid' => $courseid,
            'sourcekey' => $sourcekey,
        ]);

        if (!$record || !hash_equals((string)$record->contenthash, $contenthash)) {
            return null;
        }

        return $record;
    }

    /**
     * Store deterministic local-analysis results for one source.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Source key.
     * @param string $contenthash Content hash.
     * @param array $results Deterministic candidates and URLs.
     */
    public function store_local(int $courseid, string $sourcekey, string $contenthash, array $results): void {
        $record = $this->prepare_record($courseid, $sourcekey, $contenthash);
        $record->localresultjson = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->save($record);
    }

    /**
     * Store semantic AI results for one source.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Source key.
     * @param string $contenthash Content hash.
     * @param array $results Parsed AI results.
     */
    public function store_ai(int $courseid, string $sourcekey, string $contenthash, array $results): void {
        $record = $this->prepare_record($courseid, $sourcekey, $contenthash);
        $record->airesultjson = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->analyzedat = time();
        $this->save($record);
    }

    /**
     * Store safe link-check results for one source.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Source key.
     * @param string $contenthash Content hash.
     * @param array $results Link results.
     */
    public function store_links(int $courseid, string $sourcekey, string $contenthash, array $results): void {
        $record = $this->prepare_record($courseid, $sourcekey, $contenthash);
        $record->linkresultjson = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->linkcheckedat = time();
        $this->save($record);
    }

    /**
     * Remove cache records for source keys that no longer exist in the course.
     *
     * @param int $courseid Course id.
     * @param string[] $sourcekeys Current source keys.
     */
    public function prune_course(int $courseid, array $sourcekeys): void {
        global $DB;

        $valid = array_fill_keys($sourcekeys, true);
        $records = $DB->get_records('local_contentfresh_cache', ['courseid' => $courseid], '', 'id,sourcekey');
        foreach ($records as $record) {
            if (!isset($valid[$record->sourcekey])) {
                $DB->delete_records('local_contentfresh_cache', ['id' => $record->id]);
            }
        }
    }

    /**
     * Prepare an existing or new record. Hash changes explicitly invalidate both
     * semantic and network results instead of accidentally reusing stale state.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Source key.
     * @param string $contenthash Content hash.
     * @return stdClass
     */
    private function prepare_record(int $courseid, string $sourcekey, string $contenthash): stdClass {
        global $DB;

        $record = $DB->get_record('local_contentfresh_cache', [
            'courseid' => $courseid,
            'sourcekey' => $sourcekey,
        ]);
        $now = time();

        if (!$record) {
            $record = (object)[
                'courseid' => $courseid,
                'sourcekey' => $sourcekey,
                'contenthash' => $contenthash,
                'localresultjson' => null,
                'airesultjson' => null,
                'linkresultjson' => null,
                'analyzedat' => 0,
                'linkcheckedat' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            return $record;
        }

        if (!hash_equals((string)$record->contenthash, $contenthash)) {
            $record->contenthash = $contenthash;
            $record->localresultjson = null;
            $record->airesultjson = null;
            $record->linkresultjson = null;
            $record->analyzedat = 0;
            $record->linkcheckedat = 0;
        }
        $record->timemodified = $now;
        return $record;
    }

    /**
     * Insert or update a cache record.
     *
     * @param stdClass $record Record.
     */
    private function save(stdClass $record): void {
        global $DB;

        $record->timemodified = time();
        if (!empty($record->id)) {
            $DB->update_record('local_contentfresh_cache', $record);
        } else {
            $record->id = $DB->insert_record('local_contentfresh_cache', $record);
        }
    }
}
