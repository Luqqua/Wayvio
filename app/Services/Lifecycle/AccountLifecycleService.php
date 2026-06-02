<?php

namespace App\Services\Lifecycle;

use App\Models\AgencyHub;
use App\Models\AnalyticsResourceState;
use App\Models\Link;
use App\Models\User;
use App\Services\Agency\AgencyHubQuotaManager;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\CustomDomains\Models\UserCustomDomain;
use Modules\CustomDomains\Services\DomainSSLService;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Models\UserSubscription;
use Modules\Tiers\Services\TierResolver;

class AccountLifecycleService
{
    private const ACCOUNT_STATUS_ACTIVE = 'active';
    private const ACCOUNT_STATUS_WARNED = 'warned';
    private const ACCOUNT_STATUS_SUSPENDED = 'suspended';
    private const ACCOUNT_STATUS_PENDING_DELETION = 'pending_deletion';
    private const ACCOUNT_STATUS_DELETED = 'deleted';

    private const RESOURCE_STATUS_ACTIVE = 'active';
    private const RESOURCE_STATUS_SUSPENDED = 'suspended';
    private const RESOURCE_STATUS_PENDING_DELETION = 'pending_deletion';
    private const RESOURCE_STATUS_DELETED = 'deleted';

    /** @var array<string,int> */
    private array $tierOrderIndex = [];

    public function __construct(
        private readonly TierResolver $tierResolver,
        private readonly AgencyHubQuotaManager $quotaManager,
        private readonly LifecycleAuditService $audit,
        private readonly LifecycleNotificationService $notifications,
        private readonly DomainSSLService $domainSslService,
    ) {
        $order = config('tiers.order', ['free', 'basic', 'pro', 'agency']);
        foreach ($order as $index => $slug) {
            $this->tierOrderIndex[$this->tierResolver->normalizeSlug((string) $slug)] = (int) $index;
        }
    }

    public function syncSubscriptionLifecycle(UserSubscription $subscription): void
    {
        $currentTierId = (int) ($subscription->tier_id ?? 0);
        if ($currentTierId <= 0) {
            return;
        }

        $currentTier = Tier::query()->find($currentTierId);
        if (!$currentTier) {
            $subscription->forceFill([
                'lifecycle_last_tier_id' => $currentTierId,
                'lifecycle_last_hub_slots_included' => $subscription->hub_slots_included,
            ])->save();
            return;
        }
        $currentSlug = $this->tierResolver->normalizeSlug((string) $currentTier->slug);

        $lastTierId = (int) ($subscription->lifecycle_last_tier_id ?? 0);
        if ($lastTierId <= 0) {
            $this->repairCurrentTierEntitlements($subscription, $currentTier, $currentSlug, 'bootstrap');
            $subscription->forceFill([
                'lifecycle_last_tier_id' => $currentTierId,
                'lifecycle_last_hub_slots_included' => $subscription->hub_slots_included,
            ])->save();
            return;
        }

        if ($lastTierId === $currentTierId) {
            $this->repairCurrentTierEntitlements($subscription, $currentTier, $currentSlug, 'steady_state');
            $subscription->forceFill([
                'lifecycle_last_hub_slots_included' => $subscription->hub_slots_included,
            ])->save();
            return;
        }

        $previousTier = Tier::query()->find($lastTierId);
        if (!$previousTier) {
            $this->repairCurrentTierEntitlements($subscription, $currentTier, $currentSlug, 'missing_previous');
            $subscription->forceFill([
                'lifecycle_last_tier_id' => $currentTierId,
                'lifecycle_last_hub_slots_included' => $subscription->hub_slots_included,
            ])->save();
            return;
        }

        $previousSlug = $this->tierResolver->normalizeSlug((string) $previousTier->slug);
        $direction = $this->planDirection($previousSlug, $currentSlug);

        if ($direction < 0) {
            $this->applyDowngradeLifecycle($subscription, $previousTier, $currentTier, $previousSlug, $currentSlug);
        } elseif ($direction > 0) {
            $this->reactivateDowngradedResources((int) $subscription->user_id);
            if ($this->formsAllowedForTier($currentTier)) {
                $this->rebaseFormsRetentionForTierTransition(
                    (int) $subscription->user_id,
                    $currentTier,
                    null,
                    'upgrade',
                    'subscription_lifecycle_sync',
                );
            }
        } else {
            $this->repairCurrentTierEntitlements($subscription, $currentTier, $currentSlug, 'unknown_direction');
        }

        $subscription->forceFill([
            'lifecycle_last_tier_id' => $currentTierId,
            'lifecycle_last_hub_slots_included' => $subscription->hub_slots_included,
        ])->save();
    }

    public function processPaymentTimeline(UserSubscription $subscription): array
    {
        $actions = ['warning_sent' => 0, 'restricted' => 0, 'downgrade_warned' => 0, 'downgraded' => 0, 'reactivated' => 0];

        $user = User::query()->find((int) $subscription->user_id);
        if (!$user) {
            return $actions;
        }

        if ($this->isSubscriptionRecovered($subscription)) {
            if ($subscription->payment_failed_at !== null || $this->accountStatusOf($user) !== self::ACCOUNT_STATUS_ACTIVE) {
                $this->reactivateAfterPayment($subscription, $user);
                $actions['reactivated'] = 1;
            }
            return $actions;
        }

        if ($subscription->payment_failed_at === null) {
            $subscription->forceFill([
                'payment_failed_at' => now(),
            ])->save();

            $this->audit->event((int) $user->id, 'payment_failed', 'non_payment', [
                'stripe_status' => (string) ($subscription->stripe_status ?? ''),
            ]);
            return $actions;
        }

        $failedAt = Carbon::parse($subscription->payment_failed_at);
        $now = now();

        // Day 1: downgrade to free and apply free-tier restrictions immediately.
        if ($failedAt->copy()->addDays(1)->lessThanOrEqualTo($now) && $subscription->payment_pending_deletion_at === null) {
            $downgrade = $this->downgradeSubscriptionToFreeTier($subscription);
            $resourceReason = 'downgrade';
            $suspendedHubs = $this->suspendAgencyHubs((int) $user->id, $resourceReason);
            $suspendedDomains = $this->suspendDomains((int) $user->id, $resourceReason);
            $currentTier = Tier::query()->find((int) ($subscription->tier_id ?? 0));
            $targetAllowsForms = $this->formsAllowedForTier($currentTier);
            $targetSupportsAnalytics = $currentTier
                ? $this->tierResolver->featureEnabled($currentTier, 'analytics.enabled')
                : false;
            $formsSuspended = !$targetAllowsForms
                ? $this->suspendForms((int) $user->id, $resourceReason)
                : false;
            $analyticsSuspended = !$targetSupportsAnalytics
                ? $this->suspendAnalytics((int) $user->id, $resourceReason)
                : false;
            $metaTagsSuspended = $this->suspendMetaTags((int) $user->id, $resourceReason);

            $retentionDays = $this->downgradeRetentionDays();
            $graceDays = $this->pendingDeletionGraceDays();
            $resourcePendingAt = now()->addDays($retentionDays);
            $resourceDeleteAt = $resourcePendingAt->copy()->addDays($graceDays);

            $subscription->forceFill([
                'payment_pending_deletion_at' => now(),
                'payment_restricted_at' => now(),
                'payment_delete_after_at' => $resourceDeleteAt,
            ])->save();

            $this->notifications->enqueue((int) $user->id, 'non_payment_downgraded_to_free', [
                'downgraded_at' => now()->toIso8601String(),
                'target_plan_slug' => (string) ($downgrade['free_tier_slug'] ?? $this->tierResolver->normalizeSlug((string) config('tiers.default_free_slug', 'free'))),
                'target_plan_name' => (string) ($downgrade['free_tier_name'] ?? $this->tierResolver->displayName($this->tierResolver->normalizeSlug((string) config('tiers.default_free_slug', 'free')))),
                'resource_pending_deletion_at' => $resourcePendingAt->toIso8601String(),
                'resource_delete_at' => $resourceDeleteAt->toIso8601String(),
                'billing_portal_url' => url('/dashboard/subscription'),
            ], 'check_payment_status');

            $this->notifications->enqueue((int) $user->id, 'account_suspended_warning', [
                'status_from' => $this->accountStatusOf($user),
                'status_to' => $this->accountStatusOf($user),
                'hubs_suspended_count' => $suspendedHubs,
                'domains_suspended_count' => $suspendedDomains,
                'forms_suspended' => $formsSuspended,
                'analytics_suspended' => $analyticsSuspended,
                'meta_tags_suspended' => $metaTagsSuspended,
                'delete_after_days' => $retentionDays + $graceDays,
                'resource_pending_deletion_at' => $resourcePendingAt->toIso8601String(),
                'resource_delete_at' => $resourceDeleteAt->toIso8601String(),
                'billing_portal_url' => url('/dashboard/subscription'),
            ], 'check_payment_status');

            $this->audit->event((int) $user->id, 'non_payment_downgraded_to_free', 'non_payment', [
                'free_tier_slug' => (string) ($downgrade['free_tier_slug'] ?? ''),
                'free_tier_id' => (int) ($downgrade['free_tier_id'] ?? 0),
                'tier_updated' => (bool) ($downgrade['tier_updated'] ?? false),
            ]);
            $this->audit->event((int) $user->id, 'hubs_suspended', $resourceReason, [
                'count' => $suspendedHubs,
            ]);
            $this->audit->event((int) $user->id, 'domains_suspended', $resourceReason, [
                'count' => $suspendedDomains,
            ]);
            $this->audit->event((int) $user->id, 'features_suspended', $resourceReason, [
                'domains' => $suspendedDomains,
                'forms' => $formsSuspended,
                'analytics' => $analyticsSuspended,
                'meta_tags' => $metaTagsSuspended,
                'hubs' => $suspendedHubs,
            ]);
            $actions['downgraded']++;
            $actions['restricted']++;
        }

        if ($failedAt->copy()->addDays(3)->lessThanOrEqualTo($now) && $subscription->payment_warning_sent_at === null) {
            $this->notifications->enqueue((int) $user->id, 'payment_failed_warning', [
                'amount_cents' => null,
                'billing_portal_url' => url('/dashboard/subscription'),
                'failed_at' => $failedAt->toIso8601String(),
            ], 'check_payment_status');

            $subscription->forceFill([
                'payment_warning_sent_at' => now(),
            ])->save();
            $this->audit->event((int) $user->id, 'payment_warning_sent', 'non_payment');
            $actions['warning_sent']++;
        }

        if ($failedAt->copy()->addDays(30)->lessThanOrEqualTo($now) && $subscription->payment_deletion_warning_sent_at === null) {
            $retentionDays = $this->downgradeRetentionDays();
            $graceDays = $this->pendingDeletionGraceDays();
            $restrictedAt = $subscription->payment_restricted_at
                ? Carbon::parse($subscription->payment_restricted_at)
                : ($subscription->payment_pending_deletion_at
                    ? Carbon::parse($subscription->payment_pending_deletion_at)
                    : $failedAt->copy()->addDays(1));
            $resourcePendingAt = $restrictedAt->copy()->addDays($retentionDays);
            $resourceDeleteAt = $resourcePendingAt->copy()->addDays($graceDays);

            // Keep backward-compatible payload key while shifting message semantics to resource deletion timing.
            $plannedDowngrade = $resourceDeleteAt;
            $this->notifications->enqueue((int) $user->id, 'non_payment_downgrade_warning', [
                'downgrade_at' => $plannedDowngrade->toIso8601String(),
                'resource_pending_deletion_at' => $resourcePendingAt->toIso8601String(),
                'resource_delete_at' => $resourceDeleteAt->toIso8601String(),
                'billing_portal_url' => url('/dashboard/subscription'),
            ], 'check_payment_status');

            $subscription->forceFill([
                'payment_deletion_warning_sent_at' => now(),
            ])->save();
            $this->audit->event((int) $user->id, 'non_payment_downgrade_warning_sent', 'non_payment', [
                'downgrade_at' => $plannedDowngrade->toIso8601String(),
            ]);
            $actions['downgrade_warned']++;
        }

        return $actions;
    }

    public function processAgencyGracePeriod(UserSubscription $subscription): void
    {
        $user = User::query()->find((int) $subscription->user_id);
        if (!$user) {
            return;
        }

        $currentTier = Tier::query()->find((int) ($subscription->tier_id ?? 0));
        if (!$currentTier || $this->tierResolver->normalizeSlug((string) $currentTier->slug) !== 'agency') {
            $subscription->forceFill([
                'agency_grace_period_started_at' => null,
                'agency_grace_period_ends_at' => null,
                'agency_over_quota_count' => null,
            ])->save();
            return;
        }

        $allowedManaged = $this->allowedManagedHubSlots($subscription);
        $activeManaged = AgencyHub::query()
            ->where('agency_user_id', (int) $user->id)
            ->where('status', self::RESOURCE_STATUS_ACTIVE)
            ->count();

        $overQuota = max(0, $activeManaged - $allowedManaged);
        if ($overQuota === 0) {
            if ($subscription->agency_grace_period_ends_at !== null || $subscription->agency_over_quota_count !== null) {
                $subscription->forceFill([
                    'agency_grace_period_started_at' => null,
                    'agency_grace_period_ends_at' => null,
                    'agency_over_quota_count' => null,
                ])->save();
            }
            return;
        }

        $graceEndsAt = $subscription->agency_grace_period_ends_at
            ? Carbon::parse($subscription->agency_grace_period_ends_at)
            : null;

        if ($graceEndsAt === null) {
            $endsAt = now()->addDays(14);
            $subscription->forceFill([
                'agency_grace_period_started_at' => now(),
                'agency_grace_period_ends_at' => $endsAt,
                'agency_over_quota_count' => $overQuota,
            ])->save();

            $this->notifications->enqueue((int) $user->id, 'agency_hub_quota_exceeded', [
                'current_hubs' => $activeManaged + 1,
                'allowed_hubs' => $allowedManaged + 1,
                'over_quota' => $overQuota,
                'grace_ends_at' => $endsAt->toIso8601String(),
                'manage_hubs_url' => route('agency.hubs.index'),
            ], 'check_grace_periods');

            $this->audit->event((int) $user->id, 'agency_hub_quota_exceeded', 'downgrade', [
                'over_quota' => $overQuota,
            ]);
            $this->audit->event((int) $user->id, 'grace_period_started', 'downgrade', [
                'grace_ends_at' => $endsAt->toIso8601String(),
            ]);
            return;
        }

        if (now()->lessThan($graceEndsAt)) {
            $subscription->forceFill([
                'agency_over_quota_count' => $overQuota,
            ])->save();
            return;
        }

        $targetTotalSlots = max(1, $allowedManaged + 1);
        $result = $this->quotaManager->enforceManagedHubLimit(
            $user,
            $targetTotalSlots,
            [],
            AgencyHubQuotaManager::STRATEGY_LEAST_LINKS,
            false,
        );

        $suspended = (int) ($result['removed'] ?? 0);
        $victims = array_values((array) ($result['removed_hubs'] ?? []));

        $subscription->forceFill([
            'agency_grace_period_started_at' => null,
            'agency_grace_period_ends_at' => null,
            'agency_over_quota_count' => null,
        ])->save();

        if ($suspended > 0) {
            $this->notifications->enqueue((int) $user->id, 'agency_hub_suspended_automatically', [
                'hubs' => $victims,
                'manage_hubs_url' => route('agency.hubs.index'),
            ], 'check_grace_periods');

            $this->audit->event((int) $user->id, 'hub_suspended_automatically', 'downgrade', [
                'count' => $suspended,
                'hubs' => $victims,
            ]);
        }
    }

    public function handleSuspendedResources(): int
    {
        $retentionDays = $this->downgradeRetentionDays();
        $pendingDeletionDays = $this->pendingDeletionGraceDays();
        $processed = 0;

        $domainCandidates = collect();
        if ($this->supportsDomainLifecycle()) {
            $domainCandidates = UserCustomDomain::query()
                ->where('lifecycle_status', self::RESOURCE_STATUS_SUSPENDED)
                ->whereIn('lifecycle_reason', $this->lifecycleResourceReasons())
                ->whereNotNull('suspended_at')
                ->get();
        }

        $usersToWarn = [];
        $formsPendingByUser = [];
        $metaPendingByUser = [];
        foreach ($domainCandidates as $domain) {
            $suspendedAt = Carbon::parse($domain->suspended_at);
            if ($suspendedAt->copy()->addDays($retentionDays)->isFuture()) {
                continue;
            }

            $deleteAt = now()->addDays($pendingDeletionDays);
            $domain->forceFill([
                'lifecycle_status' => self::RESOURCE_STATUS_PENDING_DELETION,
                'lifecycle_reason' => 'downgrade',
                'pending_deletion_at' => now(),
                'delete_after_at' => $deleteAt,
            ])->save();
            $usersToWarn[(int) $domain->user_id] = $deleteAt;
            $processed++;
        }

        if ($this->supportsFormsLifecycle()) {
            $formsCandidates = DB::table('forms_resource_states')
                ->where('status', self::RESOURCE_STATUS_SUSPENDED)
                ->whereIn('reason', $this->lifecycleResourceReasons())
                ->whereNotNull('suspended_at')
                ->get(['user_id', 'suspended_at']);

            foreach ($formsCandidates as $formsState) {
                $suspendedAt = Carbon::parse((string) $formsState->suspended_at);
                if ($suspendedAt->copy()->addDays($retentionDays)->isFuture()) {
                    continue;
                }

                $deleteAt = now()->addDays($pendingDeletionDays);
                $this->setFormsPendingDeletion((int) $formsState->user_id, 'downgrade', $deleteAt);
                $usersToWarn[(int) $formsState->user_id] = $deleteAt;
                $formsPendingByUser[(int) $formsState->user_id] = (($formsPendingByUser[(int) $formsState->user_id] ?? 0) + 1);
                $processed++;
            }
        }

        $analyticsCandidates = AnalyticsResourceState::query()
            ->where('status', self::RESOURCE_STATUS_SUSPENDED)
            ->whereIn('reason', $this->lifecycleResourceReasons())
            ->whereNotNull('suspended_at')
            ->get();

        foreach ($analyticsCandidates as $state) {
            $suspendedAt = Carbon::parse($state->suspended_at);
            if ($suspendedAt->copy()->addDays($retentionDays)->isFuture()) {
                continue;
            }

            $deleteAt = now()->addDays($pendingDeletionDays);
            $state->forceFill([
                'status' => self::RESOURCE_STATUS_PENDING_DELETION,
                'reason' => 'downgrade',
                'pending_deletion_at' => now(),
                'delete_after_at' => $deleteAt,
            ])->save();

            $this->updateUserLifecycleColumns((int) $state->user_id, [
                'analytics_status' => self::RESOURCE_STATUS_PENDING_DELETION,
                'analytics_pending_deletion_at' => now(),
                'analytics_delete_after_at' => $deleteAt,
                'updated_at' => now(),
            ]);

            $usersToWarn[(int) $state->user_id] = $deleteAt;
            $processed++;
        }

        if ($this->supportsMetaLifecycle()) {
            $metaCandidates = User::query()
                ->where('meta_tags_status', self::RESOURCE_STATUS_SUSPENDED)
                ->whereIn('meta_tags_status_reason', $this->lifecycleResourceReasons())
                ->whereNotNull('meta_tags_suspended_at')
                ->get(['id', 'meta_tags_suspended_at']);

            foreach ($metaCandidates as $metaUser) {
                $suspendedAt = Carbon::parse($metaUser->meta_tags_suspended_at);
                if ($suspendedAt->copy()->addDays($retentionDays)->isFuture()) {
                    continue;
                }

                $deleteAt = now()->addDays($pendingDeletionDays);
                $this->setMetaTagsPendingDeletion((int) $metaUser->id, 'downgrade', $deleteAt);
                $usersToWarn[(int) $metaUser->id] = $deleteAt;
                $metaPendingByUser[(int) $metaUser->id] = (($metaPendingByUser[(int) $metaUser->id] ?? 0) + 1);
                $processed++;
            }
        }

        $hubsPendingByUser = [];
        if ($this->hubDowngradeAutoCleanupEnabled() && Schema::hasTable('agency_hubs')) {
            $ownerIds = AgencyHub::query()
                ->distinct()
                ->pluck('agency_user_id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($this->supportsHubInventoryQuotaColumns()) {
                $trackedOwnerIds = UserSubscription::query()
                    ->whereNotNull('hub_inventory_over_quota_since')
                    ->pluck('user_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();
                $ownerIds = array_values(array_unique(array_merge($ownerIds, $trackedOwnerIds)));
            }

            foreach ($ownerIds as $agencyUserId) {
                $allowedManaged = $this->allowedManagedHubSlotsForUser($agencyUserId);
                $totalManaged = $this->currentManagedHubInventoryCount($agencyUserId);
                $overQuota = max(0, $totalManaged - $allowedManaged);

                if ($overQuota <= 0) {
                    $this->setHubInventoryOverQuotaState($agencyUserId, null, 0);
                    continue;
                }

                $overQuotaSince = $this->hubInventoryOverQuotaSince($agencyUserId);
                if ($overQuotaSince === null) {
                    $this->setHubInventoryOverQuotaState($agencyUserId, now(), $overQuota);
                    continue;
                }

                $this->setHubInventoryOverQuotaState($agencyUserId, $overQuotaSince, $overQuota);
                if ($overQuotaSince->copy()->addDays($retentionDays)->isFuture()) {
                    continue;
                }

                $alreadyPending = AgencyHub::query()
                    ->where('agency_user_id', $agencyUserId)
                    ->where('status', self::RESOURCE_STATUS_PENDING_DELETION)
                    ->count();
                $toScheduleCount = max(0, $overQuota - $alreadyPending);
                if ($toScheduleCount <= 0) {
                    continue;
                }

                $selectedHubIds = $this->hubCandidateRowsByLeastLinks($agencyUserId, [self::RESOURCE_STATUS_SUSPENDED], true)
                    ->take($toScheduleCount)
                    ->pluck('hub_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                $remaining = $toScheduleCount - count($selectedHubIds);
                if ($remaining > 0) {
                    $fallbackRows = $this->hubCandidateRowsByLeastLinks($agencyUserId, [self::RESOURCE_STATUS_ACTIVE], false)
                        ->take($remaining);

                    $suspendUpdates = $this->filterTableColumns('agency_hubs', [
                        'status' => self::RESOURCE_STATUS_SUSPENDED,
                        'lifecycle_reason' => 'downgrade',
                        'suspended_at' => now(),
                        'updated_at' => now(),
                    ]);

                    foreach ($fallbackRows as $row) {
                        if (empty($suspendUpdates)) {
                            continue;
                        }

                        $updated = AgencyHub::query()
                            ->whereKey((int) ($row['hub_id'] ?? 0))
                            ->where('agency_user_id', $agencyUserId)
                            ->where('status', self::RESOURCE_STATUS_ACTIVE)
                            ->update($suspendUpdates);

                        if ($updated > 0) {
                            $selectedHubIds[] = (int) ($row['hub_id'] ?? 0);
                        }
                    }
                }

                $selectedHubIds = collect($selectedHubIds)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->take($toScheduleCount)
                    ->values()
                    ->all();

                foreach ($selectedHubIds as $hubId) {
                    $deleteAt = now()->addDays($pendingDeletionDays);
                    $updates = $this->filterTableColumns('agency_hubs', [
                        'status' => self::RESOURCE_STATUS_PENDING_DELETION,
                        'pending_deletion_at' => now(),
                        'delete_after_at' => $deleteAt,
                        'updated_at' => now(),
                    ]);
                    if (empty($updates)) {
                        continue;
                    }

                    $updated = AgencyHub::query()
                        ->whereKey($hubId)
                        ->where('agency_user_id', $agencyUserId)
                        ->where('status', self::RESOURCE_STATUS_SUSPENDED)
                        ->update($updates);
                    if ($updated <= 0) {
                        continue;
                    }

                    $usersToWarn[$agencyUserId] = $deleteAt;
                    $hubsPendingByUser[$agencyUserId] = (($hubsPendingByUser[$agencyUserId] ?? 0) + 1);
                    $processed++;
                }
            }
        }

        foreach ($usersToWarn as $userId => $deleteAt) {
            $this->notifications->enqueue((int) $userId, 'resources_pending_deletion_warning', [
                'delete_at' => $deleteAt instanceof Carbon ? $deleteAt->toIso8601String() : Carbon::parse($deleteAt)->toIso8601String(),
                'upgrade_url' => url('/dashboard/subscription'),
                'hubs_pending_deletion_count' => (int) ($hubsPendingByUser[(int) $userId] ?? 0),
                'forms_pending_deletion_count' => (int) ($formsPendingByUser[(int) $userId] ?? 0),
                'meta_tags_pending_deletion_count' => (int) ($metaPendingByUser[(int) $userId] ?? 0),
            ], 'check_suspended_resources');

            $this->audit->event((int) $userId, 'resources_pending_deletion', 'downgrade', [
                'delete_at' => $deleteAt instanceof Carbon ? $deleteAt->toIso8601String() : Carbon::parse($deleteAt)->toIso8601String(),
                'hubs_pending_deletion_count' => (int) ($hubsPendingByUser[(int) $userId] ?? 0),
                'forms_pending_deletion_count' => (int) ($formsPendingByUser[(int) $userId] ?? 0),
                'meta_tags_pending_deletion_count' => (int) ($metaPendingByUser[(int) $userId] ?? 0),
            ]);
        }

        return $processed;
    }

    public function handlePendingDeletions(): int
    {
        $processed = 0;

        $domainRows = collect();
        if ($this->supportsDomainLifecycle()) {
            $domainRows = UserCustomDomain::query()
                ->where('lifecycle_status', self::RESOURCE_STATUS_PENDING_DELETION)
                ->whereNotNull('delete_after_at')
                ->where('delete_after_at', '<=', now())
                ->get();
        }

        $domainDeletedByUser = [];
        foreach ($domainRows as $domain) {
            $domainDeletedByUser[(int) $domain->user_id] = (($domainDeletedByUser[(int) $domain->user_id] ?? 0) + 1);
            $domain->delete();
            $processed++;
        }

        $analyticsRows = AnalyticsResourceState::query()
            ->where('status', self::RESOURCE_STATUS_PENDING_DELETION)
            ->whereNotNull('delete_after_at')
            ->where('delete_after_at', '<=', now())
            ->get();

        $analyticsDeletedByUser = [];
        $analyticsRollupDeletedByUser = [];
        foreach ($analyticsRows as $state) {
            $userId = (int) $state->user_id;
            $purge = $this->purgeAnalyticsDataForUser($userId);
            $deletedEvents = (int) ($purge['legacy_events'] ?? 0);
            $deletedRollupRows = (int) ($purge['rollup_rows'] ?? 0) + (int) ($purge['site_tier_rows'] ?? 0);

            $state->forceFill([
                'status' => self::RESOURCE_STATUS_DELETED,
                'deleted_at' => now(),
                'delete_after_at' => null,
            ])->save();

            $this->updateUserLifecycleColumns($userId, [
                'analytics_status' => self::RESOURCE_STATUS_DELETED,
                'analytics_deleted_at' => now(),
                'analytics_delete_after_at' => null,
                'updated_at' => now(),
            ]);

            $analyticsDeletedByUser[$userId] = (($analyticsDeletedByUser[$userId] ?? 0) + (int) $deletedEvents);
            $analyticsRollupDeletedByUser[$userId] = (($analyticsRollupDeletedByUser[$userId] ?? 0) + $deletedRollupRows);
            $processed++;
        }

        $formsDeletedByUser = [];
        if ($this->supportsFormsLifecycle()) {
            $formsRows = DB::table('forms_resource_states')
                ->where('status', self::RESOURCE_STATUS_PENDING_DELETION)
                ->whereNotNull('delete_after_at')
                ->where('delete_after_at', '<=', now())
                ->get(['id', 'user_id', 'tenant_owner_user_id', 'reason']);

            foreach ($formsRows as $formsState) {
                $userId = (int) $formsState->user_id;
                if ($userId <= 0) {
                    continue;
                }

                $tenantOwnerUserId = (int) ($formsState->tenant_owner_user_id ?? 0);
                if ($tenantOwnerUserId <= 0) {
                    $tenantOwnerUserId = $userId;
                }

                $deletedSubmissions = $this->purgeFormsDataForTenantOwner($tenantOwnerUserId);
                $reason = trim((string) ($formsState->reason ?? 'downgrade'));
                if ($reason === '') {
                    $reason = 'downgrade';
                }

                $stateUpdates = $this->filterTableColumns('forms_resource_states', [
                    'status' => self::RESOURCE_STATUS_DELETED,
                    'reason' => $reason,
                    'pending_deletion_at' => null,
                    'delete_after_at' => null,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
                if (!empty($stateUpdates)) {
                    DB::table('forms_resource_states')
                        ->where('id', (int) $formsState->id)
                        ->update($stateUpdates);
                }

                $formsDeletedByUser[$userId] = (($formsDeletedByUser[$userId] ?? 0) + $deletedSubmissions);
                $processed++;
            }
        }

        $metaTagsDeletedByUser = [];
        if ($this->supportsMetaLifecycle()) {
            $metaRows = User::query()
                ->where('meta_tags_status', self::RESOURCE_STATUS_PENDING_DELETION)
                ->whereNotNull('meta_tags_delete_after_at')
                ->where('meta_tags_delete_after_at', '<=', now())
                ->get(['id', 'meta_overrides', 'meta_tags_status_reason']);

            foreach ($metaRows as $metaUser) {
                $userId = (int) $metaUser->id;
                if ($userId <= 0) {
                    continue;
                }

                $hadOverrides = !empty($metaUser->meta_overrides);
                $reason = trim((string) ($metaUser->meta_tags_status_reason ?? 'downgrade'));
                if ($reason === '') {
                    $reason = 'downgrade';
                }

                $this->updateUserLifecycleColumns($userId, [
                    'meta_overrides' => null,
                    'meta_tags_status' => self::RESOURCE_STATUS_DELETED,
                    'meta_tags_status_reason' => $reason,
                    'meta_tags_pending_deletion_at' => null,
                    'meta_tags_deleted_at' => now(),
                    'meta_tags_delete_after_at' => null,
                    'updated_at' => now(),
                ]);

                $metaTagsDeletedByUser[$userId] = (($metaTagsDeletedByUser[$userId] ?? 0) + ($hadOverrides ? 1 : 0));
                $processed++;
            }
        }

        $hubDeletedByUser = [];
        if (Schema::hasTable('agency_hubs')) {
            $hubRows = AgencyHub::query()
                ->where('status', self::RESOURCE_STATUS_PENDING_DELETION)
                ->whereNotNull('delete_after_at')
                ->where('delete_after_at', '<=', now())
                ->orderBy('id')
                ->get();

            foreach ($hubRows as $hub) {
                $agencyUserId = (int) $hub->agency_user_id;
                $managedUserId = (int) $hub->managed_user_id;
                if ($agencyUserId <= 0 || $managedUserId <= 0 || $agencyUserId === $managedUserId) {
                    continue;
                }

                $deleted = (bool) DB::transaction(function () use ($agencyUserId, $managedUserId): bool {
                    User::query()
                        ->whereKey($agencyUserId)
                        ->lockForUpdate()
                        ->value('id');

                    $lockedHub = AgencyHub::query()
                        ->where('agency_user_id', $agencyUserId)
                        ->where('managed_user_id', $managedUserId)
                        ->where('status', self::RESOURCE_STATUS_PENDING_DELETION)
                        ->lockForUpdate()
                        ->first();
                    if (!$lockedHub) {
                        return false;
                    }

                    return $this->hardDeleteManagedHub($agencyUserId, $managedUserId);
                });

                if ($deleted) {
                    $hubDeletedByUser[$agencyUserId] = (($hubDeletedByUser[$agencyUserId] ?? 0) + 1);
                    $processed++;
                }
            }
        }

        $usersToNotify = array_values(array_unique(array_merge(
            array_keys($domainDeletedByUser),
            array_keys($formsDeletedByUser),
            array_keys($analyticsDeletedByUser),
            array_keys($metaTagsDeletedByUser),
            array_keys($hubDeletedByUser),
        )));

        foreach ($usersToNotify as $userId) {
            $domainsDeleted = (int) ($domainDeletedByUser[$userId] ?? 0);
            $formsDeletedSubmissions = (int) ($formsDeletedByUser[$userId] ?? 0);
            $analyticsDeletedEvents = (int) ($analyticsDeletedByUser[$userId] ?? 0);
            $analyticsDeletedRollupRows = (int) ($analyticsRollupDeletedByUser[$userId] ?? 0);
            $metaTagsDeleted = (int) ($metaTagsDeletedByUser[$userId] ?? 0);
            $hubsDeleted = (int) ($hubDeletedByUser[$userId] ?? 0);

            if ($formsDeletedSubmissions > 0) {
                Log::info('Forms submissions purged via lifecycle pending deletion', [
                    'job' => 'check_pending_deletions',
                    'prune_job' => 'check_pending_deletions',
                    'user_id' => (int) $userId,
                    'forms_deleted_submissions' => $formsDeletedSubmissions,
                    'deleted_at' => now()->toIso8601String(),
                ]);
            }

            $this->notifications->enqueue((int) $userId, 'resources_deleted_confirmation', [
                'domains_deleted' => $domainsDeleted,
                'forms_deleted_submissions' => $formsDeletedSubmissions,
                'analytics_deleted_events' => $analyticsDeletedEvents,
                'analytics_deleted_rollup_rows' => $analyticsDeletedRollupRows,
                'meta_tags_deleted' => $metaTagsDeleted,
                'hubs_deleted' => $hubsDeleted,
                'deleted_at' => now()->toIso8601String(),
            ], 'check_pending_deletions');

            $this->audit->event((int) $userId, 'resources_deleted', 'downgrade', [
                'domains_deleted' => $domainsDeleted,
                'forms_deleted_submissions' => $formsDeletedSubmissions,
                'analytics_deleted_events' => $analyticsDeletedEvents,
                'analytics_deleted_rollup_rows' => $analyticsDeletedRollupRows,
                'meta_tags_deleted' => $metaTagsDeleted,
                'hubs_deleted' => $hubsDeleted,
            ]);
        }

        $accountsToDelete = User::query()
            ->whereIn('account_status', [self::ACCOUNT_STATUS_PENDING_DELETION, self::ACCOUNT_STATUS_SUSPENDED])
            ->whereNotNull('account_delete_after_at')
            ->where('account_delete_after_at', '<=', now())
            ->get();

        foreach ($accountsToDelete as $user) {
            $reason = (string) ($user->account_status_reason ?? 'manual');
            $this->finalizeAccountDeletion($user, $reason, 'check_pending_deletions');
            $processed++;
        }

        return $processed;
    }

    public function suspendHubByUser(User $agencyUser, int $managedUserId): bool
    {
        $result = DB::transaction(function () use ($agencyUser, $managedUserId): ?array {
            User::query()
                ->whereKey((int) $agencyUser->id)
                ->lockForUpdate()
                ->value('id');

            $hub = AgencyHub::query()
                ->where('agency_user_id', (int) $agencyUser->id)
                ->where('managed_user_id', $managedUserId)
                ->where('status', self::RESOURCE_STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (!$hub) {
                return null;
            }

            $hubUpdates = $this->filterTableColumns('agency_hubs', [
                'status' => self::RESOURCE_STATUS_SUSPENDED,
                'lifecycle_reason' => 'downgrade',
                'suspended_at' => now(),
                'updated_at' => now(),
            ]);
            if (empty($hubUpdates)) {
                return null;
            }

            AgencyHub::query()
                ->whereKey((int) $hub->id)
                ->update($hubUpdates);

            $subscription = UserSubscription::query()->where('user_id', (int) $agencyUser->id)->first();
            if ($subscription) {
                $allowedManaged = $this->allowedManagedHubSlots($subscription);
                $activeManaged = AgencyHub::query()
                    ->where('agency_user_id', (int) $agencyUser->id)
                    ->where('status', self::RESOURCE_STATUS_ACTIVE)
                    ->count();

                if ($activeManaged <= $allowedManaged) {
                    $subscriptionUpdates = $this->filterTableColumns('user_subscriptions', [
                        'agency_grace_period_started_at' => null,
                        'agency_grace_period_ends_at' => null,
                        'agency_over_quota_count' => null,
                        'updated_at' => now(),
                    ]);
                    if (!empty($subscriptionUpdates)) {
                        UserSubscription::query()
                            ->whereKey((int) $subscription->id)
                            ->update($subscriptionUpdates);
                    }
                }
            }

            return [
                'hub_id' => (int) $hub->id,
                'managed_user_id' => (int) $hub->managed_user_id,
                'hub_name' => (string) $hub->display_name,
            ];
        });

        if (!is_array($result)) {
            return false;
        }

        $this->notifications->enqueue((int) $agencyUser->id, 'agency_hub_suspended_by_user', [
            'hub_name' => (string) ($result['hub_name'] ?? ''),
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'manage_hubs_url' => route('agency.hubs.index'),
        ], 'agency_hub_user_action');

        $this->audit->event((int) $agencyUser->id, 'hub_suspended_by_user', 'downgrade', [
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'managed_user_id' => (int) ($result['managed_user_id'] ?? 0),
            'hub_name' => (string) ($result['hub_name'] ?? ''),
        ]);

        return true;
    }

    public function availableManagedHubSlots(User $agencyUser): int
    {
        $subscription = UserSubscription::query()
            ->where('user_id', (int) $agencyUser->id)
            ->first();
        if (!$subscription) {
            return 0;
        }

        $allowedManaged = $this->allowedManagedHubSlots($subscription);
        $activeManaged = AgencyHub::query()
            ->where('agency_user_id', (int) $agencyUser->id)
            ->where('status', self::RESOURCE_STATUS_ACTIVE)
            ->count();

        return max(0, $allowedManaged - $activeManaged);
    }

    public function reactivateHubByUser(User $agencyUser, int $managedUserId, bool $enforceSlotCheck = true): bool
    {
        if ($managedUserId <= 0 || $managedUserId === (int) $agencyUser->id) {
            return false;
        }

        $result = DB::transaction(function () use ($agencyUser, $managedUserId, $enforceSlotCheck): ?array {
            User::query()
                ->whereKey((int) $agencyUser->id)
                ->lockForUpdate()
                ->value('id');

            $hub = AgencyHub::query()
                ->where('agency_user_id', (int) $agencyUser->id)
                ->where('managed_user_id', $managedUserId)
                ->where('status', self::RESOURCE_STATUS_SUSPENDED)
                ->lockForUpdate()
                ->first();
            if (!$hub) {
                return null;
            }

            if ($enforceSlotCheck) {
                $subscription = UserSubscription::query()
                    ->where('user_id', (int) $agencyUser->id)
                    ->first();
                if (!$subscription) {
                    return null;
                }

                $allowedManaged = $this->allowedManagedHubSlots($subscription);
                $activeManaged = AgencyHub::query()
                    ->where('agency_user_id', (int) $agencyUser->id)
                    ->where('status', self::RESOURCE_STATUS_ACTIVE)
                    ->count();
                if ($activeManaged >= $allowedManaged) {
                    return null;
                }
            }

            $updates = $this->filterTableColumns('agency_hubs', [
                'status' => self::RESOURCE_STATUS_ACTIVE,
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'updated_at' => now(),
            ]);
            if (empty($updates)) {
                return null;
            }

            AgencyHub::query()
                ->whereKey((int) $hub->id)
                ->update($updates);

            return [
                'hub_id' => (int) $hub->id,
                'managed_user_id' => (int) $hub->managed_user_id,
                'hub_name' => (string) $hub->display_name,
            ];
        });

        if (!is_array($result)) {
            return false;
        }

        $this->notifications->enqueue((int) $agencyUser->id, 'agency_hub_reactivated_by_user', [
            'hub_name' => (string) ($result['hub_name'] ?? ''),
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'manage_hubs_url' => route('agency.hubs.index'),
        ], 'agency_hub_user_action');

        $this->audit->event((int) $agencyUser->id, 'hub_reactivated_by_user', 'downgrade', [
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'managed_user_id' => (int) ($result['managed_user_id'] ?? 0),
            'hub_name' => (string) ($result['hub_name'] ?? ''),
        ]);

        return true;
    }

    public function permanentlyDeleteHubByUser(User $agencyUser, int $managedUserId): bool
    {
        if ($managedUserId <= 0 || $managedUserId === (int) $agencyUser->id) {
            return false;
        }

        $result = DB::transaction(function () use ($agencyUser, $managedUserId): ?array {
            User::query()
                ->whereKey((int) $agencyUser->id)
                ->lockForUpdate()
                ->value('id');

            $hub = AgencyHub::query()
                ->where('agency_user_id', (int) $agencyUser->id)
                ->where('managed_user_id', $managedUserId)
                ->whereIn('status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION])
                ->lockForUpdate()
                ->first();
            if (!$hub) {
                return null;
            }

            $deleted = $this->hardDeleteManagedHub((int) $agencyUser->id, (int) $hub->managed_user_id);
            if (!$deleted) {
                return null;
            }

            $reason = trim((string) ($hub->lifecycle_reason ?? 'downgrade'));
            if ($reason === '') {
                $reason = 'downgrade';
            }

            return [
                'hub_id' => (int) $hub->id,
                'managed_user_id' => (int) $hub->managed_user_id,
                'hub_name' => (string) $hub->display_name,
                'reason' => $reason,
            ];
        });

        if (!is_array($result)) {
            return false;
        }

        $this->notifications->enqueue((int) $agencyUser->id, 'agency_hub_deleted_by_user', [
            'hub_name' => (string) ($result['hub_name'] ?? ''),
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'manage_hubs_url' => route('agency.hubs.index'),
        ], 'agency_hub_user_action');

        $this->audit->event((int) $agencyUser->id, 'hub_deleted_by_user', (string) ($result['reason'] ?? 'downgrade'), [
            'hub_id' => (int) ($result['hub_id'] ?? 0),
            'managed_user_id' => (int) ($result['managed_user_id'] ?? 0),
            'hub_name' => (string) ($result['hub_name'] ?? ''),
        ]);

        return true;
    }

    public function finalizeDeletionNow(User $user, string $reason = 'admin_delete', string $source = 'admin_delete'): void
    {
        $normalizedReason = strtolower(trim($reason));
        if ($normalizedReason === '') {
            $normalizedReason = 'admin_delete';
        }

        $normalizedSource = strtolower(trim($source));
        if ($normalizedSource === '') {
            $normalizedSource = 'admin_delete';
        }

        $this->finalizeAccountDeletion($user, $normalizedReason, $normalizedSource);
    }

    public function scheduleSelfDeletion(User $user): void
    {
        $deleteAt = now()->addDays(7);

        $this->setAccountStatus($user, self::ACCOUNT_STATUS_PENDING_DELETION, 'manual', 'account_pending_deletion', [
            'delete_at' => $deleteAt->toIso8601String(),
            'initiated_by' => 'user',
        ]);

        $this->updateUserLifecycleColumns((int) $user->id, [
            'account_delete_after_at' => $deleteAt,
            'account_status_changed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->setDomainsPendingDeletion((int) $user->id, 'manual', $deleteAt);
        $this->setFormsPendingDeletion((int) $user->id, 'manual', $deleteAt);
        $this->setAnalyticsPendingDeletion((int) $user->id, 'manual', $deleteAt);
        $this->setMetaTagsPendingDeletion((int) $user->id, 'manual', $deleteAt);
        $this->setHubsPendingDeletion((int) $user->id, 'manual', $deleteAt);

        $this->notifications->enqueue((int) $user->id, 'deletion_final_warning', [
            'delete_at' => $deleteAt->toIso8601String(),
            'billing_portal_url' => url('/dashboard/subscription'),
        ], 'self_delete');
    }

    public function accountStatusOf(User $user): string
    {
        $status = trim((string) ($user->account_status ?? self::ACCOUNT_STATUS_ACTIVE));

        return $status !== '' ? $status : self::ACCOUNT_STATUS_ACTIVE;
    }

    public function publicPageUnavailableReason(int $userId): ?string
    {
        $columns = ['id', 'role'];
        if (Schema::hasColumn('users', 'account_status')) {
            $columns[] = 'account_status';
        }

        $user = User::query()->select($columns)->find($userId);
        if (!$user) {
            return null;
        }

        $status = $this->accountStatusOf($user);
        if (in_array($status, [self::ACCOUNT_STATUS_WARNED, self::ACCOUNT_STATUS_SUSPENDED, self::ACCOUNT_STATUS_PENDING_DELETION], true)) {
            return 'account_' . $status;
        }

        if ((string) $user->role === User::ROLE_AGENCY_HUB) {
            $hubStatus = AgencyHub::query()
                ->where('managed_user_id', $userId)
                ->value('status');

            if (is_string($hubStatus) && $hubStatus !== self::RESOURCE_STATUS_ACTIVE) {
                return 'hub_' . $hubStatus;
            }
        }

        return null;
    }

    private function planDirection(string $previousSlug, string $currentSlug): int
    {
        $prevIdx = $this->tierOrderIndex[$previousSlug] ?? null;
        $currIdx = $this->tierOrderIndex[$currentSlug] ?? null;
        if ($prevIdx === null || $currIdx === null) {
            return 0;
        }

        return $currIdx <=> $prevIdx;
    }

    private function applyDowngradeLifecycle(
        UserSubscription $subscription,
        Tier $previousTier,
        Tier $currentTier,
        string $previousSlug,
        string $currentSlug
    ): void {
        $userId = (int) $subscription->user_id;
        $suspendedDomains = 0;
        $formsSuspended = false;
        $analyticsSuspended = false;
        $metaTagsSuspended = false;
        $formsRetentionRebased = null;
        $targetAllowsForms = $this->formsAllowedForTier($currentTier);
        $targetSupportsAnalytics = $this->tierResolver->featureEnabled($currentTier, 'analytics.enabled');

        if ($previousSlug === 'pro' && in_array($currentSlug, ['basic', 'free'], true)) {
            $suspendedDomains = $this->suspendDomains($userId, 'downgrade');
            $formsSuspended = !$targetAllowsForms
                ? $this->suspendForms($userId, 'downgrade')
                : false;
            $analyticsSuspended = !$targetSupportsAnalytics
                ? $this->suspendAnalytics($userId, 'downgrade')
                : false;
        } elseif ($previousSlug === 'basic' && $currentSlug === 'free') {
            $suspendedDomains = $this->suspendDomains($userId, 'downgrade');
            $formsSuspended = !$targetAllowsForms
                ? $this->suspendForms($userId, 'downgrade')
                : false;
        }

        if ($previousSlug === 'agency' && $currentSlug !== 'agency') {
            $this->suspendAgencyHubs($userId, 'downgrade');
            $suspendedDomains = max($suspendedDomains, $this->suspendDomains($userId, 'downgrade'));
            $formsSuspended = (!$targetAllowsForms
                ? $this->suspendForms($userId, 'downgrade')
                : false) || $formsSuspended;
            $analyticsSuspended = (!$targetSupportsAnalytics
                ? $this->suspendAnalytics($userId, 'downgrade')
                : false) || $analyticsSuspended;
        }

        $previousSupportsCustomMeta = $this->tierResolver->featureEnabled($previousTier, 'seo.custom_meta');
        $currentSupportsCustomMeta = $this->tierResolver->featureEnabled($currentTier, 'seo.custom_meta');
        if ($previousSupportsCustomMeta && !$currentSupportsCustomMeta) {
            $metaTagsSuspended = $this->suspendMetaTags($userId, 'downgrade');
        }

        if ($currentSlug === 'basic' && in_array($previousSlug, ['pro', 'agency'], true)) {
            $formsRetentionRebased = $this->rebaseFormsRetentionForTierTransition(
                $userId,
                $currentTier,
                now()->addDays($this->downgradeRetentionDays() + $this->pendingDeletionGraceDays()),
                'downgrade',
                'subscription_lifecycle_sync',
            );
        }

        $this->notifications->enqueue($userId, 'plan_downgrade_executed', [
            'previous_plan_slug' => $previousSlug,
            'previous_plan_name' => (string) ($previousTier->name ?? ucfirst($previousSlug)),
            'plan_slug' => $currentSlug,
            'plan_name' => (string) ($currentTier->name ?? ucfirst($currentSlug)),
            'suspended_domains_count' => $suspendedDomains,
            'forms_suspended' => $formsSuspended,
            'analytics_suspended' => $analyticsSuspended,
            'meta_tags_suspended' => $metaTagsSuspended,
            'forms_retention_rebased' => is_array($formsRetentionRebased),
            'forms_retention_rebased_rows' => (int) ($formsRetentionRebased['updated'] ?? 0),
            'forms_retention_rebased_shortened' => (int) ($formsRetentionRebased['shortened'] ?? 0),
            'forms_retention_rebased_extended' => (int) ($formsRetentionRebased['extended'] ?? 0),
            'forms_retention_prune_job' => 'forms:prune',
            'retention_days' => $this->downgradeRetentionDays(),
            'upgrade_url' => url('/dashboard/subscription'),
        ], 'subscription_lifecycle_sync');

        $this->audit->event($userId, 'plan_downgrade_executed', 'downgrade', [
            'previous_plan' => $previousSlug,
            'new_plan' => $currentSlug,
            'suspended_domains_count' => $suspendedDomains,
            'forms_suspended' => $formsSuspended,
            'analytics_suspended' => $analyticsSuspended,
            'meta_tags_suspended' => $metaTagsSuspended,
            'forms_retention_rebased' => is_array($formsRetentionRebased),
            'forms_retention_rebased_rows' => (int) ($formsRetentionRebased['updated'] ?? 0),
            'forms_retention_rebased_shortened' => (int) ($formsRetentionRebased['shortened'] ?? 0),
            'forms_retention_rebased_extended' => (int) ($formsRetentionRebased['extended'] ?? 0),
            'forms_retention_prune_job' => 'forms:prune',
        ]);

        if ($suspendedDomains > 0 || $formsSuspended || $analyticsSuspended || $metaTagsSuspended) {
            $this->audit->event($userId, 'features_suspended', 'downgrade', [
                'domains' => $suspendedDomains,
                'forms' => $formsSuspended,
                'analytics' => $analyticsSuspended,
                'meta_tags' => $metaTagsSuspended,
            ]);
        }
    }

    private function repairCurrentTierEntitlements(
        UserSubscription $subscription,
        Tier $currentTier,
        string $currentSlug,
        string $source
    ): void {
        $userId = (int) $subscription->user_id;
        if ($userId <= 0) {
            return;
        }

        $suspendedHubs = 0;
        $suspendedDomains = 0;
        $formsSuspended = false;
        $analyticsSuspended = false;
        $metaTagsSuspended = false;

        $supportsManagedHubs = $this->tierResolver->featureEnabled($currentTier, 'agency.managed_hubs');
        if (!$supportsManagedHubs) {
            $suspendedHubs = $this->suspendAgencyHubs($userId, 'downgrade');
        }

        $supportsCustomDomain = $this->tierResolver->featureEnabled($currentTier, 'domains.custom_domain');
        if (!$supportsCustomDomain) {
            $suspendedDomains = $this->suspendDomains($userId, 'downgrade');
        }

        if ($this->formsAllowedForTier($currentTier)) {
            $this->reactivateFormsForLifecycleReasons($userId);
        } else {
            $formsSuspended = $this->suspendForms($userId, 'downgrade');
        }

        $supportsAnalytics = $this->tierResolver->featureEnabled($currentTier, 'analytics.enabled');
        if ($supportsAnalytics) {
            $this->reactivateAnalyticsForLifecycleReasons($userId);
        } elseif (
            Schema::hasTable('analytics_resource_states')
            && AnalyticsResourceState::query()->where('user_id', $userId)->where('status', self::RESOURCE_STATUS_ACTIVE)->exists()
        ) {
            $analyticsSuspended = $this->suspendAnalytics($userId, 'downgrade');
        }

        $supportsCustomMeta = $this->tierResolver->featureEnabled($currentTier, 'seo.custom_meta');
        if (!$supportsCustomMeta && $this->supportsMetaLifecycle()) {
            $metaOverrides = User::query()
                ->whereKey($userId)
                ->value('meta_overrides');
            $hasMetaOverrides = is_array($metaOverrides)
                ? !empty($metaOverrides)
                : trim((string) $metaOverrides) !== '' && trim((string) $metaOverrides) !== 'null';

            if ($hasMetaOverrides) {
                $metaTagsSuspended = $this->suspendMetaTags($userId, 'downgrade');
            }
        }

        if ($suspendedHubs > 0 || $suspendedDomains > 0 || $formsSuspended || $analyticsSuspended || $metaTagsSuspended) {
            $this->audit->event($userId, 'tier_entitlements_repaired', 'downgrade', [
                'source' => $source,
                'current_plan' => $currentSlug,
                'suspended_hubs_count' => $suspendedHubs,
                'suspended_domains_count' => $suspendedDomains,
                'forms_suspended' => $formsSuspended,
                'analytics_suspended' => $analyticsSuspended,
                'meta_tags_suspended' => $metaTagsSuspended,
            ]);
        }
    }

    private function reactivateDowngradedResources(int $userId): void
    {
        $reasons = $this->lifecycleResourceReasons();

        if ($this->supportsDomainLifecycle()) {
            $domainUpdates = $this->filterTableColumns('user_custom_domains', [
                'lifecycle_status' => self::RESOURCE_STATUS_ACTIVE,
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'updated_at' => now(),
            ]);

            if (!empty($domainUpdates)) {
                $reactivatedDomains = UserCustomDomain::query()
                    ->where('user_id', $userId)
                    ->whereIn('lifecycle_reason', $reasons)
                    ->whereIn('lifecycle_status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION])
                    ->update($domainUpdates);

                if ($reactivatedDomains > 0) {
                    $this->domainSslService->queueTenantReconcileByResourceUserId($userId, [], $userId);
                }
            }
        }

        $this->reactivateFormsForLifecycleReasons($userId);
        $this->reactivateAnalyticsForLifecycleReasons($userId);
        $this->reactivateMetaTags($userId, $reasons);

        if ($this->supportsHubInventoryQuotaColumns()) {
            $subscriptionUpdates = $this->filterTableColumns('user_subscriptions', [
                'hub_inventory_over_quota_since' => null,
                'hub_inventory_over_quota_count' => null,
                'updated_at' => now(),
            ]);

            if (!empty($subscriptionUpdates)) {
                UserSubscription::query()
                    ->where('user_id', $userId)
                    ->update($subscriptionUpdates);
            }
        }
    }

    private function isSubscriptionRecovered(UserSubscription $subscription): bool
    {
        $status = strtolower(trim((string) ($subscription->stripe_status ?? '')));

        return in_array($status, ['active', 'trialing', 'complete'], true);
    }

    private function reactivateAfterPayment(UserSubscription $subscription, User $user): void
    {
        $oldStatus = $this->accountStatusOf($user);
        $this->setAccountStatus($user, self::ACCOUNT_STATUS_ACTIVE, 'non_payment', 'account_reactivated', [
            'from_status' => $oldStatus,
        ]);

        $subscription->forceFill([
            'payment_failed_at' => null,
            'payment_warning_sent_at' => null,
            'payment_restricted_at' => null,
            'payment_deletion_warning_sent_at' => null,
            'payment_pending_deletion_at' => null,
            'payment_delete_after_at' => null,
        ])->save();

        $currentTier = Tier::query()->find((int) ($subscription->tier_id ?? 0));
        $reasons = $this->lifecycleResourceReasons();

        if ($currentTier && $this->tierResolver->featureEnabled($currentTier, 'agency.managed_hubs')) {
            AgencyHub::query()
                ->where('agency_user_id', (int) $user->id)
                ->whereIn('lifecycle_reason', $reasons)
                ->whereIn('status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION])
                ->update($this->filterTableColumns('agency_hubs', [
                    'status' => self::RESOURCE_STATUS_ACTIVE,
                    'lifecycle_reason' => null,
                    'suspended_at' => null,
                    'pending_deletion_at' => null,
                    'delete_after_at' => null,
                    'deleted_at_lifecycle' => null,
                    'updated_at' => now(),
                ]));
        }

        if ($currentTier && $this->tierResolver->featureEnabled($currentTier, 'domains.custom_domain') && $this->supportsDomainLifecycle()) {
            $domainUpdates = $this->filterTableColumns('user_custom_domains', [
                'lifecycle_status' => self::RESOURCE_STATUS_ACTIVE,
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'updated_at' => now(),
            ]);
            if (!empty($domainUpdates)) {
                $reactivatedDomains = UserCustomDomain::query()
                    ->where('user_id', (int) $user->id)
                    ->whereIn('lifecycle_reason', $reasons)
                    ->whereIn('lifecycle_status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION])
                    ->update($domainUpdates);

                if ($reactivatedDomains > 0) {
                    $this->domainSslService->queueTenantReconcileByResourceUserId((int) $user->id, [], (int) $user->id);
                }
            }
        }

        if ($currentTier && $this->formsAllowedForTier($currentTier)) {
            $this->reactivateFormsForLifecycleReasons((int) $user->id);
        }

        if ($currentTier && $this->tierResolver->featureEnabled($currentTier, 'analytics.enabled')) {
            AnalyticsResourceState::query()
                ->where('user_id', (int) $user->id)
                ->whereIn('reason', $reasons)
                ->whereIn('status', [self::RESOURCE_STATUS_PENDING_DELETION, self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_DELETED])
                ->update([
                    'status' => self::RESOURCE_STATUS_ACTIVE,
                    'reason' => null,
                    'suspended_at' => null,
                    'pending_deletion_at' => null,
                    'delete_after_at' => null,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            $this->updateUserLifecycleColumns((int) $user->id, [
                'analytics_status' => self::RESOURCE_STATUS_ACTIVE,
                'analytics_status_reason' => null,
                'analytics_suspended_at' => null,
                'analytics_pending_deletion_at' => null,
                'analytics_delete_after_at' => null,
                'analytics_deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        if ($currentTier && $this->tierResolver->featureEnabled($currentTier, 'seo.custom_meta')) {
            $this->reactivateMetaTags((int) $user->id, $reasons);
        }

        $this->notifications->enqueue((int) $user->id, 'account_reactivated_confirmation', [
            'reactivated_at' => now()->toIso8601String(),
            'billing_portal_url' => url('/dashboard/subscription'),
        ], 'check_payment_status');

        $this->audit->event((int) $user->id, 'account_reactivated', 'non_payment');
    }

    private function setAccountStatus(User $user, string $newStatus, string $reason, string $eventType, array $metadata = []): void
    {
        $oldStatus = $this->accountStatusOf($user);
        if (
            (int) $user->id > 0
            && Schema::hasTable('users')
            && Schema::hasColumn('users', 'account_status')
        ) {
            $freshStatus = User::query()
                ->whereKey((int) $user->id)
                ->value('account_status');
            if (is_string($freshStatus) && trim($freshStatus) !== '') {
                $oldStatus = trim($freshStatus);
            }
        }

        if ($oldStatus === $newStatus) {
            return;
        }

        $this->updateUserLifecycleColumns((int) $user->id, [
            'account_status' => $newStatus,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
            'updated_at' => now(),
        ]);

        $user->forceFill([
            'account_status' => $newStatus,
            'account_status_reason' => $reason,
        ]);

        $this->audit->statusChange((int) $user->id, $eventType, $oldStatus, $newStatus, $reason, $metadata);
    }

    private function allowedManagedHubSlots(UserSubscription $subscription): int
    {
        $included = (int) ($subscription->hub_slots_included ?? 0);
        if ($included <= 0) {
            $tierId = (int) ($subscription->tier_id ?? 0);
            $tier = $tierId > 0 ? Tier::query()->find($tierId) : null;
            $limits = $this->tierResolver->limits($tier);
            $included = (int) ($limits['agency_hub_slots'] ?? AgencyHubQuotaManager::minAgencyHubs());
        }
        $addon = max(0, (int) ($subscription->hub_slots_addon ?? 0));
        if ($included <= 1 && $addon === 0 && Schema::hasColumn('user_subscriptions', 'tier_id')) {
            $tierId = (int) ($subscription->tier_id ?? 0);
            if ($tierId <= 0) {
                return 0;
            }

            if (Schema::hasTable('tiers')) {
                $tierSlug = (string) Tier::query()
                    ->whereKey($tierId)
                    ->value('slug');

                if ($this->tierResolver->normalizeSlug($tierSlug) !== 'agency') {
                    return 0;
                }
            }
        }

        $included = AgencyHubQuotaManager::clampAgencyHubs($included, AgencyHubQuotaManager::minAgencyHubs());

        return max(0, ($included + $addon) - 1);
    }

    private function allowedManagedHubSlotsForUser(int $userId): int
    {
        if ($userId <= 0 || !Schema::hasTable('user_subscriptions')) {
            return 0;
        }

        $subscription = UserSubscription::query()
            ->where('user_id', $userId)
            ->first();
        if (!$subscription) {
            return 0;
        }

        return $this->allowedManagedHubSlots($subscription);
    }

    private function currentManagedHubInventoryCount(int $userId): int
    {
        if ($userId <= 0 || !Schema::hasTable('agency_hubs')) {
            return 0;
        }

        return AgencyHub::query()
            ->where('agency_user_id', $userId)
            ->whereIn('status', [
                self::RESOURCE_STATUS_ACTIVE,
                self::RESOURCE_STATUS_SUSPENDED,
                self::RESOURCE_STATUS_PENDING_DELETION,
            ])
            ->count();
    }

    private function supportsHubInventoryQuotaColumns(): bool
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return false;
        }

        return Schema::hasColumn('user_subscriptions', 'hub_inventory_over_quota_since')
            && Schema::hasColumn('user_subscriptions', 'hub_inventory_over_quota_count');
    }

    private function hubInventoryOverQuotaSince(int $userId): ?Carbon
    {
        if ($userId <= 0 || !$this->supportsHubInventoryQuotaColumns()) {
            return null;
        }

        $value = UserSubscription::query()
            ->where('user_id', $userId)
            ->value('hub_inventory_over_quota_since');

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function setHubInventoryOverQuotaState(int $userId, ?Carbon $since, int $count): void
    {
        if ($userId <= 0 || !$this->supportsHubInventoryQuotaColumns()) {
            return;
        }

        $updates = $this->filterTableColumns('user_subscriptions', [
            'hub_inventory_over_quota_since' => $since,
            'hub_inventory_over_quota_count' => $count > 0 ? $count : null,
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return;
        }

        UserSubscription::query()
            ->where('user_id', $userId)
            ->update($updates);
    }

    /**
     * @param array<int,string> $statuses
     * @return Collection<int,array<string,mixed>>
     */
    private function hubCandidateRowsByLeastLinks(int $agencyUserId, array $statuses, bool $downgradeOnly): Collection
    {
        if ($agencyUserId <= 0 || !Schema::hasTable('agency_hubs')) {
            return collect();
        }

        $normalizedStatuses = collect($statuses)
            ->map(static fn ($status): string => strtolower(trim((string) $status)))
            ->filter(static fn (string $status): bool => $status !== '')
            ->values();
        if ($normalizedStatuses->isEmpty()) {
            return collect();
        }

        $query = AgencyHub::query()
            ->where('agency_user_id', $agencyUserId)
            ->whereIn('status', $normalizedStatuses->all())
            ->orderByDesc('created_at');

        if ($downgradeOnly && Schema::hasColumn('agency_hubs', 'lifecycle_reason')) {
            $query->where('lifecycle_reason', 'downgrade');
        }

        $hubs = $query->get(['id', 'managed_user_id', 'created_at']);
        if ($hubs->isEmpty()) {
            return collect();
        }

        $managedIds = $hubs
            ->pluck('managed_user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $linksPerUser = collect();
        if (!empty($managedIds) && Schema::hasTable('links')) {
            $linksPerUser = Link::withDisabled()
                ->selectRaw('user_id, COUNT(*) as aggregate_count')
                ->whereIn('user_id', $managedIds)
                ->groupBy('user_id')
                ->pluck('aggregate_count', 'user_id');
        }

        return $hubs->map(function (AgencyHub $hub) use ($linksPerUser): array {
            $managedId = (int) $hub->managed_user_id;
            return [
                'hub_id' => (int) $hub->id,
                'managed_user_id' => $managedId,
                'links_count' => (int) ($linksPerUser[$managedId] ?? 0),
                'created_at_ts' => (int) optional($hub->created_at)->timestamp,
            ];
        })->sort(static function (array $left, array $right): int {
            $linksCompare = ((int) $left['links_count']) <=> ((int) $right['links_count']);
            if ($linksCompare !== 0) {
                return $linksCompare;
            }

            return ((int) $right['created_at_ts']) <=> ((int) $left['created_at_ts']);
        })->values();
    }

    private function suspendAgencyHubs(int $userId, string $reason): int
    {
        $updates = $this->filterTableColumns('agency_hubs', [
            'status' => self::RESOURCE_STATUS_SUSPENDED,
            'lifecycle_reason' => $reason,
            'suspended_at' => now(),
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return 0;
        }

        return AgencyHub::query()
            ->where('agency_user_id', $userId)
            ->where('status', self::RESOURCE_STATUS_ACTIVE)
            ->update($updates);
    }

    private function suspendDomains(int $userId, string $reason): int
    {
        if (!$this->supportsDomainLifecycle()) {
            return 0;
        }

        $updates = $this->filterTableColumns('user_custom_domains', [
            'lifecycle_status' => self::RESOURCE_STATUS_SUSPENDED,
            'lifecycle_reason' => $reason,
            'suspended_at' => now(),
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return 0;
        }

        $updated = UserCustomDomain::query()
            ->where('user_id', $userId)
            ->where('lifecycle_status', self::RESOURCE_STATUS_ACTIVE)
            ->update($updates);

        if ($updated > 0) {
            $this->domainSslService->queueTenantReconcileByResourceUserId($userId, [], $userId);
        }

        return $updated;
    }

    private function suspendForms(int $userId, string $reason): bool
    {
        if ($userId <= 0 || !$this->supportsFormsLifecycle()) {
            return false;
        }

        $table = 'forms_resource_states';
        $status = strtolower(trim((string) DB::table($table)->where('user_id', $userId)->value('status')));
        if ($status === self::RESOURCE_STATUS_DELETED) {
            return false;
        }

        $currentReason = trim((string) DB::table($table)->where('user_id', $userId)->value('reason'));
        if ($status === self::RESOURCE_STATUS_PENDING_DELETION) {
            return false;
        }
        if ($status === self::RESOURCE_STATUS_SUSPENDED && $currentReason === $reason) {
            return false;
        }

        $tenantOwnerUserId = $this->tenantOwnerForResourceUser($userId);
        if ($tenantOwnerUserId === null || $tenantOwnerUserId <= 0) {
            $tenantOwnerUserId = $userId;
        }

        $now = now();
        $updates = $this->filterTableColumns($table, [
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'status' => self::RESOURCE_STATUS_SUSPENDED,
            'reason' => $reason,
            'suspended_at' => $now,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'updated_at' => $now,
        ]);
        if (empty($updates)) {
            return false;
        }

        $query = DB::table($table)->where('user_id', $userId);
        $exists = $query->exists();
        if ($exists) {
            $query->update($updates);
        } else {
            $insert = $this->filterTableColumns($table, array_merge(['user_id' => $userId], $updates));
            if (Schema::hasColumn($table, 'created_at')) {
                $insert['created_at'] = $now;
            }
            DB::table($table)->insert($insert);
        }

        $this->extendFormsRetentionWindow($userId, $tenantOwnerUserId, $now->copy()->addDays(
            $this->downgradeRetentionDays() + $this->pendingDeletionGraceDays()
        ));

        $this->audit->event($userId, 'forms_suspended', $reason);

        return true;
    }

    private function suspendAnalytics(int $userId, string $reason): bool
    {
        if (!Schema::hasTable('analytics_resource_states')) {
            return false;
        }

        $tenantOwnerUserId = $this->tenantOwnerForResourceUser($userId);
        $state = AnalyticsResourceState::query()->firstOrCreate(
            ['user_id' => $userId],
            $this->filterTableColumns('analytics_resource_states', [
                'tenant_owner_user_id' => $tenantOwnerUserId,
                'status' => self::RESOURCE_STATUS_ACTIVE,
                'reason' => null,
            ])
        );

        if ($state->status === self::RESOURCE_STATUS_SUSPENDED && $state->reason === $reason) {
            return false;
        }

        $updates = $this->filterTableColumns('analytics_resource_states', [
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'status' => self::RESOURCE_STATUS_SUSPENDED,
            'reason' => $reason,
            'suspended_at' => now(),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
        ]);
        if (empty($updates)) {
            return false;
        }

        $state->forceFill($updates)->save();

        $this->updateUserLifecycleColumns($userId, [
            'analytics_status' => self::RESOURCE_STATUS_SUSPENDED,
            'analytics_status_reason' => $reason,
            'analytics_suspended_at' => now(),
            'analytics_pending_deletion_at' => null,
            'analytics_delete_after_at' => null,
            'analytics_deleted_at' => null,
            'updated_at' => now(),
        ]);

        $this->audit->event($userId, 'analytics_suspended', $reason);

        return true;
    }

    private function suspendMetaTags(int $userId, string $reason): bool
    {
        if (!$this->supportsMetaLifecycle()) {
            return false;
        }

        $status = User::query()
            ->whereKey($userId)
            ->value('meta_tags_status');
        $status = strtolower(trim((string) $status));
        if ($status === self::RESOURCE_STATUS_DELETED) {
            return false;
        }

        $currentReason = User::query()
            ->whereKey($userId)
            ->value('meta_tags_status_reason');
        if ($status === self::RESOURCE_STATUS_SUSPENDED && trim((string) $currentReason) === $reason) {
            return false;
        }

        $this->updateUserLifecycleColumns($userId, [
            'meta_tags_status' => self::RESOURCE_STATUS_SUSPENDED,
            'meta_tags_status_reason' => $reason,
            'meta_tags_suspended_at' => now(),
            'meta_tags_pending_deletion_at' => null,
            'meta_tags_delete_after_at' => null,
            'meta_tags_deleted_at' => null,
            'updated_at' => now(),
        ]);

        $this->audit->event($userId, 'meta_tags_suspended', $reason);

        return true;
    }

    /**
     * @param string|array<int,string> $reason
     */
    private function reactivateMetaTags(int $userId, string|array $reason): bool
    {
        if (!$this->supportsMetaLifecycle()) {
            return false;
        }

        $reasons = is_array($reason) ? array_values($reason) : [$reason];
        $reasons = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $reasons
        ), static fn (string $value): bool => $value !== '')));
        if ($reasons === []) {
            return false;
        }

        $updates = $this->filterExistingUserColumns([
            'meta_tags_status' => self::RESOURCE_STATUS_ACTIVE,
            'meta_tags_status_reason' => null,
            'meta_tags_suspended_at' => null,
            'meta_tags_pending_deletion_at' => null,
            'meta_tags_delete_after_at' => null,
            'meta_tags_deleted_at' => null,
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return false;
        }

        $updated = User::query()
            ->whereKey($userId)
            ->whereIn('meta_tags_status_reason', $reasons)
            ->whereIn('meta_tags_status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION, self::RESOURCE_STATUS_DELETED])
            ->update($updates);

        return $updated > 0;
    }

    private function setDomainsPendingDeletion(int $userId, string $reason, Carbon $deleteAt): void
    {
        if (!$this->supportsDomainLifecycle()) {
            return;
        }

        $updates = $this->filterTableColumns('user_custom_domains', [
            'lifecycle_status' => self::RESOURCE_STATUS_PENDING_DELETION,
            'lifecycle_reason' => $reason,
            'pending_deletion_at' => now(),
            'delete_after_at' => $deleteAt,
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return;
        }

        $updated = UserCustomDomain::query()
            ->where('user_id', $userId)
            ->whereIn('lifecycle_status', [self::RESOURCE_STATUS_ACTIVE, self::RESOURCE_STATUS_SUSPENDED])
            ->update($updates);

        if ($updated > 0) {
            $this->domainSslService->queueTenantReconcileByResourceUserId($userId, [], $userId);
        }
    }

    private function reactivateFormsForLifecycleReasons(int $userId): bool
    {
        if ($userId <= 0 || !$this->supportsFormsLifecycle()) {
            return false;
        }

        $updates = $this->filterTableColumns('forms_resource_states', [
            'status' => self::RESOURCE_STATUS_ACTIVE,
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return false;
        }

        $updated = DB::table('forms_resource_states')
            ->where('user_id', $userId)
            ->whereIn('reason', $this->lifecycleResourceReasons())
            ->whereIn('status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION, self::RESOURCE_STATUS_DELETED])
            ->update($updates);

        return $updated > 0;
    }

    private function setFormsPendingDeletion(int $userId, string $reason, Carbon $deleteAt): void
    {
        if ($userId <= 0 || !$this->supportsFormsLifecycle()) {
            return;
        }

        $table = 'forms_resource_states';
        $status = strtolower(trim((string) DB::table($table)->where('user_id', $userId)->value('status')));
        if ($status === self::RESOURCE_STATUS_DELETED) {
            return;
        }

        $tenantOwnerUserId = $this->tenantOwnerForResourceUser($userId);
        if ($tenantOwnerUserId === null || $tenantOwnerUserId <= 0) {
            $tenantOwnerUserId = $userId;
        }

        $now = now();
        $updates = $this->filterTableColumns($table, [
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'status' => self::RESOURCE_STATUS_PENDING_DELETION,
            'reason' => $reason,
            'pending_deletion_at' => $now,
            'delete_after_at' => $deleteAt,
            'deleted_at' => null,
            'updated_at' => $now,
        ]);
        if (empty($updates)) {
            return;
        }

        $query = DB::table($table)->where('user_id', $userId);
        $exists = $query->exists();
        if ($exists) {
            $query->update($updates);
        } else {
            $insert = $this->filterTableColumns($table, array_merge(['user_id' => $userId], $updates));
            if (Schema::hasColumn($table, 'created_at')) {
                $insert['created_at'] = $now;
            }
            DB::table($table)->insert($insert);
        }

        $this->extendFormsRetentionWindow($userId, $tenantOwnerUserId, $deleteAt);
    }

    private function reactivateAnalyticsForLifecycleReasons(int $userId): bool
    {
        if (!Schema::hasTable('analytics_resource_states')) {
            return false;
        }

        $reasons = $this->lifecycleResourceReasons();
        $updated = AnalyticsResourceState::query()
            ->where('user_id', $userId)
            ->whereIn('reason', $reasons)
            ->whereIn('status', [self::RESOURCE_STATUS_SUSPENDED, self::RESOURCE_STATUS_PENDING_DELETION, self::RESOURCE_STATUS_DELETED])
            ->update([
                'status' => self::RESOURCE_STATUS_ACTIVE,
                'reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->updateUserLifecycleColumns($userId, [
                'analytics_status' => self::RESOURCE_STATUS_ACTIVE,
                'analytics_status_reason' => null,
                'analytics_suspended_at' => null,
                'analytics_pending_deletion_at' => null,
                'analytics_delete_after_at' => null,
                'analytics_deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        return $updated > 0;
    }

    private function setAnalyticsPendingDeletion(int $userId, string $reason, Carbon $deleteAt): void
    {
        if (!Schema::hasTable('analytics_resource_states')) {
            return;
        }

        $tenantOwnerUserId = $this->tenantOwnerForResourceUser($userId);
        AnalyticsResourceState::query()->updateOrCreate(
            ['user_id' => $userId],
            $this->filterTableColumns('analytics_resource_states', [
                'tenant_owner_user_id' => $tenantOwnerUserId,
                'status' => self::RESOURCE_STATUS_PENDING_DELETION,
                'reason' => $reason,
                'pending_deletion_at' => now(),
                'delete_after_at' => $deleteAt,
                'updated_at' => now(),
            ])
        );

        $this->updateUserLifecycleColumns($userId, [
            'analytics_status' => self::RESOURCE_STATUS_PENDING_DELETION,
            'analytics_status_reason' => $reason,
            'analytics_pending_deletion_at' => now(),
            'analytics_delete_after_at' => $deleteAt,
            'updated_at' => now(),
        ]);
    }

    private function setMetaTagsPendingDeletion(int $userId, string $reason, Carbon $deleteAt): void
    {
        if (!$this->supportsMetaLifecycle()) {
            return;
        }

        $currentStatus = User::query()
            ->whereKey($userId)
            ->value('meta_tags_status');
        $currentStatus = strtolower(trim((string) $currentStatus));
        if ($currentStatus === self::RESOURCE_STATUS_DELETED) {
            return;
        }

        $this->updateUserLifecycleColumns($userId, [
            'meta_tags_status' => self::RESOURCE_STATUS_PENDING_DELETION,
            'meta_tags_status_reason' => $reason,
            'meta_tags_pending_deletion_at' => now(),
            'meta_tags_delete_after_at' => $deleteAt,
            'meta_tags_deleted_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function setHubsPendingDeletion(int $userId, string $reason, Carbon $deleteAt): void
    {
        $updates = $this->filterTableColumns('agency_hubs', [
            'status' => self::RESOURCE_STATUS_PENDING_DELETION,
            'lifecycle_reason' => $reason,
            'pending_deletion_at' => now(),
            'delete_after_at' => $deleteAt,
            'updated_at' => now(),
        ]);
        if (empty($updates)) {
            return;
        }

        AgencyHub::query()
            ->where('agency_user_id', $userId)
            ->whereIn('status', [self::RESOURCE_STATUS_ACTIVE, self::RESOURCE_STATUS_SUSPENDED])
            ->update($updates);
    }

    /**
     * @return array{scanned:int,updated:int,extended:int,shortened:int,retention_days:int,minimum_until:?string}|null
     */
    private function rebaseFormsRetentionForTierTransition(
        int $userId,
        Tier $targetTier,
        ?Carbon $minimumUntil,
        string $reason,
        string $triggerJob,
    ): ?array {
        $retentionDays = max(1, (int) $this->tierResolver->analyticsRetention($targetTier));
        $tenantOwnerUserId = $this->tenantOwnerForResourceUser($userId);
        if ($tenantOwnerUserId === null || $tenantOwnerUserId <= 0) {
            $tenantOwnerUserId = $userId;
        }

        $summary = $this->rebaseFormsRetentionWindow(
            $userId,
            $tenantOwnerUserId,
            $retentionDays,
            $minimumUntil,
        );
        if ($summary === null) {
            return null;
        }

        $logPayload = [
            'job' => $triggerJob,
            'user_id' => $userId,
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'target_tier_slug' => $this->tierResolver->normalizeSlug((string) $targetTier->slug),
            'retention_days' => $summary['retention_days'],
            'minimum_until' => $summary['minimum_until'],
            'scanned' => $summary['scanned'],
            'updated' => $summary['updated'],
            'extended' => $summary['extended'],
            'shortened' => $summary['shortened'],
            'prune_job' => 'forms:prune',
        ];

        Log::info('Forms retention rebased for tier transition', $logPayload);
        $this->audit->event($userId, 'forms_retention_rebased_for_tier', $reason, $logPayload);

        return $summary;
    }

    /**
     * @return array{scanned:int,updated:int,extended:int,shortened:int,retention_days:int,minimum_until:?string}|null
     */
    private function rebaseFormsRetentionWindow(
        int $userId,
        int $tenantOwnerUserId,
        int $retentionDays,
        ?Carbon $minimumUntil,
    ): ?array {
        if (
            $userId <= 0
            || $tenantOwnerUserId <= 0
            || !Schema::hasTable('forms_submissions')
            || !Schema::hasColumn('forms_submissions', 'id')
            || !Schema::hasColumn('forms_submissions', 'created_at')
            || !Schema::hasColumn('forms_submissions', 'retention_until')
        ) {
            return null;
        }

        $hasTenantOwner = Schema::hasColumn('forms_submissions', 'tenant_owner_user_id');
        $hasHubUser = Schema::hasColumn('forms_submissions', 'hub_user_id');
        if (!$hasTenantOwner && !$hasHubUser) {
            return null;
        }

        $minimumUntilNormalized = $minimumUntil?->copy()->setMicro(0);
        $summary = [
            'scanned' => 0,
            'updated' => 0,
            'extended' => 0,
            'shortened' => 0,
            'retention_days' => $retentionDays,
            'minimum_until' => $minimumUntilNormalized?->toDateTimeString(),
        ];

        $rows = DB::table('forms_submissions')
            ->select(['id', 'created_at', 'retention_until'])
            ->where(function ($builder) use ($hasTenantOwner, $hasHubUser, $tenantOwnerUserId, $userId): void {
                if ($hasTenantOwner) {
                    $builder->where('tenant_owner_user_id', $tenantOwnerUserId);
                }

                if ($hasHubUser) {
                    if ($hasTenantOwner) {
                        $builder->orWhere('hub_user_id', $userId);
                    } else {
                        $builder->where('hub_user_id', $userId);
                    }
                }
            })
            ->when(
                Schema::hasColumn('forms_submissions', 'deleted_at'),
                static fn ($query) => $query->whereNull('deleted_at')
            )
            ->orderBy('id')
            ->get();

        $hasUpdatedAt = Schema::hasColumn('forms_submissions', 'updated_at');
        foreach ($rows as $row) {
            $createdAtRaw = trim((string) ($row->created_at ?? ''));
            if ($createdAtRaw === '') {
                continue;
            }

            try {
                $createdAt = Carbon::parse($createdAtRaw)->setMicro(0);
            } catch (\Throwable) {
                continue;
            }

            $summary['scanned']++;
            $targetRetentionUntil = $createdAt->copy()->addDays($retentionDays)->setMicro(0);
            if ($minimumUntilNormalized && $targetRetentionUntil->lessThan($minimumUntilNormalized)) {
                $targetRetentionUntil = $minimumUntilNormalized->copy();
            }

            $currentRetentionUntil = null;
            $currentRaw = trim((string) ($row->retention_until ?? ''));
            if ($currentRaw !== '') {
                try {
                    $currentRetentionUntil = Carbon::parse($currentRaw)->setMicro(0);
                } catch (\Throwable) {
                    $currentRetentionUntil = null;
                }
            }

            if ($currentRetentionUntil && $currentRetentionUntil->equalTo($targetRetentionUntil)) {
                continue;
            }

            $updates = ['retention_until' => $targetRetentionUntil];
            if ($hasUpdatedAt) {
                $updates['updated_at'] = now();
            }

            DB::table('forms_submissions')
                ->where('id', (int) $row->id)
                ->update($updates);

            $summary['updated']++;
            if (!$currentRetentionUntil || $currentRetentionUntil->lessThan($targetRetentionUntil)) {
                $summary['extended']++;
            } elseif ($currentRetentionUntil->greaterThan($targetRetentionUntil)) {
                $summary['shortened']++;
            }
        }

        return $summary;
    }

    private function extendFormsRetentionWindow(int $userId, int $tenantOwnerUserId, Carbon $minimumUntil): void
    {
        if (
            !Schema::hasTable('forms_submissions')
            || !Schema::hasColumn('forms_submissions', 'retention_until')
        ) {
            return;
        }

        $hasTenantOwner = Schema::hasColumn('forms_submissions', 'tenant_owner_user_id');
        $hasHubUser = Schema::hasColumn('forms_submissions', 'hub_user_id');
        if (!$hasTenantOwner && !$hasHubUser) {
            return;
        }

        $query = DB::table('forms_submissions')
            ->where(function ($builder) use ($hasTenantOwner, $hasHubUser, $tenantOwnerUserId, $userId): void {
                if ($hasTenantOwner) {
                    $builder->where('tenant_owner_user_id', $tenantOwnerUserId);
                }

                if ($hasHubUser) {
                    if ($hasTenantOwner) {
                        $builder->orWhere('hub_user_id', $userId);
                    } else {
                        $builder->where('hub_user_id', $userId);
                    }
                }
            });

        if (Schema::hasColumn('forms_submissions', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->where('retention_until', '<', $minimumUntil)->update([
            'retention_until' => $minimumUntil,
        ]);
    }

    private function purgeFormsDataForTenantOwner(int $tenantOwnerUserId): int
    {
        if (
            $tenantOwnerUserId <= 0
            || !Schema::hasTable('forms_submissions')
        ) {
            return 0;
        }

        $hasTenantOwner = Schema::hasColumn('forms_submissions', 'tenant_owner_user_id');
        $hasHubUser = Schema::hasColumn('forms_submissions', 'hub_user_id');
        if (!$hasTenantOwner && !$hasHubUser) {
            return 0;
        }

        $query = DB::table('forms_submissions')
            ->where(function ($builder) use ($hasTenantOwner, $hasHubUser, $tenantOwnerUserId): void {
                if ($hasTenantOwner) {
                    $builder->where('tenant_owner_user_id', $tenantOwnerUserId);
                }

                if ($hasHubUser) {
                    if ($hasTenantOwner) {
                        $builder->orWhere('hub_user_id', $tenantOwnerUserId);
                    } else {
                        $builder->where('hub_user_id', $tenantOwnerUserId);
                    }
                }
            });

        if (Schema::hasColumn('forms_submissions', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->delete();
    }

    private function purgeFormsDataForHubUser(int $hubUserId): int
    {
        if ($hubUserId <= 0 || !Schema::hasTable('forms_submissions') || !Schema::hasColumn('forms_submissions', 'hub_user_id')) {
            return 0;
        }

        $query = DB::table('forms_submissions')->where('hub_user_id', $hubUserId);
        if (Schema::hasColumn('forms_submissions', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->delete();
    }

    /**
     * Purge analytics payloads for one site/resource user from legacy + rollup stores.
     *
     * @return array{legacy_events:int,rollup_rows:int,site_tier_rows:int}
     */
    private function purgeAnalyticsDataForUser(int $userId): array
    {
        if ($userId <= 0) {
            return ['legacy_events' => 0, 'rollup_rows' => 0, 'site_tier_rows' => 0];
        }

        $legacyEvents = 0;
        if (Schema::hasTable('analytics_events_extended')) {
            $legacyEvents = (int) DB::table('analytics_events_extended')
                ->where('user_id', $userId)
                ->delete();
        }

        $rollupRows = 0;
        $rollupTables = [
            'analytics_rollup_events',
            'analytics_rollup_events_daily',
            'analytics_rollup_events_monthly',
            'analytics_rollup_links',
            'analytics_rollup_links_monthly',
            'analytics_rollup_referrers',
            'analytics_rollup_referrers_monthly',
            'analytics_rollup_geo',
            'analytics_rollup_geo_monthly',
            'analytics_rollup_utm',
            'analytics_rollup_utm_monthly',
        ];

        foreach ($rollupTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'site_id')) {
                continue;
            }

            $rollupRows += (int) DB::table($table)
                ->where('site_id', $userId)
                ->delete();
        }

        $siteTierRows = 0;
        if (Schema::hasTable('analytics_site_tiers') && Schema::hasColumn('analytics_site_tiers', 'site_id')) {
            $siteTierRows = (int) DB::table('analytics_site_tiers')
                ->where('site_id', $userId)
                ->delete();
        }

        return [
            'legacy_events' => $legacyEvents,
            'rollup_rows' => $rollupRows,
            'site_tier_rows' => $siteTierRows,
        ];
    }

    private function hardDeleteManagedHub(int $agencyUserId, int $managedUserId): bool
    {
        if ($managedUserId <= 0 || $agencyUserId <= 0 || $managedUserId === $agencyUserId) {
            return false;
        }

        if (Schema::hasTable('user_custom_domains')) {
            $domainCleanupHostnames = $this->domainHostnamesForScope([$managedUserId], [$managedUserId]);

            DB::table('user_custom_domains')
                ->where('page_id', $managedUserId)
                ->orWhere('user_id', $managedUserId)
                ->delete();

            $this->queueDomainSslCleanupForHostnames($domainCleanupHostnames);
        }

        Link::withDisabled()->where('user_id', $managedUserId)->delete();

        $this->purgeFormsDataForHubUser($managedUserId);
        $this->purgeAnalyticsDataForUser($managedUserId);

        if (Schema::hasTable('analytics_resource_states')) {
            DB::table('analytics_resource_states')->where('user_id', $managedUserId)->delete();
        }
        if (Schema::hasTable('forms_resource_states')) {
            DB::table('forms_resource_states')->where('user_id', $managedUserId)->delete();
        }

        if (Schema::hasTable('user_settings')) {
            DB::table('user_settings')->where('user_id', $managedUserId)->delete();
        }

        if (Schema::hasTable('social_accounts')) {
            DB::table('social_accounts')->where('user_id', $managedUserId)->delete();
        }

        $hubDeleted = AgencyHub::query()
            ->where('agency_user_id', $agencyUserId)
            ->where('managed_user_id', $managedUserId)
            ->delete();

        User::query()->whereKey($managedUserId)->delete();

        return $hubDeleted > 0;
    }

    private function finalizeAccountDeletion(User $user, string $reason, string $source): void
    {
        $userId = (int) $user->id;
        $lastEmail = strtolower(trim((string) $user->email));

        $this->notifications->enqueue($userId, 'account_deleted_confirmation', [
            'deleted_at' => now()->toIso8601String(),
            'billing_retention_years' => $this->billingRetentionYears(),
            'source' => $source,
        ], $source, 'account_deleted|' . $userId . '|' . now()->toDateString());

        DB::transaction(function () use ($userId, $user, $reason, $lastEmail): void {
            $managedIds = [];
            if (Schema::hasTable('agency_hubs')) {
                $managedIds = AgencyHub::query()
                    ->where('agency_user_id', $userId)
                    ->pluck('managed_user_id')
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn (int $id) => $id > 0)
                    ->values()
                    ->all();
            }

            if (!empty($managedIds)) {
                if (Schema::hasTable('user_custom_domains')) {
                    $managedDomainCleanupHostnames = $this->domainHostnamesForScope($managedIds, $managedIds);

                    DB::table('user_custom_domains')
                        ->whereIn('page_id', $managedIds)
                        ->orWhereIn('user_id', $managedIds)
                        ->delete();

                    $this->queueDomainSslCleanupForHostnames($managedDomainCleanupHostnames);
                }

                Link::withDisabled()->whereIn('user_id', $managedIds)->delete();

                foreach ($managedIds as $managedId) {
                    $this->purgeFormsDataForHubUser((int) $managedId);
                    $this->purgeAnalyticsDataForUser((int) $managedId);
                }
                if (Schema::hasTable('forms_resource_states')) {
                    DB::table('forms_resource_states')->whereIn('user_id', $managedIds)->delete();
                }

                if (Schema::hasTable('agency_hubs')) {
                    AgencyHub::query()->where('agency_user_id', $userId)->delete();
                }
                User::query()->whereIn('id', $managedIds)->delete();
            }

            if (Schema::hasTable('user_custom_domains')) {
                $domainCleanupHostnames = $this->domainHostnamesForScope([$userId], [$userId]);

                DB::table('user_custom_domains')
                    ->where('user_id', $userId)
                    ->orWhere('page_id', $userId)
                    ->delete();

                $this->queueDomainSslCleanupForHostnames($domainCleanupHostnames);
            }

            Link::withDisabled()->where('user_id', $userId)->delete();
            $this->purgeFormsDataForTenantOwner($userId);
            $this->purgeAnalyticsDataForUser($userId);

            if (Schema::hasTable('analytics_resource_states')) {
                DB::table('analytics_resource_states')->where('user_id', $userId)->update([
                    'status' => self::RESOURCE_STATUS_DELETED,
                    'reason' => $reason,
                    'deleted_at' => now(),
                    'delete_after_at' => null,
                    'updated_at' => now(),
                ]);
            }
            if (Schema::hasTable('forms_resource_states')) {
                $formStateUpdates = $this->filterTableColumns('forms_resource_states', [
                    'status' => self::RESOURCE_STATUS_DELETED,
                    'reason' => $reason,
                    'pending_deletion_at' => null,
                    'delete_after_at' => null,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
                if (!empty($formStateUpdates)) {
                    DB::table('forms_resource_states')->where('user_id', $userId)->update($formStateUpdates);
                }
            }

            if (Schema::hasTable('user_settings')) {
                DB::table('user_settings')->where('user_id', $userId)->delete();
            }

            if (Schema::hasTable('social_accounts')) {
                DB::table('social_accounts')->where('user_id', $userId)->delete();
            }

            $emailHash = substr(hash('sha256', $lastEmail !== '' ? $lastEmail : (string) $userId), 0, 20);
            $anonymizedEmail = 'anonymized_' . $emailHash . '@deleted.wayvio.com';
            $anonymizedSlug = 'deleted-' . $userId;

            $updates = [
                'name' => '[Gelöscht]',
                'email' => $anonymizedEmail,
                'password' => Hash::make(Str::random(64)),
                'littlelink_name' => $anonymizedSlug,
                'littlelink_description' => null,
                'meta_overrides' => null,
                'image' => null,
                'provider' => null,
                'provider_id' => null,
                'block' => 'yes',
                'account_status' => self::ACCOUNT_STATUS_DELETED,
                'account_status_reason' => $reason,
                'account_status_changed_at' => now(),
                'account_delete_after_at' => null,
                'account_deleted_at' => now(),
                'analytics_status' => self::RESOURCE_STATUS_DELETED,
                'analytics_status_reason' => $reason,
                'analytics_deleted_at' => now(),
                'analytics_delete_after_at' => null,
                'meta_tags_status' => self::RESOURCE_STATUS_DELETED,
                'meta_tags_status_reason' => $reason,
                'meta_tags_suspended_at' => null,
                'meta_tags_pending_deletion_at' => null,
                'meta_tags_deleted_at' => now(),
                'meta_tags_delete_after_at' => null,
                'updated_at' => now(),
            ];

            $safeUpdates = $this->filterExistingUserColumns($updates);
            if (!empty($safeUpdates)) {
                User::query()->whereKey($userId)->update($safeUpdates);
            }

            if (Schema::hasTable('user_subscriptions')) {
                $subscriptionUpdates = $this->filterTableColumns('user_subscriptions', [
                    'payment_failed_at' => null,
                    'payment_warning_sent_at' => null,
                    'payment_restricted_at' => null,
                    'payment_deletion_warning_sent_at' => null,
                    'payment_pending_deletion_at' => null,
                    'payment_delete_after_at' => null,
                    'pending_tier_id' => null,
                    'pending_hub_slots_included' => null,
                    'updated_at' => now(),
                ]);

                if (!empty($subscriptionUpdates)) {
                    DB::table('user_subscriptions')->where('user_id', $userId)->update($subscriptionUpdates);
                }
            }
        });

        $this->audit->statusChange($userId, 'account_deleted', self::ACCOUNT_STATUS_PENDING_DELETION, self::ACCOUNT_STATUS_DELETED, $reason, [
            'source' => $source,
        ]);
        $this->audit->event($userId, 'all_resources_deleted', $reason);
        $this->anonymizeAuditTrails($userId);
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function updateUserLifecycleColumns(int $userId, array $updates): void
    {
        if ($userId <= 0) {
            return;
        }

        $safeUpdates = $this->filterExistingUserColumns($updates);
        if (empty($safeUpdates)) {
            return;
        }

        User::query()->whereKey($userId)->update($safeUpdates);
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function filterExistingUserColumns(array $updates): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $filtered = [];
        foreach ($updates as $column => $value) {
            if (Schema::hasColumn('users', $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function filterTableColumns(string $table, array $updates): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $filtered = [];
        foreach ($updates as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @return array{tier_updated:bool,free_tier_id:int|null,free_tier_slug:string,free_tier_name:string}
     */
    private function downgradeSubscriptionToFreeTier(UserSubscription $subscription): array
    {
        $freeSlug = $this->tierResolver->normalizeSlug((string) config('tiers.default_free_slug', 'free'));
        $freeTier = Tier::query()->where('slug', $freeSlug)->first();
        $freeTierName = $freeTier ? (string) ($freeTier->name ?? $this->tierResolver->displayName($freeSlug)) : $this->tierResolver->displayName($freeSlug);

        if (!$freeTier) {
            return [
                'tier_updated' => false,
                'free_tier_id' => null,
                'free_tier_slug' => $freeSlug,
                'free_tier_name' => $freeTierName,
            ];
        }

        $updates = $this->filterTableColumns('user_subscriptions', [
            'tier_id' => (int) $freeTier->id,
            'pending_tier_id' => null,
            'pending_hub_slots_included' => null,
            'cancel_at_period_end' => false,
            'expires_at' => null,
            'lifecycle_last_tier_id' => (int) $freeTier->id,
            'updated_at' => now(),
        ]);
        if (Schema::hasColumn('user_subscriptions', 'lifecycle_last_hub_slots_included')) {
            $updates['lifecycle_last_hub_slots_included'] = $subscription->hub_slots_included;
        }

        $tierUpdated = false;
        if (!empty($updates)) {
            $tierUpdated = DB::table('user_subscriptions')
                    ->where('id', (int) $subscription->id)
                    ->update($updates) > 0;
            if ($tierUpdated) {
                $subscription->refresh();
            }
        }

        return [
            'tier_updated' => $tierUpdated,
            'free_tier_id' => (int) $freeTier->id,
            'free_tier_slug' => $freeSlug,
            'free_tier_name' => $freeTierName,
        ];
    }

    /**
     * @param array<int,int> $userIds
     * @param array<int,int> $pageIds
     */
    private function domainHostnamesForScope(array $userIds, array $pageIds): array
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return [];
        }

        $sanitizedUserIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $userIds,
        ), static fn (int $id) => $id > 0)));

        $sanitizedPageIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $pageIds,
        ), static fn (int $id) => $id > 0)));

        if ($sanitizedUserIds === [] && $sanitizedPageIds === []) {
            return [];
        }

        return DB::table('user_custom_domains')
            ->where(function ($query) use ($sanitizedUserIds, $sanitizedPageIds): void {
                if ($sanitizedUserIds !== []) {
                    $query->whereIn('user_id', $sanitizedUserIds);
                }

                if ($sanitizedPageIds !== []) {
                    if ($sanitizedUserIds !== []) {
                        $query->orWhereIn('page_id', $sanitizedPageIds);
                    } else {
                        $query->whereIn('page_id', $sanitizedPageIds);
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
    private function queueDomainSslCleanupForHostnames(array $domains): void
    {
        $hostnames = collect($domains)
            ->map(static fn ($domain): string => strtolower(trim((string) $domain)))
            ->filter(static fn (string $domain): bool => $domain !== '')
            ->unique()
            ->values()
            ->all();

        if ($hostnames === []) {
            return;
        }

        $hostnamesByTenantOwner = [];
        $unassignedHostnames = array_fill_keys($hostnames, true);

        if (Schema::hasTable('user_custom_domains')) {
            $domainRows = UserCustomDomain::query()
                ->select(['domain', 'tenant_owner_user_id', 'user_id'])
                ->whereRaw('LOWER(domain) IN (' . implode(',', array_fill(0, count($hostnames), '?')) . ')', $hostnames)
                ->get();

            foreach ($domainRows as $domainRow) {
                $hostname = strtolower(trim((string) ($domainRow->domain ?? '')));
                if ($hostname === '') {
                    continue;
                }

                $tenantOwnerUserId = (int) ($domainRow->tenant_owner_user_id ?? 0);
                if ($tenantOwnerUserId <= 0) {
                    $tenantOwnerUserId = (int) ($domainRow->user_id ?? 0);
                }

                if ($tenantOwnerUserId <= 0) {
                    continue;
                }

                if (!isset($hostnamesByTenantOwner[$tenantOwnerUserId])) {
                    $hostnamesByTenantOwner[$tenantOwnerUserId] = [];
                }

                $hostnamesByTenantOwner[$tenantOwnerUserId][$hostname] = true;
                unset($unassignedHostnames[$hostname]);
            }
        }

        foreach ($hostnamesByTenantOwner as $tenantOwnerUserId => $tenantHostnames) {
            $this->domainSslService->queueTenantReconcileByTenantOwner(
                (int) $tenantOwnerUserId,
                array_keys($tenantHostnames),
                (int) $tenantOwnerUserId
            );
        }

        foreach (array_keys($unassignedHostnames) as $hostname) {
            $this->domainSslService->queueCleanupByHostname((string) $hostname);
        }
    }

    /**
     * @return array<int,string>
     */
    private function lifecycleResourceReasons(): array
    {
        return ['downgrade', 'non_payment'];
    }

    private function downgradeRetentionDays(): int
    {
        return max(1, (int) config('billing.lifecycle.downgrade_retention_days', 30));
    }

    private function pendingDeletionGraceDays(): int
    {
        return max(1, (int) config('billing.lifecycle.pending_deletion_grace_days', 1));
    }

    private function billingRetentionYears(): int
    {
        return max(1, (int) config('billing.lifecycle.billing_retention_years', 10));
    }

    private function hubDowngradeAutoCleanupEnabled(): bool
    {
        return (bool) config('billing.lifecycle.agency_hub_auto_cleanup_enabled', true);
    }

    private function supportsDomainLifecycle(): bool
    {
        return Schema::hasTable('user_custom_domains')
            && Schema::hasColumn('user_custom_domains', 'lifecycle_status');
    }

    private function supportsFormsLifecycle(): bool
    {
        return Schema::hasTable('forms_resource_states')
            && Schema::hasColumn('forms_resource_states', 'status');
    }

    private function supportsMetaLifecycle(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'meta_tags_status');
    }

    private function formsAllowedForTier(?Tier $tier): bool
    {
        if (!$tier) {
            return false;
        }

        $slug = $this->tierResolver->normalizeSlug((string) $tier->slug);
        $allowedSlugs = collect((array) config('forms.allowed_tiers', ['basic', 'pro', 'agency']))
            ->map(fn ($value): string => $this->tierResolver->normalizeSlug((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        return in_array($slug, $allowedSlugs, true);
    }

    private function tenantOwnerForResourceUser(int $resourceUserId): ?int
    {
        if ($resourceUserId <= 0) {
            return null;
        }

        if (
            !Schema::hasTable('agency_hubs')
            || !Schema::hasColumn('agency_hubs', 'agency_user_id')
            || !Schema::hasColumn('agency_hubs', 'managed_user_id')
        ) {
            return $resourceUserId;
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

        if (count($owners) === 1) {
            return (int) ($owners[0] ?? $resourceUserId);
        }

        if (count($owners) > 1) {
            return null;
        }

        return $resourceUserId;
    }

    private function anonymizeAuditTrails(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $hashed = hash('sha256', (string) $userId);
        $this->anonymizeAuditTable('audit_log', $userId, $hashed);
        $this->anonymizeAuditTable('compliance_audit_log', $userId, $hashed);
    }

    private function anonymizeAuditTable(string $table, int $userId, string $hashedUserId): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
            return;
        }

        $hasMetadata = Schema::hasColumn($table, 'metadata');
        $columns = ['id'];
        if ($hasMetadata) {
            $columns[] = 'metadata';
        }

        $rows = DB::table($table)
            ->where('user_id', $userId)
            ->get($columns);

        foreach ($rows as $row) {
            $updates = [
                'user_id' => null,
            ];

            if ($hasMetadata) {
                $metadata = json_decode((string) ($row->metadata ?? ''), true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                $metadata['anonymized_user_hash'] = $hashedUserId;
                $metadata['anonymized_at'] = now()->toIso8601String();
                $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
                if (is_string($encoded)) {
                    $updates['metadata'] = $encoded;
                }
            }

            if (Schema::hasColumn($table, 'updated_at')) {
                $updates['updated_at'] = now();
            }

            DB::table($table)->where('id', (int) $row->id)->update($updates);
        }
    }
}
