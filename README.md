# moodle-quizaccess_sebserver
![](https://github.com/ethz-let/moodle-quizaccess_sebserver/actions/workflows/moodle-plugin-ci.yml/badge.svg)

SEB Server plugin for moodle

# Installation
MOODLE_DIR/mod/quiz/accessrule
# Service
 * Fullname: SEB-Server Webservice
 * Shortname: SEB-Server-Webservice
 * Encapsulated Functions: 
   - 'quizaccess_sebserver_backup_course'
   - 'quizaccess_sebserver_get_exams'
   - 'quizaccess_sebserver_get_restriction'
   - 'quizaccess_sebserver_set_restriction'
   - 'core_webservice_get_site_info'
   - 'core_user_get_users_by_field'
# Web Service Functions
For up-to-date documentation/arguments/returns: MOODLE_URL/admin/webservice/documentation.php
  * Current user: MOODLE_URL/mod/quiz/accessrule/sebserver/classes/external/user.php
  * quizaccess_sebserver_backup_course (Backup Course by its ID. Automated Backups *must* be enabled).
  * quizaccess_sebserver_get_exams (Return courses details and their quizzes)
  * quizaccess_sebserver_get_restriction (Get browser_keys and config_keys for certain quiz.)
  * quizaccess_sebserver_set_restriction (Set browser_keys and config_keys for certain quiz. Deletes restriction when both browser_keys & config_keys are empty)
