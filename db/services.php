<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web service definitions for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_moochat_send_message' => array(
        'classname'   => 'mod_moochat\external\send_message',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Send a message to the AI and get a response',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ),
    'mod_moochat_save_conversation' => array(
        'classname'   => 'mod_moochat\external\save_conversation',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Save a conversation exchange to the database',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ),
);
