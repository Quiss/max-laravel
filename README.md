# MAX Laravel

Laravel package for working with the MAX Bot API. It provides a container-resolved bot client, webhook registration commands, a `routes/max.php` handlers file, command/text routing inspired by Nutgram, inline keyboards, media uploads, and callback answers.

The package name is `quiss/max-laravel`, but the PHP namespace is `MaxBot`.

If you used an older copied version with the `Vibsy\MaxMessenger` namespace, update your app files to `MaxBot`. The package keeps legacy class aliases so installation does not fail during migration, but new code should use the `MaxBot` namespace and `max-bot` config key.

## Installation

```bash
composer require quiss/max-laravel
```

Laravel auto-discovers the service provider. If package discovery is disabled, add it manually:

```php
MaxBot\MaxMessengerServiceProvider::class,
```

Publish the config:

```bash
php artisan vendor:publish --tag=max-bot-config
```

Publish the webhook route and handler stubs:

```bash
php artisan vendor:publish --tag=max-bot-routes
```

Or publish them separately:

```bash
php artisan vendor:publish --tag=max-bot-api-route
php artisan vendor:publish --tag=max-bot-handlers
```

Published files:

```text
config/max-bot.php
routes/api.php
routes/max.php
```

`routes/api.php` contains the HTTP endpoint. `routes/max.php` contains bot handler registrations and is loaded automatically by the package when the file exists.

## Configuration

Add your bot credentials to `.env`:

```dotenv
MAX_BOT_TOKEN=your-bot-token
MAX_WEBHOOK_SECRET=optional-secret
MAX_API_URL=https://platform-api2.max.ru
```

`MAX_WEBHOOK_SECRET` is optional, but recommended. When it is set, the published webhook route checks the `X-Max-Bot-Api-Secret` header.

If you previously published the old config, rename `config/max-messenger.php` to `config/max-bot.php` and replace `config('max-messenger...')` usages with `config('max-bot...')`.

## Webhook

The published API route is placed into Laravel's `routes/api.php` and listens on:

```text
POST /api/max/webhook
```

Register the webhook in MAX:

```bash
php artisan max:hook:set https://example.com/api/max/webhook
```

With update types and secret:

```bash
php artisan max:hook:set https://example.com/api/max/webhook \
  --update-types=bot_started,message_created,message_callback \
  --secret=your-secret
```

Useful commands:

```bash
php artisan max:hook:info
php artisan max:hook:remove
php artisan max:test:send 123456 --text="Test message"
```

MAX requires a publicly reachable HTTPS endpoint. The endpoint should return HTTP 200 quickly; long work should be queued in your application.

## Handler Routes

Register handlers in `routes/max.php`. The package loads this file and provides a `$bot` variable:

```php
<?php

use MaxBot\MaxBot;
use MaxBot\Types\Update;

/** @var MaxBot $bot */
$bot->onCommand('start {payload}', function (Update $update, array $parameters) use ($bot) {
    $userId = $update->getUserId();

    if ($userId) {
        $bot->sendMessage($userId, 'Payload: '.$parameters['payload']);
    }
});

$bot->onCommand('start', function (Update $update) use ($bot) {
    $userId = $update->getUserId();

    if ($userId) {
        $bot->sendMessage($userId, 'Bot started');
    }
});

$bot->onText('.*', function (Update $update) {
    // Handle any text message.
});
```

Handlers can be closures, callables, or invokable class names:

```php
$bot->onCommand('start', \App\Max\Handlers\StartHandler::class);
$bot->onText('.*', \App\Max\Handlers\TextHandler::class);
$bot->onBotStopped(\App\Max\Handlers\MaxBotStoppedHandler::class);
```

An invokable handler receives the `Update` object:

```php
namespace App\Max\Handlers;

use MaxBot\Types\Update;

class StartHandler
{
    public function __invoke(Update $update): void
    {
        // ...
    }
}
```

If a command pattern has placeholders, declare a second argument:

```php
class StartHandler
{
    public function __invoke(Update $update, array $parameters): void
    {
        $payload = $parameters['payload'] ?? null;
    }
}
```

## Routing Methods

```php
use MaxBot\Webhook\UpdateType;

$bot->on(UpdateType::MessageEdited, $handler);
$bot->onBotStarted($handler);
$bot->onBotStopped($handler);
$bot->onMessageCreated($handler);
$bot->onCallback($handler);
$bot->onCommand('start {payload}', $handler);
$bot->onText('.*', $handler);
```

`onCommand()` matches text messages with or without a leading slash, so both `/start abc` and `start abc` match `start {payload}`.

`onText()` accepts a regular expression without delimiters. For example, `.*` matches any text message.

## Sending Messages

Resolve `MaxBot` from the Laravel container:

```php
use MaxBot\MaxBot;

$bot = app(MaxBot::class);

$bot->sendMessage(
    userId: 123456,
    text: 'Hello user'
);

$bot->sendChatMessage(
    chatId: 123456,
    text: 'Hello chat'
);
```

Supported text formats are passed through to MAX:

```php
$bot->sendMessage(123456, '<b>Hello</b>', format: 'html');
$bot->sendMessage(123456, '**Hello**', format: 'markdown');
```

## Inline Keyboards

```php
use MaxBot\Types\Keyboard\InlineKeyboardButton;
use MaxBot\Types\Keyboard\InlineKeyboardMarkup;

$keyboard = InlineKeyboardMarkup::make()
    ->addRow(
        InlineKeyboardButton::make('Open site', url: 'https://example.com')
    )
    ->addRow(
        InlineKeyboardButton::make('Confirm', callbackData: 'confirm')
    );

$bot->sendMessage(123456, 'Choose an action', $keyboard);
```

Handle callback button presses:

```php
use MaxBot\Types\Update;

$bot->onCallback(function (Update $update) use ($bot) {
    if ($update->callbackId) {
        $bot->answerCallback($update->callbackId, notification: 'Done');
    }
});
```

`answerCallback()` can send a one-time notification, update the original message, or both:

```php
$bot->answerCallback(
    callbackId: $update->callbackId,
    message: ['text' => 'Updated message'],
    notification: 'Saved'
);
```

## Sending Media

```php
$bot = app(MaxBot::class);

$bot->sendPhoto(123456, storage_path('app/photo.jpg'), 'Photo caption');
$bot->sendVideo(123456, storage_path('app/video.mp4'), 'Video caption');
$bot->sendAudio(123456, storage_path('app/audio.mp3'), 'Audio caption');
$bot->sendDocument(123456, storage_path('app/file.pdf'), 'File caption');
```

Media methods upload the local file first and then send the returned attachment token in a message.

## Working With Updates

Handlers receive `MaxBot\Types\Update`. Useful helpers:

```php
$update->getUserId();
$update->getChatId();
$update->getText();
$update->payload;
$update->callbackId;
$update->rawData;
```

`rawData` contains the original MAX webhook payload if you need fields that are not mapped yet.

## API Coverage

This package currently covers the common bot workflow:

- bot info: `GET /me`
- send messages to users and chats: `POST /messages`
- upload media: `POST /uploads`
- webhook subscriptions: `GET`, `POST`, `DELETE /subscriptions`
- callback answers: `POST /answers`
- webhook update routing with closures and invokable classes

The official MAX API also includes chats, members, admins, pinning, message listing/editing/deleting, long polling, video info, and more. Those methods are not fully wrapped yet; use `getHttpClient()` for low-level calls when needed:

```php
$response = app(MaxBot::class)
    ->getHttpClient()
    ->get('chats');
```

## License

MIT
