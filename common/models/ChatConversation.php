<?php

namespace common\models;

use common\enums\ChatRoomStatusType;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Exception;
use yii\db\T;

/**
 * ChatConversation model
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $admin_id
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ChatMessage[] $chatMessages
 * @property User $user
 * @property-read ActiveQuery $latestMessage
 * @property-read null|bool|string|int $unreadMessagesCount
 * @property User $admin
 */
class ChatConversation extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'chat_room';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'admin_id'], 'integer'],
            [['status'], 'string', 'max' => 20],
            [['status'], 'in', 'range' => ChatRoomStatusType::values()],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'admin_id' => 'Admin ID',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[ChatMessages]].
     */
    public function getChatMessages(): ActiveQuery
    {
        return $this->hasMany(ChatMessage::class, ['chat_room_id' => 'id'])
            ->orderBy(['created_at' => SORT_ASC]);
    }

    /**
     * Gets query for [[User]].
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[Admin]].
     */
    public function getAdmin(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'admin_id']);
    }

    /**
     * Get the latest message in the chat room
     */
    public function getLatestMessage(): ActiveQuery
    {
        return $this->hasOne(ChatMessage::class, ['chat_room_id' => 'id'])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    /**
     * Get unread messages count for admin
     */
    public function getUnreadMessagesCount(): bool|int|string|null
    {
        return $this->hasMany(ChatMessage::class, ['chat_room_id' => 'id'])
            ->where(['is_read' => false, 'sender_type' => 'user'])
            ->count();
    }

    /**
     * Find or create chat room for user
     * @throws Exception
     */
    public static function findOrCreateConversation($userId): ChatConversation|ActiveRecord|null
    {
        $chatRoom = self::find()
            ->where(['user_id' => $userId, 'status' => ChatRoomStatusType::STATUS_ACTIVE->value])
            ->one();

        if (!$chatRoom) {
            $chatRoom = new self();
            $chatRoom->user_id = $userId;
            $chatRoom->status = ChatRoomStatusType::STATUS_ACTIVE->value;
            $chatRoom->save();
        }

        return $chatRoom;
    }
}