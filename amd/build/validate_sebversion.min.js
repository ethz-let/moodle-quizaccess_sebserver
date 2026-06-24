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
        VERSIONLOADING: '.sebserver-versionloading',
        VERSIONERROR: '.sebserver-versionerror',
    };

    /** @var Template List of mustache templates. */
    const TEMPLATE = {
        VERSIONLOADING: 'quizaccess_sebserver/versionloading',
        VERSIONERROR: 'quizaccess_sebserver/versionerror',
    };

    exitLink = '';
    requiredVersions = '';
    foundVersion = '';

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
    function addVersionLoadingAlert() {
        return Templates.render(TEMPLATE.VERSIONLOADING, {}).then((html, js) => {
            const alertRegion = window.document.querySelector(SELECTOR.MAIN);
            return Templates.prependNodeContents(alertRegion, html, js);
        }).catch(Notification.exception);
    };

    /**
     * Remove the Safe Exam Browser access check alert from the page.
     */
    function clearVersionLoadingAlert() {
        const alert = window.document.querySelector(SELECTOR.VERSIONLOADING);
        if (alert) {
            Templates.replaceNode(alert, '', '');
        }
    };

    // divs for version error messages.

    /**
     * Add an alert to page to inform that Safe Exam Browser access is being checked.
     *
     * @return {Promise}
     */
    function addVersionError() {
        return Templates.render(TEMPLATE.VERSIONERROR, {exitlink: exitLink, foundversion: foundVersion, requiredversions: requiredVersions}).then((html, js) => {
            const alertRegion = window.document.querySelector(SELECTOR.MAIN);
            return Templates.prependNodeContents(alertRegion, html, js);
        }).catch(Notification.exception);
    };

    /**
     * Remove the Safe Exam Browser access check alert from the page.
     */
    function clearVersionError() {
        const alert = window.document.querySelector(SELECTOR.VERSIONERROR);
        if (alert) {
            Templates.replaceNode(alert, '', '');
        }
    };
    // END divs for version error messages.

    /**
     * Display validation failed modal.
     */
    function showVersionValidationFailedModal() {
        addVersionError();
        clearVersionLoadingAlert();
        // Hide the invalid key text caused by SEB deeper intergration.
        var q = document.querySelectorAll('.quizattempt .text-start');
        q.forEach(function(elem) {
            elem.style.display = 'none';
        });
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
            setTimeout(clearVersionLoadingAlert, 1000);
            setTimeout(clearVersionError, 1000);
            if (response.versionvalidated == true) {
               allowAccess();
            } else {
                requiredVersionsArray = response.restrectedversions;
               /* requiredVersions = '';
                for(var i = 0; i < requiredVersionsArray.length; i++){
                    for(var j = 0; j < requiredVersionsArray[i].length; j++){
                        requiredVersions += "<li>" + requiredVersionsArray[i][j] + "</li>";
                    }
                }*/
                requiredVersions = "<li>" + requiredVersionsArray.join("</li><li>") + "</li>";
                foundVersion = response.foundversion;
                setTimeout(showVersionValidationFailedModal, 1000);
            }
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
        const request = {
            methodname: 'quizaccess_sebserver_validate_sebversion',
            args: {
                version: window.SafeExamBrowser.version,
                cmid: cmid,
            },
        };

        return Ajax.call([request])[0];
    }

    return {
        init: async function(cmid, exitlink) {
                    // If the SafeExamBrowser object is instantiated, try and use it to fetch the access keys.
                    if (window.SafeExamBrowser !== null) {
                        await addVersionLoadingAlert();
                        exitLink = exitlink;
                        if (window.SafeExamBrowser.version !== null) {
                            checksebversion(cmid);
                        } else {
                            setTimeout(showVersionValidationFailedModal, 1000);
                        }
                    }
        }
    }
});