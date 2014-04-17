<?php

define('CLI_SCRIPT', true);

if (isset($_SERVER['REMOTE_ADDR'])) {
    exit(1);
}

require(dirname(__FILE__) . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');

$CFG->mymaillocalpart = "andrew";
$CFG->mymaildomain = "@nicols.co.uk";

list($options, $unrecognized) = cli_get_params(
    array(
        'courses'   => 20,
        'editors'   => 5,
        'teachers'  => 5,
        'students'  => 100
    ),
    array()
);

$transaction = $DB->start_delegated_transaction();

$generator = new test_data_generator();
$generator->create_test_data($options);

$transaction->allow_commit();

class test_data_generator {

    function create_test_data($options) {
        global $DB;

        $this->check_base_data();

        $courses = array();
        $roles = array();

        for ($i = 1; $i <= $options['courses']; $i++) {
            $data = new stdClass();
            $coursename = 'course-' . $i;
            echo "Creating course $coursename... ";
            $data->category = (isset($data->category)) ? $data->category : 1;
            $data->fullname = (isset($data->fullname)) ? $data->fullname : $coursename;
            $data->shortname = (isset($data->shortname)) ? $data->shortname : $coursename;
            $data->startdate = time();
            if ($DB->record_exists('course', array('shortname' => $data->shortname))) {
                echo "skipping - already exists.\n";
                continue;
            }
            $newcourse = $this->create_course($data);
            $newcourse->context = context_course::instance($newcourse->id, MUST_EXIST);
            $newcourse->enrol = $DB->get_record('enrol', array('courseid' => $newcourse->id, 'enrol' => 'manual'));
            $newcourse->numsections = 5;
            $courses[$coursename] = $newcourse;
            echo "created.\n";
        }

        for ($i = 1; $i <= $options['editors']; $i++) {
            $username = 'editor' . $i;
            $this->create_and_enrol($username, 'editingteacher', $courses);
        }

        for ($i = 1; $i <= $options['teachers']; $i++) {
            $username = 'teacher' . $i;
            $this->create_and_enrol($username, 'teacher', $courses);
        }

        for ($i = 1; $i <= $options['students']; $i++) {
            $username = 'student' . $i;
            $this->create_and_enrol($username, 'student', $courses);
        }
    }

    function check_base_data() {
        global $CFG, $DB;
        $adminuser = $DB->get_record('user', array('username' => 'admin'));
        $adminuser->city = 'Lancaster';
        $adminuser->country = 'GB';
        $adminuser->email = "andrew.nicols+moodletesting@luns.net.uk";
        $adminuser->email = $CFG->mymaillocalpart . '+adminuser' . $CFG->mymaildomain;
        $adminuser->confirmed = 1;

        $DB->update_record('user', $adminuser);

        $frontpagecourse = $DB->get_record('course', array('id' => 1));
        $frontpagecourse->fullname = 'Example';
        $frontpagecourse->shortname = 'example';

        $DB->update_record('course', $frontpagecourse);
    }

    function create_course($coursedata) {
        global $DB;
        $course = create_course($coursedata);
        context_course::instance($course->id);

        if (!empty($coursedata->numsections)) {
            for ($i = 1; $i < $coursedata->numsections; $i++) {
                $section = new stdClass();
                $section->course = $course->id;
                $section->section = $i;
                $section->name = "Section $i";
                $section->summary = "Section $i summary";
                $section->summaryformat = FORMAT_MOODLE;
                $DB->insert_record('course_sections', $section);
            }
        }
        return $course;
    }

    function create_user($name) {
        global $CFG, $DB;

        $newuser = new stdClass();
        $newuser->username = $name;
        $newuser->firstname = $name;
        $newuser->lastname = $name;
        $newuser->city = 'Lancaster';
        $newuser->country = 'GB';
        $newuser->email = $CFG->mymaillocalpart . '+' . $name . $CFG->mymaildomain;
        $newuser->description = "This is a new user with username $name";
        $newuser->mnethostid = $CFG->mnet_localhost_id; // always local user
        $newuser->confirmed  = 1;
        $newuser->timecreated = time();
        $newuser->password = hash_internal_user_password('test');
        $newuser->id = $DB->insert_record('user', $newuser);
        return $newuser;
    }

    function create_and_enrol($username, $role, $courses) {
        global $DB;
        echo "Creating user $username... ";
        if ($user = $DB->get_record('user', array('username' => $username))) {
            echo " already exists.\n";
        } else {
            $user = $this->create_user($username);
            echo " done.\n";
        }

        foreach ($courses as $coursename => $coursedata) {
            echo "Enrolling user $username on $coursename as: ";
            // Enrol users the manual way
            $ue = new stdClass();
            $ue->enrolid = $courses[$coursename]->enrol->id;
            $ue->status = ENROL_USER_ACTIVE;
            $ue->userid = $user->id;
            $ue->timestart = 0;
            $ue->timeend = 0;
            $ue->modifierid = 2;
            $ue->timecreated = time();
            $ue->timemodified = $ue->timecreated;
            $enrolment = $DB->insert_record('user_enrolments', $ue);

            if (!isset($roles[$role])) {
                $roles[$role] = $DB->get_record('role', array('shortname' => $role));
            }
            echo "Assigning role $role to $username in $coursename\n";
            $ra = new stdClass();
            $ra->roleid = $roles[$role]->id;
            $ra->contextid = $courses[$coursename]->context->id;
            $ra->userid = $user->id;
            $ra->modifierid = 2;
            $ra->timemodified = time();
            $DB->insert_record('role_assignments', $ra);
        }
    }
}
