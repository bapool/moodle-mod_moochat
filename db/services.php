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
 * Web service definitions for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_moochat_send_message' => [
        'classname'     => 'mod_moochat\\external\\send_message',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Send a message to the AI and get a response',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_moochat_save_conversation' => [
        'classname'     => 'mod_moochat\\external\\save_conversation',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Save a conversation exchange to the database',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_moochat_check_objectives' => [
        'classname'     => 'mod_moochat\\external\\check_objectives',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Ask the AI to evaluate which learning objectives the student has met',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
