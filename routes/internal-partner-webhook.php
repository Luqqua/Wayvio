<?php

use App\Http\Controllers\InternalPartnerWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/webhook')
    ->middleware(['throttle:internal-partner-webhook', 'internal-partner-webhook.request'])
    ->group(function (): void {
        Route::post('/transfer-paid', [InternalPartnerWebhookController::class, 'transferPaid']);
    });
