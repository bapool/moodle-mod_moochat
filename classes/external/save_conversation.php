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
 * External service for saving chat conversations
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moochat\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_module;

/**
 * External service for saving chat conversations
 */
class save_conversation extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'moochatid'        => new external_value(PARAM_INT,              'MooChat activity ID'),
            'usermessage'      => new external_value(PARAM_TEXT,             'User message'),
            'assistantmessage' => new external_value(PARAM_TEXT,             'Assistant reply'),
            'sessionid'        => new external_value(PARAM_ALPHANUMEXT, 'Client session UUID', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save a conversation exchange to the database.
     *
     * @param int    $moochatid
     * @param string $usermessage
     * @param string $assistantmessage
     * @param string $sessionid
     * @return array
     */
    public static function execute($moochatid, $usermessage, $assistantmessage, $sessionid = '') {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'moochatid'        => $moochatid,
            'usermessage'      => $usermessage,
            'assistantmessage' => $assistantmessage,
            'sessionid'        => $sessionid,
        ]);

        $cm      = get_coursemodule_from_instance('moochat', $params['moochatid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_login();

        $now = time();
        $sid = clean_param($params['sessionid'], PARAM_ALPHANUMEXT);

        // Save user message.
        $userrecord              = new \stdClass();
        $userrecord->moochatid   = $params['moochatid'];
        $userrecord->userid      = $USER->id;
        $userrecord->sessionid   = $sid;
        $userrecord->role        = 'user';
        $userrecord->message     = $params['usermessage'];
        $userrecord->timecreated = $now;
        $DB->insert_record('moochat_conversations', $userrecord);

        // Save assistant message.
        $assistantrecord              = new \stdClass();
        $assistantrecord->moochatid   = $params['moochatid'];
        $assistantrecord->userid      = $USER->id;
        $assistantrecord->sessionid   = $sid;
        $assistantrecord->role        = 'assistant';
        $assistantrecord->message     = $params['assistantmessage'];
        $assistantrecord->timecreated = $now;
        $DB->insert_record('moochat_conversations', $assistantrecord);

        return ['success' => true];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }
}
