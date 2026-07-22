<?php

namespace MaxBot\Tests;

use MaxBot\Api\HttpClient;
use MaxBot\Api\UploadClient;
use MaxBot\MaxBot;
use MaxBot\Types\Keyboard\InlineKeyboardButton;
use MaxBot\Types\Keyboard\InlineKeyboardMarkup;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MaxBotTest extends TestCase
{
    public function test_audio_can_be_sent_with_an_inline_keyboard(): void
    {
        $http = new RecordingHttpClient;
        $bot = $this->bot($http, new StubUploadClient);
        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('Create another', callbackData: 'new'),
        );

        $message = $bot->sendAudio(42, '/tmp/song.mp3', 'Ready', 'My song', $keyboard);

        $this->assertSame('audio-message', $message->messageId);
        $this->assertSame('audio', $http->postRequest['body']['attachments'][0]['type']);
        $this->assertSame('inline_keyboard', $http->postRequest['body']['attachments'][1]['type']);
    }

    public function test_message_can_be_edited_with_a_keyboard(): void
    {
        $http = new RecordingHttpClient;
        $bot = $this->bot($http, new StubUploadClient);
        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('Pop', callbackData: 'genre:pop'),
        );

        $this->assertTrue($bot->editMessage('mid.1', 'Choose genre', $keyboard));
        $this->assertSame('mid.1', $http->putRequest['query']['message_id']);
        $this->assertSame('inline_keyboard', $http->putRequest['body']['attachments'][0]['type']);
    }

    private function bot(RecordingHttpClient $http, StubUploadClient $uploader): MaxBot
    {
        $bot = new MaxBot('token');
        (new ReflectionProperty(MaxBot::class, 'http'))->setValue($bot, $http);
        (new ReflectionProperty(MaxBot::class, 'uploader'))->setValue($bot, $uploader);

        return $bot;
    }
}

class RecordingHttpClient extends HttpClient
{
    public ?array $postRequest = null;

    public ?array $putRequest = null;

    public function __construct() {}

    public function post(string $endpoint, array $body = [], array $query = []): array
    {
        $this->postRequest = compact('endpoint', 'body', 'query');

        return ['message' => ['body' => ['mid' => 'audio-message']]];
    }

    public function put(string $endpoint, array $body = [], array $query = []): array
    {
        $this->putRequest = compact('endpoint', 'body', 'query');

        return ['success' => true];
    }
}

class StubUploadClient extends UploadClient
{
    public function __construct() {}

    public function upload(string $filePath, string $type): array
    {
        return ['type' => $type, 'payload' => ['token' => 'audio-token']];
    }
}
