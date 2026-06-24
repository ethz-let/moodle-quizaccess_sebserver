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
$string['quizismanagedbysebserver'] = 'Die Prüfung wird von SEB Server verwaltet. Sie müssen den Safe Exam Browser starten, bevor Sie an der Prüfung teilnehmen.';
$string['managedbysebserver'] = 'Die Prüfung wird von SEB Server verwaltet. Das Ändern von Werten kann sich auf die Prüfungseinstellungen auswirken, bitte gehen Sie vorsichtig vor. Sie können die Verbindung zum SEB-Server über den Abschnitt SEB-Server unten deaktivieren';
$string['enablesebserver'] = 'SEB Server aktivieren';
$string['sebserver:managesebserver'] = 'SEB Server verwalten';
$string['privacy:metadata'] = 'Das SEB Server Plugin speichert keine personenbezogenen Daten.';
$string['connectionnotsetupyet'] = 'Die Verbindung wurde noch nicht konfiguriert.';
$string['notemplate'] = 'Keine Vorlage';
$string['sebserverexamtemplate'] = 'Exam Vorlage';
$string['sebserverexamtemplate_help'] = 'Wählen Sie passend zu Ihrem Prüfungsszenario eine von Ihrer Organisation bereitgestellte Exam Vorlage aus.';
$string['showquitbtn'] = 'SEB Beenden Button anzeigen';
$string['sebserverquitsecret'] = 'SEB Beenden/Entsperren Kennwort';
$string['sebserverquitsecret_help'] = 'Mit der Schaltfläche "Beenden" oder Strg+q (CMD-Q) kann der Benutzer SEB mit diesem Passwort beenden oder den Bildschirm entsperren. Für summative Prüfungen wird die Verwendung eines SEB Beenden-Passworts empfohlen.';
$string['modificationinstruction'] = 'Um die Einstellungen zu ändern, deaktivieren Sie zunächst SEB Server und speichern Sie die Einstellungen. Dadurch wird die SEB Server-Verbindung freigegeben. Aktivieren Sie dann SEB Server wieder, damit die Optionen wieder zur Verfügung stehen.';
$string['adminsonly'] = 'Nur für Website-Administratoren';
$string['resetseb'] = 'SEB Server Verbindung aufheben';
$string['resetseb_help'] = 'Website-Administratoren können die Verbindung zum SEB Server aufheben.';
$string['examnotrestrictedyet'] = 'Die Überprüfung des Browser Exam Keys ist nicht aktiviert! Damit der Test absolviert werden kann, muss im SEB Server die Überprüfung des Browser Exam Key aktiviert werden.';
$string['launchsebserverconfig'] = 'Safe Exam Browser starten';
$string['downloadsebserverconfig'] = 'SEB Server Konfiguration herunterladen';
$string['sebseverconfignotfound'] = ' SEB Server Konfiguration ist nicht vorhanden';
$string['autologintosebserver'] = 'SEB Server Monitoring';
$string['templatemustbeselected'] = 'Sie müssen eine Exam Vorlage wählen';
$string['connectionid'] = 'Verbindungs ID';
$string['connectionname'] = 'Verbindungsname';
$string['connectionurl'] = 'Verbindungs-Endpoint';
$string['autologinurl'] = 'SEB Server Autologin';
$string['accesstoken'] = 'Token';
$string['templateid'] = 'ID';
$string['templatename'] = 'Name';
$string['templatedescription'] = 'Beschreibung';
$string['templates'] = 'Vorlagen';
$string['setting:sebserverconnectiondetails'] = 'Verbindungsangaben';
$string['setting:sebserversettings'] = 'Einstellungen';
$string['sebserver:canusesebserver'] = 'Kann SEB Server verwenden';
$string['sebserver:candeletesebserver'] = 'Kann SEB Server deaktivieren';
$string['sebserver:managesebserverconnection'] = 'Kann die Seb Server Verbindung verwalten';
$string['sebserver:sebserverautologinlink'] = 'Kann automatisch in SEB Server einloggen';
$string['selectemplate'] = 'Exam Vorlage auswählen';
$string['sebservertemplateid'] = 'SEB Server Vorlage';
$string['sebservertemplateid_help'] = 'Wählen Sie passend zu Ihrem Prüfungsszenario eine von Ihrer Organisation bereitgestellte Exam Vorlage aus.';
$string['manageddevicetemplate'] = 'Exam Konfiguration ID=';
$string['proceednextquiz'] = 'Gehe zu';
$string['hasconsecutivequiz'] = 'Anschliessender Test';
$string['setting:sebclientsrestrictiondetails'] = 'SEB Clients versions restrictions';
$string['setting:sebclientrestrictionvalues'] = 'List of restricted versions';
$string['setting:noversionrestriction'] = 'Empty (No restriction)';
$string['setting:versioninghelpinfo'] = 'Allows you to specify one or more SEB version(s) that are accepted.<br />A version restriction has the following format:
<b>OS.Major.Minor.[Patch].[min/max/eq/ne]</b><br >
Please enter one restriction per line, e.g. Win.3.9.min to allow all SEB for Windows versions starting with 3.9 or
newer.<ul>
<li><b>OS</b>: Win , macOS , 10S, or iPadOS</li>
<li><b>Major / Minor / Patch</b>: numeric version components (e.g. 3.9.1)</li>
<li><b>min or max or eq or ne</b>: appends "operator" semantics to the rest of the rule</ul>
<pre>Ps maybe in future (extra attribute "release" in JSAPI) :
= or > or >= or < or <= or <>|os:macos/win/ios|major:3|minor:11|patch:999|maturity:stable/rc1/dev/beta/alpha/etc|special:ae|fullversion:3.11.06.999|etc:etc
</pre>
';
$string['invalidversiontitle'] = 'SEB version mismatch';
$string['invalidversionmsg'] = 'The installed Safe Exam Browser version does not meet the minimum requirement set for this quiz. Please install the required version and start Safe Exam Browser again.';
$string['currentversion'] = 'Current SEB Client version';
$string['requiredversions'] = 'Required SEB Client version(s)';
$string['howtofixversionerror'] = '<b>How to fix this:</b><ol><li>Click Exit Safe Exam Browser below to quit SEB.</li>
<li>Install Safe Exam Browser the above required version(s) for your operating system.</li>
<li>Open the quiz link again from your browser and start Safe Exam Browser.</li></ol>';
$string['checkingversion'] = 'Checking SEB Client version..';
$string['allowedsebversions'] = 'Allowed SEB Client version(s)';
