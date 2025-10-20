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
 * Serves an encrypted/unencrypted string as a file for download.
 *
 * @package   quizaccess_sebserver
 * @author    ETH Zurich (moodle@id.ethz.ch)
 * @copyright 2025 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_RAW);

// Try and get the course module.
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);

// Make sure the user is logged in and has access to the module.
require_login($cm->course, false, $cm);

$headers = [];
$headers[] = 'Cache-Control: private, max-age=1, no-transform';
$headers[] = 'Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT';
$headers[] = 'Pragma: no-cache';
$headers[] = 'Content-Disposition: attachment; filename=sebserverconfig.seb';
$headers[] = 'Content-Type: application/seb';

// Retrieve the config for quiz.
$context = context_module::instance($cmid);
$fs = new file_storage();
$files = $fs->get_area_files($context->id,
                            'quizaccess_sebserver',
                            'filemanager_sebserverconfigfile',
                            0,
                            'id DESC',
                            false);
$file = reset($files);

if (empty($file)) {
    throw new \moodle_exception('noconfigfound', 'quizaccess_seb', '', $cm->id);
}

$contents = $file->get_content();
// We can now send the file back to the browser.
foreach ($headers as $header) {
    header($header);
}
echo($contents);
