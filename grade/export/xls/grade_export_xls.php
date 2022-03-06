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

require_once($CFG->dirroot.'/grade/export/lib.php');

class grade_export_xls extends grade_export {

    public $plugin = 'xls';

    /**
     * Constructor should set up all the private variables ready to be pulled
     * @param object $course
     * @param int $groupid id of selected group, 0 means all
     * @param stdClass $formdata The validated data from the grade export form.
     */
    public function __construct($course, $groupid, $formdata) {
        parent::__construct($course, $groupid, $formdata);

        // Overrides.
        $this->usercustomfields = true;
    }

    /**
     * To be implemented by child classes
     */
    public function print_grades() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');

        $export_tracking = $this->track_exports();

        $strgrades = get_string('grades');

        // If this file was requested from a form, then mark download as complete (before sending headers).
        \core_form\util::form_download_complete();

        // Calculate file name
        $shortname = format_string($this->course->shortname, true, array('context' => context_course::instance($this->course->id)));
        $downloadfilename = clean_filename("$shortname $strgrades.xls");
        // Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        $workbook->send($downloadfilename);
        // Adding the worksheet
        $myxls = $workbook->add_worksheet($strgrades);

        // Print names of all the fields
        $profilefields = grade_helper::get_user_profile_fields($this->course->id, $this->usercustomfields);
		$profilefields[]='timestarted';
		$profilefields[]='timecompleted';
		$profilefields[]='attempt';
		$profilefields[]='status';
		
		
        foreach ($profilefields as $id => $field) {
			if(is_object($field))
			{
				$myxls->write_string(0, $id, $field->fullname);
			}
            else 
			{
				
				switch($field)
				{
					case 'timestarted':
										$myxls->write_string(0, $id, 'Start Date');
										break;
					case 'timecompleted':
										$myxls->write_string(0, $id, 'Completion Date');
										break;
					case 'attempt':
										$myxls->write_string(0, $id, 'Attempts');
										break;
					case 'status':
										$myxls->write_string(0, $id, 'Status');
										break;
				}
				
			}
        }
		
        $pos = count($profilefields);
        if (!$this->onlyactive) {
            $myxls->write_string(0, $pos++, get_string("suspended"));
        }
		
        foreach ($this->columns as $grade_item) {
            foreach ($this->displaytype as $gradedisplayname => $gradedisplayconst) {
				if( $grade_item->itemtype !='course')
				{
					continue;
				}
				
                $myxls->write_string(0, $pos++, $this->format_column_name($grade_item, false, $gradedisplayname));
            }
            // Add a column_feedback column
            if ($this->export_feedback) {
                $myxls->write_string(0, $pos++, $this->format_column_name($grade_item, true));
            }
        }
        // Last downloaded column header.
    //    $myxls->write_string(0, $pos++, get_string('timeexported', 'gradeexport_xls'));

        // Print all the lines of data.
        $i = 0;
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($this->course, $this->columns, $this->groupid);
        $gui->require_active_enrolment($this->onlyactive);
        $gui->allow_user_custom_fields($this->usercustomfields);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $i++;
            $user = $userdata->user;
			
			/*********** added by Pankaj Wagh ******** dt. 02/09/2021 ************/
					global $DB;
				
					$sql ='SELECT f.id, f.shortname, f.datatype, d.data
                                            FROM {user_info_field} f
                                            JOIN {user_info_data} d ON d.fieldid = f.id
                                           WHERE d.userid = ?';
					$user_profiles = $DB->get_records_sql($sql, array( $userdata->user->id));
					$profileuser = new \stdclass();
					$arr_custom_fields = Array();
					foreach($user_profiles as $user_profile)
					{
						$key = 'profile_field_'.$user_profile->shortname;
						$arr_custom_fields[$user_profile->shortname]=$key;
					    $user->$key = $user_profile->data;
					}
					
					
					
					$sql ='SELECT timeenrolled,timestarted,timecompleted from {course_completions} where course=? and userid=?';
					$user_course_completion = $DB->get_record_sql($sql, array($this->course->id, $user->id));
					
					if($user_course_completion->timestarted > 0)
					{
						$user->timestarted=    userdate($user_course_completion->timestarted, get_string("strftimedatetime", "langconfig"));
					}
					if($user_course_completion->timecompleted > 0)
					{
						$user->timecompleted=  userdate($user_course_completion->timecompleted, get_string("strftimedatetime", "langconfig"));
					}
					$sqltimemodified ='SELECT min(sst.timemodified) as timemodified from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where element="x.start.time" and userid=? and  scm.course=?';
					
					$user_scorm_started = $DB->get_record_sql($sqltimemodified, array($user->id,$this->course->id,));
					if($user_scorm_started->timemodified > 0)
					{
						$user->timestarted=    userdate($user_scorm_started->timemodified, get_string("strftimedatetime", "langconfig"));
					}
					
					$sqltimemodified ='SELECT max(attempt) as attempt from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where  userid=? and  scm.course=?';
					
					$user_scorm_attempt = $DB->get_record_sql($sqltimemodified, array($user->id,$this->course->id));
					if($user_scorm_attempt->attempt > 0)
					{
						$user->attempt=   $user_scorm_attempt->attempt;
					}
					
					$sql = 'SELECT attempt from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where element="cmi.core.score.raw" and   scm.course=? and sst.userid=? order by CAST(`value` as signed) desc';
					
					$user_scorm_attempt = $DB->get_record_sql($sql, array($this->course->id,$user->id));
					
					
				
					
					$sqltimemodified ='SELECT max(sst.timemodified) as timemodified from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where element="cmi.core.lesson_status" and lower(value) in ("Passed","Completed") and  scm.course=? and sst.userid=? and attempt =?';
					
					
					$user_scorm_completed = $DB->get_record_sql($sqltimemodified, array($this->course->id,$user->id,intval($user_scorm_attempt->attempt)));
					if($user_scorm_completed->timemodified > 0)
					{
						$user->timecompleted=    userdate($user_scorm_completed->timemodified, get_string("strftimedatetime", "langconfig"));
					}
					
					
					$sqlstatus ='SELECT value from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where element="cmi.core.lesson_status" and  scm.course=? and sst.userid=? and attempt =?';
					
					$user->status='Not started';  
					$user_scorm_status = $DB->get_record_sql($sqlstatus, array($this->course->id,$user->id,intval($user_scorm_attempt->attempt)));
					
					if($user_scorm_status->value !='')
					{
						$user->status=ucwords($user_scorm_status->value);    
					}
					else
					{
						$sqlstatus ='SELECT value from {scorm_scoes_track} sst inner join {scorm} scm on sst.scormid = scm.id where element="cmi.core.lesson_status" and  scm.course=? and sst.userid=? ';
						$user_scorm_status = $DB->get_record_sql($sqlstatus, array($this->course->id,$user->id));
					
							if($user_scorm_status->value !='')
							{
								$user->status=ucwords($user_scorm_status->value);    
							}
					}
			/*********** added by Pankaj Wagh ******** dt. 02/09/2021 ************/
			
			//print_object($profilefields);
			//print_object($arr_custom_fields);
			

            foreach ($profilefields as $id => $field) {
				
				
				if($field == 'timestarted')
				{
					$fieldvalue = $user->timestarted;
				}
				else if($field == 'timecompleted')
				{
					$fieldvalue = $user->timecompleted;
				}
				else if($field == 'attempt')
				{
					$fieldvalue = $user->attempt;
				}
				else if($field == 'status')
				{
					$fieldvalue = $user->status;
				}
				else if($field->shortname =='idnumber')
				{
					$fieldvalue = $user->username;
				
				}
				else if(trim($arr_custom_fields[$field->shortname]) !='')
				{
					$key = 'profile_field_'.$field->shortname;
					$fieldvalue = $user->$key;
				}
				else if($field->shortname =='lastname')
				{
					continue;
				}
				
				
				else
				{
					$fieldvalue = grade_helper::get_user_field_value($user, $field);
				}
				
				
               
                $myxls->write_string($i, $id, $fieldvalue);
            }
			
			
			
            $j = count($profilefields);
            if (!$this->onlyactive) {
                $issuspended = ($user->suspendedenrolment) ? get_string('yes') : '';
                $myxls->write_string($i, $j++, $issuspended);
            }
		
			
            foreach ($userdata->grades as $itemid => $grade) {
				
				if( $grade->grade_item->itemtype !='course')
				{
					continue;
				}
                if ($export_tracking) {
                    $status = $geub->track($grade);
                }
				
                foreach ($this->displaytype as $gradedisplayconst) {
                    $gradestr = $this->format_grade($grade, $gradedisplayconst);
                    if (is_numeric($gradestr)) {
                        $myxls->write_number($i, $j++, $gradestr);
                    } else {
                        $myxls->write_string($i, $j++, $gradestr);
                    }
					
                }
                // writing feedback if requested
                if ($this->export_feedback) {
                    $myxls->write_string($i, $j++, $this->format_feedback($userdata->feedbacks[$itemid], $grade));
                }
            }
            // Time exported.
          //  $myxls->write_string($i, $j++, date("Y-m-d H:i:s"));
        }
        $gui->close();
        $geub->close();

    /// Close the workbook
        $workbook->close();

        exit;
    }
}


