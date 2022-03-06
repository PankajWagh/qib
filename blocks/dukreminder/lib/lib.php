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
 * Collection of useful functions and constants
 *
 * @package    block_dukreminder
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 * @author       Florian Jungwirth <fjungwirth@gtn-solutions.com>
 * @ideaandconcept Gerhard Schwed <gerhard.schwed@donau-uni.ac.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('BLOCK_DUKREMINDER_COMPLETION_STATUS_ALL', 0);
define('BLOCK_DUKREMINDER_COMPLETION_STATUS_COMPLETED', 1);
define('BLOCK_DUKREMINDER_COMPLETION_STATUS_NOTCOMPLETED', 2);

define('BLOCK_DUKREMINDER_PLACEHOLDER_COURSENAME', '###coursename###');
define('BLOCK_DUKREMINDER_PLACEHOLDER_USERNAME', '###username###');
define('BLOCK_DUKREMINDER_PLACEHOLDER_USERMAIL', '###usermail###');
define('BLOCK_DUKREMINDER_PLACEHOLDER_USERCOUNT', '###usercount###');
define('BLOCK_DUKREMINDER_PLACEHOLDER_USERS', '###users###');

define('BLOCK_DUKREMINDER_CRITERIA_COMPLETION', 250000);
define('BLOCK_DUKREMINDER_CRITERIA_ENROLMENT', 250001);
define('BLOCK_DUKREMINDER_CRITERIA_ALL', 250002);

// SHOULD BE CHANGED.
define('BLOCK_DUKREMINDER_EMAIL_DUMMY', 2);

/**
 * Build navigation tabs
 * @param integer $courseid
 */
function block_dukreminder_build_navigation_tabs($courseid) {

    $rows[] = new tabobject('tab_course_reminders',
        new moodle_url('/blocks/dukreminder/course_reminders.php',
        array("courseid" => $courseid)),
        get_string('tab_course_reminders', 'block_dukreminder'));
    $rows[] = new tabobject('tab_new_reminder',
        new moodle_url('/blocks/dukreminder/new_reminder.php',
        array("courseid" => $courseid)),
        get_string('tab_new_reminder', 'block_dukreminder'));
    return $rows;
}

/**
 * Init Js and CSS
 * @return nothing
 */
function block_dukreminder_init_js_css() {

}
/**
 * This function gets all the pending reminder entries. An entry is pending
 * if dateabsolute is set and it is not sent yet (sent = 0)
 * OR
 * if daterelative is set
 *
 * @return array $entries
 */
function block_dukreminder_get_pending_reminders() {
    global $DB;
    $entries = $DB->get_records('block_dukreminder', array('sent' => 0));
    $now = time();

    $entries = $DB->get_records_select('block_dukreminder',
        "(sent = 0 AND dateabsolute > 0 AND dateabsolute < $now) OR (dateabsolute = 0 AND daterelative > 0)");

    return $entries;
}

/**
 * Replace placeholders
 * @param string $text
 * @param string $coursename
 * @param string $username
 * @param string $usermail
 * @param string $users
 * @param string $usercount
 * @return nothing
 */
function block_dukreminder_replace_placeholders($text, $coursename = '', $username = '',
            $usermail = '', $users = '', $usercount = '') {

    $text = str_replace(BLOCK_DUKREMINDER_PLACEHOLDER_COURSENAME, $coursename, $text);
    $text = str_replace(BLOCK_DUKREMINDER_PLACEHOLDER_USERMAIL, $usermail, $text);
    $text = str_replace(BLOCK_DUKREMINDER_PLACEHOLDER_USERNAME, $username, $text);
    $text = str_replace(BLOCK_DUKREMINDER_PLACEHOLDER_USERCOUNT, $usercount, $text);
    $text = str_replace(BLOCK_DUKREMINDER_PLACEHOLDER_USERS, $users, $text);

    return $text;
}

/**
 * This function filters the users to recieve a reminder according to the
 * criterias recorded in the database.
 * The criterias are:
 *  - deadline: amount of sec after course enrolment
 *  - groups: user groups specified in the course
 *  - completion status: if users have already completed/not completed the course
 *
 * @param stdClass $entry database entry of block_dukreminder table
 * @return array $users users to recieve a reminder
 */
function block_dukreminder_filter_users($entry) {
    global $DB;

    // All potential users.
    $users = get_role_users(5, context_course::instance($entry->courseid));

    if ($entry->dateabsolute > 0) {
        // Course completion.
        if ($entry->criteria == BLOCK_DUKREMINDER_CRITERIA_COMPLETION) {
            foreach ($users as $user) {
                $select = "course = $entry->courseid AND userid = $user->id";
                $timecompleted = $DB->get_field_select('course_completions', 'timecompleted', $select);
                // If user has completed and status is "not completed" -> unset.
                if (($timecompleted)) {
                    $timecompleted = date("d.m.Y", $timecompleted);
                    unset($users[$user->id]);
                }
            }
        } else if ($entry->criteria != BLOCK_DUKREMINDER_CRITERIA_ALL) { // Criteria (activity) completion.
            $course = $DB->get_record('course', array('id' => $entry->courseid));
            $completion = new completion_info($course);
            $criteria = completion_criteria::factory((array)$DB->get_record('course_completion_criteria',
                array('id' => $entry->criteria)));

            foreach ($users as $user) {
                $usercompleted = $completion->get_user_completion($user->id, $criteria);
                if ($usercompleted->is_complete()) {
                    unset($users[$user->id]);
                }
            }
        }
    }

    // Filter users by deadline.
    if ($entry->daterelative > 0 && $entry->criteria == BLOCK_DUKREMINDER_CRITERIA_ENROLMENT) {
        // If reminder has relative date: check if user has already got an email.
        $mailssent = $DB->get_records('block_dukreminder_mailssent', array('reminderid' => $entry->id), '', 'userid');

        $enabledenrolplugins = implode(',', $DB->get_fieldset_select('enrol', 'id', "courseid = $entry->courseid"));
        // Check user enrolment dates.
        foreach ($users as $user) {
            // If user has already got an email -> unset.
            if (array_key_exists($user->id, $mailssent)) {
                unset($users[$user->id]);
            }

            $enrolmenttime = $DB->get_field_select('user_enrolments',
                'timestart',
                "userid = $user->id AND enrolid IN ($enabledenrolplugins)");
            // If user is longer enroled than the deadline is long -> unset.
            if ($enrolmenttime + $entry->daterelative > time()) {
                unset($users[$user->id]);
            }
        }
    }

    // Filter users by deadline.
    if ($entry->daterelative > 0 && $entry->criteria == BLOCK_DUKREMINDER_CRITERIA_COMPLETION) {
        // If reminder has relative date: check if user has already got an email.
        $mailssent = $DB->get_records('block_dukreminder_mailssent', array('reminderid' => $entry->id), '', 'userid');

        // Check user completion dates.
        foreach ($users as $user) {
            // If user has already got an email -> unset.
            if (array_key_exists($user->id, $mailssent)) {
                unset($users[$user->id]);
            }

            $completiontime = $DB->get_field('course_completions',
                'timecompleted',
                array('userid' => $user->id, 'course' => $entry->courseid));
            // If user completion is not long enough ago -> unset.
            if (!isset($completiontime) || ($completiontime + $entry->daterelative > time())) {
                unset($users[$user->id]);
            }
        }
    }

    // Filter users by deadline.
    if ($entry->daterelative > 0 && $entry->criteria != BLOCK_DUKREMINDER_CRITERIA_COMPLETION && $entry->criteria != BLOCK_DUKREMINDER_CRITERIA_ENROLMENT) {
        // If reminder has relative date: check if user has already got an email.
        $mailssent = $DB->get_records('block_dukreminder_mailssent', array('reminderid' => $entry->id), '', 'userid');

        $course = $DB->get_record('course', array('id' => $entry->courseid));
        $completion = new completion_info($course);
        $criteria = completion_criteria::factory((array)$DB->get_record('course_completion_criteria',
            array('id' => $entry->criteria)));

        // Check user completion dates.
        foreach ($users as $user) {
            // If user has already got an email -> unset.
            if (array_key_exists($user->id, $mailssent)) {
                unset($users[$user->id]);
            }

            $usercompleted = $completion->get_user_completion($user->id, $criteria);
            $usercompleted->timecompleted;
            // If user criteria completion is not long enough ago -> unset.
            if (!isset($usercompleted->timecompleted) || ($usercompleted->timecompleted + $entry->daterelative > time())) {
                unset($users[$user->id]);
            }
        }
    }
    // Filter users by groups: REVERSED, send to users that are not in the groups.
    $groupids = explode(';', $entry->to_groups);
    if ($entry->to_groups) {
        foreach ($users as $user) {
            // If user is  part in 1 or more group -> unset.
            $ismember = false;
            foreach ($groupids as $groupid) {
                if (groups_is_member($groupid, $user->id)) {
                    $ismember = true;
                }
            }

            if ($ismember) {
                unset($users[$user->id]);
            }
        }
    }
    /*
    // Filter users by groups.
    $groupids = explode(';',$entry->to_groups);
    if($entry->to_groups) {
        foreach($users as $user) {
            //if user is not part in at least 1 group -> unset
            $ismember = false;
            foreach($groupids as $group_id)
                if(groups_is_member($group_id,$user->id))
                $ismember = true;

            if(!$ismember) {
                unset($users[$user->id]);
            }
        }
    }
    */
    /*filter users by completion status (if not daterelativ_completion is set)
    if($entry->to_status != BLOCK_DUKREMINDER_COMPLETION_STATUS_ALL && $entry->daterelative_completion == 0) {
        foreach ($users as $user) {
            $select = "course = $entry->courseid AND userid = $user->id";
            $timecompleted = $DB->get_field_select('course_completions', 'timecompleted', $select);
            //if user has completed and status is "not completed" -> unset
            //if user has not completed and status is "completed" -> unset
            if (($timecompleted && $entry->to_status == BLOCK_DUKREMINDER_COMPLETION_STATUS_NOTCOMPLETED) ||
                    (!$timecompleted && $entry->to_status == BLOCK_DUKREMINDER_COMPLETION_STATUS_COMPLETED)) {
                $timecompleted = date("d.m.Y", $timecompleted);
                unset($users[$user->id]);
            }
        }
    }*/

    return $users;
}

/**
 * Get manager
 * @param object $user
 * @return boolean
 */
function block_dukreminder_get_manager($user) {
    global $DB;
    // Bestimme Vorgesetzten (= Manager) zum User
    if (isset($user->address)) { // Vorgesetzte stehen in Moodle im Adressfeld des Users
        $manager = addslashes(substr($user->address, 0, 50)) . "%"; // addslashes wegen ' in manchen Usernamen
        // Suche userid des Vorgesetzten in mdl_user.
        $select = "idnumber LIKE '$manager'";
        $managerid = $DB->get_field_select('user', 'id', $select);

        // Hole Details des Vorgesetzten aus mdl_user.
        return $DB->get_record('user', array('id' => $managerid));
        /* $managers[$managerid]->username = $DB->get_field_select('user', 'username', $select);
         $managers[$managerid]->firstname = $DB->get_field_select('user', 'firstname', $select);
        $managers[$managerid]->lastname = $DB->get_field_select('user', 'lastname', $select);
        $managers[$managerid]->email = $DB->get_field_select('user', 'email', $select);
        */
    }
    return false;
}

/**
 * Replace placeholders
 * @param string $course
 * @param array $users
 * @param boolean $textteacher
 * @return nothing
 */
function block_dukreminder_get_mail_text($course, $users, $textteacher = null) {

    $userlisting = '';
    foreach ($users as $user) {
        $userlisting .= "\n" . fullname($user);
    }

    // If text_teacher is not set, use lang string (for old reminders).
    if (!$textteacher) {
        $textparams = new stdClass();
        $textparams->amount = count($users);
        $textparams->course = $course;

        $mailtext = get_string('email_teacher_notification', 'block_dukreminder', $textparams);
        $mailtext .= $userlisting;
    } else {
        // If text_teacher is set, use it and replace placeholders.
        $mailtext = block_dukreminder_replace_placeholders($textteacher, $course, '', '', $userlisting, count($users));
        $mailtext = strip_tags($mailtext);
    }

    return $mailtext;
}

/**
 * Get course teachers
 * @param string $coursecontext
 * @return array
 */
function block_dukreminder_get_course_teachers($coursecontext) {
    return array_merge(get_role_users(4, $coursecontext),
        get_role_users(3, $coursecontext),
        get_role_users(2, $coursecontext),
        get_role_users(1, $coursecontext));
}

/**
 * Get criteria
 * @param string $entry
 * @return string
 */
function block_dukreminder_get_criteria($entry) {
    global $DB;

    if ($entry == BLOCK_DUKREMINDER_CRITERIA_COMPLETION) {
        return get_string('criteria_completion', 'block_dukreminder');
    };
    if ($entry == BLOCK_DUKREMINDER_CRITERIA_ENROLMENT) {
        return get_string('criteria_enrolment', 'block_dukreminder');
    };
    if ($entry == BLOCK_DUKREMINDER_CRITERIA_ALL) {
        return get_string('criteria_all', 'block_dukreminder');
    }

    $completioncriteriaentry = $DB->get_record('course_completion_criteria', array('id' => $entry));
    $mod = get_coursemodule_from_id($completioncriteriaentry->module, $completioncriteriaentry->moduleinstance);

    return $mod->name;
}