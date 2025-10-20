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
 * Restore code for the quizaccess_sebserver plugin.
 *
 * @package   quizaccess_sebserver
 * @author    ETH Zurich (moodle@id.ethz.ch)
 * @copyright 2024 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');


/**
 * Provides the information to restore the sebserver quiz access plugin.
 *
 * If this plugin is required, a single
 * <quizaccess_sebserver><required>1</required></quizaccess_sebserver> tag
 * will be in the XML, and this needs to be written to the DB. Otherwise, nothing
 * needs to be written to the DB.
 *
 * @copyright 2024 ETH Zurich (moodle@id.ethz.ch)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_sebserver_subplugin extends restore_mod_quiz_access_subplugin{

    /**
     * Use this method to describe the XML structure required to store your
     * sub-plugin's settings for a particular quiz, and how that data is stored
     * in the database.
     */
    protected function define_quiz_subplugin_structure() {

        $paths = [];

        $elename = $this->get_namefor('');
        $elepath = $this->get_pathfor('/quizaccess_sebserver');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes the quizaccess_sebserver element, if it is in the file.
     * @param array $data the data read from the XML file.
     */
    public function process_quizaccess_sebserver($data) {
        global $DB;
        // EMDL-1022 Backup-restore will NOT contain SEBServer linking
        // due to change of courseid, quizid etc.
        $data = (object)$data;
        $data->sebserverquizid = $this->get_new_parentid('quiz');
        $DB->insert_record('quizaccess_sebserver', $data);
    }
}
