<?php

use MaxBot\MaxBot;
use MaxBot\Types\Update;

/** @var MaxBot $bot */
$bot->onCommand('start {payload}', function (Update $update, array $parameters) use ($bot) {
    $userId = $update->getUserId();

    if ($userId) {
        $bot->sendMessage($userId, 'Start payload: '.$parameters['payload']);
    }
});

$bot->onCommand('start', function (Update $update) use ($bot) {
    $userId = $update->getUserId();

    if ($userId) {
        $bot->sendMessage($userId, 'Bot started');
    }
});

$bot->onText('.*', function (Update $update) use ($bot) {
    // Handle free-form text messages here.
});

// You may also use invokable handler classes:
// $bot->onBotStopped(\App\Max\Handlers\MaxBotStoppedHandler::class);
