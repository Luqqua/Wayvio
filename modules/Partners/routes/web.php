<?php

use Illuminate\Support\Facades\Route;
use Modules\Partners\Http\Controllers\PartnerDashboardController;
use Modules\Partners\Http\Controllers\ReferralController;

Route::middleware('web')->group(function () {
    Route::get('/ref/{code}', [ReferralController::class, 'capture'])
        ->where('code', '[A-Za-z0-9_-]+')
        ->name('partner.referral.capture');
});

Route::middleware(['web', 'auth', 'blocked', 'twofactor'])->group(function () {
    Route::get('/dashboard/partner', [PartnerDashboardController::class, 'show'])
        ->name('partner.dashboard');
    Route::post('/dashboard/partner/codes', [PartnerDashboardController::class, 'createInviteCode'])
        ->middleware('throttle:partner-codes-create')
        ->name('partner.codes.create');
    Route::post('/dashboard/partner/onboarding/start', [PartnerDashboardController::class, 'startOnboarding'])
        ->middleware('throttle:partner-onboarding-start')
        ->name('partner.onboarding.start');
    Route::get('/dashboard/partner/onboarding/status', [PartnerDashboardController::class, 'onboardingStatus'])
        ->middleware('throttle:partner-onboarding-status')
        ->name('partner.onboarding.status');
});
