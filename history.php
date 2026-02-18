<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// any later version.

/**
 * View student conversation history
 *
 * @package    mod_moochat
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID
$userid = optional_param('userid', 0, PARAM_INT); // User ID for detailed view

if ($id) {
    $cm = get_coursemodule_from_id('moochat', $id, 0, false, MUST_EXIST);
    $moochat = $DB->get_record('moochat', array('id' => $cm->instance), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
} else {
    print_error('missingidandcmid', 'moochat');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/moochat:viewhistory', $context);

$PAGE->set_url('/mod/moochat/history.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_title(format_string($moochat->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Require Bootstrap JavaScript for collapse functionality
$PAGE->requires->js_call_amd('core/collapse', 'init');

// If viewing a specific user's conversation
if ($userid > 0) {
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('conversationwith', 'moochat', fullname($user)));
    
    // Back link
    $backurl = new moodle_url('/mod/moochat/history.php', array('id' => $cm->id));
    echo html_writer::link($backurl, get_string('backtolist', 'moochat'), array('class' => 'btn btn-secondary mb-3'));
    
    // Get all conversations for this user
    $conversations = $DB->get_records('moochat_conversations', 
        array('moochatid' => $moochat->id, 'userid' => $userid), 
        'timecreated ASC');
    
    // Get the activity name for the chatbot
    $chatbotname = format_string($moochat->name);
    
    if (empty($conversations)) {
        echo html_writer::tag('p', get_string('noconversations', 'moochat'), array('class' => 'alert alert-info'));
    } else {
        // Add expand/collapse all buttons
        echo html_writer::start_div('moochat-controls mb-3');
        echo html_writer::tag('button', get_string('expandall', 'moochat'), 
            array('class' => 'btn btn-sm btn-secondary', 'id' => 'moochat-expand-all'));
        echo ' ';
        echo html_writer::tag('button', get_string('collapseall', 'moochat'), 
            array('class' => 'btn btn-sm btn-secondary', 'id' => 'moochat-collapse-all'));
        echo html_writer::end_div();
        
        // Group conversations by date
        $conversationsbydate = array();
        foreach ($conversations as $message) {
            $datekey = userdate($message->timecreated, '%Y-%m-%d');
            $datelabel = userdate($message->timecreated, get_string('strftimedate', 'langconfig'));
            if (!isset($conversationsbydate[$datekey])) {
                $conversationsbydate[$datekey] = array(
                    'label' => $datelabel,
                    'messages' => array()
                );
            }
            $conversationsbydate[$datekey]['messages'][] = $message;
        }
        
        echo html_writer::start_div('moochat-conversation-view');
        
        $dayindex = 0;
        foreach ($conversationsbydate as $datekey => $daydata) {
            $dayindex++;
            $collapseclass = ''; // All collapsed by default
            $expandedaria = 'false'; // All collapsed by default
            
            // Day header
            echo html_writer::start_div('moochat-day-section');
            echo html_writer::start_tag('button', array(
                'class' => 'moochat-day-header',
                'type' => 'button',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#day-' . $datekey,
                'aria-expanded' => $expandedaria,
                'aria-controls' => 'day-' . $datekey
            ));
            echo html_writer::tag('span', $daydata['label'], array('class' => 'moochat-day-label'));
            echo html_writer::tag('span', '(' . count($daydata['messages']) . ' ' . 
                get_string('messages', 'moochat') . ')', array('class' => 'moochat-day-count'));
            echo html_writer::tag('span', '▼', array('class' => 'moochat-collapse-icon'));
            echo html_writer::end_tag('button');
            
            // Collapsible content
            echo html_writer::start_div('collapse ' . $collapseclass, array('id' => 'day-' . $datekey));
            echo html_writer::start_div('moochat-day-messages');
            
            foreach ($daydata['messages'] as $message) {
                $messageclass = ($message->role == 'user') ? 'moochat-message-user' : 'moochat-message-assistant';
                
                // Get the display name
                if ($message->role == 'user') {
                    $displayname = fullname($user);
                } else {
                    $displayname = $chatbotname;
                }
                
                echo html_writer::start_div('moochat-message ' . $messageclass);
                
                // Header with name and timestamp
                echo html_writer::start_div('moochat-message-header');
                echo html_writer::tag('strong', $displayname, array('class' => 'moochat-message-name'));
                echo html_writer::tag('span', userdate($message->timecreated, get_string('strftimetime', 'langconfig')), 
                    array('class' => 'moochat-message-time'));
                echo html_writer::end_div();
                
                // Message content
                echo html_writer::tag('div', format_text($message->message, FORMAT_PLAIN), 
                    array('class' => 'moochat-message-content'));
                
                echo html_writer::end_div();
            }
            
            echo html_writer::end_div(); // moochat-day-messages
            echo html_writer::end_div(); // collapse
            echo html_writer::end_div(); // moochat-day-section
        }
        
        echo html_writer::end_div();
        
        // Add JavaScript for expand/collapse all buttons and click handlers
        echo html_writer::start_tag('script');
        echo "
        document.addEventListener('DOMContentLoaded', function() {
            // Make date headers clickable to toggle
            var headers = document.querySelectorAll('.moochat-day-header');
            headers.forEach(function(header) {
                header.addEventListener('click', function() {
                    var target = this.getAttribute('data-bs-target');
                    var collapseDiv = document.querySelector(target);
                    if (collapseDiv) {
                        var isExpanded = this.getAttribute('aria-expanded') === 'true';
                        if (isExpanded) {
                            collapseDiv.classList.remove('show');
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            collapseDiv.classList.add('show');
                            this.setAttribute('aria-expanded', 'true');
                        }
                    }
                });
            });
            
            // Expand all button
            var expandBtn = document.getElementById('moochat-expand-all');
            if (expandBtn) {
                expandBtn.addEventListener('click', function() {
                    var collapses = document.querySelectorAll('.moochat-day-section .collapse');
                    var headers = document.querySelectorAll('.moochat-day-header');
                    collapses.forEach(function(collapse) {
                        collapse.classList.add('show');
                    });
                    headers.forEach(function(header) {
                        header.setAttribute('aria-expanded', 'true');
                    });
                });
            }
            
            // Collapse all button
            var collapseBtn = document.getElementById('moochat-collapse-all');
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function() {
                    var collapses = document.querySelectorAll('.moochat-day-section .collapse');
                    var headers = document.querySelectorAll('.moochat-day-header');
                    collapses.forEach(function(collapse) {
                        collapse.classList.remove('show');
                    });
                    headers.forEach(function(header) {
                        header.setAttribute('aria-expanded', 'false');
                    });
                });
            }
        });
        ";
        echo html_writer::end_tag('script');
    }
    
    echo $OUTPUT->footer();
    exit;
}

// Show list of students with conversations
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentconversations', 'moochat'));

// Get all users who have conversations in this moochat activity
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, 
               COUNT(c.id) as messagecount,
               MAX(c.timecreated) as lastmessage
        FROM {user} u
        JOIN {moochat_conversations} c ON c.userid = u.id
        WHERE c.moochatid = :moochatid
        GROUP BY u.id, u.firstname, u.lastname, u.email
        ORDER BY lastmessage DESC";

$users = $DB->get_records_sql($sql, array('moochatid' => $moochat->id));

if (empty($users)) {
    echo html_writer::tag('p', get_string('nostudentconversations', 'moochat'), array('class' => 'alert alert-info'));
} else {
    // Create table
    $table = new html_table();
    $table->head = array(
        get_string('fullname'),
        get_string('email'),
        get_string('messagecount', 'moochat'),
        get_string('lastmessage', 'moochat'),
        get_string('actions')
    );
    $table->attributes['class'] = 'generaltable';
    
    foreach ($users as $user) {
        $viewurl = new moodle_url('/mod/moochat/history.php', 
            array('id' => $cm->id, 'userid' => $user->id));
        
        $row = array();
        $row[] = fullname($user);
        $row[] = $user->email;
        $row[] = $user->messagecount;
        $row[] = userdate($user->lastmessage, get_string('strftimedatetime', 'langconfig'));
        $row[] = html_writer::link($viewurl, get_string('viewdetails', 'moochat'), 
            array('class' => 'btn btn-primary btn-sm'));
        
        $table->data[] = $row;
    }
    
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
