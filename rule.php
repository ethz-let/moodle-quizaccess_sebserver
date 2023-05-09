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
 * Implementaton of the quizaccess_sebserver plugin.
 *
 * @package   quizaccess_sebserver
 * @author    Amr Hourani (amr.hourani@let.ethz.ch)
 * @copyright 2022 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
/**
 * A rule requiring SEB Server connection.
 *
 * @copyright  2022 ETH Zurich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_sebserver extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {

        if (empty($quizobj->get_quiz()->sebserverenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }
    /**
     * Returns a list of finished attempts for the current user.
     *
     * @return array
     */
    private function get_user_finished_attempts() : array {
        global $USER;

        return quiz_get_user_attempts(
            $this->quizobj->get_quizid(),
            $USER->id,
            quiz_attempt::FINISHED,
            false
        );
    }
    /**
     * Helper function to display an Exit Safe Exam Browser button if configured to do so and attempts are > 0.
     *
     * @return string empty or a button which has the configured seb quit link.
     */
    private function get_quit_button() : string {
        $quitbutton = '';
        $contact = '?';

        if (empty($this->get_user_finished_attempts())) {
            return $quitbutton;
        }
        // Only display if the link has been configured and attempts are greater than 0.
        if (!empty($this->quiz->quitlink) && !empty($this->quiz->quitsecret)) {
            if (strpos($this->quiz->quitlink, '?') !== false) {
                $contact = '&';
            }
            $quitbutton = html_writer::link(
                $this->quiz->quitlink . $contact . $this->quiz->quitsecret,
                get_string('exitsebbutton', 'quizaccess_seb'),
                ['class' => 'btn btn-secondary']
            );
        }

        return $quitbutton;
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        if ($quizid = $quizform->get_instance()) { // Edit Mode.
            global $DB;
            // Check if quiz has Seb Server enabled for.
            $sebserver = $DB->get_record_sql('select * from {quizaccess_sebserver} where quizid = ?', [$quizid]);
            if (!empty($sebserver) && $sebserver->sebserverenabled == 1) {
                $mform->addElement('html',
                    '<script>var sebsection = document.getElementById("fitem_id_seb_requiresafeexambrowser"); ' .
                    'sebsection.insertAdjacentHTML( "beforebegin", "<div class=\"alert alert-warning alert-block fade in\">' .
                    get_string('managedbysebserver', 'quizaccess_sebserver') . '</div>"); </script>');
                $mform->addElement('header', 'sebserverheader', get_string('pluginname', 'quizaccess_sebserver'));
                $enableselectchange = array('style="pointer-events: none!important;background-color: #ededed;"');
                if (is_siteadmin()) {
                    $enableselectchange = array();
                }
                $mform->addElement('selectyesno', 'sebserverenabled', get_string('enablesebserver', 'quizaccess_sebserver'),
                    $enableselectchange);
                $mform->addElement('text', 'quitlink', 'QuitLink', 'readonly size=75');
                $mform->addElement('text', 'quitsecret', 'QuitSecret', 'readonly size=20');
            } else {
                $mform->addElement('hidden', 'quitlink', '');
                $mform->addElement('hidden', 'quitsecret', '');
                $mform->addElement('hidden', 'sebserverenabled', 0);
                $mform->setType('sebserverenabled', PARAM_INT);
            }
            $mform->addElement('hidden', 'overrideseb', 0);
            $mform->setType('overrideseb', PARAM_INT);
            $mform->setType('quitlink', PARAM_RAW);
            $mform->setType('quitsecret', PARAM_RAW);
        }
    }

    /**
     * Check if the current user can configure SEB Server.
     *
     * @param \context $context Context to check access in.
     * @return bool
     */
    public static function can_configure_sebserver(\context $context) : bool {
        return has_capability('quizaccess/sebserver:managesebserver', $context);
    }
    /**
     * It is possible for one rule to override other rules.
     *
     * The aim is that third-party rules should be able to replace sandard rules
     * if they want. See, for example MDL-13592.
     *
     * @return array plugin names of other rules that this one replaces.
     *      For example array('ipaddress', 'password').
     */
    public function get_superceded_rules() {
        return array();
    }
    /**
     * Generate and display description
     */
    public function description() {
        global $CFG, $DB, $USER, $PAGE;
        $quizid = $this->quizobj->get_quizid();
        $return = '';
        $return .= html_writer::start_div('alert alert-info alert-block fade in', array('style' => "text-align: left;")) .
                    get_string('quizismanagedbysebserver', 'quizaccess_sebserver') . html_writer::end_div('');
        $return .= html_writer::div($this->get_quit_button());
        return $return;

    }
    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->sebserverenabled) || $quiz->sebserverenabled == 0) {
            $DB->delete_records('quizaccess_sebserver', array('quizid' => $quiz->id));
        } else {
            $rec = $DB->get_record('quizaccess_sebserver', array('quizid' => $quiz->id));
            if (!$rec) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->sebserverenabled = $quiz->sebserverenabled;
                $record->quitlink = $quiz->quitlink;
                $record->quitsecret = $quiz->quitsecret;
                $record->overrideseb = $quiz->overrideseb;
                $DB->insert_record('quizaccess_sebserver', $record);
            } else { // Update potential manual changes.
                $record = new stdClass();
                $record->id = $rec->id;
                $record->quizid = $quiz->id;
                $record->sebserverenabled = $quiz->sebserverenabled;
                $record->quitlink = $quiz->quitlink;
                $record->quitsecret = $quiz->quitsecret;
                $record->overrideseb = $quiz->overrideseb;
                $DB->update_record('quizaccess_sebserver', $record);
            }
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_sebserver', array('quizid' => $quiz->id));
    }

    public static function get_settings_sql($quizid) {
        return array(
            'sebserverenabled, quitlink, quitsecret',
            'LEFT JOIN {quizaccess_sebserver} sebserver ON sebserver.quizid = quiz.id',
            array());
    }
}
