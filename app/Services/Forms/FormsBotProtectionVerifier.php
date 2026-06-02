<?php

namespace App\Services\Forms;

use App\Services\Security\CaptchaVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FormsBotProtectionVerifier
{
    public function __construct(
        private readonly CaptchaVerifier $captchaVerifier,
    ) {
    }

    public function provider(): ?string
    {
        $provider = strtolower(trim((string) config('forms.bot_protection.provider', '')));

        return $provider !== '' && $provider !== 'none' ? $provider : null;
    }

    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            'cap' => $this->capConfigured(),
            'turnstile' => $this->captchaVerifier->provider() === 'turnstile',
            default => false,
        };
    }

    public function shouldRenderWidget(string $formKey): bool
    {
        $provider = $this->provider();
        if ($provider === 'cap') {
            return $this->capConfigured();
        }

        if ($provider === 'turnstile') {
            return $this->captchaVerifier->isConfigured($this->turnstileContext($formKey));
        }

        return false;
    }

    public function validate(Request $request, string $formKey): void
    {
        $required = (bool) config('forms.bot_protection.required', true);
        $provider = $this->provider();

        if ($provider === null) {
            if ($required) {
                $this->fail('Bot protection is not configured.');
            }

            return;
        }

        if ($provider === 'turnstile') {
            $this->validateTurnstile($request, $formKey, $required);
            return;
        }

        if ($provider === 'cap') {
            $this->validateCap($request, $required);
            return;
        }

        Log::warning('Forms bot protection rejected: unsupported provider', [
            'provider' => $provider,
            'host' => $request->getHost(),
            'path' => $request->path(),
        ]);

        $this->fail('Unsupported bot protection provider.');
    }

    private function validateTurnstile(Request $request, string $formKey, bool $required): void
    {
        $context = $this->turnstileContext($formKey);
        if ($required) {
            if ($this->captchaVerifier->provider() !== 'turnstile' || !$this->captchaVerifier->isConfigured($context)) {
                $this->fail('Turnstile is not configured.');
            }

            $this->captchaVerifier->validate($request, $context);
            return;
        }

        $this->captchaVerifier->validateIfConfigured($request, $context);
    }

    private function validateCap(Request $request, bool $required): void
    {
        if (!$this->capConfigured()) {
            if ($required) {
                $this->fail('Cap is not configured.');
            }

            return;
        }

        $token = $this->capTokenFromRequest($request);
        if ($token === null) {
            Log::warning('Forms bot protection rejected: missing Cap token', [
                'host' => $request->getHost(),
                'path' => $request->path(),
            ]);

            $this->fail('Cap token is missing.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((float) config('forms.cap.http_timeout', 5.0))
                ->post($this->capSiteverifyUrl(), [
                    'secret' => (string) config('forms.cap.secret'),
                    'response' => $token,
                ]);
        } catch (\Throwable $e) {
            report($e);
            $this->fail('Cap verification request failed.');
        }

        $data = $response->json();
        if (!$response->successful() || !is_array($data) || !($data['success'] ?? false)) {
            Log::warning('Forms bot protection rejected: Cap denied request', [
                'host' => $request->getHost(),
                'path' => $request->path(),
                'status' => $response->status(),
                'success' => is_array($data) ? ($data['success'] ?? null) : null,
                'error' => is_array($data) ? ($data['error'] ?? null) : null,
                'error_codes' => is_array($data) ? ($data['error-codes'] ?? null) : null,
            ]);

            $this->fail('Cap validation failed.');
        }
    }

    private function capConfigured(): bool
    {
        return trim((string) config('forms.cap.base_url')) !== ''
            && trim((string) config('forms.cap.verify_base_url')) !== ''
            && trim((string) config('forms.cap.site_key')) !== ''
            && trim((string) config('forms.cap.secret')) !== '';
    }

    private function capTokenFromRequest(Request $request): ?string
    {
        foreach (['cap-token', 'cap_token'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function capSiteverifyUrl(): string
    {
        return rtrim((string) config('forms.cap.verify_base_url'), '/') . '/'
            . rawurlencode((string) config('forms.cap.site_key')) . '/siteverify';
    }

    private function turnstileContext(string $formKey): string
    {
        return 'forms_' . $formKey;
    }

    private function fail(string $reason): never
    {
        Log::warning('Forms bot protection validation failed', [
            'reason' => $reason,
            'provider' => $this->provider(),
        ]);

        throw ValidationException::withMessages([
            'captcha' => __('messages.Captcha validation failed'),
        ]);
    }
}
