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
 * Version information for the quizaccess_sebserver plugin.
 *
 * @package   quizaccess_sebserver
 * @author    Amr Hourani (amr.hourani@let.ethz.ch)
 * @copyright 2022 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2023102602;
$plugin->requires  = 2021051714;
$plugin->cron      = 0;
$plugin->component = 'quizaccess_sebserver';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = 'v0.2 for Moodle 3.11+ up until 4.1';
$plugin->supported = [311, 401];
