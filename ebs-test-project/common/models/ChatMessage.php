<?php

namespace common\models;

use common\enums\UserType;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;


/**
 * ChatMessage model
 *
 * @property int $id
 * @property int $chat_room_id
 * @property int $sender_id
 * @property string $sender_type
 * @property string $message
 * @property bool $is_read
 * @property string $created_at
 *
 * @property ChatRoom $chatRoom
 * @property User $sender
 */
class ChatMessage extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'chat_messages';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['chat_room_id', 'sender_id', 'sender_type', 'message'], 'required'],
            [['chat_room_id', 'sender_id'], 'integer'],
            [['message'], 'string'],
            [['is_read'], 'boolean'],
            [['sender_type'], 'string', 'max' => 10],
            [['sender_type'], 'in', 'range' => [UserType::USER->value,UserType::ADMIN->value]],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'chat_room_id' => 'Chat Room ID',
            'sender_id' => 'Sender ID',
            'sender_type' => 'Sender Type',
            'message' => 'Message',
            'is_read' => 'Is Read',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[ChatRoom]].
     */
    public function getChatRoom(): ActiveQuery
    {
        return $this->hasOne(ChatRoom::class, ['id' => 'chat_room_id']);
    }

    /**
     * Gets query for [[Sender]].
     */
    public function getSender(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'sender_id']);
    }

    /**
     * Mark message as read
     * @throws Exception
     */
    public function markAsRead(): bool
    {
        $this->is_read = true;
        return $this->save(false);
    }
}