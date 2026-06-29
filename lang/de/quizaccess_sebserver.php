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
$string['setting:sebclientsrestrictiondetails'] = 'SEB Client Versionsbeschränkung';
$string['setting:sebclientrestrictionvalues'] = 'Liste der erlaubten Versionen';
$string['setting:noversionrestriction'] = 'Leer (Keine Versionsbeschränkung)';
$string['setting:versioninghelpinfo'] = 'Hier die erlaubte(n) SEB-Version(en) festgelegen.<br />Eine Versionsbeschränkung hat folgendes Format:
<b>OS.Major.Minor.[Patch].[min/max/eq/ne]</b><br >
Bitte pro Zeile eine Version eingeben, z.B. Win.3.9.min um alle SEB für Windows Versionen ab 3.9 oder neuer zu erlauben.
<ul>
<li><b>OS</b>: Win , macOS oder iOS</li>
<li><b>Major / Minor / Patch</b>: numerische Versionsbestandteile (z.B. 3.9.1)</li>
<li><b>min oder max oder eq oder ne</b>: fügt der Regel eine "Operator"-Semantik hinzu</ul>';
$string['invalidversiontitle'] = 'Falsche SEB Version';
$string['invalidversionmsg'] = 'Die installierte Version des Safe Exam Browser erfüllt nicht die festgelegten Anforderungen. Bitte installieren Sie die erforderliche Version und starten Sie den Safe Exam Browser erneut.';
$string['currentversion'] = 'Installierte SEB Client Version';
$string['requiredversions'] = 'Erforderliche SEB Client Version(en)';
$string['howtofixversionerror'] = '<b>Um dies zu beheben:</b><ol><li>Klicken Sie den Button Safe Exam Browser beenden um SEB zu beenden.</li>
<li>Klicken Sie den Button Safe Exam Browser downloaden und installieren Sie die für Ihr Betriebssystem erforderliche (Mindest-)Version des Safe Exam Browsers.</li>
<li>Klicken Sie den Button Zurück zum Kurs und loggen Sie sich aus.</li>
<li>Loggen Sie sich wieder in Moodle ein.</li>
<li>Navigieren Sie wieder zum Quiz und klicken Sie erneut den Button Safe Exam Browser starten.</li></ol>';
$string['checkingversion'] = 'Überprüfe SEB Client Version..';
$string['allowedsebversions'] = 'Erlaubte SEB Client Version(en)';
