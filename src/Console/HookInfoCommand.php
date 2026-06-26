<?php

namespace MaxBot\Console;

use Illuminate\Console\Command;
use MaxBot\MaxBot;

class HookInfoCommand extends Command
{
    protected $signature = 'max:hook:info';

    protected $description = 'Get MAX bot info and current webhook status';

    public function handle(MaxBot $bot): int
    {
        $this->components->info('Fetching bot info...');

        try {
            $me = $bot->getMe();
        } catch (\Throwable $e) {
            $this->components->error('Failed to get bot info: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Bot ID</>', $me['user_id'] ?? '—');
        $this->components->twoColumnDetail('<fg=green>Name</>', $me['name'] ?? '—');
        $this->components->twoColumnDetail('<fg=green>Username</>', $me['username'] ?? '—');
        $this->components->twoColumnDetail('<fg=green>Is Bot</>', ($me['is_bot'] ?? false) ? 'Yes' : 'No');
        $this->components->twoColumnDetail('<fg=green>Avatar</>', $me['avatar_url'] ?? '—');

        $this->newLine();
        $this->components->info('Fetching subscriptions...');

        try {
            $subs = $bot->getSubscriptions();
        } catch (\Throwable $e) {
            $this->components->error('Failed to get subscriptions: '.$e->getMessage());

            return self::FAILURE;
        }

        $subscriptions = $subs['subscriptions'] ?? [];

        if (empty($subscriptions)) {
            $this->components->warn('No active webhook subscriptions.');

            return self::SUCCESS;
        }

        foreach ($subscriptions as $i => $sub) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=yellow>Subscription #'.($i + 1).'</>');
            $this->components->twoColumnDetail('  URL', $sub['url'] ?? '—');
            $this->components->twoColumnDetail('  Version', $sub['version'] ?? '—');

            $types = $sub['update_types'] ?? [];
            $this->components->twoColumnDetail('  Update types', $types ? implode(', ', $types) : 'all');

            $time = $sub['time'] ?? null;
            $this->components->twoColumnDetail('  Registered at', $time ? date('Y-m-d H:i:s', $time / 1000) : '—');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
