<?php

use Illuminate\Support\Facades\Route;
use MaxBot\MaxBot;

Route::post('/max/webhook', function (MaxBot $bot) {
    $secret = config('max-bot.webhook_secret');

    if ($secret && request()->header('X-Max-Bot-Api-Secret') !== $secret) {
        abort(403);
    }

    $bot->processWebhookUpdate(request()->all());

    return response('', 200);
})->name('max.webhook');
