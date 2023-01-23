<?php

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
require_once($CFG->libdir . "/externallib.php");

class quizaccess_sebserver_external extends external_api {

/////////////////////////// BACKUP A COURSE /////////////////////////
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
    public static function backup_course($id) {
        global $USER, $DB, $CFG;
        //Parameter validation
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

        $outcome = backup_cron_automated_helper::launch_automated_backup($course, time(), get_admin()->id);

        return $outcome;

    }
    public static function backup_course_returns() {
        return new external_value(PARAM_INT,'Returns: 1 for Success, or 0 for Fail.');
    }

/////////////////////////// END BACKUP A COURSE /////////////////////////


/////////////////////////// GET COURSES /////////////////////////


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function get_exams_parameters() {
      return new external_function_parameters(
              array(
                          'courseid' => new external_multiple_structure( new external_value(PARAM_INT, 'Course id') , 'List of course id. Use courseid[]=X&courseid[]=X... If courseid[]=0 passed, then return all courses (except front page course).', VALUE_OPTIONAL),
                          'conditions' => new external_value(PARAM_TEXT, 'SQL condition (without WHERE). uses fields "startdate", "enddate", "timecreated" with any operator (AND, OR, BETWEEN, >, <, ..etc). Should be styled as standard SQL.. Example: "((start date between 20000 and 1000000) and (enddate < 400000)) or (timecreated <= 20000) ". use empty string "" to remove the conditions',  VALUE_DEFAULT, ''),
                          'filtercourses' => new external_value(PARAM_INT, 'Apply startdate and enddate "conditions" to courses too? use 0 for no conditions.', VALUE_DEFAULT, 0),
                          'showemptycourses' => new external_value(PARAM_INT, 'List courses that have no quizzes? use 1 to list all courses regardless if they have quizzes or not.', VALUE_DEFAULT, 1),
                          'startneedle' => new external_value(PARAM_INT, 'Starting needle for the records. use 0 for first record.', VALUE_DEFAULT, 0),
                          'perpage' => new external_value(PARAM_INT, 'How many records to retrieve. Leave empty for unlimited', VALUE_DEFAULT, 99999),
                           )


                        );
    }

    public static function get_exams($courseid = array(), $conditions = '', $filtercourses = 0, $showemptycourses = 1, $startneedle = 0, $perpage = 99999){
      global $DB;
      $params = self::validate_parameters(self::get_exams_parameters(), array('courseid' => $courseid, 'conditions' => $conditions, 'filtercourses' => $filtercourses, 'showemptycourses' => $showemptycourses, 'startneedle' => $startneedle, 'perpage' => $perpage));

      if(!$conditions || trim($conditions) == '')  {
        $conditions = '';
      }
      if(!$filtercourses)  {
        $filtercourses = 0;
      }
      if(!$showemptycourses)  {
        $showemptycourses = 0;
      }
      if(!$startneedle)  {
        $startneedle = 0;
      }
      if(!$perpage)  {
        $perpage = 99999;
      }


      $wherecalled = 0;
      if ( $filtercourses == 1 ) {
          if(!empty($conditions) && trim($conditions) != ''){
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
       if(max($courseid) == 0 && count($courseid) == 1 && $courseid[0] == 0){
         $allcoursesincluded = 1;
       }
       if (!empty($courseid) && $allcoursesincluded != 1) {
          $coursesimp = implode(',', $courseid);
          if ($wherecalled == 0) {
              $sqlconditions .= ' where id in (' . $coursesimp . ')';
          } else {
            $sqlconditions .= ' id in (' . $coursesimp . ')';
          }
       }
      $csql = 'select id, shortname, fullname, idnumber,
               startdate, enddate, visible, timecreated, timemodified
               from {course} ' . $sqlconditions;
      $cparams = array();
      $courses = $DB->get_records_sql($csql, $cparams, $startneedle, $perpage);

      if(!$courses) {
        throw new moodle_exception('nocoursefound', 'webservice', '', '');
      }

      $coursesinfo = array();
      $statsarray = array();

      $statsarray['coursecount'] = count($courses);
      $statsarray['needle'] = $startneedle;
      $statsarray['perpage'] = $perpage;

      $coursesinfo['stats'] = $statsarray;

      foreach ($courses as $course) {

          // now security checks
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
        //  $quizzes = get_all_instances_in_course("quiz", $course);

          list($coursessql, $qparams) = $DB->get_in_or_equal(array_keys(array($course->id => $course)), SQL_PARAMS_NAMED, 'c0');
          $modulename = 'quiz';
          $qparams['modulename'] = $modulename;
          $includeinvisible = true;

          $foundquizes = 1;
          if(!empty($conditions) && trim($conditions) != ''){
            $quizsqlconditions = str_ireplace('startdate', 'm.timeopen', $conditions);
            $quizsqlconditions = str_ireplace('enddate', 'm.timeclose', $quizsqlconditions);
            $quizsqlconditions = str_ireplace('timecreated', 'm.timecreated', $quizsqlconditions);
            $quizsqlconditions = ' and ' . $quizsqlconditions;
          }
          if (!$rawmods = $DB->get_records_sql("SELECT cm.id AS coursemodule, m.*, cw.section, cm.visible AS visible,
                                                       cm.groupmode, cm.groupingid
                                                  FROM {course_modules} cm, {course_sections} cw, {modules} md,
                                                       {".$modulename."} m
                                                 WHERE cm.course $coursessql AND
                                                       cm.instance = m.id AND
                                                       cm.section = cw.id AND
                                                       md.name = :modulename AND
                                                       md.id = cm.module
                                                       $quizsqlconditions", $qparams)) {
              $courseinfo['quizzes'] = array();
              $foundquizes = 0;
          }
          if($foundquizes == 1){
                $modinfo = get_fast_modinfo($course, null);

                if (empty($modinfo->instances[$modulename])) {
                    continue;
                }

                foreach ($modinfo->instances[$modulename] as $cm) {
                    if (!$includeinvisible and !$cm->uservisible) {
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
                              //$quizdetails['introfiles'] = external_util::get_area_files($context->id, 'mod_quiz', 'intro', false, false);
                              $viewablefields = array('id', 'course', 'coursemodule', 'name', 'intro', 'timeopen', 'timeclose');

                              // Fields only for managers.
                              if (has_capability('moodle/course:manageactivities', $context)) {
                                  $additionalfields = array('timecreated', 'timemodified');
                                  $viewablefields = array_merge($viewablefields, $additionalfields);
                              }

                              foreach ($viewablefields as $field) {
                                  $quizdetails[$field] = $quiz->{$field};
                                  if($field == 'name' || $field == 'intro'){
                                    $quizdetails[$field] = external_format_string($quiz->{$field}, $context->id);
                                  }

                              }
                          }
                          $returnedquizzes[] = $quizdetails;
                          $courseinfo['quizzes'] = $returnedquizzes;
                      }
                }
                if ($courseadmin or $course->visible
                  or has_capability('moodle/course:viewhiddencourses', $context)) {
                    if( $foundquizes == 0 && $showemptycourses == 0 ){
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
                                      'timeopen' => new external_value(PARAM_INT, 'The time when this quiz opens. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                                      'timeclose' => new external_value(PARAM_INT, 'The time when this quiz closes. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                                      'timecreated' => new external_value(PARAM_INT, 'The time when this quiz was created',
                                                                VALUE_OPTIONAL),
                                     ]
                                ), 'Quizes in this course.', VALUE_OPTIONAL)
                )))

              )

      );
    }

/////////////////////////// END GET EXAMS /////////////////////////


/**
 * Returns description of method parameters
 *
 * @return external_function_parameters
 * @since Moodle 3.2
 */
public static function set_restriction_parameters() {

  return new external_function_parameters (
      array(
          'browserkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Browser Keys',
                  VALUE_OPTIONAL), 'Array of Browser Keys', VALUE_DEFAULT, array()),
          'configkeys' => new external_multiple_structure(new external_value(PARAM_RAW, 'Config Keys',
                   VALUE_OPTIONAL), 'Array of Config keys', VALUE_DEFAULT, array()),
          'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
          'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link'),
          'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret'),


      )
  );

}

/**
 * Set user restrictions.
 *
 * @param array $restrictions list of restrictions including name, value and userid
 * @return array of warnings and restrictions saved
 * @since Moodle 3.2
 * @throws moodle_exception
 */
public static function set_restriction($browserkeys = array(), $configkeys = array(), $quizid, $quitlink = '', $quitsecret = '') {
    global $USER, $DB;

    $params = self::validate_parameters(self::set_restriction_parameters(), array('browserkeys' => $browserkeys,'configkeys' => $configkeys, 'quizid' => $quizid, 'quitlink' => $quitlink, 'quitsecret' => $quitsecret));

    if (empty($params['quizid'])){
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
                $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST );
            } catch (Exception $e) {
                $warnings[] = array(
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'quiznotfound',
                    'message' => $e->getMessage()
                );

            }
      //  }
        try {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quizobj = quiz::create($quizid);
        $cm = $quizobj->get_cm();
        $cmid = $cm->id;
        if (has_capability('mod/quiz:manage', $quizobj->get_context())) {
                if(!empty($params['browserkeys'])){
                  $bk =  trim(implode("\n",$params['browserkeys']));
                  $bkempty = 0;
                } else {
                  $bkempty = 1;
                }
                if(!empty($params['configkeys'])){
                  $ck =  trim(implode("\n",$params['configkeys']));
                  $ckempty = 0;
                } else {
                  $ckempty = 1;
                }

                if($ckempty == 1 && $bkempty == 1){ // Delete restriction.
                  $DB->delete_records('quizaccess_sebserver', array('quizid' => $quizid));
                  $DB->set_field('quizaccess_seb_quizsettings', 'allowedbrowserexamkeys',null, array('quizid' => $quizid));
                } else {
                  if($ckempty == 1 && $bkempty == 0){
                    throw new moodle_exception('browserkeysempty');
                  }
                  $sebserverrecord = $DB->get_record('quizaccess_sebserver', array('quizid' => $quizid));
                  if($sebserverrecord){ // Update
                    $sebserverrec = new stdClass;
                    $sebserverrec->id = $sebserverrecord->id;
                    $sebserverrec->quizid = $sebserverrecord->quizid;
                    $sebserverrec->quitlink = $params['quitlink'];
                    $sebserverrec->quitsecret = $params['quitsecret'];
                    $sebserverrec->sebserverenabled = 1;
                    $sebserverrec->overrideseb = 0;
                    $DB->update_record('quizaccess_sebserver', $sebserverrec);

                  } else { // Insert
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
                  if($sebsettingsrec){ // Update.
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
                    'quizid' => $quizid
                );
            } else {
                $warnings[] = array(
                    'item' => 'quiz',
                    'itemid' => $quizid,
                    'warningcode' => 'nopermission',
                    'message' => 'You are not allowed to SET the restriction for quiz '.$quizid
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
//    }

    $result = array();
    $result['saved'] = $saved;
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
            'saved' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'quizid' => new external_value(PARAM_INT, 'The quiz the restriction was set for'),
                    )
                ), 'Restriction saved'
            ),
            'warnings' => new external_warnings()
        )
    );
}

/////////////

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
 * Set user restrictions.
 *
 * @param array $restrictions list of restrictions including name, value and userid
 * @return array of warnings and restrictions saved
 * @since Moodle 3.2
 * @throws moodle_exception
 */
public static function get_restriction($quizid) {
    global $USER, $DB;

    $params = self::validate_parameters(self::set_restriction_parameters(), array('quizid' => $quizid));

    if (empty($params['quizid']) || $params['quizid'] == 0){
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
                $quiz = $DB->get_record('quiz', $quizparams, 'id', MUST_EXIST );
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

                  $sebserverrecord = $DB->get_record('quizaccess_sebserver', array('quizid' => $quizid), 'id, quizid, quitlink, quitsecret, sebserverenabled');
                  if(!$sebserverrecord){
                    throw new moodle_exception('SEB Server is not enabled for quiz ID '.$quizid);
                  } else { // Insert
                    if($sebserverrecord->sebserverenabled == 0){
                      throw new moodle_exception('SEB Server available but not enabled for quiz ID '.$quizid);
                    }

                  }
                  // Get core seb settings.
                $sebsettingsrec = $DB->get_record('quizaccess_seb_quizsettings', array('quizid' => $quizid), 'id, allowedbrowserexamkeys');

                if(!$sebsettingsrec){
                  throw new moodle_exception('SEB Client is not enabled for quiz ID '.$quizid.'. Check if someone updated the quiz manually.');
                }
                $bkeys = preg_split('~[ \t\n\r,;]+~', $sebsettingsrec->allowedbrowserexamkeys, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($bkeys as $i => $key) {
                            $bkeys[$i] = strtolower($key);
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
                    'message' => 'You are not allowed to GET the restriction for quiz '.$quizid
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
//    }

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
                      'quitlink' => new external_value(PARAM_TEXT, 'Exam quit link'),
                      'quitsecret' => new external_value(PARAM_TEXT, 'Exam quit secret'),
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
