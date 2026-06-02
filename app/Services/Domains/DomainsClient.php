<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DomainsClient
{
    private const ERROR_META_KEY = '__domains_client_error';

    public function enabled(): bool
    {
        return (bool) config('internal-api.domains.enabled', false);
    }

    public function isErrorResponse(?array $response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $meta = $response[self::ERROR_META_KEY] ?? null;

        return is_array($meta)
            && isset($meta['status'])
            && is_numeric($meta['status'])
            && isset($meta['payload'])
            && is_array($meta['payload']);
    }

    public function errorStatus(?array $response): ?int
    {
        if (!$this->isErrorResponse($response)) {
            return null;
        }

        return (int) ($response[self::ERROR_META_KEY]['status'] ?? 0) ?: null;
    }

    /**
     * @return array<string,mixed>
     */
    public function errorPayload(?array $response): array
    {
        if (!$this->isErrorResponse($response)) {
            return ['error' => 'domains_api_unavailable'];
        }

        $payload = $response[self::ERROR_META_KEY]['payload'] ?? null;
        if (is_array($payload)) {
            return $payload;
        }

        return ['error' => 'domains_api_unavailable'];
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    public function listByUser(int $userId, ?int $actorUserId = null): ?array
    {
        $response = $this->request('get', '/api/domains/user/' . $userId, [], [
            'actor_user_id' => $actorUserId ?? $userId,
        ]);

        return is_array($response) ? $response : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function createDomain(int $userId, string $domain, ?int $pageId = null, ?int $actorUserId = null): ?array
    {
        return $this->request('post', '/api/domains', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? $userId,
            'domain' => $domain,
            'page_id' => $pageId,
        ], [], false, $this->operationTimeout('create_timeout', 15.0));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function deleteDomain(int $userId, int $domainId, ?int $actorUserId = null): ?array
    {
        return $this->request('delete', '/api/domains/' . $domainId, [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? $userId,
        ], [], false, $this->operationTimeout('delete_timeout', 20.0));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function triggerVerify(int $userId, int $domainId, ?int $actorUserId = null): ?array
    {
        return $this->request('post', '/api/domains/' . $domainId . '/verify', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? $userId,
        ], [], false, $this->operationTimeout('verify_timeout', 15.0));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function syncDomain(int $userId, int $domainId, ?int $actorUserId = null): ?array
    {
        return $this->request('post', '/api/domains/' . $domainId . '/sync', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? $userId,
        ], [], false, $this->operationTimeout('sync_timeout', 15.0));
    }

    /**
     * @param array<int,string> $extraDeleteHostnames
     * @return array<string,mixed>|null
     */
    public function reconcileTenantDomains(int $tenantOwnerUserId, array $extraDeleteHostnames = [], ?int $actorUserId = null): ?array
    {
        $payload = [
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'actor_user_id' => $actorUserId ?? $tenantOwnerUserId,
        ];

        $normalizedDeletes = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $extraDeleteHostnames,
        ), static fn (string $value): bool => $value !== '')));

        if ($normalizedDeletes !== []) {
            $payload['extra_delete_hostnames'] = $normalizedDeletes;
        }

        return $this->request(
            'post',
            '/api/domains/reconcile-tenant',
            $payload,
            [],
            false,
            $this->operationTimeout('reconcile_timeout', 20.0)
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function cleanupHostname(string $domain, bool $force = false, ?int $tenantOwnerUserId = null): ?array
    {
        $payload = [
            'domain' => $domain,
            'force' => $force,
        ];

        if (is_int($tenantOwnerUserId) && $tenantOwnerUserId > 0) {
            $payload['tenant_owner_user_id'] = $tenantOwnerUserId;
        }

        return $this->request(
            'post',
            '/api/domains/cleanup-hostname',
            $payload,
            [],
            false,
            $this->operationTimeout('cleanup_timeout', 15.0)
        );
    }

    public function allowCheck(string $domain): ?bool
    {
        $response = $this->request('get', '/api/domains/allow', [], [
            'domain' => $domain,
        ], true);

        if (!is_array($response)) {
            return null;
        }

        return isset($response['allowed']) ? (bool) $response['allowed'] : null;
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
        bool $allowNotFound = false,
        ?float $timeoutOverride = null
    ): ?array
    {
        $base = $this->baseUrl();
        if (!$base) {
            return null;
        }

        $url = rtrim($base, '/') . $path;

        try {
            $pending = Http::withHeaders($this->headers())
                ->timeout($timeoutOverride ?? $this->timeout())
                ->acceptJson();

            $response = match (strtolower($method)) {
                'get' => $pending->get($url, $query),
                'delete' => $pending->send('DELETE', $url, ['json' => $payload]),
                default => $pending->post($url, $payload),
            };

            if ($response->status() === 404 && $allowNotFound) {
                $decoded = $response->json();
                return is_array($decoded) ? $decoded : ['allowed' => false];
            }

            if ($response->failed()) {
                $status = (int) $response->status();
                $decoded = $response->json();

                if ($status >= 400 && $status < 500) {
                    return [
                        self::ERROR_META_KEY => [
                            'status' => $status,
                            'payload' => is_array($decoded) ? $decoded : ['error' => 'domains_api_request_failed'],
                        ],
                    ];
                }

                Log::warning('Domains internal API request failed', [
                    'path' => $path,
                    'status' => $status,
                ]);

                return null;
            }

            $decoded = $response->json();
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            Log::debug('Domains internal API unavailable', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function baseUrl(): ?string
    {
        $base = config('internal-api.domains.base_url') ?: config('internal-api.base_url');

        return is_string($base) && $base !== '' ? $base : null;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'Wayvio-Domains-Bridge',
        ];

        $tenantContext = function_exists('currentTenantContext') ? currentTenantContext() : null;
        if ($tenantContext) {
            $headers['X-Tenant-Owner-User-Id'] = (string) $tenantContext->tenantOwnerUserId();
            $headers['X-Actor-User-Id'] = (string) $tenantContext->actorUserId();
            $headers['X-Resource-User-Id'] = (string) $tenantContext->activeResourceUserId();
        }

        $key = config('internal-api.domains.api_key') ?: config('internal-api.api_key');
        if (is_string($key) && $key !== '') {
            $headers['X-API-KEY'] = $key;
        }

        return $headers;
    }

    private function timeout(): float
    {
        $timeout = config('internal-api.domains.timeout', config('internal-api.timeout', 2.0));

        return is_numeric($timeout) ? (float) $timeout : 2.0;
    }

    private function operationTimeout(string $configKey, float $fallbackSeconds): float
    {
        $configured = config('internal-api.domains.' . $configKey);
        if (is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured;
        }

        return max($fallbackSeconds, $this->timeout());
    }
}
