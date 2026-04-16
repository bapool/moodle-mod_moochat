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
 * Restore steps for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_moochat_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during restore.
     *
     * @return array of restore_path_element
     */
    protected function define_structure() {
        $paths    = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('moochat', '/activity/moochat');

        if ($userinfo) {
            $paths[] = new restore_path_element('moochat_usage',
                '/activity/moochat/usages/usage');
            $paths[] = new restore_path_element('moochat_conversation',
                '/activity/moochat/conversations/conversation');
            $paths[] = new restore_path_element('moochat_objectiveresult',
                '/activity/moochat/objectiveresults/objectiveresult');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process moochat record.
     *
     * @param array $data
     */
    protected function process_moochat($data) {
        global $DB;
        $data = (object)$data;
        $data->course       = $this->get_courseid();
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Ensure new fields have safe defaults if restoring from older backup.
        if (!isset($data->grade)) {
            $data->grade = 0;
        }
        if (!isset($data->objectives)) {
            $data->objectives = '';
        }
        if (!isset($data->pointsperobj)) {
            $data->pointsperobj = 1;
        }
        if (!isset($data->content_restrict)) {
            $data->content_restrict = 0;
        }

        $newitemid = $DB->insert_record('moochat', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process usage record.
     *
     * @param array $data
     */
    protected function process_moochat_usage($data) {
        global $DB;
        $data                = (object)$data;
        $oldid               = $data->id;
        $data->moochatid     = $this->get_new_parentid('moochat');
        $data->userid        = $this->get_mappingid('user', $data->userid);
        $data->firstmessage  = $this->apply_date_offset($data->firstmessage);
        $data->lastmessage   = $this->apply_date_offset($data->lastmessage);
        $newitemid           = $DB->insert_record('moochat_usage', $data);
        $this->set_mapping('moochat_usage', $oldid, $newitemid);
    }

    /**
     * Process conversation record.
     *
     * @param array $data
     */
    protected function process_moochat_conversation($data) {
        global $DB;
        $data              = (object)$data;
        $oldid             = $data->id;
        $data->moochatid   = $this->get_new_parentid('moochat');
        $data->userid      = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // sessionid is NOTNULL — provide empty string if restoring from older backup.
        if (!isset($data->sessionid)) {
            $data->sessionid = '';
        }

        $newitemid = $DB->insert_record('moochat_conversations', $data);
        $this->set_mapping('moochat_conversation', $oldid, $newitemid);
    }

    /**
     * Process objective result record.
     *
     * @param array $data
     */
    protected function process_moochat_objectiveresult($data) {
        global $DB;
        $data                 = (object)$data;
        $oldid                = $data->id;
        $data->moochatid      = $this->get_new_parentid('moochat');
        $data->userid         = $this->get_mappingid('user', $data->userid);
        $data->timechecked    = $this->apply_date_offset($data->timechecked);

        // sessionid is NOTNULL — provide empty string if restoring from older backup.
        if (!isset($data->sessionid)) {
            $data->sessionid = '';
        }

        $newitemid = $DB->insert_record('moochat_objective_results', $data);
        $this->set_mapping('moochat_objectiveresult', $oldid, $newitemid);
    }

    /**
     * Post-execution actions — restore all file areas.
     */
    protected function after_execute() {
        $this->add_related_files('mod_moochat', 'intro',        null);
        $this->add_related_files('mod_moochat', 'avatar',       null);
        $this->add_related_files('mod_moochat', 'contentfiles', null);
    }
}
