<?php

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use App\Models\UserData;
use App\Services\Billing\BillingClient;
use App\Services\Exceptions\AccountDeletionBlockedException;
use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Uploads\MediaStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\CustomDomains\Services\DomainSSLService;

class AccountDeletionService
{
    public function __construct(
        private readonly BillingClient $billingClient,
        private readonly AccountLifecycleService $lifecycleService,
        private readonly MediaStorageService $mediaStorage,
        private readonly DomainSSLService $domainSslService,
    ) {}

    /**
     * @throws AccountDeletionBlockedException
     */
    public function ensureStripeCancellationForSelfDelete(User $user): void
    {
        $userId = (int) $user->id;
        $this->ensureStripeCancellationBeforeDeletion($user, $this->normalizeDeletionContext([
            'source' => 'self_delete',
            'actor_user_id' => $userId > 0 ? $userId : null,
            'deletion_reason' => 'self',
        ]));
    }

    /**
     * @param array{source?:string,actor_user_id?:int|null,hard_delete_reason?:string|null,deletion_reason?:string|null,deletion_request_id?:string|null} $context
     */
    public function deleteUser(User $user, array $context = []): void
    {
        $userId = (int) $user->id;
        if ($userId <= 0) {
            throw new AccountDeletionBlockedException('Invalid account deletion target');
        }

        $context = $this->normalizeDeletionContext($context);
        if (($context['deletion_request_id'] ?? null) === null) {
            $context['deletion_request_id'] = (string) Str::uuid();
        }
        $deletionReason = $this->resolveDeletionReason($context);
        $preCancelSubscription = $this->captureSubscriptionStateBeforeCancel($userId);

        $billingCancellation = $this->ensureStripeCancellationBeforeDeletion($user, $context);

        $managedUserIds = $this->managedUserIdsForOwner($userId);
        $targetUserIds = array_values(array_unique(array_merge([$userId], $managedUserIds)));
        $filePaths = $this->collectOwnedFilePathsForUsers($targetUserIds);

        $snapshot = $this->buildDeletedAccountSnapshot(
            $userId,
            $deletionReason,
            $context,
            $billingCancellation,
            $preCancelSubscription
        );

        $targetUser = User::query()->find($userId) ?? $user;
        $this->lifecycleService->finalizeDeletionNow(
            $targetUser,
            $deletionReason,
            (string) ($context['source'] ?? 'unknown'),
        );

        DB::transaction(function () use ($snapshot, $targetUserIds, $userId): void {
            $this->persistDeletedAccountSnapshot($snapshot);
            $this->purgeResidualUserData($targetUserIds, $userId);
        });

        foreach ($filePaths as $relativePath) {
            $this->deleteRelativeFile($relativePath);
        }

        foreach ($targetUserIds as $targetId) {
            UserData::invalidateCache((int) $targetId);
        }
    }

    /**
     * @return array<int,int>
     */
    protected function managedUserIdsForOwner(int $userId): array
    {
        if ($userId <= 0 || !Schema::hasTable('agency_hubs') || !Schema::hasColumn('agency_hubs', 'agency_user_id') || !Schema::hasColumn('agency_hubs', 'managed_user_id')) {
            return [];
        }

        return DB::table('agency_hubs')
            ->where('agency_user_id', $userId)
            ->pluck('managed_user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param array<int,int> $userIds
     */
    protected function purgeResidualUserData(array $userIds, int $primaryUserId): void
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($id): int => (int) $id, $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $domainCleanupHostnames = $this->domainHostnamesForUserIds($ids);

        if (Schema::hasTable('page_reports')) {
            if (Schema::hasColumn('page_reports', 'reported_user_id')) {
                DB::table('page_reports')->whereIn('reported_user_id', $ids)->delete();
            }
            if (Schema::hasColumn('page_reports', 'last_reporter_user_id')) {
                DB::table('page_reports')->whereIn('last_reporter_user_id', $ids)->update(['last_reporter_user_id' => null]);
            }
            if (Schema::hasColumn('page_reports', 'processed_by_user_id')) {
                DB::table('page_reports')->whereIn('processed_by_user_id', $ids)->update(['processed_by_user_id' => null]);
            }
        }

        if (Schema::hasTable('page_report_events') && Schema::hasColumn('page_report_events', 'reporter_user_id')) {
            DB::table('page_report_events')->whereIn('reporter_user_id', $ids)->update(['reporter_user_id' => null]);
        }

        if (Schema::hasTable('user_custom_domains')) {
            DB::table('user_custom_domains')
                ->where(function ($query) use ($ids): void {
                    if (Schema::hasColumn('user_custom_domains', 'user_id')) {
                        $query->whereIn('user_id', $ids);
                    }
                    if (Schema::hasColumn('user_custom_domains', 'page_id')) {
                        if (Schema::hasColumn('user_custom_domains', 'user_id')) {
                            $query->orWhereIn('page_id', $ids);
                        } else {
                            $query->whereIn('page_id', $ids);
                        }
                    }
                })
                ->delete();

            $this->queueDomainSslCleanupForHostnames($domainCleanupHostnames);
        }

        Link::withDisabled()->whereIn('user_id', $ids)->delete();

        foreach ([
            'analytics_events_extended',
            'analytics_resource_states',
            'user_settings',
            'social_accounts',
            'billing_records',
            'user_subscriptions',
            'billing_checkout_attempts',
            'account_deletion_audit_log',
            'user_agreement_acceptances',
            'billing_notification_outbox',
        ] as $table) {
            $this->deleteRowsByUserIds($table, $ids);
        }

        if (Schema::hasTable('agency_hubs')) {
            DB::table('agency_hubs')
                ->where(function ($query) use ($ids): void {
                    if (Schema::hasColumn('agency_hubs', 'agency_user_id')) {
                        $query->whereIn('agency_user_id', $ids);
                    }
                    if (Schema::hasColumn('agency_hubs', 'managed_user_id')) {
                        if (Schema::hasColumn('agency_hubs', 'agency_user_id')) {
                            $query->orWhereIn('managed_user_id', $ids);
                        } else {
                            $query->whereIn('managed_user_id', $ids);
                        }
                    }
                })
                ->delete();
        }

        if (Schema::hasTable('partner_commission_ledger')) {
            DB::table('partner_commission_ledger')
                ->where(function ($query) use ($ids): void {
                    if (Schema::hasColumn('partner_commission_ledger', 'partner_user_id')) {
                        $query->whereIn('partner_user_id', $ids);
                    }
                    if (Schema::hasColumn('partner_commission_ledger', 'referred_user_id')) {
                        if (Schema::hasColumn('partner_commission_ledger', 'partner_user_id')) {
                            $query->orWhereIn('referred_user_id', $ids);
                        } else {
                            $query->whereIn('referred_user_id', $ids);
                        }
                    }
                })
                ->delete();
        }

        if (Schema::hasTable('partner_attributions')) {
            DB::table('partner_attributions')
                ->where(function ($query) use ($ids): void {
                    if (Schema::hasColumn('partner_attributions', 'partner_user_id')) {
                        $query->whereIn('partner_user_id', $ids);
                    }
                    if (Schema::hasColumn('partner_attributions', 'referred_user_id')) {
                        if (Schema::hasColumn('partner_attributions', 'partner_user_id')) {
                            $query->orWhereIn('referred_user_id', $ids);
                        } else {
                            $query->whereIn('referred_user_id', $ids);
                        }
                    }
                })
                ->delete();
        }

        if (Schema::hasTable('partner_invite_codes') && Schema::hasColumn('partner_invite_codes', 'partner_user_id')) {
            DB::table('partner_invite_codes')->whereIn('partner_user_id', $ids)->delete();
        }

        if (Schema::hasTable('partner_payout_batches') && Schema::hasColumn('partner_payout_batches', 'partner_user_id')) {
            DB::table('partner_payout_batches')->whereIn('partner_user_id', $ids)->delete();
        }

        if (Schema::hasTable('partner_accounts') && Schema::hasColumn('partner_accounts', 'user_id')) {
            DB::table('partner_accounts')->whereIn('user_id', $ids)->delete();
        }

        $this->purgeAuditLogByUserIds('audit_log', $ids);
        $this->purgeAuditLogByUserIds('compliance_audit_log', $ids);

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'id')) {
            DB::table('users')->whereIn('id', $ids)->delete();
        }

        // Ensure no stale anonymized traces remain for the primary deleted user hash.
        $this->deleteAnonymizedAuditRows('audit_log', $primaryUserId);
        $this->deleteAnonymizedAuditRows('compliance_audit_log', $primaryUserId);
    }

    /**
     * @param array<int,int> $ids
     */
    protected function deleteRowsByUserIds(string $table, array $ids): void
    {
        if ($ids === [] || !Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
            return;
        }

        DB::table($table)->whereIn('user_id', $ids)->delete();
    }

    /**
     * @param array<int,int> $ids
     */
    protected function purgeAuditLogByUserIds(string $table, array $ids): void
    {
        if ($ids === [] || !Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'user_id')) {
            DB::table($table)->whereIn('user_id', $ids)->delete();
        }

        if (Schema::hasColumn($table, 'actor_user_id')) {
            DB::table($table)->whereIn('actor_user_id', $ids)->delete();
        }

        foreach ($ids as $id) {
            $this->deleteAnonymizedAuditRows($table, (int) $id);
        }
    }

    protected function deleteAnonymizedAuditRows(string $table, int $userId): void
    {
        if ($userId <= 0 || !Schema::hasTable($table) || !Schema::hasColumn($table, 'metadata')) {
            return;
        }

        $pattern = '%"anonymized_user_hash":"' . hash('sha256', (string) $userId) . '"%';
        DB::table($table)->where('metadata', 'like', $pattern)->delete();
    }

    /**
     * @param array<int,int> $userIds
     */
    protected function domainHostnamesForUserIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $userIds,
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === [] || !Schema::hasTable('user_custom_domains')) {
            return [];
        }

        return DB::table('user_custom_domains')
            ->where(function ($query) use ($ids): void {
                if (Schema::hasColumn('user_custom_domains', 'user_id')) {
                    $query->whereIn('user_id', $ids);
                }

                if (Schema::hasColumn('user_custom_domains', 'page_id')) {
                    if (Schema::hasColumn('user_custom_domains', 'user_id')) {
                        $query->orWhereIn('page_id', $ids);
                    } else {
                        $query->whereIn('page_id', $ids);
                    }
                }
            })
            ->pluck('domain')
            ->map(static fn ($domain): string => strtolower(trim((string) $domain)))
            ->filter(static fn (string $domain): bool => $domain !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int,string> $domains
     */
    protected function queueDomainSslCleanupForHostnames(array $domains): void
    {
        foreach ($domains as $domain) {
            $this->domainSslService->queueCleanupByHostname((string) $domain);
        }
    }

    protected function deletedAccountEvidenceRetentionDays(): int
    {
        return max(1, (int) config('billing.lifecycle.deleted_account_evidence_retention_days', 365));
    }

    /**
     * @return array{stripe_status:string|null,cancel_at_period_end:bool|null}
     */
    protected function captureSubscriptionStateBeforeCancel(int $userId): array
    {
        if ($userId <= 0 || !Schema::hasTable('user_subscriptions')) {
            return [
                'stripe_status' => null,
                'cancel_at_period_end' => null,
            ];
        }

        $columns = $this->existingColumns('user_subscriptions', ['stripe_status', 'cancel_at_period_end']);
        if ($columns === []) {
            return [
                'stripe_status' => null,
                'cancel_at_period_end' => null,
            ];
        }

        $row = DB::table('user_subscriptions')
            ->where('user_id', $userId)
            ->first($columns);

        return [
            'stripe_status' => $this->nullableTrimmed($row->stripe_status ?? null),
            'cancel_at_period_end' => $this->nullableBoolean($row->cancel_at_period_end ?? null),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function buildDeletedAccountSnapshot(
        int $userId,
        string $deletionReason,
        array $context = [],
        array $billingCancellation = [],
        array $preCancelSubscription = []
    ): array
    {
        if (!Schema::hasTable('deleted_accounts')) {
            throw new AccountDeletionBlockedException('Deleted account archive table is unavailable');
        }

        $deletedAt = now();
        $retainedUntil = $deletedAt->copy()->addDays($this->deletedAccountEvidenceRetentionDays());

        $userColumns = $this->existingColumns('users', [
            'id',
            'created_at',
            'agb_accepted_at',
            'agb_version',
            'avv_accepted_at',
            'avv_version',
        ]);

        $userRow = null;
        if ($userColumns !== [] && Schema::hasTable('users')) {
            $userRow = DB::table('users')
                ->where('id', $userId)
                ->first($userColumns);
        }

        $subscriptionColumns = $this->existingColumns('user_subscriptions', [
            'user_id',
            'tier_id',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_status',
            'cancel_at_period_end',
            'expires_at',
            'created_at',
        ]);
        $subscription = null;
        if ($subscriptionColumns !== []) {
            $subscription = DB::table('user_subscriptions')
                ->where('user_id', $userId)
                ->first($subscriptionColumns);
        }

        $tier = null;
        if (
            $subscription
            && Schema::hasTable('tiers')
            && Schema::hasColumn('tiers', 'id')
            && Schema::hasColumn('user_subscriptions', 'tier_id')
        ) {
            $tierId = (int) ($subscription->tier_id ?? 0);
            if ($tierId > 0) {
                $tierColumns = $this->existingColumns('tiers', ['id', 'slug', 'price_1m']);
                if ($tierColumns !== []) {
                    $tier = DB::table('tiers')->where('id', $tierId)->first($tierColumns);
                }
            }
        }

        $latestBillingRecord = null;
        if (Schema::hasTable('billing_records') && Schema::hasColumn('billing_records', 'user_id')) {
            $recordColumns = $this->existingColumns('billing_records', ['id', 'tier_id', 'amount', 'currency', 'period_months', 'created_at']);
            if ($recordColumns !== []) {
                $latestBillingRecord = DB::table('billing_records')
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first($recordColumns);
            }
        }

        $planIdentifier = trim((string) ($tier->slug ?? ''));
        if ($planIdentifier === '') {
            $planIdentifier = null;
        }

        $planPrice = null;
        if (isset($tier->price_1m) && is_numeric($tier->price_1m)) {
            $planPrice = round(((int) $tier->price_1m) / 100, 2);
        } elseif (isset($latestBillingRecord->amount) && is_numeric($latestBillingRecord->amount)) {
            $planPrice = round(((int) $latestBillingRecord->amount) / 100, 2);
        }

        $currency = strtoupper(trim((string) ($latestBillingRecord->currency ?? 'EUR')));
        if ($currency === '') {
            $currency = 'EUR';
        }

        $periodMonths = is_numeric($latestBillingRecord->period_months ?? null)
            ? (int) $latestBillingRecord->period_months
            : 1;
        $billingInterval = $periodMonths >= 12 ? 'yearly' : 'monthly';

        $subscriptionStatusBeforeDelete = $this->nullableTrimmed($subscription->stripe_status ?? null);
        $cancelAtPeriodEndBeforeDelete = $this->nullableBoolean($subscription->cancel_at_period_end ?? null);
        $subscriptionStatusBeforeCancel = $this->nullableTrimmed($preCancelSubscription['stripe_status'] ?? null);
        $cancelAtPeriodEndBeforeCancel = $this->nullableBoolean($preCancelSubscription['cancel_at_period_end'] ?? null);

        $billingCancelStatus = $this->nullableTrimmed($billingCancellation['status'] ?? null);
        $billingCanceled = (bool) ($billingCancellation['canceled'] ?? false);
        $billingCanceledAt = $billingCanceled
            ? $this->nullableTimestamp($billingCancellation['canceled_at'] ?? null)
            : null;
        $stripeCancelEventId = $this->nullableTrimmed($billingCancellation['stripe_cancel_event_id'] ?? null);
        $stripeCanceledAt = $billingCanceled
            ? $this->nullableTimestamp($billingCancellation['stripe_canceled_at'] ?? null)
            : null;
        if ($stripeCanceledAt === null && $billingCanceled) {
            $stripeCanceledAt = $billingCanceledAt;
        }
        $billingCancelHttpStatus = $this->nullablePositiveInt($billingCancellation['http_status'] ?? null);
        $billingCancelErrorCode = $this->nullableTrimmed($billingCancellation['error_code'] ?? null);
        $deletionRequestId = $this->nullableTrimmed($context['deletion_request_id'] ?? null);

        $loginCount = 0;
        $lastLoginAt = null;
        if (
            Schema::hasTable('compliance_audit_log')
            && Schema::hasColumn('compliance_audit_log', 'user_id')
            && Schema::hasColumn('compliance_audit_log', 'event_type')
        ) {
            $loginCount = (int) DB::table('compliance_audit_log')
                ->where('user_id', $userId)
                ->where('event_type', 'login_success')
                ->count();

            if (Schema::hasColumn('compliance_audit_log', 'created_at')) {
                $lastLoginAt = DB::table('compliance_audit_log')
                    ->where('user_id', $userId)
                    ->where('event_type', 'login_success')
                    ->max('created_at');
            }
        }

        $hubCount = 0;
        if (Schema::hasTable('agency_hubs') && Schema::hasColumn('agency_hubs', 'agency_user_id')) {
            $hubCount = (int) DB::table('agency_hubs')->where('agency_user_id', $userId)->count();
        }

        $planHistory = $this->buildPlanHistory($userId);
        $encodedPlanHistory = null;
        if ($planHistory !== []) {
            $encodedPlanHistory = json_encode([
                'schema_version' => 1,
                'entries' => $planHistory,
            ], JSON_UNESCAPED_SLASHES);
            if (!is_string($encodedPlanHistory)) {
                $encodedPlanHistory = null;
            }
        }

        return [
            'stripe_customer_id' => $this->nullableTrimmed($subscription->stripe_customer_id ?? null),
            'stripe_subscription_id' => $this->nullableTrimmed($subscription->stripe_subscription_id ?? null),
            'plan_identifier' => $planIdentifier,
            'plan_price' => $planPrice,
            'currency' => $currency,
            'billing_interval' => $billingInterval,
            'subscription_start_at' => $this->nullableTimestamp($subscription->created_at ?? null),
            'subscription_end_at' => $this->nullableTimestamp($subscription->expires_at ?? null),
            'subscription_status_before_delete' => $subscriptionStatusBeforeDelete,
            'cancel_at_period_end_before_delete' => $cancelAtPeriodEndBeforeDelete,
            'subscription_status_before_cancel' => $subscriptionStatusBeforeCancel,
            'cancel_at_period_end_before_cancel' => $cancelAtPeriodEndBeforeCancel,
            'account_created_at' => $this->nullableTimestamp($userRow->created_at ?? null),
            'account_deleted_at' => $deletedAt,
            'deletion_reason' => $deletionReason,
            'deletion_source' => $this->nullableTrimmed($context['source'] ?? null),
            'actor_user_id' => $this->nullablePositiveInt($context['actor_user_id'] ?? null),
            'deletion_request_id' => $deletionRequestId,
            'billing_cancel_status' => $billingCancelStatus,
            'billing_canceled' => $billingCanceled,
            'billing_canceled_at' => $billingCanceledAt,
            'stripe_cancel_event_id' => $stripeCancelEventId,
            'stripe_canceled_at' => $stripeCanceledAt,
            'billing_cancel_http_status' => $billingCancelHttpStatus,
            'billing_cancel_error_code' => $billingCancelErrorCode,
            'last_login_at' => $this->nullableTimestamp($lastLoginAt),
            'login_count' => $loginCount,
            'hub_count' => $hubCount,
            'agb_accepted_at' => $this->nullableTimestamp($userRow->agb_accepted_at ?? null),
            'agb_version' => $this->nullableTrimmed($userRow->agb_version ?? null),
            'avv_accepted_at' => $this->nullableTimestamp($userRow->avv_accepted_at ?? null),
            'avv_version' => $this->nullableTrimmed($userRow->avv_version ?? null),
            'plan_history_json' => $encodedPlanHistory,
            'retained_until' => $retainedUntil,
            'created_at' => now(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function buildPlanHistory(int $userId): array
    {
        $entries = [];

        if (
            Schema::hasTable('compliance_audit_log')
            && Schema::hasColumn('compliance_audit_log', 'user_id')
            && Schema::hasColumn('compliance_audit_log', 'event_type')
        ) {
            $columns = $this->existingColumns('compliance_audit_log', ['event_type', 'created_at', 'metadata']);
            if ($columns !== []) {
                $rows = DB::table('compliance_audit_log')
                    ->where('user_id', $userId)
                    ->where('event_type', 'subscription_change_requested')
                    ->orderBy('created_at')
                    ->limit(200)
                    ->get($columns);

                foreach ($rows as $row) {
                    $meta = $this->decodeJsonObject($row->metadata ?? null);
                    $entries[] = [
                        'at' => $this->isoTimestamp($row->created_at ?? null),
                        'event' => 'subscription_change_requested',
                        'from_plan' => null,
                        'to_plan' => $this->nullableTrimmed($meta['target_tier_slug'] ?? null),
                        'result_status' => $this->nullableTrimmed($meta['result_status'] ?? null),
                        'source' => 'compliance_audit_log',
                    ];
                }
            }
        }

        if (
            Schema::hasTable('audit_log')
            && Schema::hasColumn('audit_log', 'user_id')
            && Schema::hasColumn('audit_log', 'event_type')
        ) {
            $columns = $this->existingColumns('audit_log', ['event_type', 'created_at', 'metadata']);
            if ($columns !== []) {
                $rows = DB::table('audit_log')
                    ->where('user_id', $userId)
                    ->where('event_type', 'plan_downgrade_executed')
                    ->orderBy('created_at')
                    ->limit(200)
                    ->get($columns);

                foreach ($rows as $row) {
                    $meta = $this->decodeJsonObject($row->metadata ?? null);
                    $entries[] = [
                        'at' => $this->isoTimestamp($row->created_at ?? null),
                        'event' => 'plan_downgrade_executed',
                        'from_plan' => $this->nullableTrimmed($meta['previous_plan'] ?? null),
                        'to_plan' => $this->nullableTrimmed($meta['new_plan'] ?? null),
                        'result_status' => null,
                        'source' => 'audit_log',
                    ];
                }
            }
        }

        if (
            Schema::hasTable('billing_records')
            && Schema::hasColumn('billing_records', 'user_id')
            && Schema::hasColumn('billing_records', 'tier_id')
            && Schema::hasColumn('billing_records', 'created_at')
            && Schema::hasTable('tiers')
            && Schema::hasColumn('tiers', 'id')
            && Schema::hasColumn('tiers', 'slug')
        ) {
            $rows = DB::table('billing_records')
                ->leftJoin('tiers', 'tiers.id', '=', 'billing_records.tier_id')
                ->where('billing_records.user_id', $userId)
                ->orderBy('billing_records.created_at')
                ->limit(200)
                ->get([
                    'billing_records.created_at as billed_at',
                    'tiers.slug as tier_slug',
                    'billing_records.amount as amount',
                    'billing_records.currency as currency',
                ]);

            foreach ($rows as $row) {
                $entries[] = [
                    'at' => $this->isoTimestamp($row->billed_at ?? null),
                    'event' => 'invoice_recorded',
                    'from_plan' => null,
                    'to_plan' => $this->nullableTrimmed($row->tier_slug ?? null),
                    'result_status' => null,
                    'source' => 'billing_records',
                    'amount' => isset($row->amount) && is_numeric($row->amount) ? round(((int) $row->amount) / 100, 2) : null,
                    'currency' => $this->nullableTrimmed($row->currency ?? null),
                ];
            }
        }

        $entries = array_values(array_filter($entries, static fn (array $entry): bool => !empty($entry['at'])));

        usort($entries, static function (array $left, array $right): int {
            return strcmp((string) $left['at'], (string) $right['at']);
        });

        return $entries;
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    protected function persistDeletedAccountSnapshot(array $snapshot): void
    {
        if (!Schema::hasTable('deleted_accounts')) {
            throw new AccountDeletionBlockedException('Deleted account archive table is unavailable');
        }

        $insertableColumns = $this->existingColumns('deleted_accounts', array_keys($snapshot));
        if ($insertableColumns === []) {
            throw new AccountDeletionBlockedException('Deleted account archive columns are unavailable');
        }

        $insertPayload = [];
        foreach ($insertableColumns as $column) {
            $insertPayload[$column] = $snapshot[$column] ?? null;
        }

        $deletionRequestId = $this->nullableTrimmed($insertPayload['deletion_request_id'] ?? null);
        if (in_array('deletion_request_id', $insertableColumns, true) && $deletionRequestId !== null) {
            $insertPayload['deletion_request_id'] = $deletionRequestId;
            $updateColumns = array_values(array_filter(
                array_keys($insertPayload),
                static fn (string $column): bool => !in_array($column, ['id', 'deletion_request_id', 'created_at'], true),
            ));

            if ($updateColumns === []) {
                DB::table('deleted_accounts')->insertOrIgnore($insertPayload);
                return;
            }

            DB::table('deleted_accounts')->upsert(
                [$insertPayload],
                ['deletion_request_id'],
                $updateColumns,
            );

            return;
        }

        DB::table('deleted_accounts')->insert($insertPayload);
    }

    protected function resolveDeletionReason(array $context): string
    {
        $explicit = strtolower(trim((string) ($context['deletion_reason'] ?? '')));
        if (in_array($explicit, ['self', 'admin', 'fraud'], true)) {
            return $explicit;
        }

        $source = strtolower(trim((string) ($context['source'] ?? 'unknown')));

        return str_contains($source, 'self') ? 'self' : 'admin';
    }

    /**
     * @return array<int,string>
     */
    protected function existingColumns(string $table, array $columns): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $resolved = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $resolved[] = $column;
            }
        }

        return $resolved;
    }

    protected function nullableTrimmed(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    protected function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    protected function nullablePositiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $integerValue = (int) $value;
        return $integerValue > 0 ? $integerValue : null;
    }

    protected function nullableTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateTimeString();
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isoTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws AccountDeletionBlockedException
     */
    protected function ensureStripeCancellationBeforeDeletion(User $user, array $context): array
    {
        $userId = (int) $user->id;
        if ($userId <= 0) {
            throw new AccountDeletionBlockedException('Invalid account deletion target');
        }

        if (!$this->billingClient->enabled()) {
            if ($this->localStripeSubscriptionLooksActive($userId)) {
                throw new AccountDeletionBlockedException('Billing cancellation is unavailable');
            }

            return [
                'status' => 'billing_bridge_disabled',
                'canceled' => false,
                'canceled_at' => null,
                'stripe_cancel_event_id' => null,
                'stripe_canceled_at' => null,
                'http_status' => null,
                'error_code' => null,
            ];
        }

        $email = is_string($user->email) ? trim($user->email) : '';
        $deletionRequestId = $this->nullableTrimmed($context['deletion_request_id'] ?? null);
        $result = $this->billingClient->cancelOnAccountDelete([
            'user_id' => $userId,
            'user_email' => $email,
            'deletion_request_id' => $deletionRequestId,
        ], $userId);

        if (!is_array($result)) {
            Log::warning('Account deletion blocked: billing cancellation request failed', [
                'user_id' => $userId,
                'reason' => 'empty_or_invalid_response',
            ]);
            throw new AccountDeletionBlockedException('Subscription cancellation request failed');
        }

        $httpStatus = isset($result['_status']) ? (int) $result['_status'] : 200;
        if ($httpStatus >= 400) {
            Log::warning('Account deletion blocked: billing cancellation request rejected', [
                'user_id' => $userId,
                'http_status' => $httpStatus,
                'error_code' => is_array($result['error'] ?? null) ? ($result['error']['code'] ?? null) : null,
                'error_message' => is_array($result['error'] ?? null) ? ($result['error']['message'] ?? null) : null,
            ]);
            throw new AccountDeletionBlockedException('Subscription cancellation request was rejected');
        }

        $status = strtolower(trim((string) ($result['status'] ?? '')));
        if ($status === 'subscription_canceled') {
            $this->recordDeletionBillingAudit($userId, $email, $status, $result, $context);
            return [
                'status' => $status,
                'canceled' => true,
                'canceled_at' => now()->toDateTimeString(),
                'stripe_cancel_event_id' => $this->nullableTrimmed($result['stripe_cancel_event_id'] ?? null),
                'stripe_canceled_at' => $this->nullableTimestamp($result['stripe_canceled_at'] ?? null),
                'http_status' => $this->nullablePositiveInt($result['_status'] ?? null),
                'error_code' => $this->nullableTrimmed(is_array($result['error'] ?? null) ? ($result['error']['code'] ?? null) : null),
            ];
        }

        if ($status === 'no_active_subscription') {
            if ($this->localStripeSubscriptionLooksActive($userId)) {
                Log::warning('Account deletion blocked: billing reported no active subscription but local state is active', [
                    'user_id' => $userId,
                ]);
                throw new AccountDeletionBlockedException('Subscription cancellation request was rejected');
            }

            $this->recordDeletionBillingAudit($userId, $email, $status, $result, $context);
            return [
                'status' => $status,
                'canceled' => false,
                'canceled_at' => null,
                'stripe_cancel_event_id' => null,
                'stripe_canceled_at' => null,
                'http_status' => $this->nullablePositiveInt($result['_status'] ?? null),
                'error_code' => $this->nullableTrimmed(is_array($result['error'] ?? null) ? ($result['error']['code'] ?? null) : null),
            ];
        }

        if ($status === '') {
            Log::warning('Account deletion blocked: missing billing cancellation status', [
                'user_id' => $userId,
            ]);
            throw new AccountDeletionBlockedException('Unexpected billing cancellation response');
        }

        if ($status !== 'subscription_canceled') {
            Log::warning('Account deletion blocked: unexpected billing cancellation response', [
                'user_id' => $userId,
                'status' => $status,
            ]);
            throw new AccountDeletionBlockedException('Unexpected billing cancellation response');
        }
    }

    protected function localStripeSubscriptionLooksActive(int $userId): bool
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return false;
        }

        if (!Schema::hasColumn('user_subscriptions', 'stripe_subscription_id')) {
            return false;
        }

        $columns = ['stripe_subscription_id'];
        if (Schema::hasColumn('user_subscriptions', 'stripe_status')) {
            $columns[] = 'stripe_status';
        }

        $subscription = DB::table('user_subscriptions')
            ->select($columns)
            ->where('user_id', $userId)
            ->first();

        if (!$subscription) {
            return false;
        }

        $stripeSubscriptionId = trim((string) ($subscription->stripe_subscription_id ?? ''));
        if ($stripeSubscriptionId === '') {
            return false;
        }

        $stripeStatus = strtolower(trim((string) ($subscription->stripe_status ?? '')));

        return !in_array($stripeStatus, ['canceled', 'incomplete_expired', 'unpaid'], true);
    }

    /**
     * @param array{source?:string,actor_user_id?:int|null,hard_delete_reason?:string|null,deletion_reason?:string|null,deletion_request_id?:string|null} $context
     * @param array<string,mixed> $result
     */
    protected function recordDeletionBillingAudit(
        int $userId,
        string $email,
        string $status,
        array $result,
        array $context
    ): void {
        $source = strtolower(trim((string) ($context['source'] ?? 'unknown')));
        if ($source === '') {
            $source = 'unknown';
        }

        $actorUserId = isset($context['actor_user_id']) ? (int) $context['actor_user_id'] : null;
        if ($actorUserId !== null && $actorUserId <= 0) {
            $actorUserId = null;
        }

        $billingStatus = strtolower(trim($status));
        $canceled = $billingStatus === 'subscription_canceled'
            || (bool) ($result['canceled'] ?? false);
        $httpStatus = isset($result['_status']) ? (int) $result['_status'] : null;
        if ($httpStatus !== null && $httpStatus <= 0) {
            $httpStatus = null;
        }

        $stripeCustomerId = trim((string) ($result['stripe_customer_id'] ?? ''));
        $stripeSubscriptionId = trim((string) ($result['stripe_subscription_id'] ?? ''));
        $deletionRequestId = $this->nullableTrimmed($context['deletion_request_id'] ?? null);
        $emailHash = $this->hashAuditEmail($email);

        Log::info('Account deletion billing cancellation outcome', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'source' => $source,
            'billing_status' => $billingStatus,
            'canceled' => $canceled,
            'stripe_customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null,
            'stripe_subscription_id' => $stripeSubscriptionId !== '' ? $stripeSubscriptionId : null,
            'deletion_request_id' => $deletionRequestId,
            'http_status' => $httpStatus,
            'user_email_sha256' => $emailHash !== '' ? $emailHash : null,
        ]);

        if (!Schema::hasTable('account_deletion_audit_log')) {
            return;
        }

        $metadata = $this->sanitizeDeletionBillingResult($result);
        if ($emailHash !== '') {
            $metadata['user_email_sha256'] = $emailHash;
        }
        if ($httpStatus !== null) {
            $metadata['http_status'] = $httpStatus;
        }
        if ($deletionRequestId !== null) {
            $metadata['deletion_request_id'] = $deletionRequestId;
        }

        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedMetadata)) {
            $encodedMetadata = null;
        }

        try {
            DB::table('account_deletion_audit_log')->insert([
                'user_id' => $userId,
                'actor_user_id' => $actorUserId,
                'action' => 'account_delete_billing',
                'source' => substr($source, 0, 64),
                'billing_status' => $billingStatus !== '' ? substr($billingStatus, 0, 64) : null,
                'canceled' => $canceled,
                'stripe_customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null,
                'stripe_subscription_id' => $stripeSubscriptionId !== '' ? $stripeSubscriptionId : null,
                'http_status' => $httpStatus,
                'metadata' => $encodedMetadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Account deletion audit log insert failed', [
                'user_id' => $userId,
                'source' => $source,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array{source:string,actor_user_id:int|null,hard_delete_reason:string|null,deletion_reason:string|null,deletion_request_id:string|null}
     */
    protected function normalizeDeletionContext(array $context): array
    {
        $source = strtolower(trim((string) ($context['source'] ?? 'unknown')));
        if ($source === '') {
            $source = 'unknown';
        }

        $actorUserId = isset($context['actor_user_id']) ? (int) $context['actor_user_id'] : null;
        if ($actorUserId !== null && $actorUserId <= 0) {
            $actorUserId = null;
        }

        $hardDeleteReason = trim((string) ($context['hard_delete_reason'] ?? ''));
        if ($hardDeleteReason === '') {
            $hardDeleteReason = null;
        } else {
            $hardDeleteReason = substr($hardDeleteReason, 0, 255);
        }

        $deletionReason = strtolower(trim((string) ($context['deletion_reason'] ?? '')));
        if ($deletionReason === '') {
            $deletionReason = null;
        } else {
            $deletionReason = substr($deletionReason, 0, 32);
        }

        $deletionRequestId = $this->nullableTrimmed($context['deletion_request_id'] ?? null);
        if ($deletionRequestId !== null) {
            $deletionRequestId = substr($deletionRequestId, 0, 64);
        }

        return [
            'source' => substr($source, 0, 64),
            'actor_user_id' => $actorUserId,
            'hard_delete_reason' => $hardDeleteReason,
            'deletion_reason' => $deletionReason,
            'deletion_request_id' => $deletionRequestId,
        ];
    }

    protected function hashAuditEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        return $normalized !== '' ? hash('sha256', $normalized) : '';
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    protected function sanitizeDeletionBillingResult(array $result): array
    {
        $safe = [];
        foreach (['status', 'canceled', 'stripe_customer_id', 'stripe_subscription_id', 'shadow'] as $key) {
            if (array_key_exists($key, $result)) {
                $safe[$key] = $result[$key];
            }
        }

        if (isset($result['error']) && is_array($result['error'])) {
            $safe['error'] = [
                'code' => is_string($result['error']['code'] ?? null) ? $result['error']['code'] : null,
                'message' => is_string($result['error']['message'] ?? null) ? $result['error']['message'] : null,
            ];
        }

        return $safe;
    }

    /**
     * @param array<int,int> $userIds
     * @return string[]
     */
    protected function collectOwnedFilePathsForUsers(array $userIds): array
    {
        $paths = [];
        foreach ($userIds as $userId) {
            $paths = array_merge($paths, $this->collectOwnedFilePaths((int) $userId));
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return string[]
     */
    protected function collectOwnedFilePaths(int $userId): array
    {
        $paths = [];

        $avatarPath = $this->mediaStorage->avatarPathForUser($userId);
        if ($avatarPath !== null) {
            $paths[] = $avatarPath;
        }

        $backgroundPath = $this->mediaStorage->backgroundPathForUser($userId);
        if ($backgroundPath !== null) {
            $paths[] = $backgroundPath;
        }

        $headerImage = UserData::getData($userId, 'header_image');
        if (is_string($headerImage) && trim($headerImage) !== '') {
            $paths[] = $headerImage;
        }

        $agencyBrandingAsset = UserData::getData($userId, 'agency_branding_asset');
        if (is_string($agencyBrandingAsset) && trim($agencyBrandingAsset) !== '') {
            $paths[] = $agencyBrandingAsset;
        }

        return array_values(array_unique($paths));
    }

    protected function deleteRelativeFile(string $relativePath): void
    {
        $this->mediaStorage->delete($relativePath);
    }
}
