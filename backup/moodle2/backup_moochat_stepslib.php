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
 * Backup steps for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_moochat_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        // Root element — includes all moochat table fields.
        $moochat = new backup_nested_element('moochat', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
            'display', 'chatsize', 'systemprompt', 'avatarurl', 'avatarsize',
            'include_section_content', 'include_hidden_content',
            'ratelimit_enable', 'ratelimit_period', 'ratelimit_count',
            'maxmessages', 'temperature', 'model',
            'grade', 'objectives', 'pointsperobj', 'content_restrict',
        ]);

        // Usage data.
        $usages = new backup_nested_element('usages');
        $usage  = new backup_nested_element('usage', ['id'], [
            'userid', 'messagecount', 'firstmessage', 'lastmessage',
        ]);

        // Conversation data — includes sessionid for session-based scoring.
        $conversations = new backup_nested_element('conversations');
        $conversation  = new backup_nested_element('conversation', ['id'], [
            'userid', 'sessionid', 'role', 'message', 'timecreated',
        ]);

        // Objective results — includes sessionid for session-based scoring.
        $objectiveresults = new backup_nested_element('objectiveresults');
        $objectiveresult  = new backup_nested_element('objectiveresult', ['id'], [
            'userid', 'sessionid', 'objectiveindex', 'met', 'timechecked',
        ]);

        // Build the tree.
        $moochat->add_child($usages);
        $usages->add_child($usage);

        $moochat->add_child($conversations);
        $conversations->add_child($conversation);

        $moochat->add_child($objectiveresults);
        $objectiveresults->add_child($objectiveresult);

        // Data sources.
        $moochat->set_source_table('moochat', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $usage->set_source_table('moochat_usage',
                ['moochatid' => backup::VAR_PARENTID]);
            $conversation->set_source_table('moochat_conversations',
                ['moochatid' => backup::VAR_PARENTID]);
            $objectiveresult->set_source_table('moochat_objective_results',
                ['moochatid' => backup::VAR_PARENTID]);
        }

        // ID annotations.
        $usage->annotate_ids('user', 'userid');
        $conversation->annotate_ids('user', 'userid');
        $objectiveresult->annotate_ids('user', 'userid');

        // File annotations — includes contentfiles file area.
        $moochat->annotate_files('mod_moochat', 'intro',        null);
        $moochat->annotate_files('mod_moochat', 'avatar',       null);
        $moochat->annotate_files('mod_moochat', 'contentfiles', null);

        return $this->prepare_activity_structure($moochat);
    }
}
