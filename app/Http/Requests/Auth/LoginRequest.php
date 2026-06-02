<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
            'block' => 'no',
            function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNull('role')
                        ->orWhere('role', '!=', User::ROLE_AGENCY_HUB);
                });
            },
        ];

        if (! Auth::attempt($credentials, $this->filled('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $matchedUser = User::query()
                ->select('id', 'two_factor_enabled')
                ->where('email', (string) $this->email)
                ->first();
            app(ComplianceAuditService::class)->record(
                'login_failure',
                request: $this,
                userId: $matchedUser ? (int) $matchedUser->id : null,
                source: 'auth.login',
                status: 'failure',
                metadata: [
                    'auth_method' => 'password',
                    'failure_reason' => 'invalid_credentials',
                    'email_hash' => app(ComplianceAuditService::class)->hashEmail((string) $this->email),
                    'two_factor_enabled' => $matchedUser ? (bool) $matchedUser->two_factor_enabled : null,
                ]
            );

            throw ValidationException::withMessages([
                'email' => __('messages.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited()
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $matchedUser = User::query()
            ->select('id', 'two_factor_enabled')
            ->where('email', (string) $this->email)
            ->first();
        app(ComplianceAuditService::class)->record(
            'login_failure',
            request: $this,
            userId: $matchedUser ? (int) $matchedUser->id : null,
            source: 'auth.login',
            status: 'failure',
            metadata: [
                'auth_method' => 'password',
                'failure_reason' => 'rate_limited',
                'retry_after_seconds' => $seconds,
                'email_hash' => app(ComplianceAuditService::class)->hashEmail((string) $this->email),
                'two_factor_enabled' => $matchedUser ? (bool) $matchedUser->two_factor_enabled : null,
            ]
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey()
    {
        return Str::lower($this->input('email')).'|'.$this->ip();
    }
}
