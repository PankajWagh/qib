<?php
require_once("$CFG->libdir/formslib.php");

class filtergradehtml_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
       
        $mform = $this->_form; // Don't forget the underscore! 
		$departmentoptions = $divisionoptions = $groupoptions = $statusoptions = Array();
		
		
		$sql = "SELECT  d.id,d.data,f.shortname
                                            FROM {user_info_field} f
                                            JOIN {user_info_data} d ON d.fieldid = f.id
											where d.data !='' group by f.id, d.data order by f.shortname, d.data";
											
		global  $DB,$SESSION;
		
		$arr_data = $DB->get_records_sql($sql);
		$departmentoptions['']='All';
		$divisionoptions['']='All';
		$groupoptions['']='All';
		$statusoptions['']='All';

		foreach($arr_data as $data) 
		{
			if($data->shortname=='department')
			{
				$departmentoptions[$data->data]=$data->data;
			}
			if($data->shortname=='division')
			{
				$divisionoptions[$data->data]=$data->data;
			}
			if($data->shortname=='group')
			{
				$groupoptions[$data->data]=$data->data;
			}
		}
		ksort($departmentoptions);
		ksort($divisionoptions);
		ksort($groupoptions);
		/*$sql = "SELECT distinct value FROM mdl_scorm_scoes_track where element ='cmi.core.lesson_status' ";									
		global  $DB;
		
		$arr_statuses = $DB->get_records_sql($sql);
		foreach($arr_statuses as $status) 
		{
			$statusoptions[$status->value]=ucwords($status->value);	
		}
		ksort($statusoptions);
		*/
		$statusoptions = Array();
		$statusoptions['All']='All';
		$statusoptions['completed']='Completed';
		$statusoptions['notcompleted']='Not completed';
		
		
		$graderreportsdepartment   = optional_param('department', null, PARAM_NOTAGS);
		$graderreportsdivision   = optional_param('division', null, PARAM_NOTAGS);
		$graderreportsgroup   = optional_param('group', null, PARAM_NOTAGS);
		$graderreportsstatus   = optional_param('status', null, PARAM_NOTAGS);
		$graderreportssearch   = optional_param('usersearch', null, PARAM_NOTAGS);
		$completiontimestart = optional_param_array('completiontimestart', 0, PARAM_INT);
		$completiontimeend = optional_param_array('completiontimeend', 0, PARAM_INT);
		
		if (empty($graderreportsdepartment) && !empty($SESSION->gradereport['department'])) {
			$graderreportsdepartment = $SESSION->gradereport['department'];
		}

		if (empty($graderreportsdivision) && !empty($SESSION->gradereport['division'])) {
			$graderreportsdivision = $SESSION->gradereport['division'];
		}
		if (empty($graderreportsstatus) && !empty($SESSION->gradereport['status'])) {
			$graderreportsstatus = $SESSION->gradereport['status'];
		}
		if (empty($graderreportsgroup) && !empty($SESSION->gradereport['group'])) {
			$graderreportsgroup = $SESSION->gradereport['group'];
		}
		if (empty($graderreportssearch) && !empty($SESSION->gradereport['usersearch'])) {
			$completiontimestart = $SESSION->gradereport['completiontimestart'];
		}
		
		if (empty($completiontimestart) && !empty($SESSION->gradereport['completiontimestart'])) {
			$graderreportssearch = $SESSION->gradereport['usersearch'];
		}
		
		if (empty($completiontimeend) && !empty($SESSION->gradereport['completiontimeend'])) {
			$completiontimeend = $SESSION->gradereport['completiontimeend'];
		}

		$seldepartment = $mform->addElement('select', 'department', 'Department', $departmentoptions);
		$seldivision = $mform->addElement('select', 'division','Division', $divisionoptions);
		$selgroup = $mform->addElement('select', 'group', 'Group', $groupoptions);
		$selstatus = $mform->addElement('select', 'status', 'Status', $statusoptions);
		$selusersearch = $mform->addElement('text', 'usersearch', 'Name');
	
		$mform->addElement('date_selector', 'completiontimestart', get_string('from'),array('optional' => true));
		
		$mform->disabledIf('completiontimestart', 'chkcompletiontimestart', 'checked');
		$course_id = optional_param('id',0, PARAM_INT);
		$sql = "SELECT * from {course} where id='".$course_id."'";		
		$course = $DB->get_record_sql($sql);
		
		if($course->startdate > 0)
		{
			$mform->setDefault('completiontimestart',$course->startdate);			
		}
		
		$mform->addElement('date_selector', 'completiontimeend', get_string('to'),array('optional' => true));
		
		$mform->setDefault('completiontimeend', time() + 3600 * 24);
		$mform->disabledIf('completiontimeend', 'chkcompletiontimeend', 'checked');

		$mform->setDefault('department',$graderreportsdepartment);
		$mform->setDefault('division',$graderreportsdivision);
		$mform->setDefault('group',$graderreportsgroup);
		$mform->setDefault('status',$graderreportsstatus);
		$mform->setDefault('usersearch',$graderreportssearch );
		
        $this->add_action_buttons(true, get_string('search'));
    }
    //Custom validation should be added here
    function validation($data, $files) {
		$errors = Array();
		if ($data['completiontimestart'] != 0 && $data['completiontimeend'] != 0 && $data['completiontimeend'] < $data['completiontimestart']) {
			$errors['completiontimeend'] = 'Start Date is should be greater than End Date';
			}
        return array();
    }
	 public function reset() {
		 unset($SESSION->gradereport);
	 }
}	
?>