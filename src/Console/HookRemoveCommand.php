<?php

namespace MaxBot\Console;

use Illuminate\Console\Command;
use MaxBot\MaxBot;

class HookRemoveCommand extends Command
{
    protected $signature = 'max:hook:remove';

    protected $description = 'Remove the MAX bot webhook (unsubscribe from updates)';

    public function handle(MaxBot $bot): int
    {
        $this->components->info('Removing webhook...');

        try {
            $result = $bot->deleteWebhook();
        } catch (\Throwable $e) {
            $this->components->error('Failed to remove webhook: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result) {
            $this->components->success('Webhook removed successfully.');
        } else {
            $this->components->error('API returned unsuccessful response.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
