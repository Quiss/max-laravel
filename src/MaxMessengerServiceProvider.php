<?php

namespace MaxBot;

use Illuminate\Support\ServiceProvider;
use MaxBot\Console\HookInfoCommand;
use MaxBot\Console\HookRemoveCommand;
use MaxBot\Console\HookSetCommand;
use MaxBot\Console\TestSendCommand;

class MaxMessengerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/max-bot.php', 'max-bot');

        $this->app->singleton(MaxBot::class, function ($app) {
            $config = $app['config']['max-bot'];

            return new MaxBot(
                token: $config['token'] ?? '',
                apiUrl: $config['api_url'] ?? 'https://platform-api2.max.ru',
                timeout: $config['timeout'] ?? 30,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/max-bot.php' => config_path('max-bot.php'),
            ], 'max-bot-config');

            $this->publishes([
                __DIR__.'/../routes/max.php' => base_path('routes/max.php'),
            ], 'max-bot-handlers');

            $this->publishes([
                __DIR__.'/../routes/api.php' => base_path('routes/api.php'),
            ], 'max-bot-api-route');

            $this->publishes([
                __DIR__.'/../routes/max.php' => base_path('routes/max.php'),
                __DIR__.'/../routes/api.php' => base_path('routes/api.php'),
            ], 'max-bot-routes');

            $this->commands([
                HookInfoCommand::class,
                HookSetCommand::class,
                HookRemoveCommand::class,
                TestSendCommand::class,
            ]);
        }

        // Load MAX bot handler routes if the file exists
        $routesPath = base_path('routes/max.php');
        if (file_exists($routesPath)) {
            $bot = $this->app->make(MaxBot::class);
            require $routesPath;
        }
    }
}
