<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingClient
{
    public function enabled(): bool
    {
        return (bool) config('internal-api.billing.enabled', false);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function createCheckoutSession(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/checkout-session',
            $payload,
            [],
            false,
            $this->operationTimeout('checkout_timeout', 10.0),
            $actorUserId
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function createPortalSession(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/portal-session',
            $payload,
            [],
            false,
            $this->operationTimeout('portal_timeout', 10.0),
            $actorUserId
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function changeSubscription(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/change-subscription',
            $payload,
            [],
            true,
            $this->operationTimeout('change_timeout', 20.0),
            $actorUserId
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function previewSubscriptionChange(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/change-subscription-preview',
            $payload,
            [],
            true,
            $this->operationTimeout('change_timeout', 10.0),
            $actorUserId
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function cancelOnAccountDelete(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/cancel-on-account-delete',
            $payload,
            [],
            true,
            $this->operationTimeout('delete_timeout', 20.0),
            $actorUserId
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function processWebhook(string $payload, ?string $signature): ?array
    {
        return $this->request(
            'post',
            '/api/billing/webhook',
            [
                'payload' => $payload,
                'signature' => $signature,
            ],
            [],
            true,
            $this->operationTimeout('webhook_timeout', 15.0)
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function confirmCheckout(array $payload, ?int $actorUserId = null): ?array
    {
        return $this->request(
            'post',
            '/api/billing/checkout-confirm',
            $payload,
            [],
            true,
            $this->operationTimeout('confirm_timeout', 10.0),
            $actorUserId
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function subscriptionState(int $userId, ?int $actorUserId = null, bool $refresh = false): ?array
    {
        return $this->request(
            'get',
            '/api/billing/subscription-state',
            [],
            [
                'user_id' => $userId,
                'refresh' => $refresh ? 1 : 0,
            ],
            false,
            $this->operationTimeout('state_timeout', 5.0),
            $actorUserId
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
        ?int $actorUserId = null
    ): ?array
    {
        $base = $this->baseUrl();
        if (!$base) {
            return null;
        }

        if ($actorUserId !== null && $actorUserId > 0) {
            if (strtolower($method) === 'get') {
                $query['actor_user_id'] = $actorUserId;
            } else {
                $payload['actor_user_id'] = $actorUserId;
            }
        }

        $url = rtrim($base, '/') . $path;
        $timeout = $timeoutOverride ?? $this->timeout();

        try {
            $pending = Http::withHeaders($this->headers())
                ->timeout($timeout)
                ->acceptJson();

            $response = match (strtolower($method)) {
                'get' => $pending->get($url, $query),
                default => $pending->post($url, $payload),
            };

            if ($response->failed()) {
                $decoded = $response->json();
                $errorCode = is_array($decoded) ? data_get($decoded, 'error.code') : null;
                $errorMessage = is_array($decoded) ? data_get($decoded, 'error.message') : null;

                Log::warning('Billing internal API request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'error_code' => is_string($errorCode) ? $errorCode : null,
                    'error_message' => is_string($errorMessage) ? $errorMessage : null,
                ]);

                if ($returnErrorPayload) {
                    if (is_array($decoded)) {
                        $decoded['_status'] = $response->status();
                        return $decoded;
                    }
                }

                return null;
            }

            $decoded = $response->json();
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            Log::debug('Billing internal API unavailable', [
                'path' => $path,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function operationTimeout(string $configKey, float $fallbackSeconds): float
    {
        $configured = config('internal-api.billing.' . $configKey);
        if (is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured;
        }

        return max($fallbackSeconds, $this->timeout());
    }

    private function baseUrl(): ?string
    {
        $base = config('internal-api.billing.base_url') ?: config('internal-api.base_url');

        return is_string($base) && $base !== '' ? $base : null;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'Wayvio-Billing-Bridge',
        ];

        $key = config('internal-api.billing.api_key') ?: config('internal-api.api_key');
        if (is_string($key) && $key !== '') {
            $headers['X-API-KEY'] = $key;
        }

        return $headers;
    }

    private function timeout(): float
    {
        $timeout = config('internal-api.billing.timeout', config('internal-api.timeout', 2.0));

        return is_numeric($timeout) ? (float) $timeout : 2.0;
    }
}
