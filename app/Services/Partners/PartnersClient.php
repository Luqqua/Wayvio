<?php

namespace App\Services\Partners;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartnersClient
{
    public function enabled(): bool
    {
        return (bool) config('internal-api.partners.enabled', false);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function dashboard(int $partnerUserId, ?int $actorUserId = null): ?array
    {
        $resolvedActor = $actorUserId && $actorUserId > 0 ? $actorUserId : $partnerUserId;
        return $this->request(
            'get',
            '/api/partners/dashboard',
            [],
            [
                'partner_user_id' => $partnerUserId,
                'actor_user_id' => $resolvedActor,
            ],
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function onboardingStatus(int $partnerUserId, ?int $actorUserId = null): ?array
    {
        $resolvedActor = $actorUserId && $actorUserId > 0 ? $actorUserId : $partnerUserId;
        return $this->request(
            'get',
            '/api/partners/onboard-status/' . $partnerUserId,
            [],
            [
                'actor_user_id' => $resolvedActor,
            ],
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function startOnboarding(int $partnerUserId, string $email, ?string $country = null, ?int $actorUserId = null): ?array
    {
        $resolvedActor = $actorUserId && $actorUserId > 0 ? $actorUserId : $partnerUserId;
        $payload = [
            'user_id' => $partnerUserId,
            'email' => $email,
            'actor_user_id' => $resolvedActor,
        ];

        $normalizedCountry = strtoupper(trim((string) $country));
        if ($normalizedCountry !== '') {
            $payload['country'] = $normalizedCountry;
        }

        return $this->request(
            'post',
            '/api/partners/onboard-start',
            $payload,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function executePayout(int $batchId): ?array
    {
        return $this->request(
            'post',
            '/api/partners/payout-execute',
            [
                'batch_id' => $batchId,
            ],
            [],
            true,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function verifyPayoutTransfer(int $batchId, string $transferId): ?array
    {
        return $this->request(
            'post',
            '/api/partners/payout-transfer-verify',
            [
                'batch_id' => $batchId,
                'transfer_id' => $transferId,
            ],
            [],
            true,
            $this->operationTimeout('settle_verify_timeout', 10.0),
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function processWebhook(string $payload, ?string $signature): ?array
    {
        return $this->request(
            'post',
            '/api/partners/webhook',
            [
                'payload' => $payload,
                'signature' => $signature,
            ],
            [],
            true,
            $this->operationTimeout('webhook_timeout', 15.0),
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    private function request(
        string $method,
        string $path,
        array $payload = [],
        array $query = [],
        bool $returnErrorPayload = false,
        ?float $timeoutOverride = null,
    ): ?array {
        $base = $this->baseUrl();
        if (!$base) {
            return null;
        }

        $url = rtrim($base, '/') . $path;
        $timeout = $timeoutOverride ?? $this->timeout();

        try {
            $pending = Http::withHeaders($this->headers())
                ->timeout($timeout)
                ->acceptJson();

            $response = strtolower($method) === 'get'
                ? $pending->get($url, $query)
                : $pending->post($url, $payload);

            if ($response->failed()) {
                $decoded = $response->json();
                $errorCode = is_array($decoded) ? data_get($decoded, 'error.code') : null;
                $errorMessage = is_array($decoded) ? data_get($decoded, 'error.message') : null;

                Log::warning('Partners internal API request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'error_code' => is_string($errorCode) ? $errorCode : null,
                    'error_message' => is_string($errorMessage) ? $errorMessage : null,
                ]);

                if ($returnErrorPayload && is_array($decoded)) {
                    $decoded['_status'] = $response->status();
                    return $decoded;
                }

                return null;
            }

            $decoded = $response->json();
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            Log::debug('Partners internal API unavailable', [
                'path' => $path,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function operationTimeout(string $configKey, float $fallbackSeconds): float
    {
        $configured = config('internal-api.partners.' . $configKey);
        if (is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured;
        }

        return max($fallbackSeconds, $this->timeout());
    }

    private function baseUrl(): ?string
    {
        $base = config('internal-api.partners.base_url') ?: config('internal-api.base_url');

        return is_string($base) && $base !== '' ? $base : null;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'Wayvio-Partners-Bridge',
        ];

        $key = config('internal-api.partners.api_key') ?: config('internal-api.api_key');
        if (is_string($key) && $key !== '') {
            $headers['X-API-KEY'] = $key;
        }

        return $headers;
    }

    private function timeout(): float
    {
        $timeout = config('internal-api.partners.timeout', config('internal-api.timeout', 2.0));

        return is_numeric($timeout) ? (float) $timeout : 2.0;
    }
}
