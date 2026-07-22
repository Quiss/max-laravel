<?php

namespace MaxBot\Console;

use Illuminate\Console\Command;
use MaxBot\MaxBot;

class HookSetCommand extends Command
{
    private const DEFAULT_UPDATE_TYPES = [
        'bot_started',
        'message_created',
        'message_callback',
    ];

    protected $signature = 'max:hook:set
        {url? : Webhook URL (defaults to APP_URL/api/max/webhook)}
        {--update-types= : Comma-separated update types (defaults to bot_started,message_created,message_callback)}
        {--secret= : Webhook secret (defaults to MAX_WEBHOOK_SECRET)}';

    protected $description = 'Set the MAX bot webhook (subscribe to updates)';

    public function handle(MaxBot $bot): int
    {
        $url = $this->argument('url')
            ?? rtrim(config('app.url'), '/').'/api/max/webhook';

        $secret = $this->option('secret')
            ?? config('max-bot.webhook_secret');

        $updateTypesOption = trim((string) $this->option('update-types'));
        $updateTypes = $updateTypesOption === ''
            ? self::DEFAULT_UPDATE_TYPES
            : array_values(array_filter(array_map('trim', explode(',', $updateTypesOption))));

        $this->components->info("Setting webhook to: {$url}");

        if ($updateTypes) {
            $this->components->bulletList($updateTypes);
        }

        try {
            $result = $bot->setWebhook($url, $updateTypes, $secret);
        } catch (\Throwable $e) {
            $this->components->error('Failed to set webhook: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result) {
            $this->components->success('Webhook set successfully.');
        } else {
            $this->components->error('API returned unsuccessful response.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
