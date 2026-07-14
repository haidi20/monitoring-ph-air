<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/server.php', function (Request $request) {
    $apikey = $request->input('apikey');

    if (blank($apikey)) {
        return response('Gagal');
    }

    $project = DB::table('data_project')
        ->where('apikey', $apikey)
        ->where('status', 'aktif')
        ->first();

    if (! $project) {
        return response('Gagal');
    }

    $sensors = DB::table('data_sensor_project')
        ->where('id_project', $project->id_project)
        ->where('status', 'aktif')
        ->get();

    foreach ($sensors as $sensor) {
        if (! $request->has($sensor->prefix)) {
            continue;
        }

        DB::table('data_input')->insert([
            'id_input' => 'INP'.now()->format('YmdHisv').random_int(100, 999),
            'id_project' => $project->id_project,
            'id_sensor_project' => $sensor->id_sensor_project,
            'waktu' => now()->format('Y-m-d H:i:s'),
            'nilai' => (string) $request->input($sensor->prefix),
        ]);
    }

    return response()->json([
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
    ]);
})->name('iot.server');

Route::get('/', function () {
    if (!session()->has('monitoring_user')) {
        return redirect()->route('login');
    }

    return view('dashboard', [
        'username' => session('monitoring_user'),
    ]);
})->name('dashboard');

Route::get('/login', function () {
    if (session()->has('monitoring_user')) {
        return redirect()->route('dashboard');
    }

    return view('login');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    if ($credentials['username'] === 'user' && $credentials['password'] === 'user') {
        $request->session()->regenerate();
        $request->session()->put('monitoring_user', $credentials['username']);

        return redirect()->route('dashboard');
    }

    return back()->withErrors([
        'username' => 'Username atau password salah.',
    ])->onlyInput('username');
})->name('login.attempt');

Route::post('/logout', function (Request $request) {
    $request->session()->forget('monitoring_user');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');