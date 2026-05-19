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
 * Validate Safe Exam Browser access keys.
 *
 * @module     quizaccess_seb/validate_sebversion
 * @author     ETH Zurich
 * @copyright  2026 ETH Zurich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define([
    'core/config',
    'core/ajax',
    'core/notification',
    'core/ajax',
    'core/str',
    'core/templates',
    'core/local/modal/alert'
], function($, Config, Notification, Ajax, Str, Templates, ModalAlert) {

    // SafeExamBrowser object will be automatically initialized if using the SafeExamBrowser application.
    window.SafeExamBrowser = window.SafeExamBrowser || null;

    /** @var SELECTOR List of CSS selectors. */
    const SELECTOR = {
        MAIN: '#region-main',
        LOADING: '.seb-loading',
    };

    /** @var Template List of mustache templates. */
    const TEMPLATE = {
        LOADING: 'quizaccess_seb/loading',
    };
    /**
     * Manages view when access has been granted.
     */
    function allowAccess(){
        window.location.reload();
    };

    /**
     * Add an alert to page to inform that Safe Exam Browser access is being checked.
     *
     * @return {Promise}
     */
    function addLoadingAlert() {
        return Templates.render(TEMPLATE.LOADING, {}).then((html, js) => {
            const alertRegion = window.document.querySelector(SELECTOR.MAIN);
            return Templates.prependNodeContents(alertRegion, html, js);
        }).catch(Notification.exception);
    };

    /**
     * Remove the Safe Exam Browser access check alert from the page.
     */
    function clearLoadingAlert() {
        const alert = window.document.querySelector(SELECTOR.LOADING);
        if (alert) {
            Templates.replaceNode(alert, '', '');
        }
    };

    /**
     * Display validation failed modal.
     */
    function showValidationFailedModal() {
        ModalAlert.create({
            title: Str.get_string('invalidsebversiontitle', 'quizaccess_sebserver'),
            body: Str.get_string('invalidsebversionbody', 'quizaccess_sebserver'),
            large: false,
            show: true,
        }).catch(Notification.exception);
    }

    /**
     * Once the keys are fetched, action checking access.
     *
     * @param {init} cmid Value of course module id of the quiz.
     * @param {boolean} autoreconfigure Value of Moodle setting: quizaccess_seb/autoreconfigureseb.
     */
    function checksebversion(cmid){
        // Action opening up the quiz.
        validateSebVersion(cmid).then((response) => {
            // Show the alert for an extra second to allow user to see it.
        setTimeout(clearLoadingAlert, 1000);
            if (response.configkey && response.browserexamkey) {
            // View.allowAccess();
            return true;
            } else {
                setTimeout(showValidationFailedModal, 1000);
            }

            return response;
        }).catch(err => {
            Notification.exception(err);
        });
    }

    /**
     * Validate keys in Moodle backend.
     *
     * @param {init} cmid Value of course module id of the quiz.
     * @return {Promise}
     */
    function validateSebVersion(cmid) {
        consoleparseFloat
        const request = {
            methodname: 'quizaccess_sebserver_validate_version',
            args: {
                cmid: cmid,
                sebversion: window.SafeExamBrowser.version
            },
        };

        return Ajax.call([request])[0];
    }

    return {
        init: async function(cmid) {
                    // If the SafeExamBrowser object is instantiated, try and use it to fetch the access keys.
                    if (window.SafeExamBrowser !== null) {
                        await addLoadingAlert();
                        setTimeout(showValidationFailedModal, 1000);
                        if (window.SafeExamBrowser.version !== null) {
                            checksebversion(cmid);
                        } else {
                            setTimeout(showValidationFailedModal, 1000);
                        }
                    }
        }
    }
});