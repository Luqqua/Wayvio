<?php

namespace Modules\CustomDomains\Services;

use App\Jobs\CleanupDomainSslCertificateJob;
use App\Jobs\ReconcileTenantDomainsJob;
use App\Services\Domains\DomainsClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\CustomDomains\Models\UserCustomDomain;

class DomainSSLService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROVISIONING = 'provisioning';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_FAILED = 'failed';

    public function __construct(private readonly DomainsClient $domainsClient)
    {
    }

    public function updateStatus(UserCustomDomain $domain, string $status = self::STATUS_PENDING): UserCustomDomain
    {
        $domain->ssl_status = $status;
        $domain->last_checked_at = now();
        $domain->save();

        return $domain;
    }

    public function queueProvisioning(UserCustomDomain $domain): void
    {
        if (!$this->enabled()) {
            return;
        }

        if ((string) $domain->ssl_status !== self::STATUS_ACTIVE) {
            $this->updateStatus($domain, self::STATUS_PENDING);
        }

        $this->queueTenantReconcileByDomain($domain);
    }

    public function queueCleanupByHostname(string $domain): void
    {
        if (!$this->enabled()) {
            return;
        }

        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            return;
        }

        $tenantOwnerUserId = $this->resolveTenantOwnerByHostname($normalized);
        if ($tenantOwnerUserId !== null) {
            $this->queueTenantReconcileByTenantOwner($tenantOwnerUserId, [$normalized], $tenantOwnerUserId);
            return;
        }

        // Fallback path when no tenant context can be resolved from local rows.
        CleanupDomainSslCertificateJob::dispatch($normalized)
            ->onQueue($this->queueName());
    }

    /**
     * @param array<int,string> $extraDeleteHostnames
     */
    public function queueTenantReconcileByTenantOwner(
        int $tenantOwnerUserId,
        array $extraDeleteHostnames = [],
        ?int $actorUserId = null
    ): void {
        if (!$this->enabled() || $tenantOwnerUserId <= 0) {
            return;
        }

        ReconcileTenantDomainsJob::dispatch(
            $tenantOwnerUserId,
            $this->normalizeHostnames($extraDeleteHostnames),
            $actorUserId ?? $tenantOwnerUserId
        )->onQueue($this->queueName());
    }

    /**
     * @param array<int,string> $extraDeleteHostnames
     */
    public function queueTenantReconcileByResourceUserId(
        int $resourceUserId,
        array $extraDeleteHostnames = [],
        ?int $actorUserId = null
    ): void {
        $tenantOwnerUserId = $this->resolveTenantOwnerUserId($resourceUserId);
        if ($tenantOwnerUserId === null) {
            return;
        }

        $this->queueTenantReconcileByTenantOwner(
            $tenantOwnerUserId,
            $extraDeleteHostnames,
            $actorUserId ?? $tenantOwnerUserId
        );
    }

    /**
     * @param array<int,string> $extraDeleteHostnames
     */
    public function queueTenantReconcileByDomain(
        UserCustomDomain $domain,
        array $extraDeleteHostnames = [],
        ?int $actorUserId = null
    ): void {
        $tenantOwnerUserId = $this->tenantOwnerForDomain($domain);
        if ($tenantOwnerUserId === null) {
            return;
        }

        $this->queueTenantReconcileByTenantOwner(
            $tenantOwnerUserId,
            $extraDeleteHostnames,
            $actorUserId ?? $tenantOwnerUserId
        );
    }

    /**
     * @param array<int,string> $extraDeleteHostnames
     */
    public function reconcileTenantDomains(
        int $tenantOwnerUserId,
        array $extraDeleteHostnames = [],
        ?int $actorUserId = null
    ): void {
        if (!$this->enabled()) {
            throw new \RuntimeException('Custom domain Cloudflare provisioning is disabled');
        }

        if ($tenantOwnerUserId <= 0) {
            throw new \RuntimeException('Invalid tenant owner user id for domain reconcile');
        }

        $response = $this->domainsClient->reconcileTenantDomains(
            $tenantOwnerUserId,
            $this->normalizeHostnames($extraDeleteHostnames),
            $actorUserId ?? $tenantOwnerUserId
        );

        if ($this->domainsClient->isErrorResponse($response)) {
            $payload = $this->domainsClient->errorPayload($response);
            throw new \RuntimeException($this->errorMessage($payload, 'Cloudflare tenant domain reconcile failed'));
        }

        if (!is_array($response)) {
            throw new \RuntimeException('Domains internal API unavailable during Cloudflare tenant domain reconcile');
        }

        Log::info('Custom domains tenant reconcile synced', [
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'upsert_count' => (int) ($response['upsert_count'] ?? 0),
            'delete_count' => (int) ($response['delete_count'] ?? 0),
        ]);
    }

    public function provisionCertificateByDomainId(int $domainId): void
    {
        if ($domainId <= 0) {
            return;
        }

        /** @var UserCustomDomain|null $domain */
        $domain = UserCustomDomain::query()->find($domainId);
        if (!$domain) {
            return;
        }

        $this->provisionCertificate($domain);
    }

    public function provisionCertificate(UserCustomDomain $domain): void
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Custom domain Cloudflare provisioning is disabled');
        }

        $domain->refresh();
        $hostname = $this->normalizeDomain((string) $domain->domain);
        if ($hostname === '') {
            throw new \RuntimeException('Invalid custom domain value');
        }

        if ((int) $domain->id <= 0 || (int) $domain->user_id <= 0) {
            return;
        }

        $this->updateStatus($domain, self::STATUS_PROVISIONING);
        $tenantOwnerUserId = $this->tenantOwnerForDomain($domain);
        if ($tenantOwnerUserId === null) {
            throw new \RuntimeException('Unable to resolve tenant owner for custom domain');
        }

        $this->reconcileTenantDomains($tenantOwnerUserId, [], $tenantOwnerUserId);

        $domain->refresh();

        Log::info('Custom domain Cloudflare state synced via tenant reconcile', [
            'domain_id' => (int) $domain->id,
            'domain' => $hostname,
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'status' => (string) $domain->status,
            'ssl_status' => (string) $domain->ssl_status,
            'cloudflare_hostname_status' => (string) ($domain->cloudflare_hostname_status ?? ''),
            'cloudflare_ssl_status' => (string) ($domain->cloudflare_ssl_status ?? ''),
        ]);
    }

    public function cleanupCertificate(string $domain): void
    {
        if (!$this->enabled()) {
            return;
        }

        $hostname = $this->normalizeDomain($domain);
        if ($hostname === '') {
            return;
        }

        $response = $this->domainsClient->cleanupHostname($hostname);
        if ($this->domainsClient->isErrorResponse($response)) {
            $payload = $this->domainsClient->errorPayload($response);
            throw new \RuntimeException($this->errorMessage($payload, 'Cloudflare custom hostname cleanup failed'));
        }

        if (!is_array($response)) {
            throw new \RuntimeException('Domains internal API unavailable during Cloudflare custom hostname cleanup');
        }

        Log::info('Custom domain Cloudflare cleanup processed', [
            'domain' => $hostname,
            'status' => (string) ($response['status'] ?? ''),
        ]);
    }

    public function markDomainFailedById(int $domainId, string $reason = ''): void
    {
        if ($domainId <= 0) {
            return;
        }

        /** @var UserCustomDomain|null $domain */
        $domain = UserCustomDomain::query()->find($domainId);
        if (!$domain) {
            return;
        }

        $this->updateStatus($domain, self::STATUS_FAILED);

        Log::error('Custom domain Cloudflare provisioning failed', [
            'domain_id' => $domainId,
            'domain' => (string) $domain->domain,
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }

    private function queueName(): string
    {
        $configured = trim((string) config('custom-domains.queue.name', 'domains'));
        return $configured !== '' ? $configured : 'domains';
    }

    private function enabled(): bool
    {
        return (string) config('custom-domains.provider', 'cloudflare') === 'cloudflare'
            && $this->domainsClient->enabled();
    }

    private function errorMessage(array $payload, string $fallback): string
    {
        $message = $payload['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $error = $payload['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        if (is_array($error)) {
            $nested = $error['message'] ?? $error['code'] ?? null;
            if (is_string($nested) && trim($nested) !== '') {
                return trim($nested);
            }
        }

        return $fallback;
    }

    private function normalizeDomain(string $value): string
    {
        $normalized = strtolower(trim($value, ". \t\n\r\0\x0B"));
        if ($normalized === '' || strlen($normalized) > 253) {
            return '';
        }

        if (preg_match('/^[a-z0-9.-]+$/', $normalized) !== 1) {
            return '';
        }

        if (str_contains($normalized, '..') || !str_contains($normalized, '.')) {
            return '';
        }

        $labels = explode('.', $normalized);
        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return '';
            }

            if ($label[0] === '-' || substr($label, -1) === '-') {
                return '';
            }

            if (preg_match('/^[a-z0-9-]+$/', $label) !== 1) {
                return '';
            }
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $hostnames
     * @return array<int,string>
     */
    private function normalizeHostnames(array $hostnames): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($hostname): string => $this->normalizeDomain((string) $hostname),
            $hostnames
        ), static fn (string $hostname): bool => $hostname !== '')));
    }

    private function tenantOwnerForDomain(UserCustomDomain $domain): ?int
    {
        $direct = (int) ($domain->tenant_owner_user_id ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        $resourceUserId = (int) ($domain->user_id ?? 0);
        return $this->resolveTenantOwnerUserId($resourceUserId);
    }

    private function resolveTenantOwnerByHostname(string $hostname): ?int
    {
        if ($hostname === '' || !Schema::hasTable('user_custom_domains')) {
            return null;
        }

        $query = UserCustomDomain::query()
            ->whereRaw('LOWER(domain) = ?', [$hostname])
            ->orderByDesc('id');

        $domain = $query->first();
        if (!$domain instanceof UserCustomDomain) {
            return null;
        }

        return $this->tenantOwnerForDomain($domain);
    }

    private function resolveTenantOwnerUserId(int $resourceUserId): ?int
    {
        if ($resourceUserId <= 0) {
            return null;
        }

        if (
            !Schema::hasTable('agency_hubs')
            || !Schema::hasColumn('agency_hubs', 'agency_user_id')
            || !Schema::hasColumn('agency_hubs', 'managed_user_id')
        ) {
            return $resourceUserId;
        }

        $query = DB::table('agency_hubs')
            ->where('managed_user_id', $resourceUserId);

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        $ownerIds = $query
            ->distinct()
            ->pluck('agency_user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if ($ownerIds === []) {
            return $resourceUserId;
        }

        $uniqueOwnerIds = array_values(array_unique($ownerIds));
        if (count($uniqueOwnerIds) > 1) {
            Log::warning('Multiple tenant owners resolved for domain resource user', [
                'resource_user_id' => $resourceUserId,
                'tenant_owner_user_ids' => $uniqueOwnerIds,
            ]);
        }

        return (int) $uniqueOwnerIds[0];
    }
}
