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
 * Global configuration settings for the quizaccess_sebserverserver plugin.
 *
 * @package    quizaccess_sebserver
 * @author     ETH Zurich (moodle@id.ethz.ch)
 * @copyright  2024 ETH Zurich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    if($record = $DB->get_record('config_plugins', ['plugin' => 'quizaccess_sebserver', 'name' => 'connection'])) {

        $table = new html_table();
        $conndetails = json_decode($record->value);
        $table->data[] = ['<strong>' . get_string('connectionid', 'quizaccess_sebserver') . '</strong>',
                          $conndetails->{'id'}];
        $table->data[] = ['<strong>' . get_string('connectionname', 'quizaccess_sebserver') . '</strong>',
                          $conndetails->{'name'}];
        $table->data[] = ['<strong>' . get_string('connectionurl', 'quizaccess_sebserver') . '</strong>',
                          $conndetails->{'url'}];
        $table->data[] = ['<strong>' . get_string('accesstoken', 'quizaccess_sebserver') . '</strong>',
                          $conndetails->{'access_token'}];

        $template = new html_table();
        $template->head = [get_string('templateid', 'quizaccess_sebserver'),
                                get_string('templatename', 'quizaccess_sebserver'),
                                get_string('templatedescription', 'quizaccess_sebserver')];
        foreach ($conndetails->{'exam_templates'} as $templatedetails) {
            if (!isset($templatedetails->{'template_description'})) {
                $templatedetails->{'template_description'} = '';
            }
            $template->data[] = [$templatedetails->{'template_id'} , $templatedetails->{'template_name'},
                                 $templatedetails->{'template_description'}
                                ];
        }
        $examtemplatestable = html_writer::table($template);

        $table->data[] = ['<strong>' . get_string('templates', 'quizaccess_sebserver') . '</strong>',
                          $examtemplatestable];

        $connectiondetails = html_writer::table($table);
    } else {
        $connectiondetails = get_string('connectionnotsetupyet', 'quizaccess_sebserver');
    }

    $settings->add(new admin_setting_heading(
        'quizaccess_sebserver/sebserverconnectiondetails',
        get_string('setting:sebserverconnectiondetails', 'quizaccess_sebserver'),
        $connectiondetails)
    );
}
