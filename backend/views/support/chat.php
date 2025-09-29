<?php
/** @var yii\web\View $this */
/** @var common\models\ChatConversation $conversation */

use yii\helpers\Html;

$this->title = 'Admin Support Chat';
$this->params['breadcrumbs'][] = ['label' => 'Support Conversations', 'url' => ['support/conversations']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="admin-support-chat">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>Conversation ID: <?= Html::encode($conversation->id) ?></p>
    <p>User: <?= Html::encode($conversation->user ? $conversation->user->username : 'Unknown') ?></p>

    <div class="alert alert-info">
        This is a placeholder view for the admin chat panel. Integrate the real-time chat UI here if needed.
    </div>
</div>
