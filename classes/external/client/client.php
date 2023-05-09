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

// This client for quizaccess_sebserver is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

// phpcs:disable

/**
 * XMLRPC client for Moodle 2 - quizaccess_sebserver
 *
 * This script does not depend of any Moodle code,
 * and it can be called from a browser.
 *
 * @author Amr Hourani
 */

defined('MOODLE_INTERNAL') || die();

// MOODLE ADMINISTRATION SETUP STEPS
// 1- Install the plugin
// 2- Enable web service advance feature (Admin > Advanced features)
// 3- Enable preferred web service protocols (Admin > Plugins > Web services > Manage protocols)
// 4- Create a token for a specific user (or at site level if wanted; i.e admin user) and for the service 'SEB Authentication Key Web Serice' (Admin > Plugins > Web services > Manage tokens)
// 5- Endpoint will be dependant on the used protocol: MOODLE_DOMAIN/webservice/PROTOCOL/server.php
// ie: Endpoint for xmlrpc:  MOODLE_DOMAIN/webservice/xmlrpc/server.php
// ie: Endpoint for soap:  MOODLE_DOMAIN/webservice/soap/server.php
// ie: Endpoint for rest:  MOODLE_DOMAIN/webservice/rest/server.php

// WARNING!!: you should never allow the token to be non-expired. Please expire the tokens in moodle to as soon as the exam finishes.

// This example is based on xmlrpc

// SETUP - NEED TO BE CHANGED.

$token = '076ff9e55fdcba2dccab1e6706d4bac6';
$domainname = 'http://localhost/311';

// FUNCTION NAME.
//$functionname = 'quizaccess_sebserver_backup_course';
$functionname = 'quizaccess_sebserver_get_exams';

/// PARAMETERS
//$id = 2;

///// XML-RPC CALL
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/xmlrpc/server.php'. '?wstoken=' . $token;

require_once('./curl.php');

//array(array(1,2,3),array(4,5),8,'lol')
// array(8)
$curl = new curl;
$post = xmlrpc_encode_request($functionname, array(array(2))  , array ('encoding' => 'utf-8'));
$resp = xmlrpc_decode($curl->post($serverurl, $post));

print_r($resp);
//echo json_encode($resp);
//echo json_last_error();
echo "Done..";
// phpcs:enable
