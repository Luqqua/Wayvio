<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Tiers\Models\UserSubscription;

class CheckGracePeriodsCommand extends Command
{
    protected $signature = 'check_grace_periods {--limit=1000}';
    protected $description = 'Process agency hub grace periods and automatic suspension for over-quota hubs.';

    public function handle(AccountLifecycleService $lifecycle, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        $limit = max(1, (int) $this->option('limit'));

        Log::info('Lifecycle job started', [
            'job' => 'check_grace_periods',
            'limit' => $limit,
            'started_at' => $startedAt->toIso8601String(),
        ]);

        $processed = 0;
        try {
            $subscriptions = UserSubscription::query()
                ->whereNotNull('hub_slots_included')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($subscriptions as $subscription) {
                $processed++;
                $lifecycle->processAgencyGracePeriod($subscription);
            }

            $finishedAt = now();
            Log::info('Lifecycle job finished', [
                'job' => 'check_grace_periods',
                'processed_subscriptions' => $processed,
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
            ]);

            $runLogger->record('check_grace_periods', $startedAt, $finishedAt, 'success', [], $processed);

            $this->info(sprintf('check_grace_periods processed=%d', $processed));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $finishedAt = now();
            Log::error('Lifecycle job failed', [
                'job' => 'check_grace_periods',
                'error' => $e->getMessage(),
                'started_at' => $startedAt->toIso8601String(),
            ]);
            $runLogger->record('check_grace_periods', $startedAt, $finishedAt, 'failed', [], $processed, null, $e->getMessage());
            throw $e;
        }
    }
}
