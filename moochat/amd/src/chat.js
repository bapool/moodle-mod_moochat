// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * MooChat JavaScript module
 * @package
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    return {
        init: function(moochatid) {

            var conversationHistory = [];
            var remainingQuestions = -1; // -1 means unlimited

            // Load language strings
            var strings = [];
            Str.get_strings([
                {key: 'questionsremaining_js', component: 'mod_moochat'},
                {key: 'thinking_js', component: 'mod_moochat'},
                {key: 'ratelimitreached_title', component: 'mod_moochat'},
                {key: 'error_title', component: 'mod_moochat'},
                {key: 'connectionerror', component: 'mod_moochat'},
                {key: 'chatcleared', component: 'mod_moochat'},
                {key: 'confirmclear', component: 'mod_moochat'}
            ]).done(function(s) {
                strings = s;
            });

            var messagesDiv = $('#moochat-messages-' + moochatid);
            var inputField = $('#moochat-input-' + moochatid);
            var sendButton = $('#moochat-send-' + moochatid);
            var clearButton = $('#moochat-clear-' + moochatid);
            var remainingDiv = $('#moochat-remaining-' + moochatid);

            // Update remaining questions display
            var updateRemaining = function(remaining) {
                if (remaining >= 0) {
                    var message = strings[0] + ': ' + remaining;
                    remainingDiv.html('<div class="alert alert-info">' + message + '</div>');
                    remainingDiv.show();
                } else {
                    remainingDiv.hide();
                }
            };

            // Send message
            var sendMessage = function() {
                var message = inputField.val().trim();

                if (message === '') {
                    return;
                }

                // Disable input while processing
                inputField.prop('disabled', true);
                sendButton.prop('disabled', true);

                // Add user message to display
                addMessage('user', message);

                // Add to history
                conversationHistory.push({
                    role: 'user',
                    content: message
                });

                // Clear input
                inputField.val('');

                // Show thinking indicator
                var thinkingId = 'thinking-' + Date.now();
                messagesDiv.append(
                    '<div class="moochat-message moochat-assistant" id="' + thinkingId + '">' +
                    '<em>' + strings[1] + '</em></div>'
                );
                scrollToBottom();

                // Call API using Moodle's Ajax module
                Ajax.call([{
                    methodname: 'mod_moochat_send_message',
                    args: {
                        moochatid: moochatid,
                        message: message,
                        history: JSON.stringify(conversationHistory)
                    }
                }])[0].then(function(response) {
                    // Remove thinking indicator
                    $('#' + thinkingId).remove();

                    if (response.error || !response.success) {
                        // Check if this is a rate limit error
                        if (response.remaining !== undefined && response.remaining === 0) {
                            Notification.alert(strings[2], response.error, 'OK');
                            inputField.prop('disabled', true);
                            sendButton.prop('disabled', true);
                            updateRemaining(0);
                        } else {
                            Notification.alert(strings[3], response.error, 'OK');
                        }
                    } else if (response.success && response.reply) {
                        // Add assistant reply
                        addMessage('assistant', response.reply);

                        // Add to history
                        conversationHistory.push({
                            role: 'assistant',
                            content: response.reply
                        });

                        // Update remaining questions
                        if (response.remaining !== undefined) {
                            remainingQuestions = response.remaining;
                            updateRemaining(remainingQuestions);

                            // Disable if no questions left
                            if (remainingQuestions === 0) {
                                inputField.prop('disabled', true);
                                sendButton.prop('disabled', true);
                            }
                        }
                    }

                    // Re-enable input (unless disabled by rate limit)
                    if (remainingQuestions !== 0) {
                        inputField.prop('disabled', false);
                        sendButton.prop('disabled', false);
                        inputField.focus();
                    }
                }).catch(function() {
                    $('#' + thinkingId).remove();
                    Notification.alert(strings[3], strings[4], 'OK');
                    inputField.prop('disabled', false);
                    sendButton.prop('disabled', false);
                });
            };

            // Add message to display
            var addMessage = function(role, content) {
                var messageClass = role === 'user' ? 'moochat-user' : 'moochat-assistant';
                var formattedContent = formatMessage(content);
                var messageHtml = '<div class="moochat-message ' + messageClass + '">' +
                                 formattedContent + '</div>';
                messagesDiv.append(messageHtml);
                scrollToBottom();
            };

            // Format message content with line breaks and basic formatting
            var formatMessage = function(text) {
                // Escape HTML first
                var escaped = escapeHtml(text);

                // Only format if it's a longer response (more than 100 chars or has multiple sentences)
                var sentenceCount = (text.match(/[.!?]+/g) || []).length;

                if (text.length < 100 && sentenceCount <= 3) {
                    // Short response - return as-is
                    return escaped;
                }

                // Handle markdown bold (**text** or __text__)
                escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/__([^_]+)__/g, '<strong>$1</strong>');

                // Handle markdown italic (*text* or _text_) - but not bullet points
                escaped = escaped.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');

                // Convert double line breaks to paragraphs
                escaped = escaped.replace(/\n\n+/g, '</p><p>');

                // Convert single line breaks to <br>
                escaped = escaped.replace(/\n/g, '<br>');

                // Wrap in paragraph tags
                escaped = '<p>' + escaped + '</p>';

                // Handle numbered lists (1. 2. 3.)
                escaped = escaped.replace(/(\d+)\.\s/g, '<br><strong>$1.</strong> ');

                // Handle bullet points at start of line (- or * followed by space)
                escaped = escaped.replace(/<br>[-*]\s+/g, '<br>• ');
                escaped = escaped.replace(/<p>[-*]\s+/g, '<p>• ');

                return escaped;
            };

            // Scroll to bottom of messages
            var scrollToBottom = function() {
                messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
            };

            // Escape HTML
            var escapeHtml = function(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };

            // Clear chat (visual only - doesn't reset server counter)
            var clearChat = function() {
                conversationHistory = [];
                messagesDiv.html('<p class="moochat-welcome">' + strings[5] + '</p>');
                inputField.val('').focus();
                // Note: remaining questions counter stays the same
            };

            // Event handlers
            sendButton.on('click', sendMessage);

            inputField.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            clearButton.on('click', function() {
                if (confirm(strings[6])) {
                    clearChat();
                }
            });

            // Focus input on load
            inputField.focus();
        }
    };
});
