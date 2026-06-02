<?php

namespace Modules\CustomDomains\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\CustomDomains\Models\UserCustomDomain;
use Modules\CustomDomains\Services\DomainSSLService;

class CloudflareDomainSyncCommand extends Command
{
    protected $signature = 'domains:cloudflare-sync {--domain= : Reconcile one hostname only} {--dry-run : Show what would be queued}';

    protected $description = 'Queue tenant-level Cloudflare custom domain reconcile jobs.';

    public function __construct(private readonly DomainSSLService $domainSslService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('user_custom_domains')) {
            $this->warn('user_custom_domains table does not exist.');
            return self::SUCCESS;
        }

        $domain = strtolower(trim((string) $this->option('domain')));
        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;
        $tenantOwners = [];

        $query = UserCustomDomain::query();

        if (Schema::hasColumn('user_custom_domains', 'lifecycle_status')) {
            $query->where(function ($lifecycleQuery): void {
                $lifecycleQuery
                    ->whereNull('lifecycle_status')
                    ->orWhere('lifecycle_status', '!=', 'deleted');
            });
        }

        if ($domain !== '') {
            $query->whereRaw('LOWER(domain) = ?', [$domain]);
        }

        $query->orderBy('id')->chunkById(100, function ($domains) use (&$tenantOwners): void {
            foreach ($domains as $domainRecord) {
                $tenantOwnerUserId = (int) ($domainRecord->tenant_owner_user_id ?? 0);
                if ($tenantOwnerUserId <= 0) {
                    $tenantOwnerUserId = (int) ($domainRecord->user_id ?? 0);
                }
                if ($tenantOwnerUserId <= 0) {
                    continue;
                }

                $tenantOwners[$tenantOwnerUserId] = ($tenantOwners[$tenantOwnerUserId] ?? 0) + 1;
            }
        });

        foreach (array_keys($tenantOwners) as $tenantOwnerUserId) {
            $queued++;
            if ($dryRun) {
                $this->line('Would queue tenant reconcile for owner ' . $tenantOwnerUserId . ' (domains=' . $tenantOwners[$tenantOwnerUserId] . ')');
                continue;
            }

            $this->domainSslService->queueTenantReconcileByTenantOwner((int) $tenantOwnerUserId, [], (int) $tenantOwnerUserId);
        }

        $verb = $dryRun ? 'Matched' : 'Queued';
        $this->info($verb . ' ' . $queued . ' tenant domain reconcile job(s).');

        return self::SUCCESS;
    }
}
