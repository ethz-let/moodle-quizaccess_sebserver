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
 * @copyright  2024 ETH Zurich (moodle@id.ethz.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/sebserver/rule.php');
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
          [
                       'id' => new external_value(PARAM_INT, 'Course ID or Quiz CMID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                       'backuptype' => new external_value(PARAM_RAW, '"course" or "quiz"', VALUE_DEFAULT, 'course'),

          ]
        );
    }

    /**
     * Function to backup courses
     *
     * @param int $id The course to back up
     * @param string $backuptype The type of backup
     * @throws \moodle_exception
     */
    public static function backup_course($id, $backuptype) {
        global $USER, $DB, $CFG;
        // Parameter validation.
        $params = self::validate_parameters(self::backup_course_parameters(),
                  ['id' => $id, 'backuptype' => $backuptype]);

        $id = $params['id'];
        $backuptype = $params['backuptype'];

        if ($id == 1 && $backuptype == 'course') {
            throw new moodle_exception('Site level backup is not allowed');
        }
        if ($backuptype != 'course' && $backuptype != 'quiz') {
            throw new moodle_exception('Backup type paramater is invalid');
        }
        if ($backuptype == 'quiz') {
            $qcm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
            $courseid = $qcm->course;
            $quizcontext = context_module::instance($qcm->id);
            $quizcmid = $id;
        } else {
            $courseid = $id;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        $contextid = $coursecontext->id;

        // Capability checking.
        if (!has_capability('moodle/backup:backupcourse', $coursecontext)) {
            throw new moodle_exception('User has no moodle/backup:backupcourse capabilities');
        }

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot .'/backup/util/helper/backup_cron_helper.class.php');
        $starttime = time();
        $userid = get_admin()->id;
        $warnings = [];
        $bkupdata = [];

        $outcome = backup_cron_automated_helper::BACKUP_STATUS_OK;
        if ($backuptype != 'quiz') {
            $config = get_config('backup');
            $orgdir = $config->backup_auto_destination;
            $orgstorage = $config->backup_auto_storage;
            set_config('backup_auto_destination', '', 'backup');
            set_config('backup_auto_storage', '0', 'backup');
        }
        $dir = '';
        $storage = 0;
        if ($backuptype == 'quiz') {
            $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quizcmid, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $userid);
        } else {
            $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_AUTOMATED, $userid);
        }

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
            if ($storage !== 0 && (empty($dir) || !file_exists($dir) ||
                !is_dir($dir) || !is_writable($dir))) {
                $bc->log('Specified backup directory is not writable - ', backup::LOG_ERROR, $dir);
                $dir = null;
                $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
                $warnings[] = [
                    'item' => 'backup',
                    'itemid' => $course->id,
                    'warningcode' => 'notwritabledir',
                    'message' => 'Specified backup directory is not writable - ' . $dir,
                ];
            }

            // Copy file only if there was no error.
            if ($file && !empty($dir) && $storage !== 0 &&
                $outcome != backup_cron_automated_helper::BACKUP_STATUS_ERROR) {
                $filename = backup_plan_dbops::get_default_backup_filename($format,
                            $type, $course->id, $users, $anonymised, !$config->backup_shortname);
                if (!$file->copy_content_to($dir . '/' . $filename)) {
                    $bc->log('Attempt to copy backup file to the specified directory failed - ',
                        backup::LOG_ERROR, $dir);
                    $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
                    $warnings[] = [
                        'item' => 'backup',
                        'itemid' => $course->id,
                        'warningcode' => 'copyfailed',
                        'message' => 'Attempt to copy backup file to the specified directory failed - ' . $dir,
                    ];
                }
                if ($outcome != backup_cron_automated_helper::BACKUP_STATUS_ERROR && $storage === 1) {
                    if (!$file->delete()) {
                        $outcome = backup_cron_automated_helper::BACKUP_STATUS_WARNING;
                        $bc->log('Attempt to delete the backup file from course automated backup area failed - ',
                            backup::LOG_WARNING, $file->get_filename());
                        $warnings[] = [
                            'item' => 'backup',
                            'itemid' => $course->id,
                            'warningcode' => 'deletefailed',
                            'message' => 'Attempt to delete the backup file from course automated
                                          backup area failed - ' . $dir,
                        ];
                    }
                }
            }

        } catch (moodle_exception $e) {
            $bc->log('backup_auto_failed_on_course', backup::LOG_ERROR, $course->shortname); // Log error header.
            $bc->log('Exception: ' . $e->errorcode, backup::LOG_ERROR, $e->a, 1); // Log original exception problem.
            $bc->log('Debug: ' . $e->debuginfo, backup::LOG_DEBUG, null, 1); // Log original debug information.
            $outcome = backup_cron_automated_helper::BACKUP_STATUS_ERROR;
            $warnings[] = [
                'item' => 'backup',
                'itemid' => $course->id,
                'warningcode' => 'backup_auto_failed_on_course',
                'message' => $e->errorcode . ' - ' . $e->debuginfo,
            ];
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
            throw new moodle_exception('Automated backup for course: ' . $course->fullname . ' failed.');
        }
        if ($backuptype == 'quiz') {
            $location = '/backup/activity/';
            $plugincontextid = $quizcontext->id;
        } else {
            $location = '/backup/automated/';
            $plugincontextid = $contextid;
            set_config('backup_auto_destination', $orgdir, 'backup');
            set_config('backup_auto_storage', $orgstorage, 'backup');
        }
        $bkupdata[] = [
            'status' => $outcome,
            'filelink' => $CFG->wwwroot . '/pluginfile.php/' . $plugincontextid . $location . $backupvaluename .
                '?forcedownload=1',
            'relativelink' => '/' . $plugincontextid . $location . $backupvaluename,
        ];

        $result = [];
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
            [
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'status' => new external_value(PARAM_INT, 'The backup status code'),
                            'filelink' => new external_value(PARAM_TEXT, 'Link to download the backup', VALUE_DEFAULT, ''),
                            'relativelink' => new external_value(PARAM_TEXT, 'Link to download the backup', VALUE_DEFAULT, ''),
                        ]
                    ), 'Backup Course'
                ),
                'warnings' => new external_warnings(),
            ]
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
            [
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
            ]

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
    public static function get_exams($courseid = [], $conditions = '', $filtercourses = 0, $showemptycourses = 1,
        $startneedle = 0, $perpage = 99999) {
        global $DB;
        $params = self::validate_parameters(self::get_exams_parameters(),
            ['courseid' => $courseid, 'conditions' => $conditions, 'filtercourses' => $filtercourses,
             'showemptycourses' => $showemptycourses, 'startneedle' => $startneedle, 'perpage' => $perpage]);

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
        $sqlconditions = str_ireplace('m.name', 'fullname', $sqlconditions);
        $csql = 'select id, shortname, fullname, idnumber,
                 startdate, enddate, visible, timecreated, timemodified
                 from {course} ' . $sqlconditions;
        $cparams = [];
        $courses = $DB->get_records_sql($csql, $cparams, $startneedle, $perpage);

        if (!$courses) {
            throw new moodle_exception('nocoursefound', 'webservice', '', '');
        }

        $coursesinfo = [];
        $statsarray = [];

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
                $exceptionparam = new stdClass;
                $exceptionparam->message = $e->getMessage();
                $exceptionparam->courseid = $course->id;
                throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
            }
            if ($course->id != SITEID) {
                require_capability('moodle/course:view', $context);
            }

            $courseinfo = [];
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
            $courseinfo['quizzes'] = [];
            $returnedquizzes = [];
            $quizzes = [];

            list($coursessql, $qparams) = $DB->get_in_or_equal(array_keys([$course->id => $course]),
                                          SQL_PARAMS_NAMED, 'c0');
            $includeinvisible = true;

            $foundquizes = 1;
            $quizsqlconditions = '';
            if (!empty($conditions) && trim($conditions) != '') {
                $quizsqlconditions = str_ireplace('startdate', 'm.timeopen', $conditions);
                $quizsqlconditions = str_ireplace('enddate', 'm.timeclose', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('timecreated', 'm.timecreated', $quizsqlconditions);
                $quizsqlconditions = str_ireplace('fullname', 'm.name', $quizsqlconditions);
                $quizsqlconditions = ' and ' . $quizsqlconditions;
            }
            // Speical case to list all quizes in filtered courses when shortname there.
            if (str_contains($quizsqlconditions, 'shortname')) {
                $quizsqlconditions = '';
            }
            if (!$rawmods = $DB->get_records_sql("SELECT cm.id AS coursemodule, m.*, cw.section, cm.visible AS visible,
                                                       cm.groupmode, cm.groupingid
                                                  FROM {course_modules} cm, {course_sections} cw, {modules} md,
                                                       {quiz} m
                                                 WHERE cm.course $coursessql AND
                                                       cm.instance = m.id AND
                                                       cm.section = cw.id AND
                                                       md.name = 'quiz' AND
                                                       md.id = cm.module
                                                       $quizsqlconditions", $qparams)) {
                $courseinfo['quizzes'] = [];
                $foundquizes = 0;
            }
            if ($foundquizes == 1) {
                $modinfo = get_fast_modinfo($course, null);

                if (empty($modinfo->instances['quiz'])) {
                    continue;
                }

                foreach ($modinfo->instances['quiz'] as $cm) {
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
                        $viewablefields = ['id', 'course', 'coursemodule', 'name', 'intro',
                                           'timeopen', 'timeclose'];
                        // Fields only for managers.
                        if (has_capability('moodle/course:manageactivities', $context)) {
                            $additionalfields = ['timecreated', 'timemodified'];
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
            [
                'stats' => new external_single_structure(

                    [
                        'coursecount' => new external_value(PARAM_RAW, 'Course count'),
                        'needle' => new external_value(PARAM_INT, 'needle'),
                        'perpage' => new external_value(PARAM_INT, 'perpage'),

                    ]
                )
            ,
                'results' => new external_multiple_structure(
                    new external_single_structure(
                        [
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
                                ), 'Quizes in this course.', VALUE_OPTIONAL),
                        ] )),

            ]

        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function set_restriction_parameters() {

        return new external_function_parameters(
            [
                'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys',
                    VALUE_OPTIONAL), 'Array of Browser Keys', VALUE_DEFAULT, []),
                'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys',
                    VALUE_OPTIONAL), 'Array of Config keys', VALUE_DEFAULT, []),
            ]
        );

    }

    /**
     * Set connection.
     *
     * @param string $connection
     * @throws moodle_exception.
     * @since Moodle 3.2
     */
    public static function connection($connection) {
        global $USER, $DB;

        $params = self::validate_parameters(self::connection_parameters(),
            ['connection' => $connection]);

        // Capability checking.
        $context = context_system::instance();
        if (!has_capability('quizaccess/sebserver:managesebserverconnection', $context)) {
            throw new moodle_exception('User has no
                                        quizaccess/sebserver:managesebserverconnection capabilities');
        }

        if (empty($params['connection'])) {
            throw new moodle_exception('Connection data missing');
        }
        $connectiondata = trim($params['connection']);
        $connectiondata = mb_convert_encoding(urldecode($connectiondata), 'UTF-8');
        $decodeddata = json_decode($connectiondata);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('Connection is not a valid JSON format: '.json_last_error_msg());
        }

        $warnings = [];
        $success = false;
        if ($connrecord = $DB->get_record('config_plugins',
                                      ['plugin' => 'quizaccess_sebserver',
                                       'name' => 'connection'])) {
            $conndetails = json_decode($connrecord->value);

            if ($conndetails->{'id'} == $decodeddata->{'id'}) {
                // Update same ID connection.
                $newconnection = new stdClass;
                $newconnection->id = $connrecord->id;
                $newconnection->plugin = 'quizaccess_sebserver';
                $newconnection->name = 'connection';
                $newconnection->value = $connectiondata;

                if ($update = $DB->update_record('config_plugins', $newconnection)) {
                    $success = true;
                } else {
                    $success = false;
                    $warnings[] = [
                        'item' => 'sebserver',
                        'itemid' => 0,
                        'warningcode' => 'connectionupdatefailed',
                        'message' => s('Connection update failed. ID: ' . $conndetails->{'id'}),
                    ];
                }

            } else {
                $success = false;
                $warnings[] = [
                    'item' => 'sebserver',
                    'itemid' => 0,
                    'warningcode' => 'connectiondoesntmatch',
                    'message' => s('The connection ID does not match. Update failed.
                                    Registered ID: ' . $conndetails->{'id'} .
                                    '. Update ID: ' . $decodeddata->{'id'}),
                ];
            }
        } else {
            // No previous Connection. Insert new Connection.
            $newconnection = new stdClass;
            $newconnection->plugin = 'quizaccess_sebserver';
            $newconnection->name = 'connection';
            $newconnection->value = $connectiondata;

            if ($insert = $DB->insert_record('config_plugins', $newconnection)) {
                $success = true;
            } else {
                $success = false;
                $warnings[] = [
                    'item' => 'sebserver',
                    'itemid' => 0,
                    'warningcode' => 'connectioninsertfailed',
                    'message' => s('Connection insert failed. ID: ' . $decodeddata->{'id'}),
                ];
            }
        }

        $result = [
            'success' => $success,
            'warnings' => $warnings,
        ];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function connection_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'True if the connection set, false if not.'),
                'warnings'  => new external_warnings(),
            ]
        );
    }

    /**
     * Delete connection.
     *
     * @param int $id
     * @throws moodle_exception.
     * @since Moodle 3.2
     */
    public static function connection_delete($id) {
        global $USER, $DB;

        $params = self::validate_parameters(self::connection_delete_parameters(),
                                            ['id' => $id]);
        // Capability checking.
        $context = context_system::instance();
        if (!has_capability('quizaccess/sebserver:managesebserverconnection', $context)) {
            throw new moodle_exception('User has no
                                        quizaccess/sebserver:managesebserverconnection capabilities');
        }
        $warnings = [];
        $success = false;

        if (empty($params['id'])) {
            throw new moodle_exception('Connection ID missing.');
        }

        if ($connrecord = $DB->get_record('config_plugins',
                                      ['plugin' => 'quizaccess_sebserver',
                                       'name' => 'connection'])) {
            $conndetails = json_decode($connrecord->value);

            if ($conndetails->{'id'} == $params['id']) {

                if ($deletion = $DB->delete_records('config_plugins', ['id' => $connrecord->id])) {
                    $success = true;
                } else {
                    $success = false;
                    $warnings[] = [
                        'item' => 'sebserver',
                        'itemid' => 0,
                        'warningcode' => 'connectiondeletionfailed',
                        'message' => s('Connection deletion failed.'),
                    ];
                }

            } else {
                $success = false;
                $warnings[] = [
                    'item' => 'sebserver',
                    'itemid' => 0,
                    'warningcode' => 'connectiondoesntmatch',
                    'message' => s('The connection ID does not match.'),
                ];
            }
        } else {
            $success = false;
            $warnings[] = [
                'item' => 'sebserver',
                'itemid' => 0,
                'warningcode' => 'noconnectionfound',
                'message' => s('There is no SebServer connection found.'),
            ];
        }

        $result = [
            'success' => $success,
            'warnings' => $warnings,
        ];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function connection_delete_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'True if the connection deleted, false if not.'),
                'warnings'  => new external_warnings(),
            ]
        );
    }
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function connection_parameters() {
        return new external_function_parameters(
            [
                'connection' => new external_value(PARAM_RAW, 'Connection in JSON format', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
            ]
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function connection_delete_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_RAW, 'Connection ID to delete', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
            ]
        );
    }

    /**
     * Set user restrictions.
     *
     * @param int $quizid quizid
     * @param array $browserkeys browser keys
     * @param array $configkeys config keys
     * @return array of warnings and restrictions saved
     * @throws moodle_exception moodle exception.
     * @since Moodle 3.2
     */
    public static function set_restriction($quizid, $browserkeys = [], $configkeys = []) {
        global $USER, $DB;

        $params = self::validate_parameters(self::set_restriction_parameters(),
            ['quizid' => $quizid, 'browserkeys' => $browserkeys, 'configkeys' => $configkeys]);

        if (empty($params['quizid'])) {
            throw new moodle_exception('quizidmissing');
        }
        $warnings = [];
        $saved = [];

        $context = context_system::instance();
        self::validate_context($context);
        // Check to which quiz set the preference.
        try {
            $quizid = $params['quizid'];
            $quizparams = ['id' => $quizid];
            $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST);
        } catch (Exception $e) {
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'quiznotfound',
                'message' => $e->getMessage(),
            ];

        }
        try {
            global $CFG;
            [$quizcourse, $quizcm] = get_course_and_cm_from_instance($quizid, 'quiz');
            $quizcontext = context_module::instance($quizcm->id);
            $quizcontextid = $quizcontext->id;
            $cmid = $quizcm->id;
            if (has_capability('mod/quiz:manage', $quizcontext)) {
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
                // EMDL-1043 do not allow setting restirctions when there is an attempt.
                if (quiz_has_attempts($quizid)) {
                    throw new moodle_exception('attemptexist', 'sebserver', '', null,
                        'Quiz already has at least one attempt. You can not change restriction.');
                }
                if ($ckempty == 1 && $bkempty == 1) { // Delete restriction.
                    $DB->set_field('quizaccess_sebserver', 'sebserverrestricted', 0,
                    ['sebserverquizid' => $quizid]);
                    $DB->set_field('quizaccess_seb_quizsettings', 'allowedbrowserexamkeys', null,
                    ['quizid' => $quizid]);
                    $saved[] = [
                        'quizid' => $quizid,
                        'browserkeys' => $params['browserkeys'],
                        'configkeys' => [],
                    ];
                    $warnings[] = [
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'restrictiondeleted',
                        'message' => 'You have deleted restriction for quiz: ' . $quizid,
                    ];
                    $result = [];
                    $result['data'] = $saved;
                    $result['warnings'] = $warnings;
                    return $result;

                } else {
                    if ($ckempty == 0 && $bkempty == 1) {
                        throw new moodle_exception('browserkeysempty');
                    }
                    $sebserverrecord = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quizid]);
                    if ($sebserverrecord) {
                        if ($sebserverrecord->sebserverenabled != 1) {
                            throw new moodle_exception('You can not set restriction on a quiz with SebServer disabled!');
                        }
                        // Update.
                        $record = new stdClass();
                        $record->id = $sebserverrecord->id;
                        $record->sebserverrestricted = 1;
                        if (!$updaterec = $DB->update_record('quizaccess_sebserver', $record)) {
                            throw new moodle_exception('Failed to update SebServer record: ' . $sebserverrecord->id);
                        }

                    } else {
                        throw new moodle_exception('You can not set restriction on a quiz with no SebServer Info!');
                    }

                    // Get core seb settings.
                    $sebsettingsrec = $DB->get_record('quizaccess_seb_quizsettings', ['quizid' => $quizid]);
                    if ($sebsettingsrec) { // Update.
                        $sebsettings = new stdClass;
                        $sebsettings->id = $sebsettingsrec->id;
                        $sebsettings->quizid = $quizid;
                        $sebsettings->cmid = $cmid;
                        $sebsettings->requiresafeexambrowser = \quizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG;
                        $sebsettings->allowedbrowserexamkeys = $bk;
                        $sebsettings->timemodified = time();
                        $sebsettings->usermodified = get_admin()->id;

                        if (!$updaterec = $DB->update_record('quizaccess_seb_quizsettings', $sebsettings)) {
                            throw new moodle_exception('Failed to update SEB Deeper record: ' . $sebsettingsrec->id);
                        }
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
                        if (!$insertrec = $DB->insert_record('quizaccess_seb_quizsettings', $sebsettings)) {
                            throw new moodle_exception('Failed to insert SEB Deeper for quizid: ' . $quizid);
                        }

                    }

                }

                $saved[] = [
                    'quizid' => $quizid,
                    'browserkeys' => $params['browserkeys'],
                    'configkeys' => [],
                ];

            } else {
                $warnings[] = [
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'nopermission',
                    'message' => 'You are not allowed to SET the restriction for quiz ' . $quizid,
                ];
            }
        } catch (Exception $e) {
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'errorsavingrestriction',
                'message' => $e->getMessage(),
            ];
        }

        // Delete the seb cache just in case.
        $sebcache = \cache::make('quizaccess_seb', 'config');
        $sebcache->delete($quizid);

        $quizsettingscache = \cache::make('quizaccess_seb', 'quizsettings');
        $quizsettingscache->delete($quizid);

        $configkeycache = \cache::make('quizaccess_seb', 'configkey');
        $configkeycache->delete($quizid);

        $result = [];
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
            [
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'quizid' => new external_value(PARAM_INT, 'The quiz the restriction was set for'),
                            'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys')),
                            'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys')),
                        ]
                    ), 'Get Restrictions'
                ),
                'warnings' => new external_warnings(),
            ]
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
            [
                'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
            ]
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

        $params = self::validate_parameters(self::get_restriction_parameters(), ['quizid' => $quizid]);

        if (empty($params['quizid']) || $params['quizid'] == 0) {
            throw new moodle_exception('quizidmissing');
        }

        $warnings = [];
        $saved = [];

        $context = context_system::instance();
        self::validate_context($context);
        // Check to which quiz set the preference.
        try {
            $quizid = $params['quizid'];
            $quizparams = ['id' => $quizid];
            $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST);
        } catch (Exception $e) {
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'getrestirctionquiznotfound',
                'message' => $e->getMessage(),
            ];
        }

        try {
            global $CFG;
            [$quizcourse, $quizcm] = get_course_and_cm_from_instance($quizid, 'quiz');
            $quizcontext = context_module::instance($quizcm->id);
            $quizcontextid = $quizcontext->id;
            $cmid = $quizcm->id;
            if (has_capability('mod/quiz:manage', $quizcontext)) {

                $sebserverrecord = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quizid],
                    '*');
                if (!$sebserverrecord) {
                    throw new moodle_exception('SEB Server is not enabled for quiz ID ' . $quizid);
                } else { // Insert.
                    if ($sebserverrecord->sebserverenabled == 0) {
                        throw new moodle_exception('SEB Server is disabled for quiz ID ' . $quizid);
                    }

                }
                // Get core seb settings.
                $sebsettingsrec =
                    $DB->get_record('quizaccess_seb_quizsettings', ['quizid' => $quizid],
                                    'id, allowedbrowserexamkeys');

                if (!$sebsettingsrec) {
                    throw new moodle_exception('SEB Client is not enabled for quiz ID ' . $quizid .
                        '. Check if someone updated the quiz manually.');
                }
                $bkeys = preg_split('~[ \t\n\r,;]+~', $sebsettingsrec->allowedbrowserexamkeys, -1,
                                    PREG_SPLIT_NO_EMPTY);
                foreach ($bkeys as $i => $key) {
                    $bkeys[$i] = strtolower($key);
                }
                $saved[] = [
                    'quizid' => $quizid,
                    'browserkeys' => $bkeys,
                    'configkeys' => [],
                ];
            } else {
                $warnings[] = [
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'nopermission',
                    'message' => s('You are not allowed to GET the restriction for quiz ' . $quizid),
                ];
            }
        } catch (Exception $e) {
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'errorgettingrestriction',
                'message' => $e->getMessage(),
            ];
        }

        $result = [];
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
            [
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'quizid' => new external_value(PARAM_INT, 'The quiz the restriction was set for'),
                            'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys')),
                            'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys')),
                        ]
                    ), 'Get Restrictions'
                ),
                'warnings' => new external_warnings(),
            ]
        );

    }
    /**
     * Returns description of method result value
     *
     * @param array $data data
     * @return external_description
     * @since Moodle 3.2
     */
    public static function set_exam_data($data) {
        global $USER, $DB;

        $parameters = self::validate_parameters(self::set_exam_data_parameters(),
                  ['data' => $data]);
        $params = $parameters['data'];

        if (empty($params['quizid']) || $params['quizid'] == 0) {
            throw new moodle_exception('quizidmissing');
        }
        if (!isset($params['quitsecret'])) {
            $params['quitsecret'] = '';
        }
        if ($params['quitsecret'] !== '' && $params['quitsecret'] !== trim($params['quitsecret'])) {
            throw new moodle_exception(get_string('err_wrappingwhitespace', 'core_form'));
        }
        if (!isset($params['showquitlink'])) {
            $params['showquitlink'] = 1;
        }
        if (!isset($params['quitlink'])) {
            $params['quitlink'] = '';
            $params['showquitlink'] = 0;
        }
        $nextquizid = $params['nextquizid'];
        $nextcourseid = $params['nextcourseid'];

        $warnings = [];
        $success = false;
        $contextid = null;
        $nextquizcontextid = null;

        $context = context_system::instance();
        self::validate_context($context);
        // Check to which quiz set the preference.
        try {
            $quizid = $params['quizid'];
            $quizparams = ['id' => $quizid];
            $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST);
        } catch (Exception $e) {
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'quiznotfound',
                'message' => $e->getMessage(),
            ];

        }
        if (!empty($nextquizid) && !empty($nextcourseid)) {
            // Get next quiz context data.
            [$nextquizcourse, $nextquizcm] = get_course_and_cm_from_instance($nextquizid, 'quiz');
            $nextquizcontext = context_module::instance($nextquizcm->id);
            $nextquizcontextid = $nextquizcontext->id;
        }
        if (empty($params['addordelete']) && $params['addordelete'] != 0) {
            throw new moodle_exception('No Add or Delete option is selected.');
        }
        if ((empty($params['templateid']) && $params['templateid'] != 0) ||
            is_null($params['templateid'])) {
            $templateid = 0;
        } else {
            $templateid = $params['templateid'];
        }
        if ($params['addordelete'] == 0) {
            if (!$result = $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quizid])) {
                $success = false;
                $contextid = null;
                $warnings[] = [
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'couldnotdeletequizdata',
                    'message' => s('Exam Sebserver data could not be deleted for quiz: ' . $quizid),
                ];
            } else {
                // Delete any possible SebServer connection config file.
                $cm = get_coursemodule_from_instance('quiz', $quizid);
                $context = context_module::instance($cm->id);
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'quizaccess_sebserver', 'filemanager_sebserverconfigfile');
                $success = true;
                $contextid = null;
                $warnings[] = [
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'deletioninfo',
                    'message' => s('SUCCESS: Exam Sebserver data has been deleted for quiz: ' . $quizid),
                ];
            }
        } else {
            $rec = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quizid]);
            if (!$rec) {
                $sebservrecord = new stdClass;
                $sebservrecord->sebserverquizid = $quizid;
                $sebservrecord->sebserverenabled = 1;
                $sebservrecord->sebservertemplateid = $templateid;
                $sebservrecord->sebservershowquitbtn = $params['showquitlink'];
                $sebservrecord->sebserverquitlink = $params['quitlink'];
                $sebservrecord->sebserverrestricted = 0;
                $sebservrecord->sebservertimemodified = time();
                $sebservrecord->sebservercalled = 1;
                $sebservrecord->sebserverquitsecret = $params['quitsecret'];
                $sebservrecord->nextquizid = $nextquizid;
                $sebservrecord->nextcourseid = $nextcourseid;
                if (!$result = $DB->insert_record('quizaccess_sebserver', $sebservrecord)) {
                    $success = false;
                    $contextid = null;
                    $warnings[] = [
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'couldnotinsertquizdata',
                        'message' => s('Exam Sebserver data could not be inserted for quiz: ' . $quizid),
                    ];
                } else {
                    $success = true;
                    $contextid = $nextquizcontextid;
                    $warnings[] = [
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'insertrecordinfo',
                        'message' => s('Successfully inserted for quiz: ' . $quizid .
                                       '. Attempting to request SebServerConfig.seb file in next step.'),
                    ];
                }
            } else {
                $sebservrecord = new stdClass;
                $sebservrecord->id = $rec->id;
                $sebservrecord->sebserverquizid = $quizid;
                $sebservrecord->sebserverenabled = 1;
                $sebservrecord->sebservertemplateid = $templateid;
                $sebservrecord->sebservershowquitbtn = $params['showquitlink'];
                $sebservrecord->sebserverquitlink = $params['quitlink'];
                $sebservrecord->sebservertimemodified = time();
                $sebservrecord->sebservercalled = 1;
                $sebservrecord->sebserverquitsecret = $params['quitsecret'];
                $sebservrecord->nextquizid = $nextquizid;
                $sebservrecord->nextcourseid = $nextcourseid;
                if (!$result = $DB->update_record('quizaccess_sebserver', $sebservrecord)) {
                    $success = false;
                    $contextid = null;
                    $warnings[] = [
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'couldnotupdaterecord',
                        'message' => s('Exam Sebserver data could not be updated for quiz: ' . $quizid),
                    ];
                } else {
                    $success = true;
                    $contextid = $nextquizcontextid;
                    $warnings[] = [
                        'item' => 'quiz',
                        'itemid' => $quizid,
                        'warningcode' => 'updaterecordinfo',
                        'message' => s('SUCCESS: record already exists.
                                        Successfully updated for quiz: ' . $quizid .
                                        '. You must upload SebServerConfig.seb file.'),
                    ];
                }
            }
        }
        // Request Seb Server config file.
        if (! $cm = get_coursemodule_from_instance('quiz', $quizid)) {
            throw new \moodle_exception('Unknown quiz with id: ' . $quizid);
        }
        $context = context_module::instance($cm->id);
        $sebconfigresult = \quizaccess_sebserver::request_sebserverconfig($cm->course, $quizid,
        $cm->id, $context->id);

        if (isset($sebconfigresult) && trim($sebconfigresult) !== '') {
            $success = false;
            $contextid = null;
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'requestsebconfig',
                'message' => s('Problem getting Seb Server Config file for Quiz : ' . $quizid . '. Error: ' .
                                $sebconfigresult),
            ];
        } else {
            $DB->set_field('quizaccess_sebserver', 'sebservercalled', 1, ['sebserverquizid' => $quizid]);
            $success = true;
            $contextid = $nextquizcontextid;
            $warnings[] = [
                'item' => 'quiz',
                'itemid' => $quizid,
                'warningcode' => 'requestsebconfig',
                'message' => s('Successfully uploaded Seb Server config for : ' . $quizid . ' CMID: '.
                                $cm->id),
            ];
        }
        $result = [
            'success' => $success,
            'context' => $contextid,
            'warnings' => $warnings,
        ];
        return $result;

    }
    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function set_exam_data_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'True if the exam data set/deleted, false if not.'),
                'context' => new external_value(PARAM_INT, 'Context ID for SebServer configuration file.'),
                'warnings'  => new external_warnings(),
            ]
        );
    }
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function set_exam_data_parameters() {

        return new external_function_parameters([
            'data' => new external_single_structure([
                'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                'addordelete' => new external_value(PARAM_INT, '1: Add - 0: Delete', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                'templateid' => new external_value(PARAM_INT, 'Template ID', VALUE_OPTIONAL, 0),
                'showquitlink' => new external_value(PARAM_BOOL, 'Show quit link', VALUE_OPTIONAL, 1),
                'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret', VALUE_OPTIONAL, ''),
                'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link', VALUE_OPTIONAL, ''),
                'nextquizid' => new external_value(PARAM_INT, 'Next quiz ID', VALUE_OPTIONAL),
                'nextcourseid' => new external_value(PARAM_INT, 'Next course ID', VALUE_OPTIONAL),
            ] ),
        ]);

    }

}
