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
 * Auto-login end-point to SebServer.
 *
 * @package    quizaccess_sebserver
 * @copyright  2024 ETH Zurich (moodle@id.ethz.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\access_manager;
use mod_quiz\output\list_of_attempts;
use mod_quiz\output\renderer;
use mod_quiz\output\view_page;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

require_once('../../../../config.php');

$id = required_param('id', PARAM_INT);

$quizobj = quiz_settings::create_for_cmid($id, $USER->id);
$quiz = $quizobj->get_quiz();
$cm = $quizobj->get_cm();
$course = $quizobj->get_course();
// Check login and get context.
require_login($course, false, $cm);
$context = $quizobj->get_context();

if (!confirm_sesskey()) {
    throw new \moodle_exception('sesskey');
}
if (!$quizobj->has_capability('quizaccess/sebserver:sebserverautologinlink')) {
    throw new moodle_exception('You do not have permission to access this page.');
}
$conndetails = quizaccess_sebserver::sebserverconnectiondetails();
if (empty($conndetails)) {
     $error = get_string('connectionnotsetupyet', 'quizaccess_sebserver');
     throw new moodle_exception($error);
}

$function = '/login_token';
$params = ['id' => $conndetails[2],
                'course_id' => $course->id,
                'quiz_id' => $quiz->id,
                'user_id' => $USER->id,
                'user_idnumber' => $USER->idnumber,
                'user_username' => $USER->username,
                'user_email' => $USER->email,
                'user_fullname' => fullname($USER),
                'user_firstname' => $USER->firstname,
                'user_lastname' => $USER->lastname,
                'account_time_zone' => core_date::get_user_timezone($USER),
                'localised_time' => \core_date::strftime(get_string('strftimerecentfull', 'langconfig')),
                'timestamp' => time(),
           ];
$method = 'post';
$endpointurl = $conndetails[0] . $function;
$sebserverresponse = quizaccess_sebserver::call_sebsever($endpointurl, $conndetails[1], $params, $method);

if ($sebserverresponse[2] !== 200) {
    $responsebody = $sebserverresponse[0];
    if (!empty($responsebody)) {
        $error = $sebserverresponse[1];
        $error .= ' ERROR ' . $sebserverresponse[2] .
                                ': (' . $responsebody[0]->{'systemMessage'} . ') ' .
                                $responsebody[0]->{'details'} .
                                ' [' . $function . '/' . $method . ']';
        if (isset($responsebody->error)) {
            $error .= $responsebody->error;
        }
    } else {
        $error = ' ERROR ' . $sebserverresponse[2] . ' ' .$sebserverresponse[1] . ' [' . $function . '/' . $method . ']';
    }
    throw new moodle_exception($error);
} else {
    if (!isset($sebserverresponse[0]) || !$sebserverresponse[0]->login_link) {
        $error = ' SebServer autologin ERROR: Empty link.';
        throw new moodle_exception($error);
    } else {
        $loginlink = $sebserverresponse[0]->login_link;
        if (!isset($loginlink) || empty($loginlink)) {
            $error = ' ERROR: Login link to SebServer is not valid';
            throw new moodle_exception($error);
        } else {
            redirect($loginlink);
        }
    }
}
