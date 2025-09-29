// backend/views/support/chat.php
<?php
use yii\helpers\Html;

$this->title = 'Support Chat - ' . $conversation->user->username;
?>

<div class="admin-chat-container">
    <div class="chat-header">
        <div class="user-info">
            <h4>Chat with <?= Html::encode($conversation->user->username) ?></h4>
            <span class="conversation-status status-<?= $conversation->status ?>">
                <?= ucfirst($conversation->status) ?>
            </span>
        </div>
        <div class="chat-actions">
            <span id="connection-status" class="status-indicator">Connecting...</span>
            <?= Html::a('Close Chat', ['close-conversation', 'id' => $conversation->id], [
                'class' => 'btn btn-outline-danger btn-sm',
                'data-confirm' => 'Are you sure you want to close this conversation?'
            ]) ?>
        </div>
    </div>

    <div id="chat-messages" class="chat-messages"></div>
    <div id="typing-indicator" class="typing-indicator"></div>

    <div class="chat-input">
        <?= Html::textarea('message', '', [
            'id' => 'message-input',
            'placeholder' => 'Type your response...',
            'rows' => 2
        ]) ?>
        <div class="input-actions">
            <?= Html::button('Send', ['id' => 'send-button', 'class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>

<style>
    .admin-chat-container {
        height: 80vh;
        display: flex;
        flex-direction: column;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: white;
    }

    .chat-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .user-info h4 {
        margin: 0;
        color: #333;
    }

    .conversation-status {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        margin-left: 10px;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-open {
        background: #d4edda;
        color: #155724;
    }

    .status-closed {
        background: #f8d7da;
        color: #721c24;
    }

    .chat-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .status-indicator {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-indicator.connected {
        background: #d4edda;
        color: #155724;
    }

    .status-indicator.disconnected {
        background: #f8d7da;
        color: #721c24;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8f9fa;
    }

    .message {
        margin-bottom: 15px;
        max-width: 70%;
    }

    .message.admin {
        margin-left: auto;
    }

    .message-bubble {
        padding: 12px 16px;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.4;
    }

    .message.admin .message-bubble {
        background: #007bff;
        color: white;
        border-bottom-right-radius: 5px;
    }

    .message.user .message-bubble {
        background: white;
        color: #333;
        border: 1px solid #ddd;
        border-bottom-left-radius: 5px;
    }

    .message-meta {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
        padding: 0 5px;
    }

    .message.admin .message-meta {
        text-align: right;
    }

    .message.user .message-meta {
        text-align: left;
    }

    .typing-indicator {
        padding: 10px 20px;
        font-style: italic;
        color: #666;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        min-height: 20px;
    }

    .chat-input {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        background: white;
    }

    #message-input {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        resize: vertical;
        min-height: 60px;
        max-height: 120px;
    }

    #message-input:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }

    .input-actions {
        margin-top: 10px;
        text-align: right;
    }

    #send-button {
        padding: 8px 25px;
    }
</style>

<script>
    class AdminSupportChat {
        constructor() {
            this.ws = null;
            this.userId = <?= Yii::$app->user->id ?>;
            this.username = '<?= Yii::$app->user->identity->username ?>';
            this.conversationId = <?= $conversation->id ?>;
            this.customerId = <?= $conversation->user_id ?>;
            this.typingTimer = null;
            this.isTyping = false;
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = 5;

            this.init();
        }

        init() {
            this.connect();
            this.bindEvents();
        }

        connect() {
            this.updateConnectionStatus('Connecting...');
            this.ws = new WebSocket('ws://localhost:8080');

            this.ws.onopen = () => {
                console.log('Connected to admin support chat');
                this.updateConnectionStatus('Connected', true);
                this.reconnectAttempts = 0;
                this.joinConversation();
            };

            this.ws.onmessage = (event) => {
                this.handleMessage(JSON.parse(event.data));
            };

            this.ws.onclose = () => {
                console.log('Disconnected from admin support chat');
                this.updateConnectionStatus('Disconnected', false);
                this.attemptReconnect();
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.updateConnectionStatus('Connection Error', false);
            };
        }

        attemptReconnect() {
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                setTimeout(() => {
                    console.log(`Admin attempting to reconnect... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
                    this.connect();
                }, 3000 * this.reconnectAttempts);
            }
        }

        updateConnectionStatus(text, connected = null) {
            const statusEl = document.getElementById('connection-status');
            statusEl.textContent = text;

            if (connected !== null) {
                statusEl.className = 'status-indicator ' + (connected ? 'connected' : 'disconnected');
            }
        }

        joinConversation() {
            this.send({
                type: 'join',
                user_id: this.userId,
                user_type: 'admin',
                conversation_id: this.conversationId
            });

            // Mark messages as read when joining
            setTimeout(() => {
                this.send({
                    type: 'mark_read'
                });
            }, 500);
        }

        send(data) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify(data));
            }
        }

        sendMessage(message) {
            if (message.trim()) {
                this.send({
                    type: 'message',
                    message: message.trim()
                });
            }
        }

        sendTyping(isTyping) {
            this.send({
                type: 'typing',
                is_typing: isTyping
            });
        }

        handleMessage(data) {
            switch (data.type) {
                case 'message':
                    this.displayMessage(data);
                    // Auto-mark as read if we're active
                    if (document.hasFocus() && data.sender_type === 'user') {
                        setTimeout(() => {
                            this.send({type: 'mark_read'});
                        }, 1000);
                    }
                    break;
                case 'typing':
                    this.displayTyping(data);
                    break;
                case 'user_status':
                    this.displayUserStatus(data);
                    break;
                case 'messages_read':
                    this.updateReadStatus(data);
                    break;
            }
        }

        displayMessage(data) {
            const messagesContainer = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            const isOwnMessage = data.sender_id == this.userId;

            messageDiv.className = 'message ' + (isOwnMessage ? 'admin' : 'user');

            messageDiv.innerHTML = `
            <div class="message-bubble">
                ${this.escapeHtml(data.message)}
            </div>
            <div class="message-meta">
                ${isOwnMessage ? 'You' : data.sender_name} • ${this.formatTime(data.created_at)}
                ${isOwnMessage ? (data.is_read ? ' • Read by customer' : ' • Sent') : ''}
            </div>
        `;

            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Play notification sound for customer messages
            if (!isOwnMessage) {
                this.playNotificationSound();
            }
        }

        displayTyping(data) {
            const typingIndicator = document.getElementById('typing-indicator');

            if (data.is_typing && data.sender_type === 'user') {
                typingIndicator.textContent = `${data.sender_name} is typing...`;
            } else {
                typingIndicator.textContent = '';
            }
        }

        displayUserStatus(data) {
            if (data.user_type === 'user') {
                // Could show customer online/offline status
                console.log(`Customer ${data.status === 'joined' ? 'joined' : 'left'} the chat`);
            }
        }

        updateReadStatus(data) {
            if (data.reader_type === 'user') {
                // Update UI to show messages were read by customer
                const messages = document.querySelectorAll('.message.admin .message-meta');
                messages.forEach(meta => {
                    if (meta.textContent.includes('• Sent')) {
                        meta.textContent = meta.textContent.replace('• Sent', '• Read by customer');
                    }
                });
            }
        }

        playNotificationSound() {
            // Simple notification sound
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSqJzPLe+v8PDxJ5gIMSRBVrX2LrQdPRb+FwlXFGWJSWIJRiG4CJIWKDOCUYjIDKzJlQTWNiUXJ2ZXNDVnNkRkBJYmNmRlNZRVFTVVRZcHN+VXp3c29nfIeCgXJ0gYeBhIiIo3Z8k4yGhWdIZlNSWGbCfYqjkJSFUV1aUHpZa0VwelN5cXFZcmx5eHd6WWphYkVZZEVVR1m6YZhqdV9kVnd5UGNlXWtYU3R3V1dmZGVjbfV8c26JZ35+dG4Y2nNzWF5mZ3GgqYl5coR8TnBlbnJRgUhUbkxqdYJJT4JJCIRQYVJMYYNRhoVTfT1fZmtZXk9iZXNNWl9nT45ZaVJdaG1FWFdnX1hTZ0tmWWxgVl8C');
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Could not play notification sound:', e));
        }

        bindEvents() {
            const messageInput = document.getElementById('message-input');
            const sendButton = document.getElementById('send-button');

            sendButton.addEventListener('click', () => {
                this.sendMessage(messageInput.value);
                messageInput.value = '';
                this.handleTyping(false);
                messageInput.focus();
            });

            messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage(messageInput.value);
                    messageInput.value = '';
                    this.handleTyping(false);
                } else {
                    this.handleTyping(true);
                }
            });

            messageInput.addEventListener('input', () => {
                this.handleTyping(messageInput.value.length > 0);
            });

            // Mark messages as read when window gains focus
            window.addEventListener('focus', () => {
                this.send({type: 'mark_read'});
            });

            // Auto-focus input
            messageInput.focus();
        }

        handleTyping(typing) {
            if (typing && !this.isTyping) {
                this.isTyping = true;
                this.sendTyping(true);
            }

            clearTimeout(this.typingTimer);
            this.typingTimer = setTimeout(() => {
                if (this.isTyping) {
                    this.isTyping = false;
                    this.sendTyping(false);
                }
            }, 2000);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        formatTime(timestamp) {
            return new Date(timestamp).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    // Initialize admin chat when page loads
    document.addEventListener('DOMContentLoaded', () => {
        new AdminSupportChat();
    });
</script>