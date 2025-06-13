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
 * Middle man page to trick seb client in order to pass the blackhole.
 *
 * @package    quizaccess_sebserver
 * @copyright  2024 ETH Zurich (moodle@id.ethz.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 // @codingStandardsIgnoreLine
require_once('../../../../config.php');

$urltogo = optional_param('cnfg', $CFG->wwwroot, PARAM_RAW);
$urltogo = $urltogo ?: $CFG->wwwroot;

require_login();

if (strpos($urltogo, $CFG->wwwroot) === false) {
    $urltogo = $CFG->wwwroot;
}
if (isguestuser()) {
    die(get_string('noguest', 'error'));
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('redirect');  // No header and footer needed.
$PAGE->set_title(get_string('pageshouldredirect', 'moodle'));

$CFG->docroot = false;
echo '<meta http-equiv="refresh" content="0; url=' . $urltogo . '" />';
