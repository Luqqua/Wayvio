<?php

namespace Modules\CustomDomains\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\CustomDomains\Services\DomainSSLService;

class UserCustomDomain extends Model
{
    protected $fillable = [
        'domain',
        'page_id',
        'verification_token',
    ];

    protected $hidden = [
        'cloudflare_verification_errors',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'suspended_at' => 'datetime',
        'pending_deletion_at' => 'datetime',
        'delete_after_at' => 'datetime',
        'deleted_at_lifecycle' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $domain): void {
            try {
                $tenantOwnerUserId = (int) ($domain->tenant_owner_user_id ?? 0);
                if ($tenantOwnerUserId <= 0) {
                    $tenantOwnerUserId = (int) ($domain->user_id ?? 0);
                }

                if ($tenantOwnerUserId > 0) {
                    app(DomainSSLService::class)->queueTenantReconcileByTenantOwner(
                        $tenantOwnerUserId,
                        [(string) $domain->domain],
                        $tenantOwnerUserId
                    );
                } else {
                    app(DomainSSLService::class)->queueCleanupByHostname((string) $domain->domain);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to queue custom domain SSL cleanup from model event', [
                    'domain_id' => (int) $domain->id,
                    'domain' => (string) $domain->domain,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}
