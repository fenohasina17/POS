<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProductReportController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\ValidateCentralApiKey;
use Illuminate\Support\Facades\Route;

// Health check (public — pour monitoring)
Route::get('/health', function () {
    try {
        \DB::connection()->getPdo();
        $dbStatus = 'ok';
    } catch (\Exception $e) {
        $dbStatus = 'error';
    }

    $status = $dbStatus === 'ok' ? 'healthy' : 'degraded';
    return response()->json([
        'status'    => $status,
        'db'        => $dbStatus,
        'terminals' => \App\Models\Terminal::count(),
        'timestamp' => now()->toISOString(),
    ], $status === 'healthy' ? 200 : 503);
})->name('health');

// ── Authentification dashboard ────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
});

// ── Endpoints appelés par les terminaux POS ───────────────────────────────────
// Authentifiés par la clé API partagée (CENTRAL_API_KEY)
Route::middleware(ValidateCentralApiKey::class)->group(function () {
    Route::post('/sync/receive',          [SyncController::class, 'receive']);
    Route::post('/sync/heartbeat',        [SyncController::class, 'heartbeat']);
    Route::post('/sync/import-historical',[SyncController::class, 'importHistorical']);
});

// ── Endpoints pour le dashboard siège ────────────────────────────────────────
// Authentifiés par token Sanctum (utilisateurs du siège)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard',            [DashboardController::class, 'index']);
    Route::get('/terminals',                          [TerminalController::class, 'index']);
    Route::post('/terminals',                         [TerminalController::class, 'store']);
    Route::get('/terminals/{terminal}',               [TerminalController::class, 'show']);
    Route::post('/terminals/{terminal}/rotate-key',   [TerminalController::class, 'rotateKey']);
    Route::delete('/terminals/{terminal}',            [TerminalController::class, 'destroy']);

    Route::get('/alerts',                  [AlertController::class, 'index']);
    Route::get('/alerts/counts',           [AlertController::class, 'counts']);
    Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);

    Route::get('/export/sales', [ExportController::class, 'sales']);

    Route::get('/sales',                [SalesController::class,        'index']);
    Route::get('/sales/{id}/lines',     [SalesController::class,        'lines']);
    Route::get('/sessions',             [SessionsController::class,     'index']);
    Route::get('/products/report',      [ProductReportController::class,'index']);
});
