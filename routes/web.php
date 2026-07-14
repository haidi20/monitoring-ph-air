<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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