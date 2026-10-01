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

namespace local_contentfreshness\content;

use moodle_url;
use stdClass;

/**
 * Collect supported, teacher-authored course content.
 *
 * Student submissions and user-generated posts are deliberately outside scope.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector {
    /**
     * Collect supported items for a course.
     *
     * @param stdClass $course Course record.
     * @return item[]
     */
    public function collect(stdClass $course): array {
        global $DB;

        $items = [];
        $modinfo = get_fast_modinfo($course);

        foreach ($modinfo->get_section_info_all() as $section) {
            if (trim((string)$section->summary) === '') {
                continue;
            }

            $name = get_section_name($course, $section);
            $items[] = new item(
                (int)$course->id,
                'section:' . $section->id . ':summary',
                'section',
                (int)$section->id,
                0,
                (int)$section->id,
                (int)$section->section,
                $name,
                (string)$section->summary,
                (int)($section->timemodified ?? 0),
                new moodle_url('/course/editsection.php', ['id' => $section->id])
            );
        }

        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, ['page', 'book', 'label', 'assign', 'forum', 'quiz'], true)) {
                continue;
            }

            if (!$DB->get_manager()->table_exists($cm->modname)) {
                continue;
            }

            $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
            if (!$instance) {
                continue;
            }

            switch ($cm->modname) {
                case 'page':
                    $content = trim((string)($instance->intro ?? ''));
                    $pagecontent = trim((string)($instance->content ?? ''));
                    if ($content !== '' && $pagecontent !== '') {
                        $content .= "\n\n" . $pagecontent;
                    } else if ($pagecontent !== '') {
                        $content = $pagecontent;
                    }
                    $this->append_module_item($items, $course, $cm, $instance, 'page', $content);
                    break;

                case 'book':
                    $this->append_module_item(
                        $items,
                        $course,
                        $cm,
                        $instance,
                        'book',
                        (string)($instance->intro ?? '')
                    );
                    $chapters = $DB->get_records('book_chapters', ['bookid' => $instance->id], 'pagenum ASC');
                    foreach ($chapters as $chapter) {
                        if (trim((string)$chapter->content) === '') {
                            continue;
                        }
                        $items[] = new item(
                            (int)$course->id,
                            'bookchapter:' . $chapter->id . ':content',
                            'bookchapter',
                            (int)$chapter->id,
                            (int)$cm->id,
                            (int)$cm->sectionnum,
                            (int)$cm->sectionnum,
                            format_string($instance->name) . ' — ' . format_string($chapter->title),
                            (string)$chapter->content,
                            (int)($chapter->timemodified ?? $instance->timemodified ?? 0),
                            new moodle_url('/mod/book/edit.php', [
                                'cmid' => $cm->id,
                                'id' => $chapter->id,
                            ])
                        );
                    }
                    break;

                case 'label':
                    $this->append_module_item(
                        $items,
                        $course,
                        $cm,
                        $instance,
                        'label',
                        (string)($instance->intro ?? '')
                    );
                    break;

                case 'assign':
                    $intro = trim((string)($instance->intro ?? ''));
                    $activity = trim((string)($instance->activity ?? ''));
                    $content = $intro;
                    if ($activity !== '') {
                        $content .= ($content === '' ? '' : "\n\n") . $activity;
                    }
                    $this->append_module_item($items, $course, $cm, $instance, 'assign', $content);
                    break;

                case 'forum':
                    $this->append_module_item(
                        $items,
                        $course,
                        $cm,
                        $instance,
                        'forum',
                        (string)($instance->intro ?? '')
                    );
                    break;

                case 'quiz':
                    $this->append_module_item(
                        $items,
                        $course,
                        $cm,
                        $instance,
                        'quiz',
                        (string)($instance->intro ?? '')
                    );
                    break;
            }
        }

        return $items;
    }

    /**
     * Append one normal activity item when it has non-empty teacher-authored text.
     *
     * @param array $items Output array.
     * @param stdClass $course Course record.
     * @param object $cm Course-module info.
     * @param stdClass $instance Module instance.
     * @param string $type Logical type.
     * @param string $content Content.
     */
    private function append_module_item(
        array    &$items,
        stdClass $course,
        object   $cm,
        stdClass $instance,
        string   $type,
        string   $content
    ): void {
        if (trim(strip_tags($content)) === '' && trim($content) === '') {
            return;
        }

        $items[] = new item(
            (int)$course->id,
            $type . ':' . $instance->id . ':content',
            $type,
            (int)$instance->id,
            (int)$cm->id,
            (int)$cm->sectionnum,
            (int)$cm->sectionnum,
            format_string((string)($instance->name ?? $cm->name)),
            $content,
            (int)($instance->timemodified ?? 0),
            new moodle_url('/course/modedit.php', [
                'update' => $cm->id,
                'return' => 1,
            ])
        );
    }
}
