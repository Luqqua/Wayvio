<?php

namespace App\Providers;

use App\Support\Security\IpAddressMatcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            if (config('internal-admin.enabled', false)) {
                Route::namespace($this->namespace)
                    ->group(base_path('routes/internal-admin.php'));
            }

            if (config('internal-partner-webhook.enabled', false)) {
                Route::namespace($this->namespace)
                    ->group(base_path('routes/internal-partner-webhook.php'));
            }
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('auth-register-form', function (Request $request) {
            return Limit::perMinute(20)->by('auth-register-form:' . ($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('auth-register-submit', function (Request $request) {
            return Limit::perMinute(10)->by('auth-register-submit:' . ($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('auth-validate-handle', function (Request $request) {
            return Limit::perMinute(30)->by('auth-validate-handle:' . ($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('auth-login-submit', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-login-submit', 6));
        RateLimiter::for('auth-password-request-form', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-password-request-form', 10));
        RateLimiter::for('auth-password-email', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-password-email', 10));
        RateLimiter::for('auth-password-update', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-password-update', 6));
        RateLimiter::for('auth-email-verify-link', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-email-verify-link', 6));
        RateLimiter::for('auth-email-verification-send', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-email-verification-send', 6));
        RateLimiter::for('auth-tos-accept', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-tos-accept', 6));
        RateLimiter::for('auth-legal-accept', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-legal-accept', 6));
        RateLimiter::for('auth-two-factor-challenge-form', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-two-factor-challenge-form', 6));
        RateLimiter::for('auth-two-factor-challenge-submit', fn (Request $request) => $this->perRouteMinuteLimit($request, 'auth-two-factor-challenge-submit', 6));
        RateLimiter::for('profile-email-change-confirm', fn (Request $request) => $this->perRouteMinuteLimit($request, 'profile-email-change-confirm', 6));
        RateLimiter::for('profile-email-change-request', fn (Request $request) => $this->profileChangeLimits(
            $request,
            'profile-email-change-request',
            'new_email',
            'messages.Profile email change daily limit exceeded',
            3,
            5
        ));
        RateLimiter::for('profile-password-update', fn (Request $request) => $this->profileChangeLimits(
            $request,
            'profile-password-update',
            'current_password',
            'messages.Profile password change daily limit exceeded',
            6,
            1
        ));
        RateLimiter::for('profile-delete-user', fn (Request $request) => $this->perRouteMinutesLimit($request, 'profile-delete-user', 3, 10));
        RateLimiter::for('two-factor-enable', fn (Request $request) => $this->perRouteMinuteLimit($request, 'two-factor-enable', 6));
        RateLimiter::for('two-factor-confirm', fn (Request $request) => $this->perRouteMinuteLimit($request, 'two-factor-confirm', 6));
        RateLimiter::for('two-factor-disable', fn (Request $request) => $this->perRouteMinuteLimit($request, 'two-factor-disable', 6));
        RateLimiter::for('two-factor-recovery-codes', fn (Request $request) => $this->perRouteMinuteLimit($request, 'two-factor-recovery-codes', 6));
        RateLimiter::for('stripe-webhook', fn (Request $request) => $this->perRouteMinuteLimit($request, 'stripe-webhook', 120));
        RateLimiter::for('partner-codes-create', fn (Request $request) => $this->perRouteMinuteLimit($request, 'partner-codes-create', 10));
        RateLimiter::for('partner-onboarding-start', fn (Request $request) => $this->perRouteMinuteLimit($request, 'partner-onboarding-start', 10));
        RateLimiter::for('partner-onboarding-status', fn (Request $request) => $this->perRouteMinuteLimit($request, 'partner-onboarding-status', 30));

        RateLimiter::for('report-submissions', function (Request $request) {
            $perMinute = max(1, (int) config('reports.rate_limit_per_minute', 5));
            $perDay = max(1, (int) config('reports.rate_limit_per_day', 60));
            $ip = $request->ip() ?: 'unknown';

            return [
                Limit::perMinute($perMinute)->by('minute:' . $ip),
                Limit::perDay($perDay)->by('day:' . $ip),
            ];
        });

        RateLimiter::for('forms-submissions', function (Request $request) {
            $ip = $request->ip() ?: 'unknown';
            $hub = (string) ($request->route('hub') ?? 'hub');
            $formKey = (string) ($request->route('formKey') ?? 'form');
            $scope = $hub . ':' . $formKey . ':' . $ip;

            return [
                Limit::perMinute(5)->by('forms-minute:' . $scope),
                Limit::perDay(50)->by('forms-day:' . $scope),
            ];
        });

        RateLimiter::for('internal-admin', function (Request $request) {
            $perMinute = max(30, (int) config('internal-admin.rate_limit_per_minute', 120));
            $token = (string) ($request->header('X-Internal-Admin-Token') ?? '');
            $tokenHash = $token === '' ? 'missing' : substr(hash('sha256', $token), 0, 20);

            return Limit::perMinute($perMinute)->by('internal-admin:' . $tokenHash . '|' . $this->requestIpKey($request));
        });

        RateLimiter::for('internal-partner-webhook', function (Request $request) {
            $perMinute = max(60, (int) config('internal-partner-webhook.rate_limit_per_minute', 240));
            $token = (string) ($request->header('X-Internal-Token') ?? '');
            $tokenHash = $token === '' ? 'missing' : substr(hash('sha256', $token), 0, 20);

            return Limit::perMinute($perMinute)->by('internal-partner-webhook:' . $tokenHash . '|' . $this->requestIpKey($request));
        });

        RateLimiter::for('uploads', function (Request $request) {
            $perMinute = max(1, (int) config('media.upload_rate_limit_per_minute', 10));
            $identifier = $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip();

            return Limit::perMinute($perMinute)->by($identifier);
        });
    }

    private function requestIpKey(Request $request): string
    {
        $ip = IpAddressMatcher::requestIp($request);
        return $ip !== '' ? $ip : 'unknown';
    }

    private function perRouteMinuteLimit(Request $request, string $name, int $maxAttempts): Limit
    {
        return Limit::perMinute($maxAttempts)->by($name . ':' . $this->requestActorKey($request));
    }

    private function perRouteMinutesLimit(Request $request, string $name, int $maxAttempts, int $decayMinutes): Limit
    {
        return Limit::perMinutes($decayMinutes, $maxAttempts)->by($name . ':' . $this->requestActorKey($request));
    }

    /**
     * @return array<int, Limit>
     */
    private function profileChangeLimits(
        Request $request,
        string $name,
        string $errorKey,
        string $messageKey,
        int $shortMaxAttempts,
        int $shortDecayMinutes
    ): array {
        $actorKey = $this->requestActorKey($request);

        return [
            Limit::perMinutes($shortDecayMinutes, $shortMaxAttempts)->by($name . ':short:' . $actorKey),
            Limit::perDay(4)->by($name . ':daily:' . $actorKey)->response(
                fn (Request $request, array $headers) => redirect()
                    ->back()
                    ->withInput($request->except([
                        'current_password',
                        'password',
                        'password_confirmation',
                    ]))
                    ->withErrors([$errorKey => __($messageKey)])
                    ->withHeaders($headers)
            ),
        ];
    }

    private function requestActorKey(Request $request): string
    {
        $userId = $request->user()?->id;

        if ($userId) {
            return 'user:' . $userId;
        }

        return 'ip:' . ($request->ip() ?: 'unknown');
    }
}
