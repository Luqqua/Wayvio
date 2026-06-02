<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingClient;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Tiers\Models\UserSubscription;

class BillingReconcileCheckCommand extends Command
{
    protected $signature = 'billing:reconcile-check {--limit=500 : Max subscriptions to check per run}';
    protected $description = 'Compare local stripe_status against Stripe (via billing MS) and log any discrepancies.';

    public function handle(
        BillingClient $billing,
        ComplianceAuditService $complianceAudit,
        JobRunLogger $runLogger
    ): int {
        if (!$billing->enabled()) {
            $this->info('billing:reconcile-check skipped — billing MS disabled.');
            return self::SUCCESS;
        }

        $startedAt = now();
        $limit = max(1, (int) $this->option('limit'));

        Log::info('Billing reconcile check started', [
            'job' => 'billing:reconcile-check',
            'limit' => $limit,
            'started_at' => $startedAt->toIso8601String(),
        ]);

        $checked = 0;
        $mismatches = 0;
        $errors = 0;

        try {
            UserSubscription::query()
                ->whereNotNull('stripe_subscription_id')
                ->whereNotNull('stripe_customer_id')
                ->orderBy('id')
                ->limit($limit)
                ->chunkById(50, function ($subscriptions) use (
                    $billing,
                    $complianceAudit,
                    &$checked,
                    &$mismatches,
                    &$errors
                ): void {
                    foreach ($subscriptions as $subscription) {
                        $checked++;
                        $userId = (int) $subscription->user_id;
                        $localStatus = strtolower(trim((string) ($subscription->stripe_status ?? '')));
                        $localCancelAtPeriodEnd = (bool) ($subscription->cancel_at_period_end ?? false);
                        $localPendingTierId = $subscription->pending_tier_id !== null
                            ? (int) $subscription->pending_tier_id
                            : null;

                        try {
                            // Billing MS enforces actor_user_id === user_id for subscription-state lookups.
                            // `refresh=true` forces a Stripe sync before the state is returned.
                            $remote = $billing->subscriptionState($userId, $userId, true);
                        } catch (\Throwable $e) {
                            $errors++;
                            Log::warning('billing:reconcile-check could not fetch remote state', [
                                'user_id' => $userId,
                                'error' => $e->getMessage(),
                            ]);
                            continue;
                        }

                        if (!is_array($remote)) {
                            $errors++;
                            continue;
                        }

                        $remoteSubscription = is_array($remote['subscription'] ?? null)
                            ? $remote['subscription']
                            : [];
                        $remoteStatus = strtolower(trim((string) (
                            $remoteSubscription['stripe_status']
                            ?? $remote['stripe_status']
                            ?? $remote['status']
                            ?? ''
                        )));
                        $remoteCancelAtPeriodEnd = (bool) ($remoteSubscription['cancel_at_period_end'] ?? false);
                        $remotePendingTierId = isset($remoteSubscription['pending_tier_id'])
                            ? (int) $remoteSubscription['pending_tier_id']
                            : null;

                        if (
                            $remoteStatus === ''
                            || (
                                $remoteStatus === $localStatus
                                && $remoteCancelAtPeriodEnd === $localCancelAtPeriodEnd
                                && $remotePendingTierId === $localPendingTierId
                            )
                        ) {
                            continue;
                        }

                        $mismatches++;

                        Log::warning('billing:reconcile-check mismatch', [
                            'user_id' => $userId,
                            'local_stripe_status' => $localStatus,
                            'remote_stripe_status' => $remoteStatus,
                            'local_cancel_at_period_end' => $localCancelAtPeriodEnd,
                            'remote_cancel_at_period_end' => $remoteCancelAtPeriodEnd,
                            'local_pending_tier_id' => $localPendingTierId,
                            'remote_pending_tier_id' => $remotePendingTierId,
                        ]);

                        $complianceAudit->record(
                            eventType: 'stripe_db_mismatch',
                            userId: $userId,
                            actorUserId: null,
                            source: 'billing.reconcile_check',
                            metadata: [
                                'local_stripe_status' => $localStatus,
                                'remote_stripe_status' => $remoteStatus,
                                'local_cancel_at_period_end' => $localCancelAtPeriodEnd,
                                'remote_cancel_at_period_end' => $remoteCancelAtPeriodEnd,
                                'local_pending_tier_id' => $localPendingTierId,
                                'remote_pending_tier_id' => $remotePendingTierId,
                                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                            ],
                        );
                    }
                });

            $finishedAt = now();
            Log::info('Billing reconcile check finished', [
                'job' => 'billing:reconcile-check',
                'checked' => $checked,
                'mismatches' => $mismatches,
                'errors' => $errors,
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
            ]);

            $runLogger->record(
                'billing:reconcile-check',
                $startedAt,
                $finishedAt,
                'success',
                ['mismatches' => $mismatches, 'errors' => $errors],
                $checked,
                $mismatches,
            );

            $this->info(sprintf(
                'billing:reconcile-check checked=%d mismatches=%d errors=%d',
                $checked,
                $mismatches,
                $errors,
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $finishedAt = now();
            Log::error('Billing reconcile check failed', ['job' => 'billing:reconcile-check', 'error' => $e->getMessage()]);
            $runLogger->record('billing:reconcile-check', $startedAt, $finishedAt, 'failed', [], $checked, null, $e->getMessage());
            throw $e;
        }
    }
}
