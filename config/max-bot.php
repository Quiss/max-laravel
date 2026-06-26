<?php

return [
    'token' => env('MAX_BOT_TOKEN'),
    'api_url' => env('MAX_API_URL', 'https://platform-api2.max.ru'),
    'webhook_secret' => env('MAX_WEBHOOK_SECRET'),
    'timeout' => 30,
];
