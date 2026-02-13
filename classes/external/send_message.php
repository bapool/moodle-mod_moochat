<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Define all the restore steps that will be used by the restore_moochat_activity_task
 *
 * @package    mod_moochat
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moochat\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use Exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class send_message extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'moochatid' => new external_value(PARAM_INT, 'The moochat instance ID'),
            'message' => new external_value(PARAM_TEXT, 'The user message'),
            'history' => new external_value(PARAM_RAW, 'Conversation history as JSON string'),
        ]);
    }

    /**
     * Send a chat message and get AI response.
     *
     * @param int $moochatid The moochat instance ID
     * @param string $message The user message
     * @param string $history Conversation history as JSON
     * @return array Response with success status, reply, error, and remaining questions
     */
    public static function execute($moochatid, $message, $history) {
        global $DB, $USER;

        require_once(__DIR__ . '/../../lib.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'moochatid' => $moochatid,
            'message' => $message,
            'history' => $history,
        ]);

        // Get the moochat instance.
        $moochat = $DB->get_record('moochat', ['id' => $params['moochatid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('moochat', $moochat->id, $moochat->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Validate context and check capability.
        self::validate_context($context);
        require_capability('mod/moochat:submit', $context);

        // Automatic cleanup: Delete records older than 7 days.
        $cleanuptime = time() - (7 * 86400);
        $DB->delete_records_select('moochat_usage', 'lastmessage < ?', [$cleanuptime]);

        // Check rate limiting.
        $ratelimitenabled = $moochat->ratelimit_enable;
        $usage = null;
        $remaining = -1;

        if ($ratelimitenabled) {
            $ratelimitperiod = $moochat->ratelimit_period;
            $ratelimitcount = intval($moochat->ratelimit_count);

            // Get or create usage record.
            $usage = $DB->get_record('moochat_usage',
                ['moochatid' => $moochatid, 'userid' => $USER->id]);

            $now = time();
            $periodseconds = ($ratelimitperiod === 'hour') ? 3600 : 86400;

            if ($usage) {
                // Check if we need to reset the counter.
                if (($now - $usage->firstmessage) >= $periodseconds) {
                    // Period has expired, reset counter.
                    $usage->messagecount = 0;
                    $usage->firstmessage = $now;
                    $usage->lastmessage = $now;
                    $DB->update_record('moochat_usage', $usage);
                } else {
                    // Check if limit reached.
                    if ($usage->messagecount >= $ratelimitcount) {
                        $periodstring = get_string('ratelimitreached_' . $ratelimitperiod, 'moochat');
                        return [
                            'success' => false,
                            'error' => get_string('ratelimitreached', 'moochat',
                                ['limit' => $ratelimitcount, 'period' => $periodstring]),
                            'remaining' => 0,
                        ];
                    }
                }
            } else {
                // Create new usage record.
                $usage = new \stdClass();
                $usage->moochatid = $moochatid;
                $usage->userid = $USER->id;
                $usage->messagecount = 0;
                $usage->firstmessage = $now;
                $usage->lastmessage = $now;
                $usage->id = $DB->insert_record('moochat_usage', $usage);
            }
        }

        // Parse conversation history.
        $historyarray = [];
        if (!empty($params['history'])) {
            $historyarray = json_decode($params['history'], true);
            if (!is_array($historyarray)) {
                $historyarray = [];
            }
        }

        // Check message limit (old system, kept for compatibility).
        $maxmessages = intval($moochat->maxmessages);
        if ($maxmessages > 0 && count($historyarray) >= ($maxmessages * 2)) {
            return [
                'success' => false,
                'error' => get_string('maxmessagesreached', 'moochat'),
            ];
        }

        // Build full prompt with system instructions and conversation history.
        $systemprompt = !empty($moochat->systemprompt) ? $moochat->systemprompt :
            get_string('defaultprompt', 'moochat');
        $fullprompt = $systemprompt . "\n\n";

        // Add section content if enabled.
        if ($moochat->include_section_content) {
            // Get section number from section id.
            $section = $DB->get_record('course_sections', ['id' => $cm->section]);
            $sectionnum = $section ? $section->section : 0;

            $includehidden = isset($moochat->include_hidden_content) ? $moochat->include_hidden_content : 0;
            $sectioncontent = moochat_get_section_content($moochat->course, $sectionnum, $includehidden);

            $fullprompt .= $sectioncontent;
        }

        // Add conversation history.
        foreach ($historyarray as $msg) {
            if ($msg['role'] === 'user') {
                $fullprompt .= "User: " . $msg['content'] . "\n";
            } else if ($msg['role'] === 'assistant') {
                $fullprompt .= "Assistant: " . $msg['content'] . "\n";
            }
        }

        // Add current message.
        $fullprompt .= "User: " . $params['message'] . "\nAssistant:";

        try {
            // Create AI action using Moodle's core AI system.
            $action = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $fullprompt
            );

            // Get AI manager and process the action.
            $manager = \core\di::get(\core_ai\manager::class);
            $response = $manager->process_action($action);

            if ($response->get_success()) {
                $reply = $response->get_response_data()['generatedcontent'] ?? '';

                // Update usage counter if rate limiting is enabled.
                if ($ratelimitenabled && isset($usage)) {
                    $usage->messagecount++;
                    $usage->lastmessage = time();
                    $DB->update_record('moochat_usage', $usage);

                    $remaining = $ratelimitcount - $usage->messagecount;
                } else {
                    $remaining = -1; // Unlimited.
                }

                // Return success response.
                return [
                    'success' => true,
                    'reply' => trim($reply),
                    'remaining' => $remaining,
                ];
            } else {
                // Return error from AI system.
                return [
                    'success' => false,
                    'error' => $response->get_errormessage() ?: 'AI generation failed',
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'reply' => new external_value(PARAM_RAW, 'The AI reply', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
            'remaining' => new external_value(PARAM_INT, 'Remaining questions (-1 for unlimited)', VALUE_OPTIONAL),
        ]);
    }
}
