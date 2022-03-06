<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/user/lib.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/user/user_bulk_forms.php');


$SESSION->bulk_users = array();
// Create the user filter form.
$ufiltering = new user_filtering();

 list($extrasql, $params) = $ufiltering->get_sql_filter();
    $users = get_users_listing();

foreach( $users as $user)
{
	$SESSION->bulk_users[$user->id] = $user->id;
}
$returnurl=new moodle_url('/admin/user/user_bulk_download.php');
 redirect($returnurl);
