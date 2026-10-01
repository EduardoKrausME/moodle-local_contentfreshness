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

namespace local_contentfreshness\form;

use moodle_url;
use moodleform;

/**
 * Report filter form.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends moodleform {
    /**
     * Constructor forcing GET so filtered report URLs are shareable/bookmarkable.
     *
     * @param moodle_url|null $action Action URL.
     * @param array|null $customdata Custom data.
     */
    public function __construct(?moodle_url $action = null, ?array $customdata = null) {
        parent::__construct($action, $customdata, 'get');
    }

    /**
     * Define fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $sections = $this->_customdata['sections'] ?? [];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $sectionoptions = [-1 => get_string('allsections', 'local_contentfreshness')];
        foreach ($sections as $number => $name) {
            $sectionoptions[(int)$number] = $name;
        }
        $mform->addElement('select', 'section', get_string('section', 'local_contentfreshness'), $sectionoptions);

        $mform->addElement('select', 'age', get_string('age', 'local_contentfreshness'), [
            0 => get_string('allage', 'local_contentfreshness'),
            365 => get_string('olderthan1', 'local_contentfreshness'),
            730 => get_string('olderthan2', 'local_contentfreshness'),
            1095 => get_string('olderthan3', 'local_contentfreshness'),
            1825 => get_string('olderthan5', 'local_contentfreshness'),
        ]);

        $mform->addElement('select', 'type', get_string('type', 'local_contentfreshness'), [
            '' => get_string('alltypes', 'local_contentfreshness'),
            'page' => get_string('pagetype', 'local_contentfreshness'),
            'book' => get_string('booktype', 'local_contentfreshness'),
            'bookchapter' => get_string('bookchaptertype', 'local_contentfreshness'),
            'section' => get_string('sectiontype', 'local_contentfreshness'),
            'label' => get_string('labeltype', 'local_contentfreshness'),
            'assign' => get_string('assigntype', 'local_contentfreshness'),
            'forum' => get_string('forumtype', 'local_contentfreshness'),
            'quiz' => get_string('quiztype', 'local_contentfreshness'),
        ]);
        $mform->setType('type', PARAM_ALPHA);

        $mform->addElement('select', 'severity', get_string('severity', 'local_contentfreshness'), [
            '' => get_string('allseverities', 'local_contentfreshness'),
            'low' => get_string('low', 'local_contentfreshness'),
            'medium' => get_string('medium', 'local_contentfreshness'),
            'high' => get_string('high', 'local_contentfreshness'),
        ]);
        $mform->setType('severity', PARAM_ALPHA);

        $this->add_action_buttons(false, get_string('filter', 'local_contentfreshness'));
    }
}
