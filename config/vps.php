<?php

return [
    'host' => env('VPS_HOST', '172.232.96.174'),
    'port' => (int) env('VPS_PORT', 22),
    'user' => env('VPS_USER', 'root'),
    'password' => env('VPS_PASSWORD'),
    'app_dir' => env('VPS_APP_DIR', '/var/www/monitoring-ph-air'),
    'app_url' => env('APP_URL_PRODUCTION', 'https://skripsi.haidinata.my.id'),
];
