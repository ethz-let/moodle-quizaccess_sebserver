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
 * webservice the quizaccess_sebserver plugin.
 *
 * @package   quizaccess_sebserver
 * @author    Amr Hourani (amr.hourani@let.ethz.ch)
 * @copyright 2022 ETH Zurich
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 // We define the services to install as pre-build services. A pre-build service is not editable by administrator.
 $services = array(
     'SEB-Server Webservice' => array(
         'functions' => array ('quizaccess_sebserver_backup_course', 'quizaccess_sebserver_get_exams',
                               'quizaccess_sebserver_get_restriction', 'quizaccess_sebserver_set_restriction',
                               'core_webservice_get_site_info','core_user_get_users_by_field'),
         'enabled' => 1,
         'shortname' => 'SEB-Server-Webservice',
     )
 );

 // We defined the web service functions to install.
 $functions = array(

         'quizaccess_sebserver_backup_course' => array(
             'classname'   => 'quizaccess_sebserver_external',
             'methodname'  => 'backup_course',
             'classpath'   => 'mod/quiz/accessrule/sebserver/externallib.php',
             'description' => 'Backup Course by its ID',
             'type'        => 'read',
             'capabilities' => 'moodle/course:backup',
         ),
         'quizaccess_sebserver_get_exams' => array(
             'classname' => 'quizaccess_sebserver_external',
             'methodname' => 'get_exams',
             'classpath' => 'mod/quiz/accessrule/sebserver/externallib.php',
             'description' => 'Return courses details and their quizzes',
             'type' => 'read',
             'capabilities' => 'moodle/course:view, moodle/course:update, moodle/course:viewhiddencourses, mod/quiz:view',
             ),
         'quizaccess_sebserver_set_restriction' => array(
             'classname' => 'quizaccess_sebserver_external',
             'methodname' => 'set_restriction',
             'classpath' => 'mod/quiz/accessrule/sebserver/externallib.php',
             'description' => 'Set browser_keys and config_keys (not available on moodle) for certain quiz.',
             'type' => 'write',
             'capabilities' => 'moodle/course:view, moodle/course:update, moodle/course:viewhiddencourses, mod/quiz:manage',
             ),
         'quizaccess_sebserver_get_restriction' => array(
             'classname' => 'quizaccess_sebserver_external',
             'methodname' => 'get_restriction',
             'classpath' => 'mod/quiz/accessrule/sebserver/externallib.php',
             'description' => 'Get browser_keys and config_keys (not available on moodle) for certain quiz.',
             'type' => 'read',
             'capabilities' => 'moodle/course:view, moodle/course:update, moodle/course:viewhiddencourses, mod/quiz:manage',
             ),
);
