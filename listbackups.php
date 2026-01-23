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
 * A script to serve files from web service client
 *
 * @package    quizaccess_sebserver
 * @copyright  2024 ETH Zurich (moodle@id.ethz.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * AJAX_SCRIPT - exception will be converted into JSON
 */
define('AJAX_SCRIPT', true);

/**
 * NO_MOODLE_COOKIES - we don't want any cookie
 */
define('NO_MOODLE_COOKIES', true);


require_once( '../../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

// Allow CORS requests.
header('Access-Control-Allow-Origin: *');

// Authenticate the user.
$token = required_param('token', PARAM_ALPHANUM);
$id = required_param('id', PARAM_INT);
$backuptype = required_param('backuptype', PARAM_RAW);

if ($id == 1 && $backuptype == 'course') {
    throw new moodle_exception('Site level backup is not allowed');
}
if ($backuptype != 'course' && $backuptype != 'quiz') {
    throw new moodle_exception('Backup type paramater is invalid');
}
if ($backuptype == 'quiz') {
    $qcm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
    $courseid = $qcm->course;
    $context = context_module::instance($qcm->id);
} else {
    $context = context_course::instance($id);
}


$webservicelib = new webservice();
$authenticationinfo = $webservicelib->authenticate_user($token);

// Check the service allows file download.
$enabledfiledownload = (int) ($authenticationinfo['service']->downloadfiles);
if (empty($enabledfiledownload)) {
    throw new webservice_access_exception('Web service file downloading must be enabled in external service settings');
}

$result = [];
$filescontext = 
// Get course-level backups (course or quiz).
$sql = 'select id, contextid, component, filearea, filename, timecreated, timemodified, filesize
        from {files} where
        filename != :filename and component = :component and mimetype = :mimetype
        and contextid = :contextid order by timemodified desc limit 10';
        $params = ['filename' => '.', 'component' => 'backup',
                   'mimetype' => 'application/vnd.moodle.backup',
                   'contextid' => $context->id];
$backups = $DB->get_records_sql($sql, $params);
foreach($backups as $backup){
   $location = '/' . $backup->component . '/' . $backup->filearea. '/';
   $basedownload = $CFG->wwwroot . '/mod/quiz/accessrule/sebserver/downloadbackup.php';
   $relativelink = '/' . $backup->contextid . $location . $backup->filename;
   $downloadlink = $basedownload . 
                   '?token=' . $token . '&relativelink=' . $relativelink;
   $backupdata = ['name' => $backup->filename, 'timecreated' => userdate($backup->timecreated),
                  'timemodified' => userdate($backup->timemodified), 'filesize' => display_size($backup->filesize),
                  'directdownload' => $downloadlink, 'basedownload' => $basedownload, 'relativelink' => $relativelink];

   $result[$backup->id] = $backupdata;
   unset($backupdata);

}
echo json_encode($result);