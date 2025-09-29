<?php
namespace console\Services;

use common\enums\ChatRoomStatusType;
use common\enums\UserType;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use common\models\ChatConversation;
use common\models\ChatMessage;
use common\models\User;
use yii\db\Exception;

class ChatServerService implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected array $conversations;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->conversations = [];

        echo "[" . date('Y-m-d H:i:s') . "] ChatServer initialized\n";
    }

    public function start(int $port = 8080): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting WebSocket server on 0.0.0.0:$port\n";
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $port
        );
        $server->run();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "[" . date('Y-m-d H:i:s') . "] New connection! ({$conn->resourceId}) from {$conn->remoteAddress}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "[" . date('Y-m-d H:i:s') . "] Message from {$from->resourceId}: $msg\n";

        $data = json_decode($msg, true);

        if (!$data) {
            echo "[" . date('Y-m-d H:i:s') . "] Invalid JSON received from {$from->resourceId}\n";
            return;
        }

        try {
            switch ($data['type']) {
                case 'join':
                    $this->joinConversation($from, $data);
                    break;
                case 'message':
                    $this->handleMessage($from, $data);
                    break;
                case 'typing':
                    $this->handleTyping($from, $data);
                    break;
                case 'mark_read':
                    $this->markMessagesAsRead($from, $data);
                    break;
                default:
                    echo "[" . date('Y-m-d H:i:s') . "] Unknown message type: {$data['type']}\n";
            }
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error handling message: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * @throws Exception
     */
    private function joinConversation(ConnectionInterface $conn, $data): void
    {
        $userId = $data['user_id'];
        $userType = $data['user_type'] ?? 'user';
        $conversationId = $data['conversation_id'] ?? null;

        $conn->user_id = $userId;
        $conn->user_type = $userType;

        echo "[" . date('Y-m-d H:i:s') . "] User $userId ($userType) attempting to join conversation\n";

        if ($userType === 'user') {
            $conversation = ChatConversation::findOrCreateConversation($userId);
            $conn->conversation_id = $conversation->id;
        } else {
            $conn->conversation_id = $conversationId;
        }

        if (!isset($this->conversations[$conn->conversation_id])) {
            $this->conversations[$conn->conversation_id] = [];
        }

        $this->conversations[$conn->conversation_id][$conn->resourceId] = $conn;

        echo "[" . date('Y-m-d H:i:s') . "] User $userId joined conversation {$conn->conversation_id}\n";

        // Send recent messages
        $this->sendRecentMessages($conn);

        // Notify about user joining
        $this->broadcastUserStatus($conn, 'joined');
    }

    private function handleMessage(ConnectionInterface $from, $data): void
    {
        if (!isset($from->conversation_id)) {
            echo "[" . date('Y-m-d H:i:s') . "] User {$from->resourceId} not in a conversation\n";
            return;
        }

        $conversation = ChatConversation::findOne($from->conversation_id);
        if (!$conversation) {
            echo "[" . date('Y-m-d H:i:s') . "] Conversation {$from->conversation_id} not found\n";
            return;
        }

        $senderType = $from->user_type === 'admin' ? UserType::ADMIN->value : UserType::USER->value;

        if ($senderType === UserType::ADMIN->value && !$conversation->admin_id) {
            $conversation->admin_id = $from->user_id;
            $conversation->status = ChatRoomStatusType::STATUS_ACTIVE->value;
            $conversation->save();
        }

        // Save message
        $message = new ChatMessage();
        $message->conversation_id = $from->conversation_id;
        $message->sender_id = $from->user_id;
        $message->sender_type = $senderType;
        $message->message = $data['message'];

        if (!$message->save()) {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to save message: " . json_encode($message->errors) . "\n";
            return;
        }

        $user = User::findOne($from->user_id);

        $broadcastData = [
            'type' => 'message',
            'id' => $message->id,
            'conversation_id' => $message->conversation_id, // keep client key for compatibility
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'sender_name' => $user ? $user->username : 'Unknown',
            'message' => $message->message,
            'created_at' => date('Y-m-d H:i:s', $message->created_at),
            'is_read' => false
        ];

        $this->broadcastToConversation($from->conversation_id, json_encode($broadcastData));
        echo "[" . date('Y-m-d H:i:s') . "] Message saved and broadcasted\n";
    }

    private function handleTyping(ConnectionInterface $from, $data): void
    {
        if (!isset($from->conversation_id)) return;

        $user = User::findOne($from->user_id);

        $typingData = [
            'type' => 'typing',
            'conversation_id' => $from->conversation_id,
            'sender_id' => $from->user_id,
            'sender_type' => $from->user_type,
            'sender_name' => $user ? $user->username : 'Unknown',
            'is_typing' => $data['is_typing']
        ];

        $this->broadcastToConversation($from->conversation_id, json_encode($typingData), $from);
    }

    private function markMessagesAsRead(ConnectionInterface $from, $data): void
    {
        if (!isset($from->conversation_id)) return;

        $conversation = ChatConversation::findOne($from->conversation_id);
        if (!$conversation) return;

        $senderType = $from->user_type === 'admin' ? UserType::USER->value : UserType::ADMIN->value;

        ChatMessage::updateAll(
            ['is_read' => true],
            [
                'conversation_id' => $from->conversation_id,
                'sender_type' => $senderType,
                'is_read' => false
            ]
        );

        $readData = [
            'type' => 'messages_read',
            'conversation_id' => $from->conversation_id,
            'reader_type' => $from->user_type
        ];

        $this->broadcastToConversation($from->conversation_id, json_encode($readData));
    }

    private function sendRecentMessages(ConnectionInterface $conn): void
    {
        if (!isset($conn->conversation_id)) return;

        $messages = ChatMessage::find()
            ->where(['conversation_id' => $conn->conversation_id])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();

        $messages = array_reverse($messages);

        foreach ($messages as $message) {
            $user = User::findOne($message->sender_id);
            $data = [
                'type' => 'message',
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'sender_name' => $user ? $user->username : 'Unknown',
                'message' => $message->message,
                'created_at' => date('Y-m-d H:i:s', $message->created_at),
                'is_read' => $message->is_read
            ];

            $conn->send(json_encode($data));
        }
    }

    private function broadcastToConversation($conversationId, $message, $exclude = null): void
    {
        if (!isset($this->conversations[$conversationId])) return;

        foreach ($this->conversations[$conversationId] as $client) {
            if ($exclude && $client === $exclude) continue;
            try {
                $client->send($message);
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to send to client: " . $e->getMessage() . "\n";
            }
        }
    }

    private function broadcastUserStatus(ConnectionInterface $conn, $status): void
    {
        if (!isset($conn->user_id) || !isset($conn->conversation_id)) return;

        $user = User::findOne($conn->user_id);

        $statusData = [
            'type' => 'user_status',
            'conversation_id' => $conn->conversation_id,
            'user_id' => $conn->user_id,
            'user_type' => $conn->user_type,
            'username' => $user ? $user->username : 'Unknown',
            'status' => $status
        ];

        $this->broadcastToConversation($conn->conversation_id, json_encode($statusData), $conn);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if (isset($conn->conversation_id) && isset($this->conversations[$conn->conversation_id][$conn->resourceId])) {
            $this->broadcastUserStatus($conn, 'left');
            unset($this->conversations[$conn->conversation_id][$conn->resourceId]);
        }

        $this->clients->detach($conn);
        echo "[" . date('Y-m-d H:i:s') . "] Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Error on connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }
}