<?php
/** @var yii\web\View $this */
/** @var common\models\ChatConversation $conversation */

use yii\helpers\Html;

$this->title = 'Admin Support Chat';
$this->params['breadcrumbs'][] = ['label' => 'Support Conversations', 'url' => ['support/conversations']];
$this->params['breadcrumbs'][] = $this->title;

$userLabel = $conversation->user ? $conversation->user->username : 'Unknown User';
?>
<div class="admin-support-chat-container">
    <div class="chat-header">
        <div class="chat-title">
            <h4><?= Html::encode($this->title) ?></h4>
            <div class="chat-subtitle">Talking with: <strong><?= Html::encode($userLabel) ?></strong> · Conversation #<?= Html::encode($conversation->id) ?></div>
        </div>
        <div class="chat-status">
            <span id="connection-status" class="status-indicator">Connecting...</span>
            <span id="user-status" class="participant-status"></span>
        </div>
    </div>

    <div id="chat-messages" class="chat-messages"></div>
    <div id="typing-indicator" class="typing-indicator"></div>

    <div class="chat-input">
        <?= Html::textInput('message', '', [
            'id' => 'message-input',
            'placeholder' => 'Type your message to the user...',
            'autocomplete' => 'off'
        ]) ?>
        <?= Html::button('Send', ['id' => 'send-button', 'class' => 'btn btn-primary']) ?>
    </div>
</div>

<style>
    .admin-support-chat-container {
        max-width: 900px;
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

    .chat-title h4 { margin: 0; color: #333; }
    .chat-subtitle { font-size: 12px; color: #666; }

    .status-indicator {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        margin-right: 8px;
    }
    .status-indicator.connected { background: #d4edda; color: #155724; }
    .status-indicator.disconnected { background: #f8d7da; color: #721c24; }

    .participant-status { font-size: 12px; color: #666; }

    .chat-messages { height: 480px; overflow-y: auto; padding: 15px; }

    .message { margin-bottom: 12px; max-width: 80%; display: flex; flex-direction: column; }
    .message .meta { font-size: 11px; color: #888; margin: 2px 6px; }

    .message.admin { margin-left: auto; }
    .message.user { margin-right: auto; }

    .message-bubble { padding: 10px 15px; border-radius: 18px; word-wrap: break-word; }

    /* In admin panel, admin messages on the right (primary), user messages on the left (light) */
    .message.admin .message-bubble { background: #0d6efd; color: white; border-bottom-right-radius: 5px; }
    .message.user .message-bubble { background: #f1f3f5; color: #333; border-bottom-left-radius: 5px; }

    .typing-indicator { height: 22px; padding: 0 15px 10px; font-size: 12px; color: #555; }

    .chat-input { display: flex; gap: 10px; padding: 12px; border-top: 1px solid #eee; background: #fff; }
    #message-input { flex: 1; border-radius: 20px; border: 1px solid #ddd; padding: 8px 14px; }
    #send-button { border-radius: 20px; padding: 8px 20px; }
</style>

<script>
    class AdminSupportChat {
        constructor() {
            this.ws = null;
            this.userId = <?= (int)Yii::$app->user->id ?>;
            this.username = '<?= Html::encode(Yii::$app->user->identity->username) ?>';
            this.conversationId = <?= (int)$conversation->id ?>;
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
                this.updateConnectionStatus('Connected', true);
                this.reconnectAttempts = 0;
                this.joinConversation();
            };

            this.ws.onmessage = (event) => {
                let data;
                try { data = JSON.parse(event.data); } catch (e) { return; }
                this.handleMessage(data);
            };

            this.ws.onclose = () => {
                this.updateConnectionStatus('Disconnected', false);
                this.attemptReconnect();
            };

            this.ws.onerror = () => {
                this.updateConnectionStatus('Connection Error', false);
            };
        }

        attemptReconnect() {
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                setTimeout(() => { this.connect(); }, 3000 * this.reconnectAttempts);
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

            // Mark user messages as read when opening
            this.send({ type: 'mark_read' });
        }

        send(payload) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify(payload));
            }
        }

        sendMessage(message) {
            if (message.trim()) {
                this.send({ type: 'message', message: message.trim() });
            }
        }

        sendTyping(isTyping) {
            this.send({ type: 'typing', is_typing: isTyping });
        }

        handleMessage(data) {
            switch (data.type) {
                case 'message':
                    this.displayMessage(data);
                    // After receiving a message from user, mark as read
                    if (data.sender_type !== 'admin') {
                        this.send({ type: 'mark_read' });
                    }
                    break;
                case 'typing':
                    this.displayTyping(data);
                    break;
                case 'user_status':
                    this.updateParticipantStatus(data);
                    break;
                case 'messages_read':
                    // Can be used to update UI if needed
                    break;
            }
        }

        displayMessage(data) {
            const container = document.getElementById('chat-messages');

            const wrapper = document.createElement('div');
            const whoClass = data.sender_type === 'admin' ? 'admin' : 'user';
            wrapper.className = 'message ' + whoClass;

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.textContent = data.message;

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = `${data.sender_name || (whoClass==='admin'?'You':'User')} · ${data.created_at || ''}`;

            wrapper.appendChild(bubble);
            wrapper.appendChild(meta);
            container.appendChild(wrapper);

            container.scrollTop = container.scrollHeight;
        }

        displayTyping(data) {
            const typingEl = document.getElementById('typing-indicator');
            if (data.is_typing && data.sender_type !== 'admin') {
                typingEl.textContent = (data.sender_name || 'User') + ' is typing...';
            } else {
                typingEl.textContent = '';
            }
        }

        updateParticipantStatus(data) {
            const el = document.getElementById('user-status');
            if (data.user_type !== 'admin') {
                el.textContent = `${data.username} ${data.status}`;
            }
        }

        bindEvents() {
            const input = document.getElementById('message-input');
            const sendBtn = document.getElementById('send-button');

            sendBtn.addEventListener('click', () => {
                this.sendMessage(input.value);
                input.value = '';
                this.sendTyping(false);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.sendMessage(input.value);
                    input.value = '';
                    this.sendTyping(false);
                }
            });

            input.addEventListener('input', () => {
                if (!this.isTyping) {
                    this.isTyping = true;
                    this.sendTyping(true);
                }
                clearTimeout(this.typingTimer);
                this.typingTimer = setTimeout(() => {
                    this.isTyping = false;
                    this.sendTyping(false);
                }, 1000);
            });

            window.addEventListener('focus', () => {
                this.send({ type: 'mark_read' });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        new AdminSupportChat();
    });
</script>
