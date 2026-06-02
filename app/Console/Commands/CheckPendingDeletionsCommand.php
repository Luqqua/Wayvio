<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingDeletionsCommand extends Command
{
    protected $signature = 'check_pending_deletions';
    protected $description = 'Delete pending_deletion resources/accounts (including hubs) after grace windows and record confirmation events.';

    public function handle(AccountLifecycleService $lifecycle, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        Log::info('Lifecycle job started', [
            'job' => 'check_pending_deletions',
            'started_at' => $startedAt->toIso8601String(),
        ]);

        try {
            $processed = $lifecycle->handlePendingDeletions();

            $finishedAt = now();
            Log::info('Lifecycle job finished', [
                'job' => 'check_pending_deletions',
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
                'items_processed' => $processed,
            ]);

            $runLogger->record('check_pending_deletions', $startedAt, $finishedAt, 'success', [], $processed);

            $this->info('check_pending_deletions completed');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $finishedAt = now();
            Log::error('Lifecycle job failed', ['job' => 'check_pending_deletions', 'error' => $e->getMessage()]);
            $runLogger->record('check_pending_deletions', $startedAt, $finishedAt, 'failed', [], null, null, $e->getMessage());
            throw $e;
        }
    }
}
