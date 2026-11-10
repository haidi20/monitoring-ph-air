<?php

return [
    'api_key' => env('MONITORING_API_KEY', ''),

    'dashboard' => [
        'username' => env('MONITORING_DASHBOARD_USERNAME', 'user'),
        'password' => env('MONITORING_DASHBOARD_PASSWORD', 'user'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'thresholds' => [
        'phair' => ['min' => 6.5, 'max' => 8.5],
        'suhu' => ['min' => 20, 'max' => 35],
        'kekeruhan' => ['min' => 0, 'max' => 50],
    ],
];
