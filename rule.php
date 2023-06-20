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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * A rule requiring SEB Server connection.
 *
 * @copyright  2022 ETH Zurich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_sebserver extends access_rule_base {
    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return access_rule_base|null the rule, if applicable, else null.
     */
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

    /**
     * Add any fields that this rule requires to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
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
                $mform->addElement('text', 'quitlink', get_string('quitlink', 'quizaccess_sebserver'), 'readonly size=75');
                $mform->addElement('passwordunmask', 'quitsecret',
                                   get_string('quitsecret', 'quizaccess_sebserver'), 'readonly size=20');
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
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
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

    /**
     * Save any submitted settings when the quiz settings form is submitted.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
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

    /**
     * Delete any rule-specific settings when the quiz is deleted.
     *
     * @param stdClass $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_sebserver', array('quizid' => $quiz->id));
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) : array {
        return [
            'sebserverenabled, quitlink, quitsecret',
            'LEFT JOIN {quizaccess_sebserver} sebserver ON sebserver.quizid = quiz.id',
            []
        ];
    }
}
