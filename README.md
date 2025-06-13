# moodle-quizaccess_sebserver
[![Moodle Plugin CI](https://github.com/ethz-let/moodle-quizaccess_sebserver/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/ethz-let/moodle-quizaccess_sebserver/actions/workflows/moodle-plugin-ci.yml)

SEB Server plugin for moodle. This plugin works only with SEB Server version 2.0.

# Required min versions
- Moodle: 4.2+
- SEB Server 2.0

# Installation
MOODLE_DIR/mod/quiz/accessrule/sebserver

# Service
 * Fullname: SEB-Server Webservice
 * Shortname: SEB-Server-Webservice
# Web Service Functions
For up-to-date documentation/arguments/returns: MOODLE_URL/admin/webservice/documentation.php

# First time setup:
- In /admin/settings.php?section=optionalsubsystems
1. Enable enablewebservices
2. Enable enablemobilewebservice (Optional)

- In /admin/settings.php?section=webserviceprotocols
Enable REST webservice.

- In /admin/settings.php?section=externalservices
Click edit next to SebServer Webservice, then Show more -> Tick Can download files -> Tick Can Upload files

- In /user/editadvanced.php?id=-1
Create a specific user for the Webservice.

- In /admin/roles/check.php?contextid=1
Check and add any possible missing capabilities of the created user to use the WebService. Usually a role creation with its capabilities that are specific to sebserver webservice is recommended.
Note: inherit a role from editing-teacher role and make sure to add webservice/rest:use, and remove any unnecessary capabilities.

- In /admin/settings.php?section=externalservices
Select that specific user to use Sebserver webservice.

- In /admin/webservice/tokens.php?action=create
Create a token for that specific user (make sure to select the service to be Seb Server)

- In /admin/settings.php?section=webservicesoverview
Verify that:
1. Enable web services	Yes
2. Enable protocols	rest

# Required capabilities:
- System: webservice/rest:use, moodle/course:view, moodle/course:update, moodle/course:viewhiddencourses, mod/quiz:view
- core_user_get_users_by_field
Retrieve users' information for a specified unique field - If you want to do a user search, use core_user_get_users() or core_user_search_identity().
moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail, moodle/user:update
- core_webservice_get_site_info
Return some site info / user info / list web service functions
- quizaccess_sebserver_backup_course
Backup Course by its ID and type ("course" or "quiz" ID provided)
Clone of minimum Course Editing Teacher (with moodle/backup:backupcourse, moodle/backup:backupsection, moodle/backup:backuptargetimport, moodle/backup:configure, moodle/backup:downloadfile, moodle/restore:uploadfile, moodle/backup:backupactivity, moodle/course:viewhiddencourses, moodle/course:viewhiddenactivities, mod/quiz:view, mod/quiz:manage, mod/quiz:preview, moodle/course:managefiles, moodle/course:managegroups, moodle/course:viewparticipants, report/participation:view etc)
- quizaccess_sebserver_get_exams
Return courses details and their quizzes
- quizaccess_sebserver_get_restriction
Get browser_keys and config_keys (not available on moodle) for certain quiz.
- quizaccess_sebserver_set_exam_data
Set exam data for imported exams via SebServer
- quizaccess_sebserver_set_restriction
Set browser_keys and config_keys (not available on moodle) for certain quiz.
- Set connection of SebServer: quizaccess_sebserver_connection
- Manage connection of SebServer: quizaccess/sebserver:managesebserverconnection
- Delete connection of SebServer: quizaccess_sebserver_connection_delete

# Optional capabilities:
- Can use SebServer in a quiz: quizaccess/sebserver:canusesebserver
- Can disable SebServer in a quiz: quizaccess/sebserver:candeletesebserver
- Can use autologin to SebServer proctoring: quizaccess/sebserver:sebserverautologinlink

# Credits
This plugin was made possible with the help and contribution of (in alphabetical order):
- Andreas Hefti
- ETH Zurich
- Kristina Isacson
- Luca Bösch

