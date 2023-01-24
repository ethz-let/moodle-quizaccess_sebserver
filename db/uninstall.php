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
 * Upgrade script for plugin.
 *
 * @package    quizaccess_sebserver
 * @author     Amr Hourani <amr.hourani@let.ethz.ch>
 * @copyright  2022 ETH Zurich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot  . '/mod/quiz/accessrule/sebserver/lib.php');

/**
 * Function to upgrade quizaccess_sebserver plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_quizaccess_sebserver_uninstall() {
    global $DB;
    $dbman = $DB->get_manager();
    $oldrecord = $DB->get_record('external_services', array('shortname' => 'SEB-Server-Webservice'));
    if ($oldrecord) {
      $params = ['id' => $oldrecord->id];
      $DB->delete_records('external_services', $params);

      $eparams = ['externalserviceid' => $oldrecord->id];
      $DB->delete_records('external_services_functions', $eparams);

      $uparams = ['externalserviceid' => $oldrecord->id];
      $DB->delete_records('external_services_users', $uparams);
    }

    return true;
}
