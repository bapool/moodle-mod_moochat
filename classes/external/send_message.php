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
 * External service: send a chat message and get AI response
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
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

    public static function execute_parameters() {
        return new external_function_parameters([
            'moochatid' => new external_value(PARAM_INT,  'The moochat instance ID'),
            'message'   => new external_value(PARAM_TEXT, 'The user message'),
            'history'   => new external_value(PARAM_RAW,  'Conversation history as JSON string'),
        ]);
    }

    public static function execute($moochatid, $message, $history) {
        global $DB, $USER;

        require_once(__DIR__ . '/../../lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'moochatid' => $moochatid,
            'message'   => $message,
            'history'   => $history,
        ]);

        $moochat = $DB->get_record('moochat', ['id' => $params['moochatid']], '*', MUST_EXIST);
        $cm      = get_coursemodule_from_instance('moochat', $moochat->id, $moochat->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/moochat:submit', $context);

        // Automatic cleanup: delete usage records older than 7 days.
        $cleanuptime = time() - (7 * 86400);
        $DB->delete_records_select('moochat_usage', 'lastmessage < ?', [$cleanuptime]);

        // Check rate limiting.
        $ratelimitenabled = $moochat->ratelimit_enable;
        $usage            = null;
        $remaining        = -1;

        if ($ratelimitenabled) {
            $ratelimitperiod = $moochat->ratelimit_period;
            $ratelimitcount  = intval($moochat->ratelimit_count);

            $usage = $DB->get_record('moochat_usage',
                ['moochatid' => $moochatid, 'userid' => $USER->id]);

            $now           = time();
            $periodseconds = ($ratelimitperiod === 'hour') ? 3600 : 86400;

            if ($usage) {
                if (($now - $usage->firstmessage) >= $periodseconds) {
                    $usage->messagecount = 0;
                    $usage->firstmessage = $now;
                    $usage->lastmessage  = $now;
                    $DB->update_record('moochat_usage', $usage);
                } else {
                    if ($usage->messagecount >= $ratelimitcount) {
                        $periodstring = get_string('ratelimitreached_' . $ratelimitperiod, 'moochat');
                        return [
                            'success'   => false,
                            'error'     => get_string('ratelimitreached', 'moochat',
                                ['limit' => $ratelimitcount, 'period' => $periodstring]),
                            'remaining' => 0,
                        ];
                    }
                }
            } else {
                $usage               = new \stdClass();
                $usage->moochatid    = $moochatid;
                $usage->userid       = $USER->id;
                $usage->messagecount = 0;
                $usage->firstmessage = $now;
                $usage->lastmessage  = $now;
                $usage->id           = $DB->insert_record('moochat_usage', $usage);
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

        // Check message limit.
        $maxmessages = intval($moochat->maxmessages);
        if ($maxmessages > 0 && count($historyarray) >= ($maxmessages * 2)) {
            return [
                'success' => false,
                'error'   => get_string('maxmessagesreached', 'moochat'),
            ];
        }

        // Build system prompt.
        $systemprompt = !empty($moochat->systemprompt)
            ? $moochat->systemprompt
            : get_string('defaultprompt', 'moochat');

        // ------------------------------------------------------------------
        // Uploaded content files — extract text and inject into prompt.
        //
        // If content_restrict is checked: the AI may ONLY use the uploaded
        // content and must decline questions not covered by it.
        //
        // If files are uploaded but restrict is unchecked: the content is
        // provided as reference material alongside the AI's general knowledge.
        //
        // If no files are uploaded: fall through to section content / nothing.
        // ------------------------------------------------------------------
        $uploadedcontent = moochat_get_uploaded_content($context->id, $moochat);

        if (!empty($uploadedcontent)) {
            if (!empty($moochat->content_restrict)) {
    // Strict mode — AI may only answer from uploaded content.
    // Instruction appears before AND after content so model sees it last.
    $systemprompt .=
        "\n\nCRITICAL RULE: You may ONLY use the course content between the markers below. " .
        "Do NOT use any outside knowledge, training data, or general information. " .
        "If the answer is not explicitly stated in the course content, respond with: " .
        "'I don't have information about that in the course materials.' " .
        "Do not guess. Do not supplement. Only use what is written below.\n\n" .
        "=== COURSE CONTENT BEGIN ===\n" . $uploadedcontent . "\n=== COURSE CONTENT END ===\n\n" .
        "REMINDER: Answer ONLY from the course content above. No outside knowledge.";
            } else {
                // Reference mode — uploaded content provided as helpful context.
                $systemprompt .=
                    "\n\nThe following course content has been provided as reference material. " .
                    "Use it to give accurate, course-specific answers when relevant.\n\n" .
                    "=== COURSE CONTENT ===\n" . $uploadedcontent . "\n=== END COURSE CONTENT ===";
            }
        } else if ($moochat->include_section_content) {
            // No uploaded files — fall back to section content if enabled.
            $section       = $DB->get_record('course_sections', ['id' => $cm->section]);
            $sectionnum    = $section ? $section->section : 0;
            $includehidden = isset($moochat->include_hidden_content) ? $moochat->include_hidden_content : 0;
            $systemprompt .= moochat_get_section_content($moochat->course, $sectionnum, $includehidden);
        }

        // Build the full prompt with conversation history.
        $fullprompt = $systemprompt . "\n\n";

        foreach ($historyarray as $msg) {
            if ($msg['role'] === 'user') {
                $fullprompt .= "User: " . $msg['content'] . "\n";
            } else if ($msg['role'] === 'assistant') {
                $fullprompt .= "Assistant: " . $msg['content'] . "\n";
            }
        }

        $fullprompt .= "User: " . $params['message'] . "\nAssistant:";

        try {
            $action = new \core_ai\aiactions\generate_text(
                contextid:  $context->id,
                userid:     $USER->id,
                prompttext: $fullprompt
            );

            $manager  = \core\di::get(\core_ai\manager::class);
            $response = $manager->process_action($action);

            if ($response->get_success()) {
                $reply = $response->get_response_data()['generatedcontent'] ?? '';

                if ($ratelimitenabled && isset($usage)) {
                    $usage->messagecount++;
                    $usage->lastmessage = time();
                    $DB->update_record('moochat_usage', $usage);
                    $remaining = $ratelimitcount - $usage->messagecount;
                } else {
                    $remaining = -1;
                }

                return [
                    'success'   => true,
                    'reply'     => trim($reply),
                    'remaining' => $remaining,
                ];
            } else {
                return [
                    'success' => false,
                    'error'   => $response->get_errormessage() ?: 'AI generation failed',
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'reply'     => new external_value(PARAM_RAW,  'The AI reply',                       VALUE_OPTIONAL),
            'error'     => new external_value(PARAM_TEXT, 'Error message if any',               VALUE_OPTIONAL),
            'remaining' => new external_value(PARAM_INT,  'Remaining questions (-1 unlimited)', VALUE_OPTIONAL),
        ]);
    }
}
