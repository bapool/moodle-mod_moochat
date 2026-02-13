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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_moochat_send_message' => [
        'classname'   => 'mod_moochat\external\send_message',
        'methodname'  => 'execute',
        'description' => 'Send a chat message and receive AI response',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/moochat:submit',
        'loginrequired' => true,
    ],
];
