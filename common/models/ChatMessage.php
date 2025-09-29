<?php

namespace common\models;

use common\enums\ChatRoomStatusType;
use common\enums\UserType;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;


/**
 * ChatMessage model
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $sender_type
 * @property string $message
 * @property bool $is_read
 * @property string $created_at
 *
 * @property ChatConversation $chatRoom
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
            [['conversation_id', 'sender_id', 'sender_type', 'message'], 'required'],
            [['conversation_id', 'sender_id'], 'integer'],
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
            'conversation_id' => 'Conversation ID',
            'sender_id' => 'Sender ID',
            'sender_type' => 'Sender Type',
            'message' => 'Message',
            'is_read' => 'Is Read',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[ChatConversation]].
     */
    public function getChatRoom(): ActiveQuery
    {
        return $this->hasOne(ChatConversation::class, ['id' => 'conversation_id']);
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
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            $this->chatRoom->touch('updated_at');

            if ($this->sender_type === UserType::USER->value && !$this->chatRoom->admin_id) {
                $this->chatRoom->status = ChatRoomStatusType::STATUS_PENDING;
                $this->chatRoom->save();
            } elseif ($this->sender_type === UserType::ADMIN->value && $this->chatRoom->status === ChatRoomStatusType::STATUS_PENDING->value) {
                $this->chatRoom->status = ChatRoomStatusType::STATUS_ACTIVE->value;
                $this->chatRoom->save();
            }
        }
    }
}