==============================================================================
MooChat Activity Module for Moodle
==============================================================================

Author:       Brian A. Pool
Organization: National Trail Local Schools
Version:      1.7.0
License:      GNU GPL v3 or later
Moodle Required: 4.4 or higher

==============================================================================
DESCRIPTION
==============================================================================

MooChat is an AI-powered chat activity module that brings intelligent
conversation capabilities to your Moodle courses. Teachers can create
customizable AI assistants with unique personalities, avatars, and purposes —
from subject tutors to historical figures to fictional characters from
literature.

MooChat supports AI-based grading through learning objectives. Students chat
naturally without knowing the objectives, and the AI automatically awards
points as topics are covered. Only the student's best attempt is recorded in
the gradebook.

Teachers can upload course-specific content (PDF or text files) and restrict
the AI to only answer from that material — perfect for ensuring students
engage with assigned readings rather than relying on general AI knowledge.

==============================================================================
KEY FEATURES
==============================================================================

AI Chat:
- Custom AI Personalities — Define unique system prompts for each chatbot
- Flexible Display Modes — Inline on course page or separate activity page
- Avatar Support — Upload custom images with adjustable sizing (48-128px)
- Adjustable Chat Size — Small, Medium, or Large interface
- Message Formatting — Long responses formatted with paragraphs and bullets

Content Integration:
- Upload PDF or text files directly to the activity for AI reference
- "Only Use Uploaded Content" mode — AI restricted to uploaded files only
- Section Content Integration — AI can access course pages, books, glossaries,
  assignments, URLs, and labels from the current section
- PDF extraction via pdftotext or bundled smalot/pdfparser library

AI Grading with Learning Objectives:
- Teachers enter objectives (one per line) — students never see them
- Teacher provides a hint in the activity description to guide conversation
- AI evaluates only the most recent exchange — max 1 objective per reply
- Points revealed progressively as students discover topics through chat
- Session-based scoring — each "Clear Chat" starts fresh
- Only the student's best session score goes to the gradebook
- Full gradebook integration with teacher override capability

Conversation History (Teachers):
- Complete student-AI conversation logs organized by session
- Per-session score breakdown with best score highlighted
- Student list shows best score instead of email
- Expand/collapse individual sessions

Rate Limiting:
- Configurable question limits per hour or per day
- Server-side tracking — cannot be bypassed by clearing chat
- Auto-cleanup of usage records after 7 days

Privacy & Security:
- Full GDPR compliance with data export and deletion
- Backup/restore support including conversation history
- Teachers-only access to student conversations

==============================================================================
SYSTEM REQUIREMENTS
==============================================================================

- Moodle 4.4 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.2+ or PostgreSQL 9.6+
- Moodle Core AI Subsystem configured with at least one AI provider

RECOMMENDED:
- poppler-utils installed on server (apt install poppler-utils) for PDF support
  Falls back to bundled smalot/pdfparser if pdftotext is not available

IMPORTANT: If nginx sits in front of Apache, set client_max_body_size to at
least 10M in your nginx config to allow PDF uploads.

==============================================================================
INSTALLATION
==============================================================================

OPTION 1: Manual Installation
------------------------------
1. Download the plugin package
2. Extract contents to: [moodleroot]/mod/moochat
3. Navigate to: Site Administration > Notifications
4. Click "Upgrade Moodle database now"
5. Follow on-screen instructions

OPTION 2: Via Moodle Plugin Installer
--------------------------------------
1. Go to: Site Administration > Plugins > Install plugins
2. Upload the plugin ZIP file
3. Click "Install plugin from the ZIP file"
4. Follow on-screen instructions

POST-INSTALLATION:
------------------
1. Configure Moodle AI subsystem:
   Site Administration > AI > AI providers
2. Enable and configure at least one AI provider
3. Test with a sample activity

==============================================================================
CONFIGURATION
==============================================================================

ACTIVITY SETTINGS:

General:
- Chat Name — Name your AI assistant
- Introduction — Describe the activity purpose (shown to students as hint)
- Display Mode — Separate page or inline on course page
- Chat Interface Size — Small, Medium, or Large
- Include Section Content — Let AI access course section materials
- Avatar — Upload image and set display size
- System Prompt — Define AI personality and behavior

Course Content for AI (NEW in 1.7.0):
- Upload Content Files — Upload up to 5 PDF or .txt files
- Only Use Uploaded Content — When checked, AI answers ONLY from these files
  When unchecked, files are provided as reference alongside general knowledge

Learning Objectives & Grading:
- Objectives — One per line; students never see these directly
  Example:
    The student can name the three types of RAM sold today
    The student can explain what CL timing means
    The student can state the difference between DDR4 and DDR5
- Grade — Set maximum points (0 = ungraded)
- Use activity Introduction to give students a hint about what to discuss

Rate Limiting:
- Enable Rate Limiting — Prevent AI resource abuse
- Rate Limit Period — Per Hour or Per Day
- Maximum Questions — Questions allowed per period

Advanced:
- Maximum Messages per Session — Hard limit per conversation
- Temperature — AI creativity level (0.1 = focused, 0.9 = creative)

==============================================================================
HOW AI GRADING WORKS
==============================================================================

1. Teacher creates MooChat with a system prompt (e.g., "You are Friar Tuck")
2. Teacher enters learning objectives (e.g., "How many Merry Men are named?")
3. Teacher writes a hint in the Description (e.g., "Tell me about the Merry Men")
4. Students chat naturally — they never see the objectives
5. After each AI reply, the system evaluates only that exchange
6. If the specific fact required by an objective was explicitly stated,
   the objective is awarded — max 1 per exchange
7. The awarded objective appears in the student's sidebar with a notification
8. Student must keep chatting to earn remaining objectives
9. If student clears chat and starts over, it's a new session
10. Only the best session score goes to the gradebook

==============================================================================
CONVERSATION HISTORY (TEACHERS)
==============================================================================

Teachers with mod/moochat:viewhistory capability can:
- View student list with best score and message count
- Click "View Details" for full session-by-session breakdown
- See which objectives each student met and when
- See which session score is recorded in the gradebook
- Sessions are collapsible for easy navigation

==============================================================================
UPLOADED CONTENT (TEACHERS)
==============================================================================

To restrict the AI to specific course materials:
1. Edit the MooChat activity
2. Open "Course Content for AI" section
3. Upload PDF or .txt files (up to 5 files)
4. Check "Only Use Uploaded Content"
5. Save

The AI will now only answer from those files. If a student asks something
not covered, the AI will say "I don't have information about that in the
course materials."

Leave the checkbox unchecked to use files as reference material while
still allowing the AI to use general knowledge.

==============================================================================
TROUBLESHOOTING
==============================================================================

PDF Upload Fails / Times Out:
- If nginx is in front of Apache, add client_max_body_size 10M; to your
  nginx server block and reload nginx
- Check /etc/php/8.1/fpm/php.ini for upload_max_filesize and post_max_size
- Ensure max_execution_time is at least 300 in fpm/php.ini

AI Not Responding:
- Check Moodle AI subsystem configuration
- Verify at least one AI provider is enabled
- Check PHP error logs for API connection issues

Objectives Not Being Awarded:
- Only 1 objective is awarded per chat exchange by design
- The specific fact must be explicitly stated in the exchange
- Check that grade > 0 is set in the activity settings
- Try asking more specific questions that directly address the topic

Grades Not Updating:
- Grades only update when the new session score exceeds the previous best
- Check Site Administration > Grades for gradebook configuration
- Teachers can manually override grades in the gradebook

Section Content Not Loading:
- Verify Include Section Content is enabled
- Check content exists in the same section as MooChat
- Large sections may slow AI responses

==============================================================================
DATABASE TABLES
==============================================================================

[prefix]_moochat
- Activity instances and all configuration settings

[prefix]_moochat_usage
- Student usage tracking for rate limiting
- Auto-cleaned after 7 days

[prefix]_moochat_conversations
- Complete conversation history (all messages)
- Includes sessionid for session-based tracking
- Deleted on activity delete or course reset
- Included in backup/restore with user data

[prefix]_moochat_objective_results
- Per-session objective results
- Tracks which objectives were met in each session
- Used to calculate best-session grade

==============================================================================
CHANGELOG SUMMARY
==============================================================================

v1.7.0 (2026-04-16) — Course content upload; Only Use Uploaded Content mode
v1.6.0 (2026-04-16) — Session-based scoring; objectives revealed on discovery
v1.5.0 (2026-02-28) — AI grading with learning objectives; gradebook integration
v1.4.0 (2026-02-22) — PDF/PPTX support; personalized welcome message
v1.3.0 (2026-02-18) — Conversation history tracking for teachers
v1.2.5 (2025-11-13) — Rate limiting / message limit bug fixes
v1.2.4 (2025-11-13) — JavaScript coding standards fixes
v1.0.1 (2025-11-08) — Event implementation; privacy provider; Ajax upgrade
v1.0.0 (2025-10-30) — Initial release

==============================================================================
SUPPORT & DEVELOPMENT
==============================================================================

Author: Brian A. Pool
Organization: National Trail Local Schools

==============================================================================
LICENSE
==============================================================================

GNU General Public License v3 or later
https://www.gnu.org/licenses/

==============================================================================
