<?php
require_once('../../../../config.php');
require_login();
if (!is_siteadmin()) { die('Admin only'); }
// Update plugin and version below
set_config('version', '2026062300', 'quizaccess_sebserver'); 
echo "SebServer plugin version is fixed. Set version in version.php to 2026062400";
