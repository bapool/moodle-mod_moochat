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
 * View student conversation history and objective results
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/moochat/lib.php');

$id     = optional_param('id',     0, PARAM_INT); // Course module ID.
$userid = optional_param('userid', 0, PARAM_INT); // User ID for detailed view.

if ($id) {
    $cm      = get_coursemodule_from_id('moochat', $id, 0, false, MUST_EXIST);
    $moochat = $DB->get_record('moochat',  ['id' => $cm->instance], '*', MUST_EXIST);
    $course  = $DB->get_record('course',   ['id' => $cm->course],   '*', MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'moochat');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/moochat:viewhistory', $context);

$PAGE->set_url('/mod/moochat/history.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($moochat->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$hasobjectives  = (!empty($moochat->objectives) && $moochat->grade > 0);
$objectives     = $hasobjectives ? moochat_parse_objectives($moochat->objectives) : [];
$objectivetotal = count($objectives);

// =========================================================================
// Helper: get per-session scores for a user.
// =========================================================================
function moochat_get_session_scores($moochat, $userid) {
    global $DB;
    if (empty($moochat->objectives) || $moochat->grade == 0) {
        return [];
    }
    $objectives = moochat_parse_objectives($moochat->objectives);
    $total      = count($objectives);
    if ($total === 0) {
        return [];
    }
    $sql = "SELECT sessionid, COUNT(*) AS metcount
              FROM {moochat_objective_results}
             WHERE moochatid = :moochatid AND userid = :userid AND met = 1
             GROUP BY sessionid";
    $rows = $DB->get_records_sql($sql, ['moochatid' => $moochat->id, 'userid' => $userid]);
    $scores = [];
    foreach ($rows as $r) {
        $scores[$r->sessionid] = round(($r->metcount / $total) * $moochat->grade, 2);
    }
    return $scores;
}

// Helper: best score for user.
function moochat_get_best_score_for_user($moochat, $userid) {
    $sessionscores = moochat_get_session_scores($moochat, $userid);
    if (empty($sessionscores)) {
        return 0;
    }
    return max($sessionscores);
}

// =========================================================================
// DETAILED VIEW — one student's conversation + objective results.
// =========================================================================
if ($userid > 0) {
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('conversationwith', 'moochat', fullname($user)));

    // Back link.
    $backurl = new moodle_url('/mod/moochat/history.php', ['id' => $cm->id]);
    echo html_writer::link($backurl, get_string('backtolist', 'moochat'), ['class' => 'btn btn-secondary mb-3']);

    // -----------------------------------------------------------------
    // Objective Results panel (teacher view).
    // -----------------------------------------------------------------
    if ($hasobjectives) {
        // Get all sessions for this user.
        $sessionscores = moochat_get_session_scores($moochat, $userid);
        $bestscore     = empty($sessionscores) ? 0 : max($sessionscores);

        echo '<div class="moochat-teacher-objectives card mb-4">';
        echo '<div class="card-header"><strong>' . get_string('objectivesresults', 'moochat') . '</strong>';
        if ($moochat->grade > 0) {
            echo ' &mdash; <strong>' . get_string('bestscorelabel', 'moochat') . ': ' .
                 $bestscore . ' / ' . $moochat->grade . '</strong>';
        }
        echo '</div>';
        echo '<div class="card-body">';

        // Per-session breakdown.
        if (!empty($sessionscores)) {
            echo '<h6>' . get_string('sessionscore', 'moochat') . '</h6>';
            echo '<table class="table table-sm table-bordered mb-3">';
            echo '<thead><tr><th>' . get_string('session', 'moochat') . '</th>';
            echo '<th>' . get_string('objectivesmet', 'moochat') . '</th>';
            echo '<th>' . get_string('scorelabel', 'moochat') . '</th>';
            echo '<th>' . get_string('ingradebook', 'moochat') . '</th></tr></thead><tbody>';
            $sessionnum = 1;
            arsort($sessionscores); // Highest score first.
            foreach ($sessionscores as $sid => $score) {
                $sesmet = $DB->count_records('moochat_objective_results', [
                    'moochatid' => $moochat->id, 'userid' => $userid, 'sessionid' => $sid, 'met' => 1,
                ]);
                $isBest = ($score == $bestscore) ? '<span class="badge badge-success">' . get_string('bestscorelabel', 'moochat') . '</span>' : '';
                echo '<tr>';
                echo '<td>' . get_string('session', 'moochat') . ' ' . $sessionnum . '</td>';
                echo '<td>' . $sesmet . ' / ' . $objectivetotal . '</td>';
                echo '<td>' . $score . ' / ' . $moochat->grade . '</td>';
                echo '<td>' . $isBest . '</td>';
                echo '</tr>';
                $sessionnum++;
            }
            echo '</tbody></table>';
        }

        // Per-objective table (all sessions combined — show if ever met).
        $storedresults = $DB->get_records('moochat_objective_results', [
            'moochatid' => $moochat->id,
            'userid'    => $userid,
        ]);
        // Build best-per-objective (met if ever met in any session).
        $bestbyidx = [];
        foreach ($storedresults as $r) {
            $idx = (int)$r->objectiveindex;
            if (!isset($bestbyidx[$idx])) {
                $bestbyidx[$idx] = $r;
            } else if ($r->met && !$bestbyidx[$idx]->met) {
                $bestbyidx[$idx] = $r;
            }
        }

        $metcount = 0;
        echo '<h6>' . get_string('objectivesresults', 'moochat') . '</h6>';
        echo '<table class="table table-sm table-bordered moochat-objectives-table">';
        echo '<thead><tr>';
        echo '<th>#</th>';
        echo '<th>' . get_string('objectives', 'moochat') . '</th>';
        echo '<th>' . get_string('objectivesmet', 'moochat') . '</th>';
        echo '<th>' . get_string('lastmessage', 'moochat') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($objectives as $idx => $obj) {
            $r      = isset($bestbyidx[$idx]) ? $bestbyidx[$idx] : null;
            $met    = ($r && $r->met);
            $badgec = $met ? 'badge-success' : 'badge-secondary';
            $badget = $met ? get_string('objectivemet', 'moochat') : get_string('objectivenotmet', 'moochat');
            $lastchk = ($r && $r->timechecked)
                ? userdate($r->timechecked, get_string('strftimedatetime', 'langconfig'))
                : '—';
            if ($met) {
                $metcount++;
            }
            echo '<tr>';
            echo '<td>' . ($idx + 1) . '</td>';
            echo '<td>' . s($obj) . '</td>';
            echo '<td><span class="badge ' . $badgec . '">' . $badget . '</span></td>';
            echo '<td>' . $lastchk . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p class="mb-0">';
        echo '<strong>' . get_string('objectivesmetof', 'moochat',
            ['met' => $metcount, 'total' => $objectivetotal]) . '</strong>';
        echo '</p>';

        echo '</div></div>'; // card-body / card.
    }

    // -----------------------------------------------------------------
    // Conversation history — grouped by session.
    // -----------------------------------------------------------------
    $conversations = $DB->get_records('moochat_conversations',
        ['moochatid' => $moochat->id, 'userid' => $userid],
        'timecreated ASC');

    $chatbotname = format_string($moochat->name);

    if (empty($conversations)) {
        echo html_writer::tag('p', get_string('noconversations', 'moochat'), ['class' => 'alert alert-info']);
    } else {
        // Expand / collapse buttons.
        echo html_writer::start_div('moochat-controls mb-3');
        echo html_writer::tag('button', get_string('expandall',   'moochat'),
            ['class' => 'btn btn-sm btn-secondary', 'id' => 'moochat-expand-all']);
        echo ' ';
        echo html_writer::tag('button', get_string('collapseall', 'moochat'),
            ['class' => 'btn btn-sm btn-secondary', 'id' => 'moochat-collapse-all']);
        echo html_writer::end_div();

        // Get session scores for labeling.
        $sessionscores = moochat_get_session_scores($moochat, $userid);
        $bestscore     = empty($sessionscores) ? null : max($sessionscores);

        // Group by session (then by date within session).
        $bysession = [];
        $sessionorder = [];
        foreach ($conversations as $message) {
            $sid = $message->sessionid ?: 'legacy';
            if (!isset($bysession[$sid])) {
                $bysession[$sid]   = [];
                $sessionorder[]    = $sid;
            }
            $bysession[$sid][] = $message;
        }
        // Remove duplicate session IDs from order.
        $sessionorder = array_values(array_unique($sessionorder));

        echo html_writer::start_div('moochat-conversation-view');

        $sessionnum = 1;
        foreach ($sessionorder as $sid) {
            $messages     = $bysession[$sid];
            $sessionscore = isset($sessionscores[$sid]) ? $sessionscores[$sid] : null;
            $isBest       = ($hasobjectives && $sessionscore !== null && $sessionscore == $bestscore);

            // Session header.
            $firstmsg   = reset($messages);
            $datelabel  = userdate($firstmsg->timecreated, get_string('strftimedate', 'langconfig'));
            $sessionlabel = get_string('session', 'moochat') . ' ' . $sessionnum . ' &mdash; ' . $datelabel;
            if ($sessionscore !== null) {
                $sessionlabel .= ' &mdash; ' . get_string('scorelabel', 'moochat') . ': ' .
                    $sessionscore . ' / ' . $moochat->grade;
                if ($isBest) {
                    $sessionlabel .= ' <span class="badge badge-success">' . get_string('ingradebook', 'moochat') . '</span>';
                }
            }

            $collapseId = 'session-' . preg_replace('/[^a-z0-9]/', '', $sid) . '-' . $sessionnum;

            echo html_writer::start_div('moochat-day-section');
            echo html_writer::start_tag('button', [
                'class'          => 'moochat-day-header',
                'type'           => 'button',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#' . $collapseId,
                'aria-expanded'  => 'false',
                'aria-controls'  => $collapseId,
            ]);
            echo html_writer::tag('span', $sessionlabel, ['class' => 'moochat-day-label']);
            echo html_writer::tag('span', '(' . count($messages) . ' ' . get_string('messages', 'moochat') . ')',
                ['class' => 'moochat-day-count']);
            echo html_writer::tag('span', '▼', ['class' => 'moochat-collapse-icon']);
            echo html_writer::end_tag('button');

            echo html_writer::start_div('collapse', ['id' => $collapseId]);
            echo html_writer::start_div('moochat-day-messages');

            foreach ($messages as $message) {
                $messageclass = ($message->role == 'user') ? 'moochat-message-user' : 'moochat-message-assistant';
                $displayname  = ($message->role == 'user') ? fullname($user) : $chatbotname;

                echo html_writer::start_div('moochat-message ' . $messageclass);
                echo html_writer::start_div('moochat-message-header');
                echo html_writer::tag('strong', $displayname, ['class' => 'moochat-message-name']);
                echo html_writer::tag('span',
                    userdate($message->timecreated, get_string('strftimetime', 'langconfig')),
                    ['class' => 'moochat-message-time']);
                echo html_writer::end_div();
                echo html_writer::tag('div', format_text($message->message, FORMAT_PLAIN),
                    ['class' => 'moochat-message-content']);
                echo html_writer::end_div();
            }

            echo html_writer::end_div(); // moochat-day-messages.
            echo html_writer::end_div(); // collapse.
            echo html_writer::end_div(); // moochat-day-section.

            $sessionnum++;
        }
        echo html_writer::end_div();

        echo html_writer::start_tag('script');
        echo "
        document.addEventListener('DOMContentLoaded', function() {
            var headers = document.querySelectorAll('.moochat-day-header');
            headers.forEach(function(header) {
                header.addEventListener('click', function() {
                    var target     = this.getAttribute('data-bs-target');
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
            var expandBtn = document.getElementById('moochat-expand-all');
            if (expandBtn) {
                expandBtn.addEventListener('click', function() {
                    document.querySelectorAll('.moochat-day-section .collapse').forEach(function(c) { c.classList.add('show'); });
                    document.querySelectorAll('.moochat-day-header').forEach(function(h) { h.setAttribute('aria-expanded', 'true'); });
                });
            }
            var collapseBtn = document.getElementById('moochat-collapse-all');
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function() {
                    document.querySelectorAll('.moochat-day-section .collapse').forEach(function(c) { c.classList.remove('show'); });
                    document.querySelectorAll('.moochat-day-header').forEach(function(h) { h.setAttribute('aria-expanded', 'false'); });
                });
            }
        });
        ";
        echo html_writer::end_tag('script');
    }

    echo $OUTPUT->footer();
    exit;
}

// =========================================================================
// LIST VIEW — all students with conversations.
// =========================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentconversations', 'moochat'));

$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
               COUNT(c.id) as messagecount,
               MAX(c.timecreated) as lastmessage
          FROM {user} u
          JOIN {moochat_conversations} c ON c.userid = u.id
         WHERE c.moochatid = :moochatid
         GROUP BY u.id, u.firstname, u.lastname, u.email
         ORDER BY lastmessage DESC";

$users = $DB->get_records_sql($sql, ['moochatid' => $moochat->id]);

if (empty($users)) {
    echo html_writer::tag('p', get_string('nostudentconversations', 'moochat'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();

    $headers = [
        get_string('fullname'),
        get_string('messagecount', 'moochat'),
        get_string('lastmessage',  'moochat'),
    ];

    if ($hasobjectives) {
        $headers[] = get_string('objectivesmet', 'moochat');
        if ($moochat->grade > 0) {
            $headers[] = get_string('bestscorelabel', 'moochat');
        }
    }
    $headers[] = get_string('actions');

    $table->head              = $headers;
    $table->attributes['class'] = 'generaltable';

    foreach ($users as $user) {
        $viewurl = new moodle_url('/mod/moochat/history.php', ['id' => $cm->id, 'userid' => $user->id]);

        $row   = [];
        $row[] = fullname($user);
        // Note: email deliberately omitted — replaced by grade column.
        $row[] = $user->messagecount;
        $row[] = userdate($user->lastmessage, get_string('strftimedatetime', 'langconfig'));

        if ($hasobjectives) {
            // Count objectives ever met (across any session).
            $metcount = $DB->count_records_select(
                'moochat_objective_results',
                'moochatid = :moochatid AND userid = :userid AND met = 1',
                ['moochatid' => $moochat->id, 'userid' => $user->id]
            );
            // De-duplicate: count unique objectiveindices that were ever met.
            $sql2 = "SELECT COUNT(DISTINCT objectiveindex) AS cnt
                       FROM {moochat_objective_results}
                      WHERE moochatid = :moochatid AND userid = :userid AND met = 1";
            $uniquemet = $DB->get_field_sql($sql2, ['moochatid' => $moochat->id, 'userid' => $user->id]);
            $row[] = ($uniquemet ?: 0) . ' / ' . $objectivetotal;

            if ($moochat->grade > 0) {
                $bestscore = moochat_get_best_score_for_user($moochat, $user->id);
                $row[] = $bestscore . ' / ' . $moochat->grade;
            }
        }

        $row[] = html_writer::link($viewurl, get_string('viewdetails', 'moochat'), ['class' => 'btn btn-primary btn-sm']);
        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
