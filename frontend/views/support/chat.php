<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Support Chat';

try {
    $conversation = \common\models\ChatConversation::findOrCreateConversation(Yii::$app->user->id);
} catch (\yii\db\Exception $e) {
    dd($e);
}
?>

<div class="support-chat-container">
    <div class="chat-header">
        <h4>Support Chat</h4>
        <span id="connection-status" class="status-indicator">Connecting...</span>
        <span id="admin-status" class="admin-status"></span>
    </div>

    <div id="chat-messages" class="chat-messages"></div>
    <div id="typing-indicator" class="typing-indicator"></div>

    <div class="chat-input">
        <?= Html::textInput('message', '', [
            'id' => 'message-input',
            'placeholder' => 'Type your message to support...',
            'autocomplete' => 'off'
        ]) ?>
        <?= Html::button('Send', ['id' => 'send-button', 'class' => 'btn btn-primary']) ?>
    </div>
</div>

<style>
    .support-chat-container {
        max-width: 600px;
        margin: 20px auto;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .chat-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-header h4 {
        margin: 0;
        color: #333;
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

    .admin-status {
        font-size: 12px;
        color: #666;
    }

    .chat-messages {
        height: 400px;
        overflow-y: auto;
        padding: 15px;
    }

    .message {
        margin-bottom: 15px;
        max-width: 80%;
    }

    .message.user {
        margin-left: auto;
    }

    .message-bubble {
        padding: 10px 15px;
        border-radius: 18px;
        word-wrap: break-word;
    }

    .message.user .message-bubble {
        background: #007bff;
        color: white;
        border-bottom-right-radius: 5px;
    }

    .message.admin .message-bubble {
        background: #f1f3f5;
        color: #333;
        border-bottom-left-radius: 5px;
    }

    .message-meta {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
        text-align: right;
    }

    .message.admin .message-meta {
        text-align: left;
    }

    .typing-indicator {
        padding: 10px 15px;
        font-style: italic;
        color: #666;
        min-height: 20px;
    }

    .chat-input {
        padding: 15px;
        border-top: 1px solid #eee;
        display: flex;
        gap: 10px;
    }

    #message-input {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
    }

    #message-input:focus {
        border-color: #007bff;
    }

    #send-button {
        border-radius: 20px;
        padding: 8px 20px;
    }
</style>

<script>
    class SupportChat {
        constructor() {
            this.ws = null;
            this.userId = <?= Yii::$app->user->id ?>;
            this.username = '<?= Yii::$app->user->identity->username ?>';
            this.conversationId = <?= $conversation->id ?>;
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
            this.ws = new WebSocket('ws://localhost:8080','echo-protocol');

            this.ws.onopen = () => {
                console.log('Connected to support chat');
                this.updateConnectionStatus('Connected', true);
                this.reconnectAttempts = 0;
                this.joinConversation();
            };

            this.ws.onmessage = (event) => {
                this.handleMessage(JSON.parse(event.data));
            };

            this.ws.onclose = () => {
                console.log('Disconnected from support chat');
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
                    console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
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
                user_type: 'user',
                conversation_id: this.conversationId
            });

            // Mark messages as read when joining
            this.send({
                type: 'mark_read'
            });
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
                    // Mark as read if we're active
                    if (document.hasFocus()) {
                        this.send({type: 'mark_read'});
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

            messageDiv.className = 'message ' + (isOwnMessage ? 'user' : 'admin');

            messageDiv.innerHTML = `
            <div class="message-bubble">
                ${this.escapeHtml(data.message)}
            </div>
            <div class="message-meta">
                ${isOwnMessage ? 'You' : 'Support'} • ${this.formatTime(data.created_at)}
                ${isOwnMessage ? (data.is_read ? ' • Read' : ' • Sent') : ''}
            </div>
        `;

            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        displayTyping(data) {
            const typingIndicator = document.getElementById('typing-indicator');

            if (data.is_typing && data.sender_id != this.userId) {
                typingIndicator.textContent = 'Support is typing...';
            } else {
                typingIndicator.textContent = '';
            }
        }

        displayUserStatus(data) {
            const adminStatus = document.getElementById('admin-status');

            if (data.user_type === 'admin') {
                if (data.status === 'joined') {
                    adminStatus.textContent = 'Support agent is online';
                    adminStatus.style.color = '#28a745';
                } else if (data.status === 'left') {
                    adminStatus.textContent = 'Support agent is offline';
                    adminStatus.style.color = '#6c757d';
                }
            }
        }

        updateReadStatus(data) {
            if (data.reader_type === 'admin') {
                // Update UI to show messages were read by admin
                const messages = document.querySelectorAll('.message.user .message-meta');
                messages.forEach(meta => {
                    if (meta.textContent.includes('• Sent')) {
                        meta.textContent = meta.textContent.replace('• Sent', '• Read');
                    }
                });
            }
        }

        bindEvents() {
            const messageInput = document.getElementById('message-input');
            const sendButton = document.getElementById('send-button');

            sendButton.addEventListener('click', () => {
                this.sendMessage(messageInput.value);
                messageInput.value = '';
                this.handleTyping(false);
            });

            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
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
            }, 1000);
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

    // Initialize chat when page loads
    document.addEventListener('DOMContentLoaded', () => {
        new SupportChat();
    });
</script>