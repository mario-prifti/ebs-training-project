<?php

namespace console\controllers;

use console\Services\ChatServerService;
use yii\console\Controller;

class ChatController extends Controller
{
    public function actionStart(int $port = 8080): void
    {
        $server = new ChatServerService();
        $server->start($port);
    }
}