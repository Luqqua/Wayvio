<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomDomains\Http\Controllers\CustomDomainController;

Route::middleware(['web', 'auth', 'tos.pending', 'twofactor', 'subscription', 'custom-domain.tier'])->group(function () {
    Route::get('/account/domains', [CustomDomainController::class, 'index'])->name('domains.index');
    Route::post('/account/domains', [CustomDomainController::class, 'store'])->name('domains.store');
    Route::post('/account/domains/{domain}/verify', [CustomDomainController::class, 'verify'])
        ->missing(fn () => response()->json([
            'error' => 'domain_not_found',
            'message' => 'The selected domain could not be found.',
        ], 404))
        ->name('domains.verify');
    Route::delete('/account/domains/{domain}', [CustomDomainController::class, 'destroy'])
        ->missing(fn () => response()->json([
            'error' => 'domain_not_found',
            'message' => 'The selected domain could not be found.',
        ], 404))
        ->name('domains.destroy');
    Route::post('/account/agency-branding', [CustomDomainController::class, 'saveAgencyBranding'])
        ->middleware('throttle:uploads')
        ->name('agency.branding.save');
});
