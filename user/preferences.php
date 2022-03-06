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
 * Preferences.
 *
 * @package    core_user
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/navigationlib.php');

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$userid = optional_param('userid', $USER->id, PARAM_INT);
$currentuser = $userid == $USER->id;

// Check that the user is a valid user.
$user = core_user::get_user($userid);
if (!$user || !core_user::is_real_user($userid)) {
    throw new moodle_exception('invaliduser', 'error');
}

$PAGE->set_context(context_user::instance($userid));
$PAGE->set_url('/user/preferences.php', array('userid' => $userid));
$PAGE->set_pagelayout('admin');
$PAGE->set_pagetype('user-preferences');
$PAGE->set_title(get_string('preferences'));
$PAGE->set_heading(fullname($user));
$PAGE->requires->jquery();
if (!$currentuser) {
    $PAGE->navigation->extend_for_user($user);
    // Need to check that settings exist.
    if ($settings = $PAGE->settingsnav->find('userviewingsettings' . $user->id, null)) {
        $settings->make_active();
    }
    $url = new moodle_url('/user/preferences.php', array('userid' => $userid));
    $navbar = $PAGE->navbar->add(get_string('preferences', 'moodle'), $url);
    // Show an error if there are no preferences that this user has access to.
    if (!$PAGE->settingsnav->can_view_user_preferences($userid)) {
        throw new moodle_exception('cannotedituserpreferences', 'error');
    }
} else {
    // Shutdown the users node in the navigation menu.
    $usernode = $PAGE->navigation->find('users', null);
    $usernode->make_inactive();

    $settings = $PAGE->settingsnav->find('usercurrentsettings', null);
    $settings->make_active();
}

// Identifying the nodes.
$groups = array();
$orphans = array();
foreach ($settings->children as $setting) {
    if ($setting->has_children()) {
        $groups[] = new preferences_group($setting->get_content(), $setting->children);
    } else {
        $orphans[] = $setting;
    }
}
if (!empty($orphans)) {
    $groups[] = new preferences_group(get_string('miscellaneous'), $orphans);
}
$preferences = new preferences_groups($groups);

echo $OUTPUT->header();



echo $OUTPUT->heading(get_string('preferences'));
echo $OUTPUT->render($preferences);
/************** added by Pankaj Wagh ******27/Sep/2021*************/
echo html_writer::start_tag('fieldset');
echo html_writer::tag('div', '<!-- -->', array('class' => 'clearer'));
echo '<div class="checkbox-container" data-region="disable-notification-container">
        <input id="disable-notifications"
           type="checkbox"
           data-disable-notifications'.($USER->emailstop==1?' checked':'').'
            />
        <label for="disable-notifications">Disable notifications (قم بإيقاف تشغيل التنبيهات)</label>
        <span class="loading-icon icon-no-margin"><i class="icon fa fa-circle-o-notch fa-spin fa-fw "  title="Loading" aria-label="Loading"></i></span>
    </div>';
echo html_writer::end_tag('fieldset');
?>
<script >
jQuery( document ).ready(function() {
	jQuery(".loading-icon").hide();
    console.log( "ready!" );
	
	   
		 $('#disable-notifications').click(function() {
			 var ischecked = $(this).prop('checked');
			 var checkbox = $('[data-region="disable-notification-container"][data-disable-notifications]');
			var container = $('[data-region="disable-notification-container"]');
			var ischecked = $(this).prop('checked');
			  container.addClass('loading');
			console.log(ischecked);
				var request = {
					index:0,
					methodname: 'core_user_update_user_preferences',
					args: {
						userid: <?php echo $USER->id;?>,
						emailstop: ischecked ? 1 : 0,
					}
				};
				
				$.ajax({
				url: M.cfg.wwwroot+"/lib/ajax/service.php?sesskey="+M.cfg.sesskey+"&info=core_user_update_user_preferences",
				type: 'POST',
				data: "["+JSON.stringify(request)+"]",
				contentType: "application/json; charset=utf-8"
				})
				.done(function(request){
					console.log( request );
					
				});
				
				
		});
		  
});
</script>
<?php
/************** added by Pankaj Wagh ******27/Sep/2021*************/
echo $OUTPUT->footer();
