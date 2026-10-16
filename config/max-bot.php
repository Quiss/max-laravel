<?php

return [
    'token' => env('MAX_BOT_TOKEN'),
    'api_url' => env('MAX_API_URL', 'https://platform-api2.max.ru'),
    'webhook_secret' => env('MAX_WEBHOOK_SECRET'),
    'timeout' => 30,
    'upload_timeout' => 45,
    // At most five POST /messages attempts; [] disables retries.
    'attachment_retry_delays_ms' => [1000, 2000, 4000, 8000],
    // Seconds for ALL message attempts and delays, including the first request.
    'attachment_retry_timeout' => 20,
];
