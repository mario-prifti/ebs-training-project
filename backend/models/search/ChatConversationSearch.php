<?php

namespace backend\models\search;

use common\enums\ChatRoomStatusType;
use common\models\ChatConversation;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

class ChatConversationSearch extends ChatConversation
{
    public $user_username;
    public $admin_username;
    public $latest_message;
    public $unread_gt; // 0 or 1 (has unread from user)

    public function rules(): array
    {
        return [
            [['id', 'user_id', 'admin_id'], 'integer'],
            [['status', 'created_at', 'updated_at', 'user_username', 'admin_username', 'latest_message'], 'safe'],
            [['unread_gt'], 'in', 'range' => [0, 1, '0', '1', null, '']],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search($params): ActiveDataProvider
    {
        $query = ChatConversation::find()
            ->alias('c')
            ->with(['user', 'admin', 'latestMessage']) // Use with() instead of joinWith()
            ->where(['!=', 'c.status', ChatRoomStatusType::STATUS_INACTIVE->value])
            ->orderBy(['c.updated_at' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['updated_at' => SORT_DESC],
                'attributes' => [
                    'id' => [
                        'asc' => ['c.id' => SORT_ASC],
                        'desc' => ['c.id' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => ['c.status' => SORT_ASC],
                        'desc' => ['c.status' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => ['c.created_at' => SORT_ASC],
                        'desc' => ['c.created_at' => SORT_DESC],
                    ],
                    'updated_at' => [
                        'asc' => ['c.updated_at' => SORT_ASC],
                        'desc' => ['c.updated_at' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        // Filter by conversation attributes
        $query->andFilterWhere(['c.id' => $this->id]);
        $query->andFilterWhere(['c.user_id' => $this->user_id]);
        $query->andFilterWhere(['c.admin_id' => $this->admin_id]);
        $query->andFilterWhere(['c.status' => $this->status]);

        // For filtering by username, we need joinWith
        if (!empty($this->user_username)) {
            $query->joinWith(['user u']);
            $query->andFilterWhere(['like', 'u.username', $this->user_username]);
        }

        if (!empty($this->admin_username)) {
            $query->joinWith(['admin a']);
            $query->andFilterWhere(['like', 'a.username', $this->admin_username]);
        }

        // For filtering by latest message, we need joinWith
        if (!empty($this->latest_message)) {
            $query->joinWith(['latestMessage lm']);
            $query->andFilterWhere(['like', 'lm.message', $this->latest_message]);
        }

        // Date filters
        if (!empty($this->created_at)) {
            $query->andWhere(new Expression("DATE(FROM_UNIXTIME(c.created_at)) = :cd"), [
                ':cd' => $this->created_at,
            ]);
        }

        if (!empty($this->updated_at)) {
            $query->andWhere(new Expression("DATE(FROM_UNIXTIME(c.updated_at)) = :ud"), [
                ':ud' => $this->updated_at,
            ]);
        }

        // Unread messages filter
        if ($this->unread_gt === '1' || $this->unread_gt === 1) {
            $query->andWhere(new Expression(
                "(SELECT COUNT(1) FROM {{%chat_messages}} m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_type = 'user') > 0"
            ));
        } elseif ($this->unread_gt === '0' || $this->unread_gt === 0) {
            $query->andWhere(new Expression(
                "(SELECT COUNT(1) FROM {{%chat_messages}} m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_type = 'user') = 0"
            ));
        }

        return $dataProvider;
    }
}