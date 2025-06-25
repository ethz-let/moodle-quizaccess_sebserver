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
 * Auto-login end-point, a user can be fully authenticated in the site providing a valid key.
 *
 * @package    quizaccess_sebserver
 * @copyright  2024 ETH Zurich (moodle@id.ethz.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 // No login check is expected here because it related to login key.
 // @codingStandardsIgnoreLine
require_once('../../../../config.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUMEXT);
$urltogo = optional_param('urltogo', $CFG->wwwroot, PARAM_RAW);
$urltogo = $urltogo ?: $CFG->wwwroot;
$context = context_system::instance();
$PAGE->set_context($context);

$params = ['cnfg' => $urltogo];
$middleman = new moodle_url('/mod/quiz/accessrule/sebserver/middleman.php?',
                            $params);
$dielink = ' <a href="'.$CFG->wwwroot.'">' . get_string('continue') . '</a>';
// Check if the user is already logged-in.
if (isloggedin() && !isguestuser()) {
    delete_user_key( 'quizaccess_sebserver', $userid, $id);
    if ($USER->id == $userid) {
        redirect($middleman);
        exit;
    } else {
        die('Login key does not belong to the current user.
             Either download the config file manually,
             or reload the exam page again. Code: 1.' . $dielink);
    }
}

if (!$CFG->enablewebservices) {
    die(get_string('enablewsdescription', 'webservice') . $dielink);
}

if (!is_https()) {
     die(get_string('httpsrequired', 'tool_mobile') . $dielink);
}

if (has_capability('moodle/site:config', context_system::instance(), $userid) ||
    is_siteadmin($userid)) {
     die(get_string('autologinnotallowedtoadmins', 'tool_mobile') . $dielink);
}

// Validate and delete the key.
if (!$keyrec = $DB->get_record('user_private_key', ['script' => 'quizaccess_sebserver', 'value' => $key, 'instance' => $id])) {
    // Check if the user is already logged-in.
    if (isloggedin() && !isguestuser()) {
        delete_user_key( 'quizaccess_sebserver', $userid, $id);
        if ($USER->id == $userid) {
            redirect($middleman);
            exit;
        } else {
             die('Login key does not belong to the current user.
                  Either download the config file manually,
                  or reload the exam page again. Code: 2.' . $dielink);
        }
    }
    die('There is no login key record in the database.
         It could have expired, also please make sure that session clearing in SEB is disabled.' . $dielink);
}

if (!empty($keyrec->validuntil) && $keyrec->validuntil < time()) {
     die(get_string('expiredkey', 'error') . $dielink);
}

if ($keyrec->iprestriction) {
    $remoteaddr = getremoteaddr(null);
    if (empty($remoteaddr) || !address_in_subnet($remoteaddr, $keyrec->iprestriction)) {
        die(get_string('ipmismatch', 'error') . $dielink);
    }
}

// Double check key belong to user.
if ($keyrec->userid != $userid) {
    die('Login key does not belong to the current user.
         Either download the config file manually,
         or reload the exam page again. Code: 3.' . $dielink);
}

// Key validated, now require an active user: not guest, not suspended.
$user = core_user::get_user($keyrec->userid, '*', MUST_EXIST);
core_user::require_active_user($user, true, true);

// Do the user log-in.
if (!$user = get_complete_user_data('id', $user->id)) {
    die('Can not find user.' . $dielink);
}

@complete_user_login($user);

\core\session\manager::apply_concurrent_login_limit($user->id, session_id());

redirect($middleman);
