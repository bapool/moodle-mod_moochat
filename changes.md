# MooChat Plugin - Changes for Moodle.org Submission

## Version 1.4.0 (2026-02-22)

### New Features
- **PDF and PPTX File Support** - MooChat can now read the content of PDF and PowerPoint (.pptx) files uploaded as File resources in the course section
  - PDF text extraction uses `pdftotext` (poppler-utils) if available on the server, with automatic fallback to the bundled smalot/pdfparser library
  - PPTX text extraction uses PHP's built-in ZipArchive and DOMDocument — no external dependencies required
  - Plain text files (txt, csv, html) are also extracted
  - The smalot/pdfparser library is bundled directly in the plugin's `vendor/` folder — no Composer required on the server
- **Personalized Welcome Message** - The chat welcome message now says "Start chatting with [Name]!" using the actual name of the MooChat instance, making the interface more personal
- **Active Chat Hint** - After the first message exchange, the welcome message changes to "Clear the chat to start a new topic." to guide students
- **Section Content Warning** - When enabling "Include Section Content" in the activity settings, a warning is now displayed: "Sections with excessive or varied content can result in slower and lower quality responses."

### Files Added
- `vendor/` - Bundled smalot/pdfparser library and dependencies (symfony/polyfill-mbstring) for PDF text extraction
- `composer.json` - Composer configuration for bundled library
- `composer.lock` - Composer lock file

### Files Modified
- `lib.php` - Added `resource` case to `moochat_get_section_content()` to extract text from PDF, PPTX, and plain text file resources; added `$CFG` to global variables
- `classes/output/chat_interface.php` - Changed `startchat` to use new `startchatwith` string with the moochat name
- `lang/en/moochat.php` - Added `startchatwith`, `chattingwith`, and `include_section_content_warning` strings
- `mod_form.php` - Added warning static element after Include Section Content checkbox
- `amd/src/chat.js` - Added `chattingwith` string; welcome message swaps to hint after first exchange; resets on clear
- `amd/build/chat.min.js` - Rebuilt with updated JS
- `view.php` - Fixed hardcoded `startchat` string to use new `startchatwith` with moochat name
- `version.php` - Bumped to 2026022201 / v1.4.0

### Notes
- PDF support works best with `pdftotext` installed on the server (`apt install poppler-utils`)
- If `pdftotext` is not available, the bundled smalot/pdfparser library is used as a fallback
- Image-only PDFs (scanned documents without OCR) cannot be read by either method
- Only `.pptx` files are supported; older `.ppt` (binary format) files are not supported and should be converted to .pptx before uploading

**Status:** ✓ COMPLETE

---

## Version 1.3.0 (2026-02-18)

### New Features
- **Conversation History Tracking** - Teachers can now view complete conversation histories between students and the AI
  - All student-AI conversations are automatically logged to the database
  - New `moochat_conversations` table stores individual messages with role (user/assistant)
  - History page displays conversations organized by date with collapsible sections
  - "History" button added to activity sidebar (visible to teachers only)
  - Student list shows total message count and last message timestamp
  - Conversations are automatically deleted when activities are deleted or courses are reset
  - Backup/restore includes conversation history when "Include user data" is selected
  
### Files Added
- `classes/external/save_conversation.php` - External web service to save conversations
- `history.php` - Teacher interface to view student conversation history

### Files Modified
- `db/install.xml` - Added `moochat_conversations` table definition
- `db/upgrade.php` - Added upgrade step to create conversations table
- `db/services.php` - Registered `mod_moochat_save_conversation` web service
- `db/access.php` - Added `mod/moochat:viewhistory` capability for teachers
- `lang/en/moochat.php` - Added language strings for history feature
- `amd/src/chat.js` - Added Ajax call to save conversations after each message
- `amd/build/chat.min.js` - Rebuilt with conversation saving functionality
- `view.php` - Added "History" link for teachers in sidebar
- `lib.php` - Updated delete and reset functions to remove conversation data
- `styles.css` - Added styling for history page and collapsible conversation display
- `backup/moodle2/backup_moochat_stepslib.php` - Added conversations to backup
- `backup/moodle2/restore_moochat_stepslib.php` - Added conversations to restore
- `version.php` - Bumped to 2026021801 / v1.3.0

### Database Changes
- New table: `moochat_conversations`
  - Fields: id, moochatid, userid, role, message, timecreated
  - Indexes: moochatid-userid, timecreated
  - Foreign keys: moochatid → moochat.id, userid → user.id

### Privacy Compliance
- Conversation data is included in user data export
- Conversations are deleted when users request data deletion
- History feature respects user privacy - only teachers with appropriate capability can view

**Status:** ✓ COMPLETE

---

## Version 1.2.5 (2025-11-13)

### Bug Fixes
- Fixed confusion between rate limiting and message limits
  - Rate limiting now correctly counts **conversations per day/hour** (not individual messages)
  - Max messages per session now correctly limits **messages per conversation**
  - Both limits now display separately and clearly:
    - "Conversations remaining today: X"
    - "Messages remaining in this conversation: X"
  - Clearing chat now starts a new conversation (counts toward rate limit)
  - Continuing an existing conversation does not count toward rate limit
- Updated language strings for clarity:
  - Changed "Questions remaining" to "Conversations remaining today"
  - Added "Messages remaining in this conversation"
- Fixed message limit error dialog:
  - Changed title from "Error" to "Chat Limit Reached"
  - Updated message to be more helpful and instructive

**Status:** ✓ COMPLETE

---

## Version 1.2.4 (2025-11-13)

### Bug Fixes
- Fixed missing language string 'missingidandcmid' causing validation errors
- Fixed JavaScript coding standards violations (125 errors corrected)
  - Removed tab characters, replaced with spaces
  - Removed unused variables (messageCount, ex parameter)
  - Fixed @package JSDoc tag formatting
  - Fixed promise return value warning
  - Added eslint-disable comment for necessary confirm() usage
- Rebuilt JavaScript with Grunt - all files now properly minified
- All AMD build files now meet Moodle.org submission requirements

**Status:** ✓ COMPLETE

---

## Version: 1.0.1
Date: 2025-11-08

---

## Issue #3: Undefined function usage (FIXED)

**Problem:** Plugin was using deprecated `print_error()` function which is not defined in newer Moodle versions.

**File Changed:** `view.php` (line 19)

**Change Made:**
- **Before:** `print_error('missingidandcmid', 'moochat');`
- **After:** `throw new moodle_exception('missingidandcmid', 'moochat');`

**Reason:** The `print_error()` function is deprecated in Moodle 4.x. The correct method is to throw a `moodle_exception` which provides the same functionality with better error handling.

**Status:** ✓ COMPLETE

---
## Issue #6: Missing module events implementation (FIXED)

**Problem:** Plugin was missing the required module viewed event implementation for proper activity logging and completion tracking.

**Files Changed:**
1. `classes/event/course_module_viewed.php` (NEW FILE)
2. `view.php` (lines 27-34)

**Changes Made:**

1. **Created custom event class** (`classes/event/course_module_viewed.php`):
   - Extended `\core\event\course_module_viewed` abstract class
   - Implemented required methods: `init()`, `get_description()`, `validate_data()`, and `get_objectid_mapping()`
   - Set proper object table, CRUD operation, and education level

2. **Updated view.php**:
   - Uncommented and corrected the event trigger code
   - Changed from `\core\event\course_module_viewed` to `\mod_moochat\event\course_module_viewed`
   - Event now properly logs when users view the moochat activity

**Reason:** Moodle requires activity modules to trigger events for proper logging, analytics, reporting, and activity completion tracking. The abstract `\core\event\course_module_viewed` class cannot be instantiated directly; modules must create their own implementation.

**Status:** ✓ COMPLETE

---
## Issue #5: Hard-coded language strings (FIXED)

**Problem:** JavaScript file contained hard-coded English strings that should be defined in the language file for proper multilingual support and internationalization.

**Files Changed:**
1. `lang/en/moochat.php` - Added 7 new language strings
2. `amd/src/chat.js` - Updated to use language strings
3. `amd/build/chat.min.js` - Rebuilt minified version

**Changes Made:**

1. **Added new language strings** to `lang/en/moochat.php`:
   - `questionsremaining_js` - "Questions remaining"
   - `thinking_js` - "Thinking..."
   - `ratelimitreached_title` - "Rate Limit Reached"
   - `error_title` - "Error"
   - `connectionerror` - "Failed to connect to AI service"
   - `chatcleared` - "Chat cleared. Start a new conversation!"
   - `confirmclear` - "Clear all messages? (Your question limit will not reset)"

2. **Updated JavaScript** (`amd/src/chat.js`):
   - Added `core/str` module to imports
   - Loaded all language strings using `Str.get_strings()` at initialization
   - Replaced all 7 hard-coded strings with references to loaded strings array
   - Lines changed: 3, 11-23, 21, 57, 79, 84, 120, 192, 208

3. **Rebuilt minified JavaScript** (`amd/build/chat.min.js`)

**Reason:** All user-facing strings must be defined in language files to support proper internationalization and allow translation into other languages. This is a Moodle coding standard requirement.

**Status:** ✓ COMPLETE

---
## Issue #4: Update Ajax implementation to External Services (FIXED)

**Problem:** Plugin was using direct jQuery $.ajax() calls to a custom PHP script (chat_service.php), which bypasses Moodle's security, session handling, and web services framework. This is not the recommended approach for Moodle 4.x plugins.

**Files Changed:**
1. `classes/external/send_message.php` (NEW FILE)
2. `db/services.php` (NEW FILE)
3. `amd/src/chat.js` - Converted from $.ajax to core/ajax
4. `amd/build/chat.min.js` - Rebuilt
5. `version.php` - Bumped to 2025103005 / v1.2

**Status:** ✓ COMPLETE

---
## Issue #1: Missing Backup/Restore API implementation (FIXED)

**Status:** ✓ COMPLETE

---
## Issue #9: The Privacy Provider is not implemented (FIXED)

**Status:** ✓ COMPLETE

---
## Issue #8: Transition to Templates and Output API (FIXED)

**Status:** ✓ COMPLETE

---
## Version 1.2.3 (2025-11-10)

### Bug Fixes
- Fixed backup and restore functionality failing with status code 500
- Added missing 'model' and 'avatarurl' fields to backup structure
- Backup and restore now correctly preserves all moochat activity settings

**Status:** ✓ COMPLETE
