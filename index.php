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
 * index.php
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use core\notification;
use local_contentfreshness\audit_service;
use local_contentfreshness\form\filter_form;

$courseid = required_param('id', PARAM_INT);
$run = optional_param('run', 0, PARAM_BOOL);
$sectionfilter = optional_param('section', -1, PARAM_INT);
$agefilter = optional_param('age', 0, PARAM_INT);
$typefilter = optional_param('type', '', PARAM_ALPHA);
$severityfilter = optional_param('severity', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/contentfreshness:audit', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/contentfreshness/index.php', ['id' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'local_contentfreshness'));
$PAGE->set_heading(format_string($course->fullname));

if ($run) {
    require_sesskey();
}

$service = new audit_service();
$result = $service->audit($course, (bool)$run);

if ($run) {
    notification::success(get_string('auditcomplete', 'local_contentfreshness'));
}
foreach ($result['errors'] as $error) {
    notification::error($error);
}
foreach ($result['warnings'] as $warning) {
    notification::warning($warning);
}

$modinfo = get_fast_modinfo($course);
$sections = [];
foreach ($modinfo->get_section_info_all() as $section) {
    $sections[(int)$section->section] = get_section_name($course, $section);
}

$filterform = new filter_form(
    new moodle_url('/local/contentfreshness/index.php'),
    ['sections' => $sections]
);
$filterform->set_data((object)[
    'id' => $courseid,
    'section' => $sectionfilter,
    'age' => $agefilter,
    'type' => $typefilter,
    'severity' => $severityfilter,
]);

$rows = array_values(array_filter($result['rows'], static function (array $row) use (
    $sectionfilter,
    $agefilter,
    $typefilter,
    $severityfilter
): bool {
    if ($sectionfilter >= 0 && (int)$row['sectionnum'] !== $sectionfilter) {
        return false;
    }
    if ($typefilter !== '' && $row['itemtype'] !== $typefilter) {
        return false;
    }
    if ($severityfilter !== '' && $row['severity'] !== $severityfilter) {
        return false;
    }
    if ($agefilter > 0) {
        if (empty($row['timemodified'])) {
            return false;
        }
        $cutoff = time() - ($agefilter * DAYSECS);
        if ((int)$row['timemodified'] > $cutoff) {
            return false;
        }
    }
    return true;
}));

$typekeys = [
    'page' => 'pagetype',
    'book' => 'booktype',
    'bookchapter' => 'bookchaptertype',
    'section' => 'sectiontype',
    'label' => 'labeltype',
    'assign' => 'assigntype',
    'forum' => 'forumtype',
    'quiz' => 'quiztype',
];

foreach ($rows as &$row) {
    $row['typename'] = get_string($typekeys[$row['itemtype']] ?? 'type', 'local_contentfreshness');
    $row['risklabel'] = get_string($row['risktype'], 'local_contentfreshness');
    $row['severitylabel'] = get_string($row['severity'], 'local_contentfreshness');
    $row['classificationlabel'] = get_string($row['classification'], 'local_contentfreshness');
    $row['modifiedlabel'] = !empty($row['timemodified'])
        ? userdate((int)$row['timemodified'])
        : get_string('unknownmodified', 'local_contentfreshness');
    $row['sectionlabel'] = $sections[(int)$row['sectionnum']] ?? (string)$row['sectionnum'];
    $row['itemname'] = format_string($row['itemname']);
    $row['cachedlabel'] = $row['cached'] ? get_string('cached', 'local_contentfreshness') : '';
}
unset($row);

$summary = (object)[
    'items' => $result['itemcount'],
    'candidates' => count($rows),
];

$runurl = new moodle_url('/local/contentfreshness/index.php', [
    'id' => $courseid,
    'run' => 1,
    'sesskey' => sesskey(),
]);
$runbutton = new single_button($runurl, get_string('runaudit', 'local_contentfreshness'), 'post', single_button::BUTTON_PRIMARY);

$templatecontext = [
    'intro' => get_string('intro', 'local_contentfreshness'),
    'summary' => get_string('summary', 'local_contentfreshness', $summary),
    'runbutton' => $OUTPUT->render($runbutton),
    'filterform' => $filterform->render(),
    'hasrows' => !empty($rows),
    'rows' => $rows,
    'noresults' => get_string('noresults', 'local_contentfreshness'),
    'stritem' => get_string('item', 'local_contentfreshness'),
    'strsnippet' => get_string('snippet', 'local_contentfreshness'),
    'strreason' => get_string('reason', 'local_contentfreshness'),
    'strrisktype' => get_string('risktype', 'local_contentfreshness'),
    'strseverity' => get_string('severity', 'local_contentfreshness'),
    'strclassification' => get_string('classification', 'local_contentfreshness'),
    'strmodified' => get_string('modified', 'local_contentfreshness'),
    'straction' => get_string('action', 'local_contentfreshness'),
    'stredit' => get_string('edit', 'local_contentfreshness'),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_contentfreshness'));
echo $OUTPUT->render_from_template('local_contentfreshness/report', $templatecontext);
echo $OUTPUT->footer();
