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
 * Plugin settings.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_contentfreshness', get_string('pluginname', 'local_contentfreshness'));

    $settings->add(new admin_setting_configtext(
        'local_contentfreshness/maxurlchecks',
        get_string('maxurlchecks', 'local_contentfreshness'),
        get_string('maxurlchecks_desc', 'local_contentfreshness'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_contentfreshness/linktimeout',
        get_string('linktimeout', 'local_contentfreshness'),
        get_string('linktimeout_desc', 'local_contentfreshness'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_contentfreshness/maxaicandidates',
        get_string('maxaicandidates', 'local_contentfreshness'),
        get_string('maxaicandidates_desc', 'local_contentfreshness'),
        100,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
