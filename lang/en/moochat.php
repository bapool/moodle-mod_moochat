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
 * Language strings for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']          = 'MooChat';
$string['modulename']          = 'MooChat';
$string['modulenameplural']    = 'MooChats';
$string['modulename_help']     = 'The MooChat activity allows you to create an AI-powered chatbot for your course. Students can interact with the AI assistant to get help, ask questions, or engage with course content. Learning objectives can be set so the AI automatically grades the conversation.';
$string['moochat:addinstance']  = 'Add a new MooChat activity';
$string['moochat:view']         = 'View MooChat activity';
$string['moochat:submit']       = 'Submit messages to MooChat';
$string['moochat:viewhistory']  = 'View student conversation history';
$string['moochat:grade']        = 'Grade MooChat submissions';

// ---- Settings ----
$string['chatname']      = 'Chat Name';
$string['chatname_help'] = 'Give your AI assistant a name (e.g., "Math Tutor", "Historical Figure Chat", "Science Helper")';
$string['display']       = 'Display Mode';
$string['display_help']  = 'Choose how the chat interface is displayed:<br>
<strong>Separate page:</strong> Students click the activity link to open the chat on its own page.<br>
<strong>Inline on course page:</strong> The chat interface appears directly on the course page.';
$string['display_page']   = 'Separate page';
$string['display_inline'] = 'Inline on course page';

$string['chatsize']        = 'Chat Interface Size';
$string['chatsize_help']   = 'Choose the size of the chat interface.';
$string['chatsize_small']  = 'Small (300px messages, 400px total)';
$string['chatsize_medium'] = 'Medium (400px messages, 500px total)';
$string['chatsize_large']  = 'Large (600px messages, 700px total)';

$string['include_section_content']         = 'Include Section Content';
$string['include_section_content_help']    = 'When enabled, the AI will have access to all content in the current course section.';
$string['include_section_content_warning'] = 'Warning: Sections with excessive content can result in slower and lower quality responses.';
$string['include_hidden_content']          = 'Include Hidden Content';
$string['include_hidden_content_help']     = 'When enabled, hidden activities will be included in section content provided to the AI.';

$string['avatar']        = 'Avatar Image';
$string['avatar_help']   = 'Upload an image to represent your chatbot. Recommended size: 128x128 pixels.';
$string['avatarsize']    = 'Avatar Size';
$string['avatarsize_help'] = 'Choose the size of the avatar image displayed.';

$string['systemprompt']      = 'System Prompt (AI Personality)';
$string['systemprompt_help'] = 'Define how the AI should behave. Example: "You are a friendly math tutor who explains concepts step-by-step."';
$string['defaultprompt']     = 'You are a helpful educational assistant designed to help students learn.';

// ---- Content upload & restriction ----
$string['contentheader']          = 'Course Content for AI';
$string['contentfiles']           = 'Upload Content Files';
$string['contentfiles_help']      = 'Upload PDF or plain text (.txt) files for the AI to use as reference material. You may upload multiple files. The AI will use this content when answering student questions.';
$string['content_restrict']       = 'Only Use Uploaded Content';
$string['content_restrict_help']  = 'When checked, the AI will ONLY answer from the uploaded files above. If a student asks something not covered in those files, the AI will say it does not have that information in the course materials. When unchecked, the uploaded files are provided as reference but the AI may also use its general knowledge.';
$string['nocontentfiles']         = 'No content files uploaded.';

// ---- Objectives & Grading ----
$string['objectivesheader'] = 'Learning Objectives & Grading';

$string['objectives']      = 'Learning Objectives';
$string['objectives_help'] = 'Enter one learning objective per line. After each AI response the AI will check whether the student has met each objective. Students earn points proportionally: meeting all objectives earns the full grade. Leave blank to disable AI grading. Example:<br><br>The student can state when Albert Einstein was born.<br>The student can explain the theory of relativity in simple terms.<br>The student can name at least two of Einstein\'s major contributions.';

$string['objectiveshint'] = 'Enter one learning objective per line. The AI will check each objective after every student message. The grade updates live as objectives are met. Teachers can override grades at any time from the gradebook.';

$string['gradingheader']            = 'Grading';
$string['objectivesmet']            = 'Objectives Met';
$string['objectivesmetof']          = '{$a->met} of {$a->total} objectives met';
$string['objectivesresults']        = 'Objective Results';
$string['noobjectives']             = 'No learning objectives have been set for this activity.';
$string['objectivemet']             = 'Met';
$string['objectivenotmet']          = 'Not met';
$string['viewobjectives']           = 'View Objective Results';
$string['gradeupdate']              = 'Grade updated';
$string['currentgrade']             = 'Current Grade';
$string['gradeupdated']             = 'Your grade has been updated to {$a}.';
$string['objectiveschecking']       = 'Checking objectives...';
$string['objectivescheckingdone']   = 'Objectives checked.';
$string['objectiveprogress']        = 'Objective Progress';

// ---- Rate limiting ----
$string['ratelimiting']          = 'Rate Limiting';
$string['ratelimit_enable']      = 'Enable Rate Limiting';
$string['ratelimit_enable_help'] = 'When enabled, students will be limited to a specific number of questions per time period.';
$string['ratelimit_period']      = 'Rate Limit Period';
$string['ratelimit_period_help'] = 'Choose whether to limit questions per hour or per day.';
$string['ratelimit_count']       = 'Maximum Questions';
$string['ratelimit_count_help']  = 'Number of questions a student can ask during the selected time period.';
$string['period_hour']           = 'Per Hour';
$string['period_day']            = 'Per Day';
$string['questionsremaining']    = 'Questions remaining: {$a}';
$string['ratelimitreached']      = 'You have reached your limit of {$a->limit} questions {$a->period}. Please try again later.';
$string['ratelimitreached_hour'] = 'per hour';
$string['ratelimitreached_day']  = 'per day';

// ---- Advanced settings ----
$string['advancedsettings']    = 'Advanced Settings';
$string['modelselection']      = 'AI Model';
$string['modelselection_help'] = 'Choose which AI model to use.';
$string['maxmessages']         = 'Maximum Messages per Session';
$string['maxmessages_help']    = 'Limit how many messages a student can send in one session (0 = unlimited).';
$string['temperature']         = 'Creativity Level';
$string['temperature_help']    = 'Lower values make responses more focused. Higher values make responses more creative.';

// ---- Completion ----
$string['completionmessages']       = 'Require interactions';
$string['completionmessages_help']  = 'When enabled, the student must send at least the specified number of messages to the AI before the activity is marked complete. Set to 0 to disable.';
$string['completionmessages_label'] = 'messages sent to the AI';
$string['completionmessages_desc']  = 'Send at least {$a} message(s) to the AI';

// ---- Chat interface ----
$string['startchat']          = 'Start chatting with the AI assistant!';
$string['startchatwith']      = 'Start chatting with {$a}!';
$string['chattingwith']       = 'Clear the chat to start a new topic.';
$string['typemessage']        = 'Type your message here...';
$string['send']               = 'Send';
$string['clear']              = 'Clear Chat';
$string['maxmessagesreached'] = 'You have reached the maximum number of messages for this session.';
$string['thinking']           = 'Thinking...';
$string['missingidandcmid']   = 'Either course module ID or instance ID must be specified';

// ---- History ----
$string['history']                  = 'History';
$string['studentconversations']     = 'Student Conversations';
$string['viewconversations']        = 'View Student Conversations';
$string['conversationwith']         = 'Conversation with {$a}';
$string['backtolist']               = 'Back to List';
$string['noconversations']          = 'No conversation history found.';
$string['nostudentconversations']   = 'No students have used this MooChat activity yet.';
$string['messages']                 = 'messages';
$string['messagecount']             = 'Messages';
$string['lastmessage']              = 'Last Message';
$string['viewdetails']              = 'View Details';
$string['expandall']                = 'Expand All';
$string['collapseall']              = 'Collapse All';

// ---- Privacy ----
$string['privacy:metadata:moochat_usage']                     = 'Information about user interactions with MooChat activities';
$string['privacy:metadata:moochat_usage:userid']              = 'The ID of the user';
$string['privacy:metadata:moochat_usage:messagecount']        = 'Number of messages sent';
$string['privacy:metadata:moochat_usage:firstmessage']        = 'Timestamp of first message';
$string['privacy:metadata:moochat_usage:lastmessage']         = 'Timestamp of last message';
$string['privacy:metadata:moochat_conversations']             = 'Stores chat conversations between students and the AI assistant';
$string['privacy:metadata:moochat_conversations:userid']      = 'The ID of the user';
$string['privacy:metadata:moochat_conversations:moochatid']   = 'The MooChat activity ID';
$string['privacy:metadata:moochat_conversations:role']        = 'The role of the message sender (user or assistant)';
$string['privacy:metadata:moochat_conversations:message']     = 'The message content';
$string['privacy:metadata:moochat_conversations:timecreated'] = 'When the message was created';
$string['privacy:metadata:moochat_objective_results']                       = 'Stores AI grading results for learning objectives';
$string['privacy:metadata:moochat_objective_results:userid']                = 'The ID of the user';
$string['privacy:metadata:moochat_objective_results:moochatid']             = 'The MooChat activity ID';
$string['privacy:metadata:moochat_objective_results:objectiveindex']        = 'The index of the objective';
$string['privacy:metadata:moochat_objective_results:met']                   = 'Whether the objective was met';
$string['privacy:metadata:moochat_objective_results:timechecked']           = 'When the objective was last checked';

// ---- JavaScript strings ----
$string['questionsremaining_js']  = 'Questions remaining';
$string['thinking_js']            = 'Thinking...';
$string['ratelimitreached_title'] = 'Rate Limit Reached';
$string['error_title']            = 'Error';
$string['connectionerror']        = 'Failed to connect to AI service';
$string['chatcleared']            = 'Chat cleared. Start a new conversation!';
$string['confirmclear']           = 'Clear all messages? (Your question limit will not reset)';

// ---- Session / scoring strings ----
$string['scorelabel']         = 'Score';
$string['bestscorelabel']     = 'Best Score';
$string['session']            = 'Session';
$string['sessionscore']       = 'Score per Session';
$string['ingradebook']        = 'In Gradebook';
$string['objectiveshintchat'] = 'Objectives will appear here as you discover them!';
$string['objectiveunlocked']  = 'Objectives will appear here as you discover them!';
