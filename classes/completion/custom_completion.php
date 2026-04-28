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

declare(strict_types=1);

namespace mod_moochat\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the moochat activity.
 *
 * Defines the "minimum interactions" custom completion rule.
 * Grade-based completion (pass grade) is handled automatically by
 * Moodle core via FEATURE_GRADE_HAS_GRADE — no custom rule needed.
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $cm     = $this->cm;

        $moochat = $DB->get_record('moochat', ['id' => $cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionmessages') {
            $required = (int) $moochat->completionmessages;
            if ($required <= 0) {
                return COMPLETION_COMPLETE;
            }
            $usage = $DB->get_record('moochat_usage', [
                'moochatid' => $moochat->id,
                'userid'    => $userid,
            ]);
            $count = $usage ? (int) $usage->messagecount : 0;
            return ($count >= $required) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionmessages'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $count = $this->cm->customdata['customcompletionrules']['completionmessages'] ?? 0;
        return [
            'completionmessages' => get_string('completionmessages_desc', 'mod_moochat', $count),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionmessages',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
