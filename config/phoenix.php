<?php

return [

    'use_mock' => env('PHOENIX_USE_MOCK', true),

    'base_url' => env('PHOENIX_API_BASE_URL', 'https://phoenix.example.com'),

    'api_key' => env('PHOENIX_API_KEY'),

    'username' => env('PHOENIX_API_USERNAME'),

    'password' => env('PHOENIX_API_PASSWORD'),

    'timeout' => (int) env('PHOENIX_TIMEOUT', 30),

    'retry_times' => (int) env('PHOENIX_RETRY_TIMES', 3),

    'log_channel' => env('PHOENIX_LOG_CHANNEL', 'stack'),

];
