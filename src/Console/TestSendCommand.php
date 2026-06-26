<?php

namespace MaxBot\Console;

use Illuminate\Console\Command;
use MaxBot\MaxBot;

class TestSendCommand extends Command
{
    protected $signature = 'max:test:send
        {user_id : MAX user ID to send to}
        {--text= : Text message to send}
        {--photo= : Path to image file to send}
        {--video= : Path to video file to send}
        {--audio= : Path to audio file to send}
        {--file= : Path to file to send}';

    protected $description = 'Test sending messages/media to a MAX user (for debugging)';

    public function handle(MaxBot $bot): int
    {
        $userId = (int) $this->argument('user_id');

        if ($this->option('photo')) {
            return $this->sendPhoto($bot, $userId);
        }

        if ($this->option('video')) {
            return $this->sendVideo($bot, $userId);
        }

        if ($this->option('audio')) {
            return $this->sendAudio($bot, $userId);
        }

        if ($this->option('file')) {
            return $this->sendFile($bot, $userId);
        }

        $text = $this->option('text') ?? 'Test message from MAX Laravel';

        return $this->sendText($bot, $userId, $text);
    }

    private function sendText(MaxBot $bot, int $userId, string $text): int
    {
        $this->components->info("Sending text to user {$userId}...");

        try {
            $message = $bot->sendMessage($userId, $text);
            $this->components->success('Sent. Message ID: '.($message->mid ?? '—'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sendPhoto(MaxBot $bot, int $userId): int
    {
        $path = $this->option('photo');
        $this->components->info("Sending photo '{$path}' to user {$userId}...");

        try {
            $message = $bot->sendPhoto($userId, $path, 'Test photo from MAX Laravel');
            $this->components->success('Sent. Message ID: '.($message->mid ?? '—'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sendVideo(MaxBot $bot, int $userId): int
    {
        $path = $this->option('video');
        $this->components->info("Sending video '{$path}' to user {$userId}...");

        try {
            $message = $bot->sendVideo($userId, $path, 'Test video from MAX Laravel');
            $this->components->success('Sent. Message ID: '.($message->mid ?? '—'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sendAudio(MaxBot $bot, int $userId): int
    {
        $path = $this->option('audio');
        $this->components->info("Sending audio '{$path}' to user {$userId}...");

        try {
            $message = $bot->sendAudio($userId, $path, 'Test audio from MAX Laravel');
            $this->components->success('Sent. Message ID: '.($message->mid ?? '—'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sendFile(MaxBot $bot, int $userId): int
    {
        $path = $this->option('file');
        $this->components->info("Sending file '{$path}' to user {$userId}...");

        try {
            $message = $bot->sendDocument($userId, $path, 'Test file from MAX Laravel');
            $this->components->success('Sent. Message ID: '.($message->mid ?? '—'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
