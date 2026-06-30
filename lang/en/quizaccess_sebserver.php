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
 * Strings for the quizaccess_sebserver plugin.
 *
 * @package   quizaccess_sebserver
 * @author    Kristina Isacson (kristina.isacson@id.ethz.ch)
 * @copyright 2024 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SEB Server';
$string['quizismanagedbysebserver'] = 'The Exam is managed by SEB Server. You must launch Safe Exam Browser before attempting the exam.';
$string['managedbysebserver'] = 'The Exam is managed by SEB Server. Changing values might affect the exam setup, please proceed carefully. You can deactivate the connection to the SEB Server via the SEB Server section below';
$string['enablesebserver'] = 'Enable SEB Server';
$string['sebserver:managesebserver'] = 'Manage SEB Server';
$string['privacy:metadata'] = 'The SEB Server access rule plugin does not store any personal data.';
$string['connectionnotsetupyet'] = 'The connection is not setup yet';
$string['notemplate'] = 'No template';
$string['sebserverexamtemplate'] = 'Exam template';
$string['sebserverexamtemplate_help'] = 'Select from your organization\'s SEB Server exam templates according to your scenario.';
$string['showquitbtn'] = 'Show quit button';
$string['sebserverquitsecret'] = 'Quit/unlock password';
$string['sebserverquitsecret_help'] = 'With either the "Quit" button or Ctrl+q (CMD-Q) the user can quit SEB with this password or unlock the screen. For summative exams it is recommended to use a quit password.';
$string['modificationinstruction'] = 'To change settings, first disable SEB Server and save the settings. This releases the SEB Server connection. Then enable SEB Server again for the options to become available again.';
$string['adminsonly'] = 'Admins only';
$string['resetseb'] = 'Release SEB Server connection';
$string['resetseb_help'] = 'Website administrators can terminate the connection to the SEB server.';
$string['examnotrestrictedyet'] = 'The browser exam key check is not activated! In order for the test to be completed, the browser exam key check must be activated in the SEB server.';
$string['launchsebserverconfig'] = 'Start Safe Exam Browser';
$string['downloadsebserverconfig'] = 'Download SEB Server configuration file';
$string['sebseverconfignotfound'] = ' SEB Server configuration file is not found!';
$string['autologintosebserver'] = 'SEB Server monitoring';
$string['templatemustbeselected'] = 'You must select an Exam template';
$string['connectionid'] = 'Connection ID';
$string['connectionname'] = 'Connection name';
$string['connectionurl'] = 'Connection endpoint';
$string['autologinurl'] = 'SEB Server autologin';
$string['accesstoken'] = 'Token';
$string['templateid'] = 'ID';
$string['templatename'] = 'Name';
$string['templatedescription'] = 'Description';
$string['templates'] = 'Templates';
$string['setting:sebserverconnectiondetails'] = 'Connection details';
$string['setting:sebserversettings'] = 'Settings';
$string['sebserver:canusesebserver'] = 'Can use SEB Server';
$string['sebserver:candeletesebserver'] = 'Can deactivate SEB Server';
$string['sebserver:managesebserverconnection'] = 'Can manage Seb Server connection';
$string['sebserver:sebserverautologinlink'] = 'Can autologin into SEB Server';
$string['selectemplate'] = 'Select Exam template';
$string['sebservertemplateid'] = 'SEB Server template';
$string['sebservertemplateid_help'] = 'Select from your organisation\'s SEB Server exam templates according to your scenario.';
$string['manageddevicetemplate'] = 'Exam Configuration ID =';
$string['proceednextquiz'] = 'Go to';
$string['hasconsecutivequiz'] = 'Subsequent quiz';
$string['setting:sebclientsrestrictiondetails'] = 'SEB Client version restrictions';
$string['setting:sebclientrestrictionvalues'] = 'List of permitted versions';
$string['setting:noversionrestriction'] = 'Empty (No restriction)';
$string['setting:versioninghelpinfo'] = 'Allows you to specify one or more SEB version(s) that are permitted.<br />A version restriction has the following format:
<b>OS.Major.Minor.[Patch].[min/max/eq/ne]</b><br >
Please enter one version per line, e.g. Win.3.9.min to allow all SEB for Windows versions starting with 3.9 or
newer.<ul>
<li><b>OS</b>: Win , macOS , or iOS</li>
<li><b>Major / Minor / Patch</b>: numeric version components (e.g. 3.9.1)</li>
<li><b>min or max or eq or ne</b>: appends "operator" semantics to the rest of the rule</ul>';
$string['invalidversiontitle'] = 'Wrong SEB version';
$string['invalidversionmsg'] = 'The installed Safe Exam Browser version does not meet the specified minimum requirements. Please install the required version and start Safe Exam Browser again.';
$string['currentversion'] = 'Installed SEB Client version';
$string['requiredversions'] = 'Required SEB Client version(s)';
$string['howtofixversionerror'] = '<b>How to fix this:</b><ol><li>Click the button Exit Safe Exam Browser below to quit SEB.</li>
<li>Click the button Download Safe Exam Browser and install the required (minimum) version of SEB for your operating system.</li>
<li>Reload the Moodle Page.</li>
<li>You might need to log in to Moodle again.</li>
<li>Click on the button Start Safe Exam Browser.</li></ol>';
$string['checkingversion'] = 'Checking SEB Client version..';
$string['allowedsebversions'] = 'Allowed SEB Client version(s)';
