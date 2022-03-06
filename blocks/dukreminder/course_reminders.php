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
 * course_reminders.php
 *
 * @package    block_dukreminder
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 * @author       Florian Jungwirth <fjungwirth@gtn-solutions.com>
 * @ideaandconcept Gerhard Schwed <gerhard.schwed@donau-uni.ac.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__)."/inc.php");
global $DB, $OUTPUT, $PAGE, $cg;
require_once($CFG->libdir . "/tablelib.php");

$courseid = required_param('courseid', PARAM_INT);
$sorting = optional_param('sorting', 'id', PARAM_TEXT);
$sorttype = optional_param('type', 'asc', PARAM_TEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_simplehtml', $courseid);
}

require_login($course);

$context = context_course::instance($courseid);
require_capability('block/dukreminder:use', $context);

/* DELETE */
if (($deleteid = optional_param('delete', 0, PARAM_INT)) > 0) {
    $deleterecord = $DB->get_record('block_dukreminder', array('id' => $deleteid));
    if ($deleterecord->courseid == $courseid) {
        $DB->delete_records('block_dukreminder', array('id' => $deleteid));
    };
}

$pageidentifier = 'tab_course_reminders';

$PAGE->set_url('/blocks/dukreminder/course_reminders.php', array('courseid' => $courseid));
$PAGE->set_heading(get_string('pluginname', 'block_dukreminder'));
$PAGE->set_title(get_string($pageidentifier, 'block_dukreminder'));

// Build breadcrumbs navigation.
$coursenode = $PAGE->navigation->find($courseid, navigation_node::TYPE_COURSE);
$blocknode = $coursenode->add(get_string('pluginname', 'block_dukreminder'));
$pagenode = $blocknode->add(get_string($pageidentifier, 'block_dukreminder'), $PAGE->url);
$pagenode->make_active();

// Build tab navigation & print header.
echo $OUTPUT->header();
echo $OUTPUT->tabtree(block_dukreminder_build_navigation_tabs($courseid), $pageidentifier);

/* CONTENT REGION */
$status = array(0 => get_string('form_to_status_all', 'block_dukreminder'),
    1 => get_string('form_to_status_completed', 'block_dukreminder'),
    2 => get_string('form_to_status_notcompleted', 'block_dukreminder'));
$table = new html_table();

$table->head = array(html_writer::link($PAGE->url . "&sorting=title", get_string('form_title', 'block_dukreminder')),
        html_writer::link($PAGE->url . "&sorting=subject", get_string('form_subject', 'block_dukreminder')),
        html_writer::link($PAGE->url . "&sorting=dateabsolute&type=desc", get_string('form_dateabsolute', 'block_dukreminder')),
    // HIDE  html_writer::link($PAGE->url . "&sorting=to_status&type=desc", get_string('form_to_status', 'block_dukreminder')),.
        html_writer::link($PAGE->url . "&sorting=criteria&type=desc", get_string('form_criteria', 'block_dukreminder')),
        html_writer::link($PAGE->url . "&sorting=mailssent&type=desc", get_string('form_mailssent', 'block_dukreminder')),
        '');

$data = $DB->get_records('block_dukreminder',
    array('courseid' => $courseid),
    $sorting . ' ' . $sorttype,
    'id, title, subject, dateabsolute, criteria, mailssent');
foreach ($data as $record) {
    $record->dateabsolute = ($record->dateabsolute > 0) ? date('d.m.Y', $record->dateabsolute) : '-';
    // HIDE $record->to_status = $status[$record->to_status];.
    $record->criteria = block_dukreminder_get_criteria($record->criteria);
    $record->actions =
        html_writer::link(
            new moodle_url('/blocks/dukreminder/new_reminder.php', array('courseid' => $COURSE->id, 'reminderid' => $record->id)),
            $OUTPUT->pix_icon("t/editstring", "edit"))
        .' '.html_writer::link(
            new moodle_url('/blocks/dukreminder/course_reminders.php',
                array('courseid' => $COURSE->id, 'sorting' => $sorting, 'delete' => $record->id)),
            $OUTPUT->pix_icon("t/delete", "delete"),
            array("onclick" => "return confirm('".get_string('form_delete', 'block_dukreminder')."')"));

    // Don't display id, it is only used for the delete link.
    unset($record->id);
}
$table->data = $data;
echo html_writer::table($table);

/* END CONTENT REGION */

echo $OUTPUT->footer();