<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CaptchaVerifier
{
    public const CONTEXT_REGISTER = 'register';
    public const CONTEXT_LOGIN = 'login';
    public const CONTEXT_PASSWORD_RESET = 'password_reset';
    public const CONTEXT_REPORT = 'report';
    public const CONTEXT_FORMS_IMPRINT_CONTACT = 'forms_imprint_contact';
    public const CONTEXT_FORMS_HUB_CONTACT_BLOCK = 'forms_hub_contact_block';

    public function provider(): ?string
    {
        $provider = config('services.captcha.provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    public function siteKey(?string $context = null): ?string
    {
        $siteKeys = config('services.captcha.sitekeys', []);

        if ($context && is_array($siteKeys)) {
            $contextKey = $siteKeys[$context] ?? null;
            if (is_string($contextKey) && $contextKey !== '') {
                return $contextKey;
            }
        }

        $siteKey = config('services.captcha.sitekey');

        return is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
    }

    public function secret(?string $context = null): ?string
    {
        $secrets = config('services.captcha.secrets', []);

        if ($context && is_array($secrets)) {
            $contextSecret = $secrets[$context] ?? null;
            if (is_string($contextSecret) && $contextSecret !== '') {
                return $contextSecret;
            }
        }

        $secret = config('services.captcha.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function isConfigured(?string $context = null): bool
    {
        return $this->provider() !== null
            && $this->secret($context) !== null
            && $this->siteKey($context) !== null;
    }

    public function validateIfConfigured(Request $request, ?string $context = null): void
    {
        if (! $this->isConfigured($context)) {
            return;
        }

        $this->validate($request, $context);
    }

    public function validate(Request $request, ?string $context = null): void
    {
        if (! $this->isConfigured($context)) {
            $this->fail();
        }

        $token = $this->tokenFromRequest($request);
        if ($token === null) {
            Log::warning('Captcha validation rejected: missing token', [
                'provider' => $this->provider(),
                'context' => $context,
                'host' => $request->getHost(),
                'path' => $request->path(),
            ]);

            $this->fail();
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post($this->verificationEndpoint(), [
                    'secret' => $this->secret($context),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            report($e);
            $this->fail();
        }

        $data = $response->json();

        if (! $response->successful() || ! is_array($data) || ! ($data['success'] ?? false)) {
            Log::warning('Captcha validation rejected: provider denied request', [
                'provider' => $this->provider(),
                'context' => $context,
                'host' => $request->getHost(),
                'path' => $request->path(),
                'status' => $response->status(),
                'success' => is_array($data) ? ($data['success'] ?? null) : null,
                'error_codes' => is_array($data) ? ($data['error-codes'] ?? null) : null,
                'hostname' => is_array($data) ? ($data['hostname'] ?? null) : null,
                'action' => is_array($data) ? ($data['action'] ?? null) : null,
            ]);

            $this->fail();
        }

        if ($this->provider() === 'turnstile' && ! $this->usingTurnstileTestingSecret($context)) {
            if (
                $context !== null
                && ($data['action'] ?? null) !== $this->turnstileAction($context)
            ) {
                Log::warning('Captcha validation rejected: unexpected action', [
                    'provider' => $this->provider(),
                    'context' => $context,
                    'host' => $request->getHost(),
                    'path' => $request->path(),
                    'expected_action' => $this->turnstileAction($context),
                    'received_action' => $data['action'] ?? null,
                ]);

                $this->fail();
            }

            $hostname = $data['hostname'] ?? null;

            if (
                ! is_string($hostname)
                || $hostname === ''
                || strcasecmp($hostname, $request->getHost()) !== 0
            ) {
                Log::warning('Captcha validation rejected: hostname mismatch', [
                    'provider' => $this->provider(),
                    'context' => $context,
                    'path' => $request->path(),
                    'request_host' => $request->getHost(),
                    'turnstile_hostname' => $hostname,
                ]);

                $this->fail();
            }
        }
    }

    private function usingTurnstileTestingSecret(?string $context = null): bool
    {
        return in_array($this->secret($context), [
            '1x0000000000000000000000000000000AA',
            '2x0000000000000000000000000000000AA',
            '3x0000000000000000000000000000000AA',
        ], true);
    }

    protected function tokenFromRequest(Request $request): ?string
    {
        foreach (['cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function verificationEndpoint(): string
    {
        return match ($this->provider()) {
            'hcaptcha' => 'https://hcaptcha.com/siteverify',
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            default => 'https://www.google.com/recaptcha/api/siteverify',
        };
    }

    public function turnstileAction(string $context): string
    {
        return str_replace('_', '-', $context);
    }

    protected function fail(): never
    {
        throw ValidationException::withMessages([
            'captcha' => __('messages.Captcha validation failed'),
        ]);
    }
}
