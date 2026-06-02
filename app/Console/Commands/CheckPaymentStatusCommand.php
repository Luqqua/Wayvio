<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Tiers\Models\UserSubscription;

class CheckPaymentStatusCommand extends Command
{
    protected $signature = 'check_payment_status {--limit=0}';
    protected $description = 'Process payment-failure lifecycle transitions and plan downgrade execution effects.';

    public function handle(AccountLifecycleService $lifecycle, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        $limit = max(0, (int) $this->option('limit'));
        $batchSize = 250;
        $processed = 0;
        $paymentCandidates = 0;
        $actionTotals = ['warning_sent' => 0, 'restricted' => 0, 'downgrade_warned' => 0, 'downgraded' => 0, 'reactivated' => 0];

        Log::info('Lifecycle job started', [
            'job' => 'check_payment_status',
            'limit' => $limit,
            'started_at' => $startedAt->toIso8601String(),
        ]);

        try {
            UserSubscription::query()
                ->orderBy('id')
                ->chunkById($batchSize, function ($subscriptions) use (
                    $lifecycle,
                    $limit,
                    &$processed,
                    &$paymentCandidates,
                    &$actionTotals
                ): bool {
                    foreach ($subscriptions as $subscription) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }

                        $processed++;
                        $lifecycle->syncSubscriptionLifecycle($subscription);

                        $stripeStatus = strtolower(trim((string) ($subscription->stripe_status ?? '')));
                        if (
                            $subscription->payment_failed_at !== null
                            || in_array($stripeStatus, ['past_due', 'unpaid', 'incomplete', 'payment_failed'], true)
                        ) {
                            $paymentCandidates++;
                            $result = $lifecycle->processPaymentTimeline($subscription->fresh());
                            foreach ($actionTotals as $k => $_) {
                                $actionTotals[$k] += (int) ($result[$k] ?? 0);
                            }
                        }
                    }

                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }

                    return true;
                }
            );

            $finishedAt = now();
            Log::info('Lifecycle job finished', [
                'job' => 'check_payment_status',
                'processed_subscriptions' => $processed,
                'payment_candidates' => $paymentCandidates,
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
            ]);

            $runLogger->record(
                'check_payment_status',
                $startedAt,
                $finishedAt,
                'success',
                array_merge(['payment_candidates' => $paymentCandidates], $actionTotals),
                $processed,
            );

            $this->info(sprintf(
                'check_payment_status processed=%d payment_candidates=%d',
                $processed,
                $paymentCandidates,
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $finishedAt = now();
            Log::error('Lifecycle job failed', [
                'job' => 'check_payment_status',
                'error' => $e->getMessage(),
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
            ]);
            $runLogger->record(
                'check_payment_status',
                $startedAt,
                $finishedAt,
                'failed',
                ['payment_candidates' => $paymentCandidates],
                $processed,
                null,
                $e->getMessage(),
            );
            throw $e;
        }
    }
}
