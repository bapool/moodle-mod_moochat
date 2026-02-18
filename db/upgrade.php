<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade script for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_moochat_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026021801) {
        // Define table moochat_conversations to be created.
        $table = new xmldb_table('moochat_conversations');

        // Adding fields to table moochat_conversations.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('moochatid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table moochat_conversations.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('moochatid', XMLDB_KEY_FOREIGN, ['moochatid'], 'moochat', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table moochat_conversations.
        $table->add_index('moochatid-userid', XMLDB_INDEX_NOTUNIQUE, ['moochatid', 'userid']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch create table for moochat_conversations.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moochat savepoint reached.
        upgrade_mod_savepoint(true, 2026021801, 'moochat');
    }

    return true;
}
