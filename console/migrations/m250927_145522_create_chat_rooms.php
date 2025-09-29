<?php

use yii\db\Migration;

class m250927_145522_create_chat_rooms extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // Conversations table
        $this->createTable('chat_conversations', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'admin_id' => $this->integer()->null(),
            'status' => $this->string(20)->defaultValue('open'), // open, closed, pending
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // Messages table
        $this->createTable('chat_messages', [
            'id' => $this->primaryKey(),
            'conversation_id' => $this->integer()->notNull(),
            'sender_id' => $this->integer()->notNull(),
            'sender_type' => $this->string(10)->notNull(), // 'user' or 'admin'
            'message' => $this->text()->notNull(),
            'is_read' => $this->boolean()->defaultValue(false),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // Foreign keys
        $this->addForeignKey(
            'fk-chat_conversations-user_id',
            'chat_conversations',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-chat_conversations-admin_id',
            'chat_conversations',
            'admin_id',
            'user',
            'id',
            'SET NULL'
        );

        $this->addForeignKey(
            'fk-chat_messages-conversation_id',
            'chat_messages',
            'conversation_id',
            'chat_conversations',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-chat_messages-sender_id',
            'chat_messages',
            'sender_id',
            'user',
            'id',
            'CASCADE'
        );

        // Indexes
        $this->createIndex('idx-chat_conversations-status', 'chat_conversations', 'status');
        $this->createIndex('idx-chat_messages-is_read', 'chat_messages', 'is_read');
    }

    public function safeDown(): void
    {
        $this->dropTable('chat_messages');
        $this->dropTable('chat_conversations');
    }
}

