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
 * @author     ETH Zurich (moodle@id.ethz.ch)
 * @copyright  2024 ETH Zurich
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
function xmldb_quizaccess_sebserver_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024052900) {
         // Delete old version records to reset Round 1 data.
         $table = new xmldb_table('quizaccess_sebserver');
         $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
         $table->add_field('sebserverquizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
         $table->add_field('sebserverenabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
         $table->add_field('sebserverrestricted', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
         $table->add_field('sebserverquitsecret', XMLDB_TYPE_CHAR, '255', null, null, null, '');
         $table->add_field('sebserverquitlink', XMLDB_TYPE_TEXT, null, null, null, null);
         $table->add_field('sebservertemplateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
         $table->add_field('sebservershowquitbtn', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
         $table->add_field('sebservercalled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);
         $table->add_field('sebservertimemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
         $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
         $table->add_key('sebserverquizid', XMLDB_KEY_FOREIGN, ['sebserverquizid'], 'quiz', ['id']);

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

         $dbman->create_table($table);
         upgrade_plugin_savepoint(true, 2024052900, 'quizaccess', 'sebserver');
    }
    if ($oldversion < 2025032100) {
        $table = new xmldb_table('quizaccess_sebserver');
        $quizidfield = new xmldb_field('nextquizid', XMLDB_TYPE_INTEGER, '10', null, false, null, null);
        // Conditionally add nextquizid field.
        if (!$dbman->field_exists($table, $quizidfield)) {
            $dbman->add_field($table, $quizidfield);
        }
        $courseidfield = new xmldb_field('nextcourseid', XMLDB_TYPE_INTEGER, '10', null, false, null, null);
        // Conditionally add nextcourseid field.
        if (!$dbman->field_exists($table, $courseidfield)) {
            $dbman->add_field($table, $courseidfield);
        }
        upgrade_plugin_savepoint(true, 2025032100, 'quizaccess', 'sebserver');
    }
    return true;
}
