<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\search\ChatConversationSearch $searchModel */

use common\enums\ChatRoomStatusType;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\Pjax;

$this->title = 'Support Conversations';
$this->params['breadcrumbs'][] = $this->title;

$statusItems = [];
foreach (ChatRoomStatusType::cases() as $case) {
    $statusItems[$case->value] = ucfirst(strtolower(str_replace('_',' ', $case->name)));
}
?>
<div class="support-conversations-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php Pjax::begin(); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            [
                'attribute' => 'user_username',
                'label' => 'User',
                'value' => function($model){ return $model->user ? $model->user->username : '-'; },
            ],
            [
                'attribute' => 'admin_username',
                'label' => 'Admin',
                'value' => function($model){ return $model->admin ? $model->admin->username : '-'; },
            ],
            [
                'attribute' => 'status',
                'filter' => $statusItems,
                'value' => function($model){ return $model->status; },
            ],
            [
                'attribute' => 'latest_message',
                'label' => 'Latest message',
                'value' => function($model){ return $model->latestMessage ? $model->latestMessage->message : ''; },
            ],
            [
                'attribute' => 'unread_gt',
                'label' => 'Has unread from user',
                'filter' => [
                    '' => 'All',
                    '1' => 'Yes',
                    '0' => 'No',
                ],
                'value' => function($model){
                    $count = $model->unreadMessagesCount;
                    return $count > 0 ? 'Yes (' . $count . ')' : 'No';
                }
            ],
            [
                'attribute' => 'updated_at',
                'format' => ['datetime'],
                'filter' => Html::input('date', Html::getInputName($searchModel, 'updated_at'), $searchModel->updated_at, ['class' => 'form-control'])
            ],
            [
                'class' => 'yii\\grid\\ActionColumn',
                'template' => '{view} {close}',
                'buttons' => [
                    'view' => function ($url, $model) {
                        return Html::a('Open', ['support/chat', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']);
                    },
                    'close' => function ($url, $model) {
                        return Html::a('Close', ['support/close-conversation', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-outline-danger',
                            'data-method' => 'post',
                            'data-confirm' => 'Close this conversation?'
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>

    <?php Pjax::end(); ?>
</div>
