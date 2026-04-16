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
 * View page for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/moochat/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.

if ($id) {
    $cm      = get_coursemodule_from_id('moochat', $id, 0, false, MUST_EXIST);
    $course  = $DB->get_record('course', ['id' => $cm->course],    '*', MUST_EXIST);
    $moochat = $DB->get_record('moochat', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'moochat');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/moochat:view', $context);

// Trigger module viewed event.
$event = \mod_moochat\event\course_module_viewed::create([
    'objectid' => $moochat->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course',   $course);
$event->add_record_snapshot('moochat',  $moochat);
$event->trigger();

// Set up the page.
$PAGE->set_url('/mod/moochat/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moochat->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_moochat/chat', 'init', [$moochat->id]);

// Get avatar URL if one has been uploaded.
$avatarurl = null;
$fs        = get_file_storage();
$files     = $fs->get_area_files($context->id, 'mod_moochat', 'avatar', 0, 'filename', false);
if (!empty($files)) {
    $file      = reset($files);
    $avatarurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

// Determine if this activity has grading / objectives.
$hasobjectives = (!empty($moochat->objectives) && $moochat->grade > 0);
$objectives    = $hasobjectives ? moochat_parse_objectives($moochat->objectives) : [];
$isteacher     = has_capability('mod/moochat:viewhistory', $context);

echo $OUTPUT->header();

echo '<div class="moochat-activity-container">';

// -----------------------------------------------------------------------
// LEFT SIDEBAR — avatar, info, objectives panel (students) / history (teachers).
// -----------------------------------------------------------------------
echo '<div class="moochat-sidebar">';

if ($avatarurl) {
    echo '<div class="moochat-avatar-large">';
    echo html_writer::img($avatarurl, $moochat->name, ['width' => $moochat->avatarsize, 'height' => $moochat->avatarsize]);
    echo '</div>';
}

echo '<h3 class="moochat-sidebar-title">' . format_string($moochat->name) . '</h3>';

// Teacher: show History link.
if ($isteacher) {
    $historyurl = new moodle_url('/mod/moochat/history.php', ['id' => $cm->id]);
    echo '<div class="moochat-history-link">';
    echo html_writer::link($historyurl, get_string('history', 'moochat'), ['class' => 'btn btn-secondary btn-sm']);
    echo '</div>';
}

// Rate-limit remaining display.
echo '<div class="moochat-remaining-sidebar" id="moochat-remaining-' . $moochat->id . '"></div>';

// ---- Objectives panel ----
if ($hasobjectives && !$isteacher) {
    // Student panel — JavaScript populates it. Starts empty: objectives revealed as they are met.
    echo '<div class="moochat-objectives-wrapper" id="moochat-objectives-' . $moochat->id . '">';
    echo '<div class="moochat-objectives-panel">';
    echo '<div class="moochat-score-display">';
    echo '<span class="moochat-score-label">' . get_string('scorelabel', 'moochat') . ':</span> ';
    echo '<strong class="moochat-score-value">0 / ' . (int)$moochat->grade . '</strong>';
    echo '</div>';
    echo '<div class="moochat-objectives-progress mb-2">';
    echo '<div class="moochat-progress-label">';
    echo '<span class="badge badge-secondary">0 / ' . count($objectives) . '</span>';
    echo '</div>';
    echo '<div class="progress moochat-progress mt-1">';
    echo '<div class="progress-bar bg-success" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
    echo '</div></div>';
    echo '<p class="moochat-objectives-hint small text-muted">' . get_string('objectiveshintchat', 'moochat') . '</p>';
    echo '</div>'; // moochat-objectives-panel
    echo '</div>'; // moochat-objectives-wrapper

} else if ($hasobjectives && $isteacher) {
    // Teacher sees the raw objectives list for reference.
    echo '<div class="moochat-objectives-wrapper mt-3">';
    echo '<h5 class="moochat-objectives-heading">&#127919; ' . get_string('objectives', 'moochat') . '</h5>';
    echo '<ul class="moochat-objectives-list list-unstyled small">';
    foreach ($objectives as $obj) {
        echo '<li class="mb-1">&#8226; ' . s($obj) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

echo '</div>'; // End sidebar.

// -----------------------------------------------------------------------
// RIGHT SIDE — chat interface.
// -----------------------------------------------------------------------
$sizeclass = 'moochat-size-' . $moochat->chatsize;
echo '<div class="moochat-chat-area">';
echo '<div class="moochat-interface ' . $sizeclass . '" id="moochat-' . $moochat->id . '">';

// Chat messages area.
echo '<div class="moochat-messages" id="moochat-messages-' . $moochat->id . '">';
echo '<p class="moochat-welcome">' . get_string('startchatwith', 'moochat', format_string($moochat->name)) . '</p>';
echo '</div>';

// Input area.
echo '<div class="moochat-input-area">';
echo '<textarea id="moochat-input-' . $moochat->id . '" class="moochat-input" placeholder="' .
     get_string('typemessage', 'moochat') . '" rows="3"></textarea>';

echo '<div class="moochat-buttons">';
echo '<button id="moochat-send-' . $moochat->id . '" class="btn btn-primary moochat-send">' .
     get_string('send', 'moochat') . '</button>';
echo '<button id="moochat-clear-' . $moochat->id . '" class="btn btn-secondary moochat-clear">' .
     get_string('clear', 'moochat') . '</button>';
echo '</div>'; // End buttons.

echo '</div>'; // End input area.
echo '</div>'; // End moochat-interface.
echo '</div>'; // End chat-area.

echo '</div>'; // End moochat-activity-container.

echo $OUTPUT->footer();
