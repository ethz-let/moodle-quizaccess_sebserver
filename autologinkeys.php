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
require_capability('mod/quiz:view', $context);

if (!confirm_sesskey()) {
    throw new \moodle_exception('sesskey');
}
if (has_capability('moodle/site:config', context_system::instance(), $USER->id) || is_siteadmin($USER->id)) {
    throw new moodle_exception('Admins are not allowed to use Autologin.');
}

$fs = new file_storage();
$files = $fs->get_area_files($context->id, 'quizaccess_sebserver', 'filemanager_sebserverconfigfile', 0, 'id DESC', false);
$file  = reset($files);
if ($file) {
    $fileurl = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                $file->get_filearea(), $file->get_itemid(),
                                                $file->get_filepath(), $file->get_filename(), false
                                               );
} else {
    $error = get_string('sebseverconfignotfound', 'quizaccess_sebserver');
    throw new moodle_exception($error);
}


// Delete previous keys.
delete_user_key('quizaccess_sebserver', $USER->id);
// Create a new key.
$iprestriction = getremoteaddr();
$validuntil = time() + 60; // Expires in 60 sec.

$key = create_user_key('quizaccess_sebserver', $USER->id, $id, $iprestriction, $validuntil);
$params = ['id' => $id, 'userid' => $USER->id, 'key' => $key, 'urltogo' => $fileurl];
$autologinurl = new moodle_url('/mod/quiz/accessrule/sebserver/sebclientautologin.php?',
                               $params
);
is_https() ? $autologinurl->set_scheme('sebs') : $autologinurl->set_scheme('seb');
@header($_SERVER['SERVER_PROTOCOL'] . ' 303 See Other');
@header('Location: ' . $autologinurl->out(false));
