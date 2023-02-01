<?php
// This client for quizaccess_sebserver is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

/**
 * XMLRPC client for Moodle 2 - quizaccess_sebserver
 *
 * This script does not depend of any Moodle code,
 * and it can be called from a browser.
 *
 */

// !!WARNING!!: you should never allow the token to be non-expired. Please expire the tokens in moodle to as soon as the exam finishes.

// This example is based on xmlrpc.

/// SETUP - NEED TO BE CHANGED

$token = '076ff9e55fdcba2dccab1e6706d4bac6';
$domainname = 'http://localhost/';

/// FUNCTION NAME
$functionname = 'quizaccess_sebserver_get_exams';

///// XML-RPC CALL
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/xmlrpc/server.php'. '?wstoken=' . $token;

require_once('./curl.php');

$curl = new curl;
$post = xmlrpc_encode_request($functionname, array(array(2))  , array ('encoding' => 'utf-8'));
$resp = xmlrpc_decode($curl->post($serverurl, $post));

print_r($resp);
