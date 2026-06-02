<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if(config('advanced-config.register_url') != '') {
    $register = config('advanced-config.register_url');
} else {
    $register = "/register";
}

if(config('advanced-config.login_url') != '') {
    $login = config('advanced-config.login_url');
} else {
    $login = "/login";
}

if(config('advanced-config.forgot_password_url') != '') {
    $forgot_password = config('advanced-config.forgot_password_url');
} else {
    $forgot_password = "/forgot-password";
}

Route::post('/validate-handle', [RegisteredUserController::class, 'validateHandle'])
    ->middleware(['guest', 'throttle:auth-validate-handle']);
Route::get($register, [RegisteredUserController::class, 'create'])
    ->middleware(['guest','max.users','throttle:auth-register-form'])
    ->name('register');

Route::post($register, [RegisteredUserController::class, 'store'])
    ->middleware(['guest','max.users','throttle:auth-register-submit']);

Route::get($login, [AuthenticatedSessionController::class, 'create'])
                ->middleware('guest')
                ->name('login');

Route::post($login, [AuthenticatedSessionController::class, 'store'])
                ->middleware(['guest','throttle:auth-login-submit']);

Route::get( $forgot_password, [PasswordResetLinkController::class, 'create'])
                ->middleware(['guest','throttle:auth-password-request-form'])
                ->name('password.request');

Route::post( $forgot_password, [PasswordResetLinkController::class, 'store'])
                ->middleware(['guest','throttle:auth-password-email'])
                ->name('password.email');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
                ->middleware('guest')
                ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
                ->middleware(['guest', 'throttle:auth-password-update'])
                ->name('password.update');

Route::get('/verify-email', [EmailVerificationPromptController::class, '__invoke'])
                ->middleware(['auth','twofactor'])
                ->name('verification.notice');

Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
                ->middleware(['auth', 'twofactor', 'signed', 'throttle:auth-email-verify-link'])
                ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware(['auth', 'twofactor', 'throttle:auth-email-verification-send'])
                ->name('verification.send');

Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->middleware(['auth','twofactor'])
                ->name('password.confirm');

Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store'])
                ->middleware(['auth','twofactor']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth')
                ->name('logout');

Route::get('/accept-terms', [SocialLoginController::class, 'showTosAcceptance'])
                ->middleware(['auth', 'twofactor'])
                ->name('tos.accept.show');

Route::post('/accept-terms', [SocialLoginController::class, 'acceptTos'])
                ->middleware(['auth', 'twofactor', 'throttle:auth-tos-accept'])
                ->name('tos.accept.store');

Route::get('/accept-legal', [SocialLoginController::class, 'showTosAcceptance'])
                ->middleware(['auth', 'twofactor'])
                ->name('legal.accept.show');

Route::post('/accept-legal', [SocialLoginController::class, 'acceptTos'])
                ->middleware(['auth', 'twofactor', 'throttle:auth-legal-accept'])
                ->name('legal.accept.store');

Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
                ->middleware(['auth','throttle:auth-two-factor-challenge-form'])
                ->name('two-factor.challenge');

Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                ->middleware(['auth','throttle:auth-two-factor-challenge-submit'])
                ->name('two-factor.challenge.store');

Route::get('/blocked', function () {
                    $user = Auth::user();
                    if ($user && $user->block == 'yes') {
                        return view('auth.blocked');
                    } else {
                        return redirect(url('dashboard'));
                    }
                })->middleware('auth')->name('blocked');
                
