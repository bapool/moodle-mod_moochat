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
 * External service: check learning objectives against the conversation
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
use external_multiple_structure;
use context_module;
use Exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Check which objectives the student has met in the current session.
 *
 * Only the most recent student/assistant exchange is evaluated.
 * At most ONE new objective can be unlocked per exchange.
 */
class check_objectives extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'moochatid' => new external_value(PARAM_INT,         'The moochat instance ID'),
            'history'   => new external_value(PARAM_RAW,         'Full conversation history as JSON string'),
            'sessionid' => new external_value(PARAM_ALPHANUMEXT, 'Client session UUID', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute($moochatid, $history, $sessionid = '') {
        global $DB, $USER, $CFG;

        require_once(__DIR__ . '/../../lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'moochatid' => $moochatid,
            'history'   => $history,
            'sessionid' => $sessionid,
        ]);

        $moochat = $DB->get_record('moochat', ['id' => $params['moochatid']], '*', MUST_EXIST);
        $cm      = get_coursemodule_from_instance('moochat', $moochat->id, $moochat->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/moochat:submit', $context);

        $sid = clean_param($params['sessionid'], PARAM_ALPHANUMEXT);

        $objectives = moochat_parse_objectives($moochat->objectives);
        if (empty($objectives) || $moochat->grade == 0) {
            return [
                'success'    => true,
                'results'    => [],
                'rawgrade'   => 0,
                'grademax'   => (int)$moochat->grade,
                'metcount'   => 0,
                'totalcount' => 0,
                'newlymet'   => [],
                'bestscore'  => 0,
            ];
        }

        $total = count($objectives);

        $historyarray = [];
        if (!empty($params['history'])) {
            $historyarray = json_decode($params['history'], true);
            if (!is_array($historyarray)) {
                $historyarray = [];
            }
        }

        if (empty($historyarray) || empty($sid)) {
            return self::build_current_results($moochat, $objectives, $USER->id, $sid);
        }

        // ------------------------------------------------------------------
        // Extract ONLY the most recent exchange (last student + last assistant
        // message). We do NOT evaluate the full history — each exchange can
        // unlock at most one objective, forcing the student to keep chatting.
        // ------------------------------------------------------------------
        $lastStudent   = '';
        $lastAssistant = '';
        foreach (array_reverse($historyarray) as $msg) {
            if ($lastAssistant === '' && $msg['role'] === 'assistant') {
                $lastAssistant = $msg['content'];
            }
            if ($lastStudent === '' && $msg['role'] === 'user') {
                $lastStudent = $msg['content'];
            }
            if ($lastStudent !== '' && $lastAssistant !== '') {
                break;
            }
        }

        if ($lastStudent === '' || $lastAssistant === '') {
            return self::build_current_results($moochat, $objectives, $USER->id, $sid);
        }

        $exchange = "Student: " . $lastStudent . "\nAssistant: " . $lastAssistant;

        // ------------------------------------------------------------------
        // Build the list of unmet objectives only.
        // ------------------------------------------------------------------
        $alreadyMetIndices = [];
        $storedResults = $DB->get_records('moochat_objective_results', [
            'moochatid' => $moochat->id,
            'userid'    => $USER->id,
            'sessionid' => $sid,
            'met'       => 1,
        ]);
        foreach ($storedResults as $r) {
            $alreadyMetIndices[] = (int)$r->objectiveindex;
        }

        $unmetObjectives = [];
        foreach ($objectives as $idx => $obj) {
            if (!in_array($idx, $alreadyMetIndices)) {
                $unmetObjectives[$idx] = $obj;
            }
        }

        if (empty($unmetObjectives)) {
            return self::build_current_results($moochat, $objectives, $USER->id, $sid);
        }

        $unmetList = '';
        foreach ($unmetObjectives as $idx => $obj) {
            $unmetList .= "[" . $idx . "] " . $obj . "\n";
        }

        // ------------------------------------------------------------------
        // Single AI call: find the ONE best-matching objective from this
        // exchange. The AI must quote the exact evidence or return NONE.
        // ------------------------------------------------------------------
        $prompt =
            "You are a strict grader checking a single student exchange against a list of learning objectives.\n\n" .
            "EXCHANGE:\n" . $exchange . "\n\n" .
            "UNMET OBJECTIVES:\n" . $unmetList . "\n" .
            "TASK: Identify at most ONE objective that was clearly satisfied by this exchange.\n\n" .
            "RULES:\n" .
            "- The objective is met ONLY if the specific answer required by the objective appears in the exchange.\n" .
            "- The topic being mentioned is NOT enough. The actual specific answer must be present.\n" .
            "- Example: objective 'What is the weather in France' requires weather information to appear. A message only mentioning France's location does NOT satisfy it.\n" .
            "- A general answer about the topic does NOT satisfy an objective about a specific detail.\n" .
            "- If multiple could qualify, pick only the SINGLE BEST match.\n" .
            "- If NONE were clearly satisfied, return NONE.\n\n" .
            "Respond in exactly this format:\n" .
            "EVIDENCE: [copy the exact sentence from the exchange proving the objective, or NONE]\n" .
            "INDEX: [the index number in brackets, or NONE]";
        try {
            $manager  = \core\di::get(\core_ai\manager::class);
            $action   = new \core_ai\aiactions\generate_text(
                contextid:  $context->id,
                userid:     $USER->id,
                prompttext: $prompt
            );
            $response = $manager->process_action($action);

            if (!$response->get_success()) {
                return self::build_current_results($moochat, $objectives, $USER->id, $sid);
            }

            $raw = trim($response->get_response_data()['generatedcontent'] ?? '');

            // Parse EVIDENCE and INDEX.
            $evidence = '';
            $matchIdx = null;

            if (preg_match('/EVIDENCE:\s*(.+)/i', $raw, $em)) {
                $evidence = trim($em[1]);
            }
            if (preg_match('/INDEX:\s*\[?(\d+|NONE)\]?/i', $raw, $im)) {
                $val = strtoupper(trim($im[1]));
                if ($val !== 'NONE' && is_numeric($val)) {
                    $matchIdx = (int)$val;
                }
            }

            // Only award if evidence is real, index is in our unmet list,
            // AND at least one meaningful word from the objective appears in the exchange.
            $newlymet = [];
            $keywordFound = false;
            if ($matchIdx !== null && isset($unmetObjectives[$matchIdx])) {
                $objectiveWords = preg_split('/\s+/', strtolower($unmetObjectives[$matchIdx]));
                $stopwords = ['a','an','the','is','in','of','to','for','and','or','what','when','where','who','how','why'];
                $exchangeLower = strtolower($exchange);
                $matchCount = 0;
                foreach ($objectiveWords as $word) {
                    $word = trim($word, '.,?!');
                    if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                        if (strpos($exchangeLower, $word) !== false) {
                            $matchCount++;
                        }
                    }
                }
                $keywordFound = ($matchCount >= 2);
            }
            if (
                $matchIdx !== null &&
                isset($unmetObjectives[$matchIdx]) &&
                strtoupper($evidence) !== 'NONE' &&
                $evidence !== '' &&
                $keywordFound
            ) {
                $now      = time();
                $existing = $DB->get_record('moochat_objective_results', [
                    'moochatid'      => $moochat->id,
                    'userid'         => $USER->id,
                    'sessionid'      => $sid,
                    'objectiveindex' => $matchIdx,
                ]);
                if ($existing) {
                    if (!$existing->met) {
                        $existing->met         = 1;
                        $existing->timechecked = $now;
                        $DB->update_record('moochat_objective_results', $existing);
                        $newlymet[] = $matchIdx;
                    }
                } else {
                    $record                 = new \stdClass();
                    $record->moochatid      = $moochat->id;
                    $record->userid         = $USER->id;
                    $record->sessionid      = $sid;
                    $record->objectiveindex = $matchIdx;
                    $record->met            = 1;
                    $record->timechecked    = $now;
                    $DB->insert_record('moochat_objective_results', $record);
                    $newlymet[] = $matchIdx;
                }

                // Update gradebook if this beats the previous best.
                $sessionmet = $DB->count_records('moochat_objective_results', [
                    'moochatid' => $moochat->id,
                    'userid'    => $USER->id,
                    'sessionid' => $sid,
                    'met'       => 1,
                ]);
                $sessiongrade = round(($sessionmet / $total) * $moochat->grade, 2);
                $bestscore    = self::get_best_score($moochat, $USER->id, $total);
                if ($sessiongrade >= $bestscore) {
                    moochat_update_grade($moochat, $USER->id, $sessiongrade);
                    $course     = $DB->get_record('course', ['id' => $moochat->course], '*', MUST_EXIST);
                    $completion = new \completion_info($course);
                    if ($completion->is_enabled($cm)) {
                        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
                    }
                }
                // Trigger pass-grade completion regardless of whether this is a new best score.
                $completion = new \completion_info($DB->get_record('course', ['id' => $moochat->course], '*', MUST_EXIST));
                if ($completion->is_enabled($cm)) {
                    $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
                }
            }

            // Ensure unmatched unmet objectives have a DB row (met=0).
            $now = time();
            foreach ($unmetObjectives as $idx => $obj) {
                if (in_array($idx, $newlymet)) {
                    continue;
                }
                $exists = $DB->record_exists('moochat_objective_results', [
                    'moochatid'      => $moochat->id,
                    'userid'         => $USER->id,
                    'sessionid'      => $sid,
                    'objectiveindex' => $idx,
                ]);
                if (!$exists) {
                    $record                 = new \stdClass();
                    $record->moochatid      = $moochat->id;
                    $record->userid         = $USER->id;
                    $record->sessionid      = $sid;
                    $record->objectiveindex = $idx;
                    $record->met            = 0;
                    $record->timechecked    = $now;
                    $DB->insert_record('moochat_objective_results', $record);
                }
            }

            return self::build_current_results($moochat, $objectives, $USER->id, $sid);

        } catch (Exception $e) {
            return self::build_current_results($moochat, $objectives, $USER->id, $sid);
        }
    }

    private static function get_best_score($moochat, $userid, $total) {
        global $DB;
        if ($total == 0 || $moochat->grade == 0) {
            return 0;
        }
        $sql = "SELECT sessionid, COUNT(*) AS metcount
                  FROM {moochat_objective_results}
                 WHERE moochatid = :moochatid AND userid = :userid AND met = 1
                 GROUP BY sessionid
                 ORDER BY metcount DESC";
        $rows = $DB->get_records_sql($sql, ['moochatid' => $moochat->id, 'userid' => $userid], 0, 1);
        if (empty($rows)) {
            return 0;
        }
        $best = reset($rows);
        return round(($best->metcount / $total) * $moochat->grade, 2);
    }

    private static function build_current_results($moochat, $objectives, $userid, $sid) {
        global $DB;

        $total   = count($objectives);
        $results = [];

        $stored = [];
        if (!empty($sid)) {
            $stored = $DB->get_records('moochat_objective_results', [
                'moochatid' => $moochat->id,
                'userid'    => $userid,
                'sessionid' => $sid,
            ]);
        }

        $storedByIndex = [];
        foreach ($stored as $r) {
            $storedByIndex[(int)$r->objectiveindex] = (bool)$r->met;
        }

        $metcount = 0;
        foreach ($objectives as $idx => $obj) {
            $met       = isset($storedByIndex[$idx]) ? $storedByIndex[$idx] : false;
            $results[] = [
                'index'     => $idx,
                'objective' => $obj,
                'met'       => $met,
            ];
            if ($met) {
                $metcount++;
            }
        }

        $rawgrade = ($total > 0 && $moochat->grade > 0)
            ? round(($metcount / $total) * $moochat->grade, 2)
            : 0;

        $bestscore = self::get_best_score($moochat, $userid, $total);

        return [
            'success'    => true,
            'results'    => $results,
            'rawgrade'   => $rawgrade,
            'grademax'   => (int)$moochat->grade,
            'metcount'   => $metcount,
            'totalcount' => $total,
            'newlymet'   => [],
            'bestscore'  => $bestscore,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL,  'Whether the request was successful'),
            'results'    => new external_multiple_structure(
                new external_single_structure([
                    'index'     => new external_value(PARAM_INT,  'Objective index'),
                    'objective' => new external_value(PARAM_TEXT, 'Objective text'),
                    'met'       => new external_value(PARAM_BOOL, 'Whether this objective has been met'),
                ])
            ),
            'rawgrade'   => new external_value(PARAM_FLOAT, 'Session raw grade'),
            'grademax'   => new external_value(PARAM_INT,   'Maximum possible grade'),
            'metcount'   => new external_value(PARAM_INT,   'Objectives met this session'),
            'totalcount' => new external_value(PARAM_INT,   'Total number of objectives'),
            'newlymet'   => new external_multiple_structure(
                new external_value(PARAM_INT, 'Index of newly-met objective'),
                'Objectives newly met in this check'
            ),
            'bestscore'  => new external_value(PARAM_FLOAT, 'Best-ever grade across all sessions'),
        ]);
    }
}
