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
            ->joinWith(['user u', 'admin a', 'latestMessage lm'])
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
                    'id',
                    'status',
                    'created_at',
                    'updated_at',
                    'user_username' => [
                        'asc' => ['u.username' => SORT_ASC],
                        'desc' => ['u.username' => SORT_DESC],
                    ],
                    'admin_username' => [
                        'asc' => ['a.username' => SORT_ASC],
                        'desc' => ['a.username' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['c.id' => $this->id]);
        $query->andFilterWhere(['c.user_id' => $this->user_id]);
        $query->andFilterWhere(['c.admin_id' => $this->admin_id]);
        $query->andFilterWhere(['c.status' => $this->status]);

        // Filter by related usernames
        $query->andFilterWhere(['like', 'u.username', $this->user_username]);
        $query->andFilterWhere(['like', 'a.username', $this->admin_username]);

        // Filter by latest message text
        $query->andFilterWhere(['like', 'lm.message', $this->latest_message]);

        // Updated_at simple date filter (YYYY-MM-DD) matches the day
        if (!empty($this->updated_at)) {
            $query->andWhere(new Expression("DATE(FROM_UNIXTIME(c.updated_at)) = :ud"), [
                ':ud' => $this->updated_at,
            ]);
        }

        // Has unread messages from user
        if ($this->unread_gt === '1' || $this->unread_gt === 1) {
            $query->andWhere(new Expression(
                "(SELECT COUNT(1) FROM chat_messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_type = 'user') > 0"
            ));
        } elseif ($this->unread_gt === '0' || $this->unread_gt === 0) {
            $query->andWhere(new Expression(
                "(SELECT COUNT(1) FROM chat_messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_type = 'user') = 0"
            ));
        }

        return $dataProvider;
    }
}
