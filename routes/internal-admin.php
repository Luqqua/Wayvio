<?php

use App\Http\Controllers\InternalAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/admin')
    ->middleware(['throttle:internal-admin', 'internal-admin.request'])
    ->group(function (): void {
        Route::get('/health', [InternalAdminController::class, 'health']);
        Route::get('/status', [InternalAdminController::class, 'status']);
        Route::get('/actions', [InternalAdminController::class, 'actions']);
        Route::post('/execute', [InternalAdminController::class, 'execute']);
    });
