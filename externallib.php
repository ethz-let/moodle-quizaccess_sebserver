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
 * External Web Service for SEB Auth Key
 *
 * @package    quizaccess_sebserver
 * @copyright  2022 ETH Zurich - amr.hourani@id.ethz.ch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
/**
 * Service functions.
 */
class quizaccess_sebserver_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function backup_course_parameters() {
        return new external_function_parameters(
                        array('id' => new external_value(PARAM_INT, 'Course ID')));

    }

    /**
     * Backup course.
     *
     * @param string $id Course ID.
     * @return array
     */
    public static function backup_course($id) {
        global $USER, $DB, $CFG;
        // Parameter validation.
        $params = self::validate_parameters(self::backup_course_parameters(), array('id' => $id));
        if ($id == 1) {
            throw new moodle_exception('accessnotallowed');
        }
        $course = $DB->get_record('course', array('id' => $id), 'id', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        $contextid = $coursecontext->id;

        // Capability checking.
        if (!has_capability('moodle/backup:backupcourse', $coursecontext)) {
            throw new moodle_exception('accessnotallowed');
        }

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot .'/backup/util/helper/backup_cron_helper.class.php');
        $starttime = time();
        $userid = get_admin()->id;
        $warnings = array();
        $bkupdata = array();

        $outcome = backup_cron_automated_helper::BACKUP_STATUS_OK;
        $config = get_config('backup');
        $dir = $config->backup_auto_destination;
        $storage = (int) $config->backup_auto_storage;

        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_AUTOMATED, $userid);

        try {

            // Set the default filename.
            $format = $bc->get_format();
            $type = $bc->get_type();
            $id = $bc->get_id();
            $users = $bc->get_plan()->get_setting('users')->get_value();
            $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
            $incfiles = (bool) $config->backup_auto_files;
            $backupvaluename = backup_plan_dbops::get_default_backup_filename($format, $type,
                $id, $users, $anonymised, false, $incfiles);
            $bc->get_plan()->get_setting('filename')->set_value($backupvaluename);

            $bc->set_status(backup::STATUS_AWAITING);

            $bc->execute_plan();
            $results = $bc->get_results();
            $outcome = backup_cron_automated_helper::outcome_from_results($results);
            $file = $results['backup_destination']; // May be empty if file already moved to target location.

            // If we need to copy the backup file to an external dir and it is not writable, change status to error.
            // This is a feature to prevent moodledata to be filled up and break a site when the admin misconfigured
            // the automated backups storage type and destination directory.
            if ($storage !== 0 && (empty($dir) || !file_exists($dir) || !is_dir($dir) || !is_writable($dir))) {
                $bc->log('Specified backup directory is not writable - ', backup::LOG_ERROR, $dir);
                $dir = null;
                $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
                $warnings[] = array(
                    'item' => 'backup',
                    'itemid' => $course->id,
                    'warningcode' => 'notwritabledir',
                    'message' => 'Specified backup directory is not writable - ' . $dir
                );
            }

            // Copy file only if there was no error.
            if ($file && !empty($dir) && $storage !== 0 && $outcome != backup_cron_automated_helper::BACKUP_STATUS_ERROR) {
                $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $course->id, $users, $anonymised,
                    !$config->backup_shortname);
                if (!$file->copy_content_to($dir . '/' . $filename)) {
                    $bc->log('Attempt to copy backup file to the specified directory failed - ',
                        backup::LOG_ERROR, $dir);
                    $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
                    $warnings[] = array(
                        'item' => 'backup',
                        'itemid' => $course->id,
                        'warningcode' => 'copyfailed',
                        'message' => 'Attempt to copy backup file to the specified directory failed - ' . $dir
                    );
                }
                if ($outcome != backup_cron_automated_helper::BACKUP_STATUS_ERROR && $storage === 1) {
                    if (!$file->delete()) {
                        $outcome = backup_cron_automated_helper::BACKUP_STATUS_WARNING;
                        $bc->log('Attempt to delete the backup file from course automated backup area failed - ',
                            backup::LOG_WARNING, $file->get_filename());
                        $warnings[] = array(
                            'item' => 'backup',
                            'itemid' => $course->id,
                            'warningcode' => 'deletefailed',
                            'message' => 'Attempt to delete the backup file from course automated backup area failed - ' . $dir
                        );
                    }
                }
            }

        } catch (moodle_exception $e) {
            $bc->log('backup_auto_failed_on_course', backup::LOG_ERROR, $course->shortname); // Log error header.
            $bc->log('Exception: ' . $e->errorcode, backup::LOG_ERROR, $e->a, 1); // Log original exception problem.
            $bc->log('Debug: ' . $e->debuginfo, backup::LOG_DEBUG, null, 1); // Log original debug information.
            $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
            $warnings[] = array(
                'item' => 'backup',
                'itemid' => $course->id,
                'warningcode' => 'backup_auto_failed_on_course',
                'message' => $e->errorcode . ' - ' . $e->debuginfo
            );
        }

        // Delete the backup file immediately if something went wrong.
        if ($outcome === backup_cron_automated_helper::BACKUP_STATUS_ERROR) {

            // Delete the file from file area if exists.
            if (!empty($file)) {
                $file->delete();
            }

            // Delete file from external storage if exists.
            if ($storage !== 0 && !empty($filename) && file_exists($dir . '/' . $filename)) {
                @unlink($dir . '/' . $filename);
            }
        }

        $bc->destroy();
        unset($bc);

        if ($outcome == backup_cron_automated_helper::BACKUP_STATUS_ERROR ||
            $outcome == backup_cron_automated_helper::BACKUP_STATUS_UNFINISHED) {
            // Reset unfinished to error.
            $backupcourse->laststatus = \backup_cron_automated_helper::BACKUP_STATUS_ERROR;
            throw new moodle_exception('Automated backup for course: ' . $course->fullname . ' failed.');
        }
        $context = context_course::instance($course->id);
        $bkupdata[] = array(
            'status' => $outcome,
            'filelink' => $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/backup/automated/' . $backupvaluename .
                '?forcedownload=1',
            'relativelink' => '/' . $context->id . '/backup/automated/' . $backupvaluename,
        );
        $result = array();
        $result['data'] = $bkupdata;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.2
     */
    public static function backup_course_returns() {

        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'status' => new external_value(PARAM_INT, 'The backup status code'),
                            'filelink' => new external_value(PARAM_TEXT, 'Link to download the backup', VALUE_DEFAULT, ''),
                            'relativelink' => new external_value(PARAM_TEXT, 'Link to download the backup', VALUE_DEFAULT, ''),
                        )
                    ), 'Backup Course'
                ),
                'warnings' => new external_warnings()
            )
        );

    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function get_exams_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_multiple_structure(new external_value(PARAM_INT, 'Course id'),
                    'List of course id. If empty return all courses except front page course.', VALUE_OPTIONAL),
                'conditions' => new external_value(PARAM_TEXT,
                    'SQL condition (without WHERE). uses fields "startdate", "enddate", "timecreated" with any operator ' .
                    '(AND, OR, BETWEEN, >, <, ..etc). Should be styled as standard SQL.. Example: "((start date between 20000 ' .
                    'and 1000000) and (enddate < 400000)) or (timecreated <= 20000) ". use empty string "" to remove the ' .
                    'conditions',
                    VALUE_DEFAULT, ''),
                'filtercourses' => new external_value(PARAM_INT,
                    'Apply startdate and enddate "conditions" to courses too? use 0 for no conditions.', VALUE_DEFAULT, 0),
                'showemptycourses' => new external_value(PARAM_INT,
                    'List courses that have no quizzes? use 1 to list all courses regardless if they have quizzes or not.',
                    VALUE_DEFAULT, 1),
                'startneedle' => new external_value(PARAM_INT, 'Starting needle for the records. use 0 for first record.',
                    VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'How many records to retrieve. Leave empty for unlimited', VALUE_DEFAULT,
                    99999),
            )

        );
    }

    /**
     * Get Exams.
     *
     * @param array $courseid Course IDs.
     * @param string $conditions conditions.
     * @param int $filtercourses filters courses.
     * @param int $showemptycourses filters courses.
     * @param int $startneedle start needed.
     * @param int $perpage perpage.
     * @return array
     */
    public static function get_exams($courseid = array(), $conditions = '', $filtercourses = 0, $showemptycourses = 1,
        $startneedle = 0, $perpage = 99999) {
        global $DB;
        $params = self::validate_parameters(self::get_exams_parameters(),
            array('courseid' => $courseid, 'conditions' => $conditions, 'filtercourses' => $filtercourses,
                'showemptycourses' => $showemptycourses, 'startneedle' => $startneedle, 'perpage' => $perpage));

        if (!$conditions || trim($conditions) == '') {
            $conditions = '';
        }
        if (!$filtercourses) {
            $filtercourses = 0;
        }
        if (!$showemptycourses) {
            $showemptycourses = 0;
        }
        if (!$startneedle) {
            $startneedle = 0;
        }
        if (!$perpage) {
            $perpage = 99999;
        }

        $wherecalled = 0;
        if ($filtercourses == 1) {
            if (!empty($conditions) && trim($conditions) != '') {
                $sqlconditions = ' where ' . $conditions;
                $wherecalled = 1;
            } else {
                $sqlconditions = '';
                $wherecalled = 0;
            }
        } else {
            $sqlconditions = '';
            $wherecalled = 0;
        }
        // Special case for top level search for all courses.
        $allcoursesincluded = 0;
        if (max($courseid) == 0 && count($courseid) == 1 && $courseid[0] == 0) {
            $allcoursesincluded = 1;
        }
        if (!empty($courseid) && $allcoursesincluded != 1) {
            $coursesimp = implode(',', $courseid);
            if ($wherecalled == 0) {
                $sqlconditions .= ' where id in (' . $coursesimp . ')';
            } else {
                $sqlconditions .= ' and id in (' . $coursesimp . ')';
            }
        }
        $csql = 'select id, shortname, fullname, idnumber,
               startdate, enddate, visible, timecreated, timemodified
               from {course} ' . $sqlconditions . ' ORDER BY id DESC';

        $cparams = array();
        $courses = $DB->get_records_sql($csql, $cparams, $startneedle, $perpage);

        if (!$courses) {
            throw new moodle_exception('nocoursefound', 'webservice', '', '');
        }

        $coursesinfo = array();
        $statsarray = array();

        $statsarray['coursecount'] = count($courses);
        $statsarray['needle'] = $startneedle;
        $statsarray['perpage'] = $perpage;
        $coursesinfo['stats'] = $statsarray;

        foreach ($courses as $course) {

            // Now security checks.
            $context = context_course::instance($course->id, IGNORE_MISSING);
            try {
                self::validate_context($context);
            } catch (Exception $e) {
                $exceptionparam = new stdClass();
                $exceptionparam->message = $e->getMessage();
                $exceptionparam->courseid = $course->id;
                throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
            }
            if ($course->id != SITEID) {
                require_capability('moodle/course:view', $context);
            }

            $courseinfo = array();
            $courseinfo['id'] = $course->id;
            $courseinfo['fullname'] = external_format_string($course->fullname, $context->id);
            $courseinfo['shortname'] = external_format_string($course->shortname, $context->id);
            $courseinfo['startdate'] = $course->startdate;
            $courseinfo['enddate'] = $course->enddate;

            $courseadmin = has_capability('moodle/course:update', $context);
            if ($courseadmin) {
                $courseinfo['idnumber'] = $course->idnumber;
                $courseinfo['visible'] = $course->visible;
                $courseinfo['timecreated'] = $course->timecreated;
                $courseinfo['timemodified'] = $course->timemodified;
            }

            // Now get the quizes in this course.
            $courseinfo['quizzes'] = array();
            $returnedquizzes = array();
            $quizzes = array();

            list($coursessql, $qparams) = $DB->get_in_or_equal(array_keys(array($course->id => $course)), SQL_PARAMS_NAMED, 'c0');
            $modulename = 'quiz';
            $qparams['modulename'] = $modulename;
            $includeinvisible = true;

            $foundquizes = 1;
            if (!empty($conditions) && trim($conditions) != '') {
                $quizsqlconditions = str_ireplace('startdate', 'm.timeopen', $conditions);
                $quizsqlconditions = str_ireplace('enddate', 'm.timeclose', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('timecreated', 'm.timecreated', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('name', 'm.name', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('and shortname', '', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('or shortname', '', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('and fullname', '', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('or fullname', '', $quizsqlconditions);
                $quizsqlconditions = ' and ' . $quizsqlconditions;
            }
            if (!$rawmods = $DB->get_records_sql("SELECT cm.id AS coursemodule, m.*, cw.section, cm.visible AS visible,
                                                       cm.groupmode, cm.groupingid
                                                  FROM {course_modules} cm, {course_sections} cw, {modules} md,
                                                       {" . $modulename . "} m
                                                 WHERE cm.course $coursessql AND
                                                       cm.instance = m.id AND
                                                       cm.section = cw.id AND
                                                       md.name = :modulename AND
                                                       md.id = cm.module
                                                       $quizsqlconditions", $qparams)) {
                $courseinfo['quizzes'] = array();
                $foundquizes = 0;
            }
            if ($foundquizes == 1) {
                $modinfo = get_fast_modinfo($course, null);

                if (empty($modinfo->instances[$modulename])) {
                    continue;
                }

                foreach ($modinfo->instances[$modulename] as $cm) {
                    if (!$includeinvisible && !$cm->uservisible) {
                        continue;
                    }
                    if (!isset($rawmods[$cm->id])) {
                        continue;
                    }
                    $instance = $rawmods[$cm->id];
                    if (!empty($cm->extra)) {
                        $instance->extra = $cm->extra;
                    }
                    $quizzes[] = $instance;
                }

                foreach ($quizzes as $quiz) {
                    $context = context_module::instance($quiz->coursemodule);
                    if (has_capability('mod/quiz:view', $context)) {
                        $viewablefields = array('id', 'course', 'coursemodule', 'name', 'intro', 'timeopen', 'timeclose');
                        // Fields only for managers.
                        if (has_capability('moodle/course:manageactivities', $context)) {
                            $additionalfields = array('timecreated', 'timemodified');
                            $viewablefields = array_merge($viewablefields, $additionalfields);
                        }

                        foreach ($viewablefields as $field) {
                            $quizdetails[$field] = $quiz->{$field};
                            if ($field == 'name' || $field == 'intro') {
                                $quizdetails[$field] = external_format_string($quiz->{$field}, $context->id);
                            }

                        }
                    }
                    $returnedquizzes[] = $quizdetails;
                    $courseinfo['quizzes'] = $returnedquizzes;
                }
            }
            if ($courseadmin || $course->visible
                || has_capability('moodle/course:viewhiddencourses', $context)) {
                if ($foundquizes == 0 && $showemptycourses == 0) {
                    unset($courseinfo);
                } else {
                    $coursesinfo['results'][] = $courseinfo;
                }

            }

        }
        return $coursesinfo;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.2
     */
    public static function get_exams_returns() {

        return new external_single_structure(
            array(
                'stats' => new external_single_structure(

                    [
                        'coursecount' => new external_value(PARAM_RAW, 'Course count'),
                        'needle' => new external_value(PARAM_INT, 'needle'),
                        'perpage' => new external_value(PARAM_INT, 'perpage')

                    ]
                )
            ,
                'results' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'course id'),
                            'shortname' => new external_value(PARAM_RAW, 'course short name'),
                            'fullname' => new external_value(PARAM_RAW, 'full name'),
                            'idnumber' => new external_value(PARAM_RAW, 'id number', VALUE_OPTIONAL),
                            'startdate' => new external_value(PARAM_INT,
                                'timestamp when the course start'),
                            'enddate' => new external_value(PARAM_INT,
                                'timestamp when the course end'),
                            'timecreated' => new external_value(PARAM_INT,
                                'timestamp when the course have been created', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT,
                                '1: available to student, 0:not available', VALUE_OPTIONAL),
                            'quizzes' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'id' => new external_value(PARAM_INT, 'Quiz id'),
                                        'course' => new external_value(PARAM_INT, 'course id'),
                                        'coursemodule' => new external_value(PARAM_INT, 'Coursemodule id'),
                                        'name' => new external_value(PARAM_RAW, 'Quiz name'),
                                        'intro' => new external_value(PARAM_RAW, 'Quiz intro'),
                                        'timeopen' => new external_value(PARAM_INT,
                                            'The time when this quiz opens. (0 = no restriction.)',
                                            VALUE_OPTIONAL),
                                        'timeclose' => new external_value(PARAM_INT,
                                            'The time when this quiz closes. (0 = no restriction.)',
                                            VALUE_OPTIONAL),
                                        'timecreated' => new external_value(PARAM_INT, 'The time when this quiz was created',
                                            VALUE_OPTIONAL),
                                    ]
                                ), 'Quizes in this course.', VALUE_OPTIONAL)
                        )))

            )

        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function set_restriction_parameters() {

        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys',
                    VALUE_OPTIONAL), 'Array of Browser Keys', VALUE_DEFAULT, array()),
                'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys',
                    VALUE_OPTIONAL), 'Array of Config keys', VALUE_DEFAULT, array()),
                'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link', VALUE_DEFAULT, ''),
                'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret', VALUE_DEFAULT, ''),

            )
        );

    }

    /**
     * Set user restrictions.
     *
     * @param int $quizid
     * @param array $browserkeys
     * @param array $configkeys
     * @param string $quitlink
     * @param string $quitsecret
     * @return array of warnings and restrictions saved
     * @throws moodle_exception.
     * @since Moodle 3.2
     */
    public static function set_restriction($quizid, $browserkeys = array(), $configkeys = array(), $quitlink = '',
        $quitsecret = '') {
        global $USER, $DB;

        $params = self::validate_parameters(self::set_restriction_parameters(),
            array('quizid' => $quizid, 'browserkeys' => $browserkeys, 'configkeys' => $configkeys, 'quitlink' => $quitlink,
                'quitsecret' => $quitsecret));

        if (empty($params['quizid'])) {
            throw new moodle_exception('quizidmissing');
        }
        if (empty(trim($params['quitlink']))) {
            $params['quitlink'] = '';
        }
        if (empty(trim($params['quitsecret']))) {
            $params['quitsecret'] = '';
        }
        $warnings = array();
        $saved = array();

        $context = context_system::instance();
        self::validate_context($context);
        // Check to which quiz set the preference.
        try {
            $quizid = $params['quizid'];
            $quizparams = array('id' => $quizid);
            $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST);
        } catch (Exception $e) {
            $warnings[] = array(
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'quiznotfound',
                'message' => $e->getMessage()
            );

        }
        try {
            global $CFG;
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            if (quiz_has_attempts($quizid)) {
                throw new moodle_exception('attemptexist', 'sebserver', '', null,
                    'Quiz already has at least one attempt. You can not change restriction.');
            }
            $quizobj = quiz::create($quizid);
            $cm = $quizobj->get_cm();
            $cmid = $cm->id;
            if (has_capability('mod/quiz:manage', $quizobj->get_context())) {
                if ($params['browserkeys']) {
                    $bk = trim(implode("\n", $params['browserkeys']));
                }
                if ($params['configkeys']) {
                    $ck = trim(implode("\n", $params['configkeys']));
                }
                $bkempty = 0;
                if (!$bk) {
                    $bkempty = 1;
                }
                $ckempty = 0;
                if (!$ck) {
                    $ckempty = 1;
                }

                if ($ckempty == 1 && $bkempty == 1) { // Delete restriction.
                    $DB->delete_records('quizaccess_sebserver', array('quizid' => $quizid));
                    $DB->set_field('quizaccess_seb_quizsettings', 'allowedbrowserexamkeys', null, array('quizid' => $quizid));
                    $saved[] = array(
                        'quizid' => $quizid,
                        'quitlink' => $params['quitlink'],
                        'quitsecret' => $params['quitsecret'],
                        'browserkeys' => $params['browserkeys'],
                        'configkeys' => array(),
                    );
                    $warnings[] = array(
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'restrictiondeleted',
                        'message' => 'You have deleted restriction for quiz: ' . $quizid
                    );
                    $result = array();
                    $result['data'] = $saved;
                    $result['warnings'] = $warnings;
                    return $result;

                } else {
                    if ($ckempty == 0 && $bkempty == 1) {
                        throw new moodle_exception('browserkeysempty');
                    }
                    $sebserverrecord = $DB->get_record('quizaccess_sebserver', array('quizid' => $quizid));
                    if ($sebserverrecord) {
                        // Update.
                        $sebserverrec = new stdClass;
                        $sebserverrec->id = $sebserverrecord->id;
                        $sebserverrec->quizid = $sebserverrecord->quizid;
                        $sebserverrec->quitlink = $params['quitlink'];
                        $sebserverrec->quitsecret = $params['quitsecret'];
                        $sebserverrec->sebserverenabled = 1;
                        $sebserverrec->overrideseb = 0;
                        $DB->update_record('quizaccess_sebserver', $sebserverrec);

                    } else {
                        // Insert.
                        $sebserverrec = new stdClass;
                        $sebserverrec->quizid = $quizid;
                        $sebserverrec->quitlink = $params['quitlink'];
                        $sebserverrec->quitsecret = $params['quitsecret'];
                        $sebserverrec->sebserverenabled = 1;
                        $sebserverrec->overrideseb = 0;
                        $DB->insert_record('quizaccess_sebserver', $sebserverrec);
                    }

                    // Get core seb settings.
                    $sebsettingsrec = $DB->get_record('quizaccess_seb_quizsettings', array('quizid' => $quizid));
                    if ($sebsettingsrec) { // Update.
                        $sebsettings = new stdClass;
                        $sebsettings->id = $sebsettingsrec->id;
                        $sebsettings->quizid = $quizid;
                        $sebsettings->cmid = $cmid;
                        $sebsettings->requiresafeexambrowser = \quizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG;
                        $sebsettings->allowedbrowserexamkeys = $bk;
                        $sebsettings->timemodified = time();
                        $sebsettings->usermodified = get_admin()->id;

                        $DB->update_record('quizaccess_seb_quizsettings', $sebsettings);
                    } else { // Insert.
                        $sebsettings = new stdClass;
                        $sebsettings->quizid = $quizid;
                        $sebsettings->cmid = $cmid;
                        $sebsettings->requiresafeexambrowser = \quizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG;
                        $sebsettings->allowedbrowserexamkeys = $bk;
                        $sebsettings->timemodified = time();
                        $sebsettings->timecreated = time();
                        $sebsettings->usermodified = get_admin()->id;
                        $sebsettings->templateid = 0;
                        $DB->insert_record('quizaccess_seb_quizsettings', $sebsettings);

                    }

                }

                $saved[] = array(
                    'quizid' => $quizid,
                    'quitlink' => $params['quitlink'],
                    'quitsecret' => $params['quitsecret'],
                    'browserkeys' => $params['browserkeys'],
                    'configkeys' => array(),
                );

            } else {
                $warnings[] = array(
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'nopermission',
                    'message' => 'You are not allowed to SET the restriction for quiz ' . $quizid
                );
            }
        } catch (Exception $e) {
            $warnings[] = array(
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'errorsavingrestriction',
                'message' => $e->getMessage()
            );
        }

        // Delete the seb cache just in case.
        $sebcache = \cache::make('quizaccess_seb', 'config');
        $sebcache->delete($quizid);

        $quizsettingscache = \cache::make('quizaccess_seb', 'quizsettings');
        $quizsettingscache->delete($quizid);

        $configkeycache = \cache::make('quizaccess_seb', 'configkey');
        $configkeycache->delete($quizid);

        $result = array();
        $result['data'] = $saved;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function set_restriction_returns() {
        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'The quiz the restriction was set for'),
                            'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link', VALUE_DEFAULT, ''),
                            'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret', VALUE_DEFAULT, ''),
                            'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys')),
                            'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys')),
                        )
                    ), 'Get Restrictions'
                ),
                'warnings' => new external_warnings()
            )
        );

    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function get_restriction_parameters() {

        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED)
            )
        );

    }

    /**
     * Get user restrictions.
     *
     * @param in $quizid
     * @return array of warnings and restrictions saved
     * @throws moodle_exception
     * @since Moodle 3.2
     */
    public static function get_restriction($quizid) {
        global $USER, $DB;

        $params = self::validate_parameters(self::set_restriction_parameters(), array('quizid' => $quizid));

        if (empty($params['quizid']) || $params['quizid'] == 0) {
            throw new moodle_exception('quizidmissing');
        }

        $warnings = array();
        $saved = array();

        $context = context_system::instance();
        self::validate_context($context);
        // Check to which quiz set the preference.
        try {
            $quizid = $params['quizid'];
            $quizparams = array('id' => $quizid);
            $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST);
        } catch (Exception $e) {
            $warnings[] = array(
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'quiznotfound',
                'message' => $e->getMessage()
            );

        }

        try {
            global $CFG;
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            $quizobj = quiz::create($quizid);
            $cm = $quizobj->get_cm();
            $cmid = $cm->id;
            if (has_capability('mod/quiz:manage', $quizobj->get_context())) {

                $sebserverrecord = $DB->get_record('quizaccess_sebserver', array('quizid' => $quizid),
                    'id, quizid, quitlink, quitsecret, sebserverenabled');
                if (!$sebserverrecord) {
                    throw new moodle_exception('SEB Server is not enabled for quiz ID ' . $quizid);
                } else { // Insert.
                    if ($sebserverrecord->sebserverenabled == 0) {
                        throw new moodle_exception('SEB Server available but not enabled for quiz ID ' . $quizid);
                    }

                }
                // Get core seb settings.
                $sebsettingsrec =
                    $DB->get_record('quizaccess_seb_quizsettings', array('quizid' => $quizid), 'id, allowedbrowserexamkeys');

                if (!$sebsettingsrec) {
                    throw new moodle_exception('SEB Client is not enabled for quiz ID ' . $quizid .
                        '. Check if someone updated the quiz manually.');
                }
                $bkeys = preg_split('~[ \t\n\r,;]+~', $sebsettingsrec->allowedbrowserexamkeys, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($bkeys as $i => $key) {
                    $bkeys[$i] = strtolower($key);
                }
                if (!$sebserverrecord->quitlink) {
                    $sebserverrecord->quitlink = '';
                }
                if (!$sebserverrecord->quitsecret) {
                    $sebserverrecord->quitsecret = '';
                }
                $saved[] = array(
                    'quizid' => $quizid,
                    'quitlink' => $sebserverrecord->quitlink,
                    'quitsecret' => $sebserverrecord->quitsecret,
                    'browserkeys' => $bkeys,
                    'configkeys' => array(),
                );
            } else {
                $warnings[] = array(
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'nopermission',
                    'message' => 'You are not allowed to GET the restriction for quiz ' . $quizid
                );
            }
        } catch (Exception $e) {
            $warnings[] = array(
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'errorsavingrestriction',
                'message' => $e->getMessage()
            );
        }

        $result = array();
        $result['data'] = $saved;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function get_restriction_returns() {

        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'The quiz the restriction was set for'),
                            'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link', VALUE_DEFAULT, ''),
                            'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret', VALUE_DEFAULT, ''),
                            'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys')),
                            'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys')),
                        )
                    ), 'Get Restrictions'
                ),
                'warnings' => new external_warnings()
            )
        );

    }

}
