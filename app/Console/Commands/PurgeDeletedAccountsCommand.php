<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeDeletedAccountsCommand extends Command
{
    protected $signature = 'lifecycle:purge-deleted-accounts
        {--limit= : Max deleted-account rows to purge in this run (defaults to configured batch size)}
        {--dry-run : Report what would be purged without deleting data}';

    protected $description = 'Purge deleted_accounts evidence rows after retained_until has elapsed.';

    public function handle(): int
    {
        if (!Schema::hasTable('deleted_accounts') || !Schema::hasColumn('deleted_accounts', 'retained_until')) {
            $this->info('Skipping purge: deleted_accounts table or retained_until column is not available.');
            return self::SUCCESS;
        }

        $configuredBatch = max(1, (int) config('billing.lifecycle.deleted_account_purge_batch_size', 200));
        $optionLimit = max(0, (int) $this->option('limit'));
        $limit = min(5000, $optionLimit > 0 ? $optionLimit : $configuredBatch);
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('deleted_accounts')
            ->select(['id', 'retained_until'])
            ->whereNotNull('retained_until')
            ->where('retained_until', '<=', now())
            ->orderBy('retained_until')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No deleted_accounts rows eligible for purge at this time.');
            return self::SUCCESS;
        }

        $ids = $rows
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            $this->info('No valid deleted_accounts ids found for purge.');
            return self::SUCCESS;
        }

        $deletedCount = $dryRun
            ? count($ids)
            : DB::table('deleted_accounts')->whereIn('id', $ids)->delete();

        $mode = $dryRun ? 'DRY-RUN' : 'EXECUTED';
        $this->info(sprintf(
            '[%s] Purged deleted_accounts rows=%d (eligible=%d, limit=%d)',
            $mode,
            (int) $deletedCount,
            count($ids),
            $limit,
        ));

        return self::SUCCESS;
    }
}
