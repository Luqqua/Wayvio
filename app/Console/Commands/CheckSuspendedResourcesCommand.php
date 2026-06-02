<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSuspendedResourcesCommand extends Command
{
    protected $signature = 'check_suspended_resources';
    protected $description = 'Move long-suspended downgrade/non-payment resources (domains, analytics, hubs) to pending_deletion and send final warnings.';

    public function handle(AccountLifecycleService $lifecycle, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        Log::info('Lifecycle job started', [
            'job' => 'check_suspended_resources',
            'started_at' => $startedAt->toIso8601String(),
        ]);

        try {
            $processed = $lifecycle->handleSuspendedResources();

            $finishedAt = now();
            Log::info('Lifecycle job finished', [
                'job' => 'check_suspended_resources',
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
                'items_processed' => $processed,
            ]);

            $runLogger->record('check_suspended_resources', $startedAt, $finishedAt, 'success', [], $processed);

            $this->info('check_suspended_resources completed');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $finishedAt = now();
            Log::error('Lifecycle job failed', ['job' => 'check_suspended_resources', 'error' => $e->getMessage()]);
            $runLogger->record('check_suspended_resources', $startedAt, $finishedAt, 'failed', [], null, null, $e->getMessage());
            throw $e;
        }
    }
}
