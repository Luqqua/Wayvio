<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * @var array<string,array<int,string>>
     */
    private array $sourceColumns = [
        'links' => ['user_id'],
        'user_custom_domains' => ['user_id'],
        'analytics_events_extended' => ['user_id'],
        'partner_attributions' => ['referred_user_id', 'partner_user_id'],
        'analytics_resource_states' => ['user_id'],
        'compliance_audit_log' => ['user_id', 'actor_user_id'],
    ];

    /**
     * @var array<int,int>
     */
    private array $ownerCache = [];

    public function up(): void
    {
        foreach (array_keys($this->sourceColumns) as $table) {
            $this->ensureTenantOwnerColumn($table);
        }

        foreach ($this->sourceColumns as $table => $columns) {
            $this->backfillTenantOwnerColumn($table, $columns);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->sourceColumns) as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_owner_user_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('tenant_owner_user_id');
            });
        }
    }

    private function ensureTenantOwnerColumn(string $table): void
    {
        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_owner_user_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('tenant_owner_user_id')->nullable()->index();
        });
    }

    /**
     * @param array<int,string> $candidateColumns
     */
    private function backfillTenantOwnerColumn(string $table, array $candidateColumns): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_owner_user_id')) {
            return;
        }

        if (!Schema::hasColumn($table, 'id')) {
            return;
        }

        $sourceColumn = $this->firstExistingSourceColumn($table, $candidateColumns);
        if ($sourceColumn === null) {
            return;
        }

        DB::table($table)
            ->select('id', $sourceColumn)
            ->whereNull('tenant_owner_user_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $sourceColumn): void {
                foreach ($rows as $row) {
                    $resourceUserId = (int) ($row->{$sourceColumn} ?? 0);
                    if ($resourceUserId <= 0) {
                        continue;
                    }

                    $tenantOwnerId = $this->resolveTenantOwnerId($resourceUserId);
                    if ($tenantOwnerId === null || $tenantOwnerId <= 0) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', (int) $row->id)
                        ->whereNull('tenant_owner_user_id')
                        ->update([
                            'tenant_owner_user_id' => $tenantOwnerId,
                        ]);
                }
            }, 'id');
    }

    /**
     * @param array<int,string> $candidateColumns
     */
    private function firstExistingSourceColumn(string $table, array $candidateColumns): ?string
    {
        foreach ($candidateColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function resolveTenantOwnerId(int $resourceUserId): ?int
    {
        if ($resourceUserId <= 0) {
            return null;
        }

        if (array_key_exists($resourceUserId, $this->ownerCache)) {
            return $this->ownerCache[$resourceUserId];
        }

        if (
            !Schema::hasTable('agency_hubs')
            || !Schema::hasColumn('agency_hubs', 'agency_user_id')
            || !Schema::hasColumn('agency_hubs', 'managed_user_id')
        ) {
            return $this->ownerCache[$resourceUserId] = $resourceUserId;
        }

        $query = DB::table('agency_hubs')
            ->where('managed_user_id', $resourceUserId)
            ->select('agency_user_id')
            ->distinct();

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        $owners = $query
            ->pluck('agency_user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if (count($owners) > 1) {
            return null;
        }

        if (count($owners) === 1) {
            return $this->ownerCache[$resourceUserId] = (int) ($owners[0] ?? $resourceUserId);
        }

        return $this->ownerCache[$resourceUserId] = $resourceUserId;
    }
};
