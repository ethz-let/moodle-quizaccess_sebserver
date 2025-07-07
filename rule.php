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
 * @author    ETH Zurich (moodle@id.ethz.ch)
 * @copyright 2024 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use quizaccess_seb\seb_access_manager;
use mod_quiz\access_manager;

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
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {

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
    private function get_user_finished_attempts(): array {
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
    private function get_quit_button(): string {

        global $CFG;
        $quitbutton = '';

        if (empty($this->get_user_finished_attempts())) {
            return $quitbutton;
        }
        if (isset($this->quiz->nextquizid) && isset($this->quiz->nextcourseid) &&
                  $this->quiz->nextquizid != 0 && $this->quiz->nextcourseid != 0) {
            // Get cmid for the next quiz.
            [$nextquizcourse, $nextquizcm] = get_course_and_cm_from_instance($this->quiz->nextquizid, 'quiz');
            $nextquizcontext = context_module::instance($nextquizcm->id);
            $nextquizconfiglink = new moodle_url('/pluginfile.php/' . $nextquizcontext->id .
                                  '/quizaccess_sebserver/filemanager_sebserverconfigfile/0/SEBServerSettings.seb');
            $nextquizconfiglink->set_scheme('sebs');
            $quitbutton = html_writer::link(
                $nextquizconfiglink->out(),
                get_string('proceednextquiz', 'quizaccess_sebserver') . ': ' . $nextquizcm->name,
                ['id' => 'seb-nextquiz-button', 'class' => 'btn btn-primary']
            );
        } else {
            // Only display if the link has been configured and attempts are greater than 0.
            if (!empty($this->quiz->sebservershowquitbtn) &&
                $this->quiz->sebservershowquitbtn &&
                !empty($this->quiz->sebserverquitlink)) {
                $quitbutton = html_writer::link(
                    $this->quiz->sebserverquitlink,
                    get_string('exitsebbutton', 'quizaccess_seb'),
                    ['id' => 'seb-quit-button', 'class' => 'btn btn-primary']
                );
            }
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
        global $DB;
        $quizid = $ineditmode = $quizform->get_instance();
        $displaydwnloadbutton = [];
        if ($ineditmode) {
            $readonly = 'readonly ';
            // Check if quiz has Seb Server enabled for.
            $sebserver = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quizid]);
            if (!empty($sebserver) && $sebserver->sebserverenabled == 1) {
                $displaydwnloadbutton = ['style="pointer-events: none!important;background-color: #ededed;"'];
                if (!quiz_has_attempts($quizid)) {
                    $mform->addElement('html',
                    '<script>var sebsection = document.getElementById("fitem_id_seb_requiresafeexambrowser"); ' .
                    'sebsection.insertAdjacentHTML( "beforebegin", "<div class=\"alert alert-warning alert-block fade in\">' .
                    get_string('managedbysebserver', 'quizaccess_sebserver') . '</div>"); </script>');
                }
            }
        } else {
            $readonly = '';
            $displaydwnloadbutton = [];
        }
        $mform->addElement('header', 'sebserverheader', get_string('pluginname', 'quizaccess_sebserver'));

        $enableselectchange = ['style="pointer-events: none!important;background-color: #ededed;"'];

        $connection = self::sebserverconnectiondetails(1);
        $readonlymanageddevices = '';
        $templates = [];
        if (empty($connection)) {
            $mform->addElement('html',
                    '<div class="alert alert-warning alert-block fade in">' .
                    get_string('connectionnotsetupyet', 'quizaccess_sebserver') . '</div>');
        } else {
            $templates = [-1 => get_string('selectemplate', 'quizaccess_sebserver')];
            // Sometimes Sebserver set quiz with no template.
            if ($ineditmode && $sebserver && $sebserver->sebservertemplateid == 0) {
                $templates += [0 => get_string('notemplate', 'quizaccess_sebserver')];
            }
            foreach ($connection[3] as $templatedetails) {
                  $templates += [$templatedetails->{'template_id'} => $templatedetails->{'template_name'}];
            }
            // What if Template was imported direct via sebserver?.
            if ($ineditmode && $sebserver) {
                if (!array_key_exists($sebserver->sebservertemplateid, $templates)) {
                    $templates += [$sebserver->sebservertemplateid =>
                        get_string('manageddevicetemplate', 'quizaccess_sebserver') . ' ' . $sebserver->sebservertemplateid];
                    $readonlymanageddevices =
                        'sebserverenabled.setAttribute("style","pointer-events: none!important;background-color: #ededed;");';
                }
            }
            // Now prevent anyone from modifying if there are attempts.
            if ($ineditmode && quiz_has_attempts($quizid)) {
                $readonlymanageddevices =
                        'sebserverenabled.setAttribute("style","pointer-events: none!important;background-color: #ededed;");';
            }
        }
        global $COURSE;
        $context = context_course::instance($COURSE->id);
        $canusesebserver = has_capability('quizaccess/sebserver:canusesebserver', $context);
        $candeletesebserver = has_capability('quizaccess/sebserver:candeletesebserver', $context);

        if (!$ineditmode && $canusesebserver && $connection) { // Create Mode.
            $enableselectchange = [];
        }
        if ($ineditmode && $candeletesebserver && $connection) { // Edit Mode.
            $enableselectchange = [];
        }

        $sebserverformchange = $enableselectchange + ['onChange' => 'sebserevrselectionchange(this)'];
        $mform->addElement('selectyesno', 'sebserverenabled', get_string('enablesebserver', 'quizaccess_sebserver'),
          $sebserverformchange);
        $mform->setType('sebserverenabled', PARAM_INT);

        $embedjsscript = '<script>

                          coresebplugin = document.querySelector("#id_seb_requiresafeexambrowser");
                          initialselectedseboption = coresebplugin.value;
                          sebserverenabled = document.getElementById("id_sebserverenabled");
                          initialsebserverenabled = sebserverenabled.selectedIndex;
                          if (initialsebserverenabled == 1) { // Enabled.
                           sebserevrselectionchange(sebserverenabled);

                          }
                          ' . $readonlymanageddevices . '
                          function sebserevrselectionchange(sel) {
                                var coresebplugin = document.querySelector("#id_seb_requiresafeexambrowser");
                                var allowedexamkeys = document.getElementById("id_seb_allowedbrowserexamkeys");
                                // Create a new change event
                                if (sel.value == 1) {
                                    coresebplugin.setAttribute("style","pointer-events: none!important;background-color: #ededed;");
                                    coresebplugin.value = 4;
                                    allowedexamkeys.readOnly = true;
                                  } else {
                                    coresebplugin.value = initialselectedseboption;
                                    coresebplugin.setAttribute("style","pointer-events: inherit!important;");
                                    coresebplugin.setAttribute("style","background-color: inherit!important;");
                                    allowedexamkeys.readOnly = false;
                                  }

                                var event = new Event("change");
                                // Dispatch it.
                                coresebplugin.dispatchEvent(event);

                          }
                          </script>';
        $mform->addElement('html', $embedjsscript);
        if (is_array($templates) && $connection) {
            if (!$ineditmode) {
                $allowtemplatechange = [];
            } else {
                $allowtemplatechange = ['style="pointer-events: none!important;background-color: #ededed;"'];
            }
            // Address previous quizes that were created before sebserver.
            if ($ineditmode && empty($enableselectchange) && (!$sebserver || $sebserver->sebserverenabled == 0) ) {
                $allowtemplatechange = [];
                $readonly = ''; // Quit secret needs to be enabled too.
            }
            $mform->addElement('select', 'sebservertemplateid', get_string('sebserverexamtemplate', 'quizaccess_sebserver'),
                               $templates, $allowtemplatechange);
            $mform->setType('sebservertemplateid', PARAM_INT);
            $mform->disabledif ('sebservertemplateid', 'sebserverenabled', 'neq', 1);
            $mform->addHelpButton('sebservertemplateid', 'sebservertemplateid', 'quizaccess_sebserver');
        }
        $mform->addElement('selectyesno', 'sebservershowquitbtn', get_string('showquitbtn', 'quizaccess_sebserver'),
                           $displaydwnloadbutton);
        $mform->setType('sebservershowquitbtn', PARAM_INT);
        $mform->setDefault('sebservershowquitbtn', 1);
        $mform->disabledif ('sebservershowquitbtn', 'sebserverenabled', 'neq', 1);
        $mform->addElement('text', 'sebserverquitsecret',
                            get_string('sebserverquitsecret', 'quizaccess_sebserver'), $readonly . ' size="70"');
        $mform->setType('sebserverquitsecret', PARAM_RAW);
        $mform->setDefault('sebserverquitsecret', '');
        $mform->disabledif ('sebserverquitsecret', 'sebserverenabled', 'neq', 1);
        $mform->addHelpButton('sebserverquitsecret', 'sebserverquitsecret', 'quizaccess_sebserver');

        if ($ineditmode) {
            $mform->addElement('html',
                    '<div class="alert alert-warning alert-block fade in">' .
                    get_string('modificationinstruction', 'quizaccess_sebserver') . '</div>');
            if (is_siteadmin() && $sebserver) {
                $mform->addElement('checkbox', 'resetseb', get_string('adminsonly', 'quizaccess_sebserver'),
                                   get_string('resetseb', 'quizaccess_sebserver'));
                $mform->addHelpButton('resetseb', 'resetseb', 'quizaccess_sebserver');
            }
        }

    }

    /**
     * Check if the current user can configure SEB Server.
     *
     * @param \context $context Context to check access in.
     * @return bool
     */
    public static function can_configure_sebserver(\context $context): bool {
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
        return [];
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
        global $CFG, $DB, $USER, $PAGE, $SESSION;

        $quizid = $this->quizobj->get_quizid();
        $cmid = $this->quizobj->get_cmid();
        $courseid = $this->quizobj->get_courseid();
        $return = '';
        if ($this->quizobj->has_capability('quizaccess/sebserver:sebserverautologinlink')) {
            $return .= html_writer::start_div('alert alert-info alert-block fade in',
                                             ['style' => "text-align: left;"]) .
                                             get_string('quizismanagedbysebserver', 'quizaccess_sebserver') .
                                             html_writer::end_div('');
            if (isset($this->quiz->nextquizid) && $this->quiz->nextquizid != 0) {
                [$nextquizcourse, $nextquizcm] = get_course_and_cm_from_instance($this->quiz->nextquizid, 'quiz');
                $nextquizparams = ['id' => $nextquizcm->id];
                $nextquizurl = new moodle_url('/mod/quiz/view.php?',
                                                $nextquizparams);
                $nextquizinfo = html_writer::link($nextquizurl->out(),
                                                  $nextquizcm->name . ' (' . $nextquizcourse->fullname . ')',
                                                  ['target' => '_blank']);
                $return .= html_writer::start_div('alert alert-info alert-block fade in',
                                                ['style' => "text-align: left;"]) .
                                                get_string('hasconsecutivequiz', 'quizaccess_sebserver') .
                                                ': ' . $nextquizinfo .
                                                html_writer::end_div('');
            }
        }
        $validsession = !empty($SESSION->quizaccess_seb_access[$cmid]);
        if ($validsession) {
            $return .= html_writer::div($this->get_quit_button()) .' ';
        }
        // Get SebConfig file from SebServer.
        $conndetails = self::sebserverconnectiondetails();
        if (empty($conndetails)) {
             throw new moodle_exception('connectionnotsetupyet', 'quizaccess_sebserver');
        }
        $endpoint = $conndetails[0];
        $token = $conndetails[1];
        $connid = $conndetails[2];

        // Check Sebserver settings for this quiz.
        if (!$sebserversettings = $DB->get_record('quizaccess_sebserver',
                                      ['sebserverquizid' => $quizid])) {
             throw new moodle_exception('quizhasnosebserverenabled', 'quizaccess_sebserver');
        }
        $context = context_module::instance($cmid);

        // Update Seb Server and get Seb Server config.
        // This function will be called so long as the sebservercalled is not set.
        if ($sebserversettings->sebservercalled == 0) {
            // Never call make_sebserver_exam_call it again after the first call.
            $DB->set_field('quizaccess_sebserver', 'sebservercalled', 1,
                    ['sebserverquizid' => $quizid]);
            $sebserversettings = self::make_sebserver_exam_call($conndetails, $context, $cmid);
        }
        $showactionbtns = 1;
        if ($sebserversettings->sebserverrestricted == 0) {
            if ($this->quizobj->has_capability('quizaccess/seb:bypassseb')) {
                $restrictionerror = get_string('examnotrestrictedyet', 'quizaccess_sebserver');
            } else {
                $restrictionerror = get_string('notavailable', 'quizaccess_openclosedate');
                $showactionbtns = 0;
            }
            $return .= '<div class="alert alert-warning alert-block fade in">' . $restrictionerror . '</div>';
        }

        // Display download SEB config link for those who can bypass using SEB.
        if ($this->display_sebserver_actionbtns($cmid) && $showactionbtns != 0) {
            $timenow = time();
            $quizobj = quiz_settings::create_for_cmid($cmid, $USER->id);
            $accessmanager = new access_manager($quizobj, $timenow,
            has_capability('mod/quiz:ignoretimelimits', $context, null, false));

            $timewindowcheck = new quizaccess_openclosedate($quizobj, time());
            if ($timewindowcheck->prevent_access() && !$this->quizobj->has_capability('quizaccess/seb:bypassseb')) {
                return;
            }

            $numattemptscheck = new quizaccess_numattempts($quizobj, time());
            if ($numattemptscheck->prevent_access() && !$this->quizobj->has_capability('quizaccess/seb:bypassseb')) {
                return;
            }

            $fs = new file_storage();
            $files = $fs->get_area_files($context->id, 'quizaccess_sebserver', 'filemanager_sebserverconfigfile',  0,
                        'id DESC', false);
            $file  = reset($files);
            if ($file) {
                $url = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );

                // Autologin area (only non admins).
                if (!has_capability('moodle/site:config', context_system::instance(), $USER->id) &&
                                   !is_siteadmin($USER->id)) {
                    // Delete previous keys.
                    delete_user_key('quizaccess_sebserver', $USER->id);
                    // Create a new key.
                    $iprestriction = getremoteaddr();
                    $validuntil = time() + 900; // Expires in 15 mins.
                    $key = create_user_key('quizaccess_sebserver', $USER->id, $cmid, $iprestriction, $validuntil);
                    $params = ['id' => $cmid, 'userid' => $USER->id, 'key' => $key, 'urltogo' => $url];
                    $autologinurl = new moodle_url('/mod/quiz/accessrule/sebserver/sebclientautologin.php?',
                                                $params);
                } else {
                    $autologinurl = new moodle_url('/mod/quiz/accessrule/sebserver/config.php?cmid=' . $cmid);
                }
                is_https() ? $autologinurl->set_scheme('sebs') : $autologinurl->set_scheme('seb');
                // End of autologin area.

                // Launch SebServerConfig.
                $extrabtnparams = [
                                    'class' => 'btn btn-primary',
                                    'id'    => 'id_launchsebserverconfigfile',
                                  ];
                $return .= html_writer::link(
                    $autologinurl,
                    get_string('launchsebserverconfig', 'quizaccess_sebserver'),
                    $extrabtnparams
                );
                // Download SebServerConfig.
                is_https() ? $url->set_scheme('https') : $url->set_scheme('http');
                $url->param('forcedownload', 1);
                $return .= ' ' . html_writer::link(
                    $url,
                    get_string('downloadsebserverconfig', 'quizaccess_sebserver'),
                    ['class' => 'btn btn-primary',
                    'id'     => 'id_downloadsebserverconfigfile']
                );
            } else {
                $error = get_string('sebseverconfignotfound', 'quizaccess_sebserver');
                $return .= '<div class="alert alert-warning alert-block fade in">'.$error."</div>";

            }
            // SebServer Auto-login link.
            if ($this->quizobj->has_capability('quizaccess/sebserver:sebserverautologinlink')  &&
                ($this->quiz->timeclose == 0 || $this->quiz->timeclose > time())) {
                $sebserverautologinlink = new moodle_url('/mod/quiz/accessrule/sebserver/sebserverautologin.php?',
                                                         ['id' => $cmid, 'sesskey' => sesskey()]);
                $return .= ' ' . html_writer::link(
                    $sebserverautologinlink,
                    get_string('autologintosebserver', 'quizaccess_sebserver'),
                    ['class'  => 'btn btn-primary',
                    'target'  => 'SEBServerAutoLogin',
                    'id'      => 'id_sebserverautologinlink',
                    ]
                );
            }
        }
        return $return;

    }
    /**
     * Validate the data from any form fields added.
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param mod_quiz_mod_form $quizform the quiz form object.
     * @return array $errors the updated $errors array.
     */
    public static function validate_settings_form_fields(array $errors,
                                                         array $data, $files, mod_quiz_mod_form $quizform): array {
        global $CFG, $DB, $USER;
        $quizid = $data['instance'];
        $courseid = $data['course'];
        $sebserverenabled = $data['sebserverenabled'];

        // If Template is -1 (disbaled option), then no SebServer intentions here.
        if (!isset($data['sebservertemplateid'])) {
            return $errors;
        }
        $sebservertemplateid = $data['sebservertemplateid'];
        $sebserverquitsecret = $data['sebserverquitsecret'];
        $cmid = $data['coursemodule'];
        $draftitemid = $data['filemanager_sebconfigfile'];
        // Check if admin wants to drop sebserver link.
        if (isset($data['resetseb']) && is_siteadmin() && $quizid) {
            return $errors;
        }
        // Quiz creation mode with SebServer not being enabled.
        // Ignore the validations.
        if (!$quizid && !$sebserverenabled) {
            return $errors;
        }
        $conndetails = self::sebserverconnectiondetails();
        if (empty($conndetails)) {
             $errors['sebserverenabled'] = get_string('connectionnotsetupyet', 'quizaccess_sebserver');
             return $errors;
        }
        if ($sebservertemplateid == -1 && $sebserverenabled == 1) {
            $errors['sebservertemplateid'] = get_string('templatemustbeselected', 'quizaccess_sebserver');
            return $errors;
        }

        $endpoint = $conndetails[0];
        $token = $conndetails[1];
        $connid = $conndetails[2];

        // Check if its a disable and send info to SebServer.
        $sebserver = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quizid]);
        // Disable SebServer?.
        if ($sebserver && $data['sebserverenabled'] == 0) {
            $function = '/exam';
            $params = ['id' => $connid,
                            'course_id' => $courseid,
                            'quiz_id' => $quizid,
                      ];
            $method = 'delete';

            $url = $endpoint . $function;
            $sebserverresponse = self::call_sebsever($url, $token, $params , $method);
            // SebServer deletion issue?.
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
                    $error = ' ERROR ' . $sebserverresponse[2] . ' ' .$sebserverresponse[1] .
                            ' [' . $function . '/' . $method . ']';
                }
                $errors['sebserverenabled'] = $error;
                return $errors;
            }
            // Server empty? is it really needed validation?.
            if (!isset($sebserverresponse)) {
                $error = 'Empty response: ' . $sebserverresponse[1] .
                                            ' [HTTP Code: ' . $sebserverresponse[2] . ']' .
                                            '(' . $function . '/' . $method . ')';
                $errors['sebserverenabled'] = $error;
                return $errors;
            }
        }

        return $errors;
    }
    /**
     * Save any submitted settings when the quiz settings form is submitted.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;
        if (!isset($quiz->sebservertemplateid)) {
            // Sebserver is not involved here.
            return;
        }
        $context = context_module::instance($quiz->coursemodule);
        if (empty($quiz->sebserverenabled) || $quiz->sebserverenabled == 0 ||
            (isset($quiz->resetseb) && is_siteadmin()) ) {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'quizaccess_sebserver', 'filemanager_sebserverconfigfile');
            $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
            $DB->set_field('quizaccess_seb_quizsettings', 'allowedbrowserexamkeys',
                            null, ['quizid' => $quiz->id]);
            // Delete the seb cache just in case.
            $sebcache = \cache::make('quizaccess_seb', 'config');
            $sebcache->delete($quiz->id);

            $quizsettingscache = \cache::make('quizaccess_seb', 'quizsettings');
            $quizsettingscache->delete($quiz->id);

            $configkeycache = \cache::make('quizaccess_seb', 'configkey');
            $configkeycache->delete($quiz->id);                
        } else {
            $rec = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
            if (!$rec) {
                $record = new stdClass();
                $record->sebserverquizid = $quiz->id;
                $record->sebserverenabled = $quiz->sebserverenabled;
                $record->sebservertemplateid = $quiz->sebservertemplateid;
                $record->sebservershowquitbtn = $quiz->sebservershowquitbtn;
                $record->sebserverquitlink = '';
                $record->sebserverrestricted = 0;
                $record->sebservercalled = 0;
                $record->sebservertimemodified = time();
                if (!isset($quiz->sebserverquitsecret)) {
                    $quiz->sebserverquitsecret = '';
                }
                $record->sebserverquitsecret = $quiz->sebserverquitsecret;
                $DB->insert_record('quizaccess_sebserver', $record);
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
        $conndetails = self::sebserverconnectiondetails();
        $function = '/exam';
        $params = ['id' => $conndetails[2],
                        'course_id' => $quiz->course,
                        'quiz_id' => $quiz->id,
                  ];
        $method = 'delete';
        $url = $conndetails[0] . $function;
        @self::call_sebsever($url, $conndetails[1], $params , $method);
        $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
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
    public static function get_settings_sql($quizid): array {

        return [
            'sebserver.id as sebserverid, sebserver.sebserverquizid as sebserverquizid,
             sebserver.sebserverenabled as sebserverenabled, sebserver.sebserverrestricted as sebserverrestricted,
             sebserver.sebservertimemodified as sebservertimemodified, sebserver.sebservertemplateid as sebservertemplateid,
             sebserver.sebserverquitsecret as sebserverquitsecret,
             sebserver.sebservercalled as sebservercalled, sebserver.sebservershowquitbtn as sebservershowquitbtn,
             sebserver.sebserverquitlink as sebserverquitlink, sebserver.nextquizid as nextquizid,
             sebserver.nextcourseid as nextcourseid',
            'LEFT JOIN {quizaccess_sebserver} sebserver ON sebserver.sebserverquizid = quiz.id',
            [],
        ];
    }
    /**
     * Setup attempt page.
     *
     * @param stdClass $page page.
     * Set header earlier so that Safe Exam Browser can detect proctoring using SAML2 method of login.
     */
    public function setup_attempt_page($page) {
        global $USER;

        // Force session init on preload.
        if (isloggedin() && !isguestuser()) {
            // You could log this or preload something.
            @header("X-LMS-USER-ID: $USER->id");
            @header("X-LMS-USER-USERNAME: $USER->username");
            @header("X-LMS-USER-EMAIL: $USER->email");
            @header("X-LMS-USER-IDNUMBER: $USER->idnumber");
        }
    }
    /**
     * Calls Rest API of SebServer.
     *
     * @param string $url end point.
     * @param string $token token.
     * @param array $data data of the api call.
     * @param string $method POST or GET or DELETE etc.
     * @param int $binary if the call is expecting a binary file.
     * @return array $response, $errormsg, $httpcode, $result array.
     */
    public static function call_sebsever($url, $token, $data, $method = 'post', $binary = 0): array {

        $ch = curl_init();
        $data = http_build_query($data,  '&amps;', '&');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        $authorization = "Authorization: Bearer " . trim ($token);
        $header = [
                  'application/x-www-form-urlencoded',
                  $authorization,
                  'Content-Length: ' . strlen($data),
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($binary == 1) {
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        switch ($method) {
            case 'get':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
            case 'post':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                break;
            case 'put':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
        }
        $result = curl_exec($ch);
        $response = json_decode($result);

        if ($binary == 1) {
            $headers = [];
            $filearray = explode("\n\r", $result, 2);
            $headerarray = explode("\n", $filearray[0]);
            foreach ($headerarray as $headervalue) {
                $headerpieces = explode(':', $headervalue);
                if (count($headerpieces) == 2) {
                      $headers[$headerpieces[0]] = trim($headerpieces[1]);
                }
            }
            header('Content-type: ' . $headers['Content-Type']);
            header('Content-Disposition: ' . $headers['Content-Disposition']);
        }

        $info = curl_getinfo($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $errormsg = '';
        if (curl_errno($ch)) {
            $errormsg = curl_error($ch);
        }
        curl_close($ch);
        return [$response, $errormsg, $httpcode, $result];
    }
    /**
     * Request helper for SebServer Config.
     *
     * @param int $courseid moodle course ID.
     * @param int $quizid moodle quiz ID.
     * @param int $cmid moodle course module ID.
     * @param int $contextid moodle quiz context ID.
     * @return array $errors if any.
     */
    public static function request_sebserverconfig($courseid, $quizid, $cmid, $contextid) {
        global $CFG, $DB;
        $conndetails = self::sebserverconnectiondetails();
        $error = '';
        $function = '/seb_config';
        $params = ['id' => $conndetails[2],
                        'course_id' => $courseid,
                        'quiz_id' => $quizid,
                  ];
        $method = 'POST';
        $endpoint = $conndetails[0];
        $url = $endpoint . $function . '?' . http_build_query($params,  '&amps;', '&');
        $binaryfileopts = [
                            'http' => [
                                      'method'  => $method,
                                      'header'  => "Content-Type: application/x-www-form-urlencoded\r\n".
                                                   "Content-Encoding: compress, deflate, gzip\r\n".
                                                   "Authorization: Bearer " . $conndetails[1] . "\r\n",
                                      'timeout' => 60,
                                      ],
                            'ssl' => [
                                     'verify_peer' => false,
                                     'verify_peer_name' => false,
                            ],
                           ];
        $curlcontext  = stream_context_create($binaryfileopts);
        $file = @file_get_contents($url, false, $curlcontext);

        if ($file !== false) {
            // Copy the file into temp.
            $realfilename = 'SEBServerSettings.seb';
            $destinationdir = $CFG->tempdir.'/sebserver';
            if (!is_dir($destinationdir)) {
                mkdir($destinationdir, 0777, true);
            }
            $destinationfile = $destinationdir . '/' . $realfilename;
            file_put_contents($destinationfile, $file);
            // Generate componenet file.
            $filerecord = new stdClass;
            $filerecord->component = 'quizaccess_sebserver';
            $filerecord->contextid = $contextid;
            $filerecord->filearea = 'filemanager_sebserverconfigfile';
            $filerecord->filename = $realfilename;
            $filerecord->filepath = '/';
            $filerecord->itemid = 0;

            // Check if the file already exist.
            $fs = get_file_storage();
            // Only one file is allowed. Clear the area.
            $fs->delete_area_files($filerecord->contextid, $filerecord->component,
                                   $filerecord->filearea);

            if ($storedfile = $fs->create_file_from_pathname($filerecord, $destinationfile)) {
                unlink($destinationfile);
            }

        } else {
            $error = 'Error: ' . $function . ': ' . json_encode(error_get_last());
        }
        return $error;
    }
    /**
     * Get connection details for SebServer.
     *
     * @param int $returntemplates to return templates of not.
     * @return array $endpoint, $token, $connid, $templates.
     */
    public static function sebserverconnectiondetails($returntemplates = 0) {
        global $DB;
        // Get SebConfig file from SebServer.
        if (!$connection = $DB->get_record('config_plugins',
                                      ['plugin' => 'quizaccess_sebserver',
                                       'name' => 'connection'])) {
            return [];
        }
        $conndetails = json_decode($connection->value);
        $endpoint = $conndetails->{'url'};
        $token = $conndetails->{'access_token'};
        $connid = $conndetails->{'id'};
        $templates = null;
        if ($returntemplates) {
            $templates = $conndetails->{'exam_templates'};
        }

        return [$endpoint, $token, $connid, $templates];
    }
    /**
     * Whether the user should see download/launch sebserver configuration.
     *
     * @param int $cmid course module id.
     * @return string true buttons to be shown.
     */
    public function display_sebserver_actionbtns($cmid) {
        global $SESSION;
        // Check if allowed before with valid session.
        if (isset($SESSION->quizaccess_seb_access[$cmid])) {
            return false;
        }
        // Check SEB Header.
        if (isset($_SERVER['HTTP_USER_AGENT']) &&
            strpos($_SERVER['HTTP_USER_AGENT'], 'SEB') !== false) {
            return false;
        }
        // Leave other validation else to Seb based on USE_SEB_CLIENT_CONFIG.
        return true;
    }
    /**
     * Update SebServer with the quiz data, and request seb server config.
     *
     * @param array $conndetails connecton details.
     * @param context $context context.
     * @param int $cmid cmid.
     *
     * @return stdClass $settings settings.
     */
    public function make_sebserver_exam_call($conndetails, $context, $cmid) {
        global $COURSE, $DB, $CFG;
        $quiz = $this->quizobj->get_quiz();
        $endpoint = $conndetails[0];
        $token = $conndetails[1];
        $connid = $conndetails[2];
        $examdata = '{
            "stats": {
                "coursecount":1,
                "needle":0,
                "perpage":10
            },
            "results": [{
                "id": "' . $COURSE->id. '",
                "shortname": ' . json_encode($COURSE->shortname) . ',
                "fullname": ' . json_encode($COURSE->fullname) . ',
                "idnumber": ' . json_encode($COURSE->idnumber) . ',
                "summary": ' . json_encode($COURSE->summary) . ',
                "startdate": "' . $COURSE->startdate . '",
                "enddate": "' . $COURSE->enddate . '",
                "timecreated": "' . $COURSE->timecreated . '",
                "visible": "' . $COURSE->visible . '",
                "quizzes": [
                    {
                        "id": "' . $quiz->id . '",
                        "course": "' . $COURSE->id . '",
                        "coursemodule": "' . $cmid . '",
                        "name": ' . json_encode($quiz->name) . ',
                        "intro": ' . json_encode($quiz->intro) . ',
                        "timeopen": ' . $quiz->timeopen . ',
                        "timeclose": ' . $quiz->timeclose . ',
                        "timecreated": ' . $quiz->timecreated . '
                    }
                ]
            }],
            "warnings": []
        }
        ';
        $function = '/exam';
        $params = ['id' => $connid,
                        'course_id' => $COURSE->id,
                        'exam_template_id' => $quiz->sebservertemplateid,
                        'quit_password' => $quiz->sebserverquitsecret,
                        'quiz_id' => $quiz->id,
                        'quit_link' => $quiz->sebservershowquitbtn,
                        'exam_data' => $examdata,
                  ];
        $method = 'post';
        $url = $endpoint . $function;
        $sebserverresponse = self::call_sebsever($url, $token, $params , $method);
        // SebServer validation issue?.
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
                $error = ' ERROR ' . $sebserverresponse[2] . ' ' .$sebserverresponse[1] .
                         ' [' . $function . '/' . $method . ']';
            }
            // Delete SebServer record.
            $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
            throw new moodle_exception($error, 'quizaccess_sebserver');
        }
        // Server empty? is it really needed validation?.
        if (!isset($sebserverresponse)) {
            $error = 'Empty response: ' . $sebserverresponse[1] .
                                          ' [HTTP Code: ' . $sebserverresponse[2] . ']' .
                                          '(' . $function . '/' . $method . ')';
            // Delete SebServer record.
            $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
            throw new moodle_exception($error, 'quizaccess_sebserver');
        }
        // Request Seb Server config file.
        $sebconfigresult = self::request_sebserverconfig($COURSE->id, $quiz->id,
        $cmid, $context->id);
        if (isset($sebconfigresult) && trim($sebconfigresult) !== '') {
            // Delete SebServer record.
            $DB->delete_records('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
            throw new moodle_exception($sebconfigresult, 'quizaccess_sebserver');
        }
        $settings = $DB->get_record('quizaccess_sebserver', ['sebserverquizid' => $quiz->id]);
        return $settings;
    }
}
