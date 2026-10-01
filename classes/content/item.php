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

/**
 * Immutable description of one teacher-authored course content source.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item {
    /** @var int Course id. */
    public readonly int $courseid;
    /** @var string Stable source key. */
    public readonly string $sourcekey;
    /** @var string Logical content type. */
    public readonly string $type;
    /** @var int Source record id. */
    public readonly int $sourceid;
    /** @var int Course-module id, zero for sections. */
    public readonly int $cmid;
    /** @var int Course section id. */
    public readonly int $sectionid;
    /** @var int Section number. */
    public readonly int $sectionnum;
    /** @var string Human-readable item name. */
    public readonly string $name;
    /** @var string HTML/text content to inspect. */
    public readonly string $content;
    /** @var int Moodle modification time. */
    public readonly int $timemodified;
    /** @var moodle_url Edit URL. */
    public readonly moodle_url $editurl;

    /**
     * Constructor.
     *
     * @param int $courseid Course id.
     * @param string $sourcekey Stable source key.
     * @param string $type Logical content type.
     * @param int $sourceid Source record id.
     * @param int $cmid Course-module id, zero for sections.
     * @param int $sectionid Course section id.
     * @param int $sectionnum Section number.
     * @param string $name Human-readable item name.
     * @param string $content HTML/text content to inspect.
     * @param int $timemodified Moodle modification time.
     * @param moodle_url $editurl Edit URL.
     */
    public function __construct(
        int $courseid,
        string $sourcekey,
        string $type,
        int $sourceid,
        int $cmid,
        int $sectionid,
        int $sectionnum,
        string $name,
        string $content,
        int $timemodified,
        moodle_url $editurl
    ) {
        $this->courseid = $courseid;
        $this->sourcekey = $sourcekey;
        $this->type = $type;
        $this->sourceid = $sourceid;
        $this->cmid = $cmid;
        $this->sectionid = $sectionid;
        $this->sectionnum = $sectionnum;
        $this->name = $name;
        $this->content = $content;
        $this->timemodified = $timemodified;
        $this->editurl = $editurl;
    }

    /**
     * Content hash used to invalidate only changed source text.
     *
     * @return string
     */
    public function content_hash(): string {
        $normalised = preg_replace('/\s+/u', ' ', trim($this->content));
        return hash('sha256', $normalised ?? trim($this->content));
    }
}
