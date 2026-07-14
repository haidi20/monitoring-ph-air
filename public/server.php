<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apikey = $_REQUEST['apikey'] ?? null;

if (!$apikey) {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'Gagal';
    exit;
}

$project = DB::table('data_project')
    ->where('apikey', $apikey)
    ->where('status', 'aktif')
    ->first();

if (! $project) {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'Gagal';
    exit;
}

$sensors = DB::table('data_sensor_project')
    ->where('id_project', $project->id_project)
    ->where('status', 'aktif')
    ->get();

foreach ($sensors as $sensor) {
    if (!array_key_exists($sensor->prefix, $_REQUEST)) {
        continue;
    }

    DB::table('data_input')->insert([
        'id_input' => 'INP'.date('YmdHisv').random_int(100, 999),
        'id_project' => $project->id_project,
        'id_sensor_project' => $sensor->id_sensor_project,
        'waktu' => date('Y-m-d H:i:s'),
        'nilai' => (string) $_REQUEST[$sensor->prefix],
    ]);
}

header('Content-Type: application/json');
echo json_encode([
    'out_1' => '0',
    'out_2' => '0',
    'out_3' => '0',
    'out_4' => '0',
    'out_5' => '0',
    'out_6' => '0',
    'out_7' => '0',
    'out_8' => '0',
    'out_9' => '0',
    'out_10' => '0',
], JSON_PRETTY_PRINT);