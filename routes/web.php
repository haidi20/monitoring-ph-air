<?php

use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], 'server.php', [MonitoringController::class, 'ingest'])->name('iot.server');
Route::get('/api/monitoring', [MonitoringController::class, 'api'])->name('monitoring.api');
Route::get('/detail/{sensor}', [MonitoringController::class, 'detail'])->name('monitoring.detail');
Route::get('/', [MonitoringController::class, 'dashboard'])->name('dashboard');
Route::get('/login', [MonitoringController::class, 'loginForm'])->name('login');
Route::post('/login', [MonitoringController::class, 'login'])->name('login.attempt');
Route::post('/logout', [MonitoringController::class, 'logout'])->name('logout');
