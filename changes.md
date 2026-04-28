# MooChat Plugin - Changelog

## Version 1.8.2 (2026-04-28)
Prompt input changed to full sized editor.

## Version 1.8.1 (2026-04-28)
Changed default on addition to No Grade.

## Version 1.8.0 (2026-04-27)

### New Features
- **Activity Completion — Minimum Interactions** — Teachers can require students to send a minimum number of messages to the AI before the activity is marked complete
  - New `completionmessages` field in the moochat table (0 = disabled, N = required count)
  - Default is 5 messages when the rule is enabled
  - Message count is tracked via the existing `moochat_usage` table and persists across sessions
  - Completion triggers automatically in `send_message.php` after each successful AI reply
- **Activity Completion — Passing Grade** — The activity is marked complete when the student's gradebook grade meets or exceeds the passing grade set by the teacher
  - Uses Moodle's standard `COMPLETION_COMPLETE` via `completion_info::update_state()`
  - Triggers automatically in `check_objectives.php` after each grade write
  - Works alongside the minimum interactions rule — both can be required simultaneously

### Bug Fixes
- Fixed grade not being written to the gradebook during live chat sessions
  - `check_objectives.php` was using `$sessiongrade > $bestscore` which prevented the grade write when the current session tied the best score
  - Changed to `$sessiongrade >= $bestscore` so grades are always written when objectives are met
- Added second completion trigger in `check_objectives.php` outside the best-score guard to ensure grade-based completion fires even when the score does not change
- Fixed objective grading false positives — objectives were being awarded for thematically related but off-topic exchanges
  - Added PHP keyword validation requiring at least 2 meaningful words from the objective text to appear in the exchange before the AI's verdict is accepted
  - Common stop words excluded from keyword matching
  - Example: objective "What is the weather in France" now requires both "weather" and "france" to appear in the exchange — asking about England's location no longer awards this objective

### Files Added
- `classes/completion/custom_completion.php` — Custom completion rule class implementing `activity_custom_completion`

### Files Modified
- `lib.php` — Added `FEATURE_COMPLETION_HAS_RULES` to `moochat_supports()`; added `completionmessages` handling to add/update instance functions; added `customdata` population in `moochat_get_coursemodule_info()` for completion rule descriptions
- `mod_form.php` — Added `add_completion_rules()` with interactions checkbox and count field; added `completion_rule_enabled()`; updated `data_preprocessing()` and `data_postprocessing()` to map `completionmessages` to/from the form group fields
- `classes/external/send_message.php` — Added message count tracking for non-rate-limited activities; added completion trigger after successful AI reply
- `classes/external/check_objectives.php` — Changed `>` to `>=` in best-score comparison; added second completion trigger outside best-score guard; added PHP keyword validation with 2-word minimum match requirement
- `db/install.xml` — Added `completionmessages` field to moochat table
- `db/upgrade.php` — Added upgrade step 2026042702 for `completionmessages` field
- `lang/en/moochat.php` — Added strings: `completionmessages`, `completionmessages_help`, `completionmessages_label`, `completionmessages_desc`
- `version.php` — Bumped to 2026042702 / v1.8.0

**Status:** ✓ COMPLETE

---

## Version 1.7.0 (2026-04-16)

### New Features
- **Course Content Upload** - Teachers can upload up to 5 PDF or plain text files directly to the activity
  - Files are stored in Moodle's file system using the standard filemanager widget
  - PDF text extraction uses `pdftotext` (poppler-utils) with automatic fallback to bundled smalot/pdfparser
  - Plain text (.txt) files are read directly
  - Uploaded content takes priority over "Include Section Content" when files are present
- **"Only Use Uploaded Content" Restriction** - New checkbox that locks the AI to only answer from uploaded files
  - When checked: AI responds with "I don't have information about that in the course materials" for anything not in the files
  - When unchecked: uploaded files are provided as reference material alongside AI general knowledge
  - Restriction instruction appears before AND after the content in the prompt for maximum enforcement

### Files Modified
- `db/install.xml` - Added `content_restrict` field to moochat table
- `db/upgrade.php` - Added upgrade step for `content_restrict` field
- `mod_form.php` - Added "Course Content for AI" section with filemanager and checkbox; updated data_preprocessing and data_postprocessing for contentfiles
- `lib.php` - Added `moochat_get_uploaded_content()` function; updated `mod_moochat_pluginfile()` to serve contentfiles area; added `content_restrict` handling to add/update instance functions
- `classes/external/send_message.php` - Added uploaded content injection logic with strict/reference modes; uploaded content takes priority over section content
- `lang/en/moochat.php` - Added strings: contentheader, contentfiles, contentfiles_help, content_restrict, content_restrict_help, nocontentfiles
- `version.php` - Bumped to v1.7.0

### Notes
- Requires nginx `client_max_body_size` to be set if nginx sits in front of Apache (default nginx limit is ~1MB)
- PDF extraction uses the same pdftotext/smalot pipeline already present in the plugin
- Files are teacher-only for download (requires mod/moochat:viewhistory capability)

**Status:** ✓ COMPLETE

---

## Version 1.6.0 (2026-04-16)

### New Features
- **Session-Based Scoring** - Each "Clear Chat" starts a brand new session with independent grade tracking
  - Only the highest session score is recorded in the gradebook
  - If a student earned 70 points previously, a new session score must exceed 70 to update the gradebook
  - Students can retry as many times as they want; only their best attempt counts
- **Objectives Revealed on Discovery** - Students no longer see objectives listed upfront
  - The sidebar starts empty with a progress bar and score display
  - Each objective appears in the sidebar only when the student earns it
  - A green toast notification pops up in chat when an objective is unlocked
  - Newly revealed objectives animate with a highlight effect
- **Per-Session Score in History** - Teacher history view now shows per-session breakdown
  - Each conversation session is labeled with its score
  - Best session is marked "In Gradebook"
  - Email column replaced with Best Score column in student list

### Files Modified
- `db/install.xml` - Added `sessionid` field to moochat_conversations and moochat_objective_results; added `pointsperobj` field; updated unique index to be session-scoped
- `db/upgrade.php` - Added upgrade step for sessionid fields
- `classes/external/check_objectives.php` - Complete rewrite: session-aware, best-score logic, per-exchange evaluation
- `classes/external/save_conversation.php` - Added `sessionid` parameter
- `amd/src/chat.js` - Added session UUID generation; objectives panel reveals on discovery; toast notifications; Clear Chat generates new session
- `amd/build/chat.min.js` - Rebuilt
- `view.php` - Student sidebar starts empty; objectives not shown until earned
- `history.php` - Per-session score breakdown; email replaced with best score; conversations grouped by session
- `lib.php` - Updated `moochat_calculate_grade()` to use best-session logic
- `lang/en/moochat.php` - Added: scorelabel, bestscorelabel, session, sessionscore, ingradebook, objectiveshintchat, objectiveunlocked
- `styles.css` - Added styles for score display, objective reveal animation, toast notifications
- `version.php` - Bumped to v1.6.0

**Status:** ✓ COMPLETE

---

## Version 1.5.0 (2026-02-28)

### New Features
- **AI-Based Grading with Learning Objectives** - Teachers can enter learning objectives (one per line) and the AI automatically grades student conversations
  - Students earn points as they cover objectives through natural conversation
  - Students do NOT see the objectives — they discover them through chat
  - Teacher hint in the activity description guides students toward the topics
  - Grades update live as objectives are met
  - Only 1 objective can be unlocked per chat exchange (forces continued engagement)
  - Only the most recent exchange is evaluated (prevents retroactive credit)
  - AI grader requires explicit evidence in the transcript — no topic-similarity credit
- **Live Objectives Panel** - Students see a progress bar and score in the sidebar
- **Teacher Grading Interface** - History page shows objectives met per student with detailed results table
- **Gradebook Integration** - Grades pushed to Moodle gradebook automatically; teachers can override

### Files Added
- `classes/external/check_objectives.php` - Web service to evaluate objectives against conversation

### Files Modified
- `db/install.xml` - Added `grade`, `objectives` fields to moochat table; added moochat_objective_results table
- `db/upgrade.php` - Added upgrade step for grading fields
- `db/services.php` - Registered mod_moochat_check_objectives web service
- `db/access.php` - Added mod/moochat:grade capability
- `mod_form.php` - Added Learning Objectives & Grading section with objectives textarea and standard grade elements
- `lib.php` - Added moochat_grade_item_update(), moochat_grade_item_delete(), moochat_update_grade(), moochat_get_user_grades(), moochat_calculate_grade(), moochat_parse_objectives(); updated supports() for FEATURE_GRADE_HAS_GRADE; updated delete/reset functions
- `view.php` - Added objectives panel to student sidebar
- `history.php` - Added objectives results table and grade summary to teacher detail view; added objectives met and grade columns to student list
- `amd/src/chat.js` - Added checkObjectives() and updateObjectivesPanel() functions
- `amd/build/chat.min.js` - Rebuilt
- `lang/en/moochat.php` - Added all objectives and grading strings
- `styles.css` - Added objectives panel styles
- `backup/moodle2/backup_moochat_stepslib.php` - Added grade, objectives, objectiveresults to backup
- `backup/moodle2/restore_moochat_stepslib.php` - Added objectiveresults restore handler
- `classes/privacy/provider.php` - Added moochat_objective_results metadata and deletion
- `version.php` - Bumped to v1.5.0

**Status:** ✓ COMPLETE

---

## Version 1.4.0 (2026-02-22)

### New Features
- **PDF and PPTX File Support** - MooChat can now read PDF and PowerPoint (.pptx) files uploaded as File resources in the course section
  - PDF text extraction uses `pdftotext` with automatic fallback to bundled smalot/pdfparser library
  - PPTX text extraction uses PHP's built-in ZipArchive and DOMDocument
  - Plain text files (txt, csv, html) are also extracted
  - smalot/pdfparser library bundled in `vendor/` folder — no Composer required on server
- **Personalized Welcome Message** - Chat now says "Start chatting with [Name]!"
- **Active Chat Hint** - After first exchange, welcome message changes to "Clear the chat to start a new topic."
- **Section Content Warning** - Warning displayed when enabling Include Section Content

### Files Added
- `vendor/` - Bundled smalot/pdfparser and symfony/polyfill-mbstring
- `composer.json`, `composer.lock`

### Files Modified
- `lib.php` - Added resource case to moochat_get_section_content() for PDF/PPTX/text extraction
- `classes/output/chat_interface.php` - Uses startchatwith string
- `lang/en/moochat.php` - Added startchatwith, chattingwith, include_section_content_warning
- `mod_form.php` - Added section content warning element
- `amd/src/chat.js` - Welcome message swap after first exchange
- `amd/build/chat.min.js` - Rebuilt
- `view.php` - Uses startchatwith string
- `version.php` - Bumped to v1.4.0

**Status:** ✓ COMPLETE

---

## Version 1.3.0 (2026-02-18)

### New Features
- **Conversation History Tracking** - Teachers can view complete student-AI conversation histories
  - All conversations automatically logged to database
  - History page with collapsible date sections
  - Student list shows message count and last message timestamp
  - Conversations deleted on activity delete or course reset
  - Backup/restore support with user data

### Files Added
- `classes/external/save_conversation.php`
- `history.php`

### Files Modified
- `db/install.xml` - Added moochat_conversations table
- `db/upgrade.php` - Added upgrade step for conversations table
- `db/services.php` - Registered mod_moochat_save_conversation
- `db/access.php` - Added mod/moochat:viewhistory capability
- `lang/en/moochat.php` - Added history strings
- `amd/src/chat.js` - Added conversation saving
- `amd/build/chat.min.js` - Rebuilt
- `view.php` - Added History button for teachers
- `lib.php` - Updated delete/reset for conversation data
- `styles.css` - History page styles
- `backup/moodle2/backup_moochat_stepslib.php` - Conversations backup
- `backup/moodle2/restore_moochat_stepslib.php` - Conversations restore
- `version.php` - Bumped to v1.3.0

**Status:** ✓ COMPLETE

---

## Version 1.2.5 (2025-11-13)

### Bug Fixes
- Fixed confusion between rate limiting and message limits
- Rate limiting now correctly counts conversations per day/hour
- Max messages per session correctly limits messages per conversation
- Clearing chat starts a new conversation (counts toward rate limit)
- Updated language strings for clarity

**Status:** ✓ COMPLETE

---

## Version 1.2.4 (2025-11-13)

### Bug Fixes
- Fixed missing language string 'missingidandcmid'
- Fixed JavaScript coding standards violations (125 errors corrected)
- Rebuilt JavaScript with Grunt

**Status:** ✓ COMPLETE

---

## Version 1.0.1 (2025-11-08)

### Bug Fixes
- Fixed deprecated print_error() usage → moodle_exception
- Added missing module viewed event implementation
- Fixed hard-coded language strings in JavaScript
- Updated Ajax to use Moodle External Services API
- Fixed backup/restore implementation
- Implemented Privacy Provider

**Status:** ✓ COMPLETE

---

## Version 1.0.0 (2025-10-30)

- Initial release
