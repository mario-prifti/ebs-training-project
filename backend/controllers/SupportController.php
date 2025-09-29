<?php

namespace backend\controllers;

use backend\models\search\ChatConversationSearch;
use common\enums\ChatRoomStatusType;
use common\models\ChatConversation;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;

class SupportController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->identity->role === 'admin'; // Assuming you have role field
                        }
                    ],
                ],
            ],
        ];
    }

    public function actionConversations(): string
    {
        $searchModel = new ChatConversationSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('conversations', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionChat($id)
    {
        $conversation = ChatConversation::findOne($id);
        if (!$conversation) {
            throw new \yii\web\NotFoundHttpException('Conversation not found');
        }

        return $this->render('chat', [
            'conversation' => $conversation,
        ]);
    }

    /**
     * @throws Exception
     */
    public function actionCloseConversation($id): \yii\web\Response
    {
        $conversation = ChatConversation::findOne($id);
        if ($conversation) {
            $conversation->status = ChatRoomStatusType::STATUS_INACTIVE;
            $conversation->save();
        }

        return $this->redirect(['conversations']);
    }
}