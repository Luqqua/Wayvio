<?php

namespace App\Services\Agency;

use App\Models\AgencyHub;
use App\Models\Link;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Models\UserSubscription;
use Modules\Tiers\Services\TierResolver;

class AgencyHubQuotaManager
{
    public const MIN_AGENCY_HUBS = 2;
    public const MAX_AGENCY_HUBS = 15;

    public const STRATEGY_LEAST_LINKS = 'least_links';
    public const STRATEGY_NEWEST = 'newest';
    public const STRATEGY_OLDEST = 'oldest';

    /**
     * @var array<int,string>
     */
    public const STRATEGIES = [
        self::STRATEGY_LEAST_LINKS,
        self::STRATEGY_NEWEST,
        self::STRATEGY_OLDEST,
    ];

    public static function minAgencyHubs(): int
    {
        return max(1, (int) config('tiers.agency.min_hubs', self::MIN_AGENCY_HUBS));
    }

    public static function maxAgencyHubs(): int
    {
        return max(self::minAgencyHubs(), (int) config('tiers.agency.max_hubs', self::MAX_AGENCY_HUBS));
    }

    public static function clampAgencyHubs(?int $value, ?int $fallback = null): int
    {
        $min = self::minAgencyHubs();
        $max = self::maxAgencyHubs();
        $resolved = $value ?? $fallback ?? $min;

        return max($min, min($max, (int) $resolved));
    }

    public function __construct(private readonly TierResolver $tierResolver)
    {
    }

    /**
     * @param array<int,int> $preferredDeleteManagedUserIds
     * @return array<string,mixed>
     */
    public function applyTierTransition(
        User $user,
        Tier $targetTier,
        ?CarbonInterface $expiresAt = null,
        ?int $agencyHubSlots = null,
        array $preferredDeleteManagedUserIds = [],
        string $strategy = self::STRATEGY_LEAST_LINKS,
        bool $dryRun = false,
    ): array {
        $strategy = $this->normalizeStrategy($strategy);
        $targetIsAgency = $this->isAgencyTier($targetTier);
        $subscription = UserSubscription::query()->where('user_id', $user->id)->first();

        $currentIncluded = (int) ($subscription?->hub_slots_included ?? 0);
        $targetSlots = $targetIsAgency
            ? $this->resolveTargetAgencySlots($agencyHubSlots, $currentIncluded, $targetTier)
            : 0;
        $targetManagedSlots = $this->managedHubTargetFromTotalSlots($targetSlots, $targetIsAgency);

        $plan = $this->planHubPrune(
            $user,
            $targetManagedSlots,
            $preferredDeleteManagedUserIds,
            $strategy,
        );

        if ($dryRun) {
            return array_merge($plan, [
                'dry_run' => true,
                'target_tier_slug' => $this->tierResolver->normalizeSlug($targetTier->slug),
                'target_slots' => $targetSlots,
                'target_managed_slots' => $targetManagedSlots,
                'expires_at' => $expiresAt?->toDateTimeString(),
                'updated_subscription' => false,
                'removed' => 0,
                'removed_hubs' => [],
            ]);
        }

        $updates = [
            'tier_id' => $targetTier->id,
            'expires_at' => $expiresAt,
        ];
        if ($this->supportsHubColumns()) {
            $updates['hub_slots_included'] = $targetIsAgency ? $targetSlots : null;
            $updates['hub_slots_addon'] = $targetIsAgency ? max(0, (int) ($subscription?->hub_slots_addon ?? 0)) : 0;
        }

        $execution = DB::transaction(function () use ($user, $updates, $plan): array {
            UserSubscription::query()->updateOrCreate(
                ['user_id' => $user->id],
                $updates,
            );

            return $this->executePlannedHubPrune($user, $plan['victims']);
        });

        return array_merge($plan, [
            'dry_run' => false,
            'target_tier_slug' => $this->tierResolver->normalizeSlug($targetTier->slug),
            'target_slots' => $targetSlots,
            'target_managed_slots' => $targetManagedSlots,
            'expires_at' => $expiresAt?->toDateTimeString(),
            'updated_subscription' => true,
            'removed' => $execution['removed'],
            'removed_hubs' => $execution['removed_hubs'],
        ]);
    }

    /**
     * @param array<int,int> $preferredDeleteManagedUserIds
     * @return array<string,mixed>
     */
    public function enforceManagedHubLimit(
        User $agencyUser,
        int $targetSlots,
        array $preferredDeleteManagedUserIds = [],
        string $strategy = self::STRATEGY_LEAST_LINKS,
        bool $dryRun = false,
    ): array {
        $strategy = $this->normalizeStrategy($strategy);
        $targetSlots = max(0, $targetSlots);
        $targetManagedSlots = $this->managedHubTargetFromTotalSlots($targetSlots, true);

        $plan = $this->planHubPrune(
            $agencyUser,
            $targetManagedSlots,
            $preferredDeleteManagedUserIds,
            $strategy,
        );

        if ($dryRun) {
            return array_merge($plan, [
                'dry_run' => true,
                'target_slots' => $targetSlots,
                'target_managed_slots' => $targetManagedSlots,
                'removed' => 0,
                'removed_hubs' => [],
            ]);
        }

        $execution = $this->executePlannedHubPrune($agencyUser, $plan['victims']);

        return array_merge($plan, [
            'dry_run' => false,
            'target_slots' => $targetSlots,
            'target_managed_slots' => $targetManagedSlots,
            'removed' => $execution['removed'],
            'removed_hubs' => $execution['removed_hubs'],
        ]);
    }

    /**
     * @param array<int,int> $preferredDeleteManagedUserIds
     * @return array<string,mixed>
     */
    public function planHubPrune(
        User $agencyUser,
        int $targetSlots,
        array $preferredDeleteManagedUserIds = [],
        string $strategy = self::STRATEGY_LEAST_LINKS,
    ): array {
        $strategy = $this->normalizeStrategy($strategy);
        $targetSlots = max(0, $targetSlots);

        $inventory = $this->activeHubInventory($agencyUser);
        $used = $inventory->count();
        $removeCount = max(0, $used - $targetSlots);

        $preferences = collect($preferredDeleteManagedUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $agencyUser->id)
            ->values();

        $preferenceSet = $preferences->flip();
        $preferredVictims = $inventory
            ->filter(fn (array $row) => $preferenceSet->has((int) $row['managed_user_id']))
            ->sortBy(fn (array $row) => (int) $preferenceSet[(int) $row['managed_user_id']])
            ->values();

        $unresolvedPreferences = $preferences
            ->filter(fn (int $id) => !$preferredVictims->contains(fn (array $row) => (int) $row['managed_user_id'] === $id))
            ->values()
            ->all();

        $victims = collect();
        if ($removeCount > 0) {
            $victims = $preferredVictims->take($removeCount)->values();
            $remaining = $removeCount - $victims->count();
            if ($remaining > 0) {
                $picked = $victims->pluck('managed_user_id')->map(fn ($id) => (int) $id)->flip();
                $pool = $inventory
                    ->reject(fn (array $row) => $picked->has((int) $row['managed_user_id']))
                    ->values();

                $sortedPool = match ($strategy) {
                    self::STRATEGY_NEWEST => $pool->sortByDesc(fn (array $row) => (int) $row['created_at_ts'])->values(),
                    self::STRATEGY_OLDEST => $pool->sortBy(fn (array $row) => (int) $row['created_at_ts'])->values(),
                    default => $pool
                        ->sort(static function (array $left, array $right): int {
                            // Primary key: least links first.
                            $linksCompare = ((int) $left['links_count']) <=> ((int) $right['links_count']);
                            if ($linksCompare !== 0) {
                                return $linksCompare;
                            }

                            // Tie-breaker: newer hubs first (existing behavior intention).
                            return ((int) $right['created_at_ts']) <=> ((int) $left['created_at_ts']);
                        })
                        ->values(),
                };

                $victims = $victims->concat($sortedPool->take($remaining))->values();
            }
        }

        return [
            'used' => $used,
            'target' => $targetSlots,
            'remove_count' => $removeCount,
            'strategy' => $strategy,
            'unresolved_preferences' => $unresolvedPreferences,
            'inventory' => $inventory->values()->all(),
            'victims' => $victims->values()->all(),
        ];
    }

    protected function isAgencyTier(Tier $tier): bool
    {
        $slug = $this->tierResolver->normalizeSlug($tier->slug);
        if (in_array($slug, ['agency', 'enterprise'], true)) {
            return true;
        }

        return $this->tierResolver->featureEnabled($tier, 'agency.managed_hubs');
    }

    protected function normalizeStrategy(string $strategy): string
    {
        $normalized = trim(strtolower($strategy));
        if (in_array($normalized, self::STRATEGIES, true)) {
            return $normalized;
        }

        return self::STRATEGY_LEAST_LINKS;
    }

    protected function managedHubTargetFromTotalSlots(int $totalSlots, bool $isAgency): int
    {
        if (!$isAgency) {
            return 0;
        }

        // Slot quota includes the owner page, while pruning works on managed hubs only.
        return max(0, $totalSlots - 1);
    }

    protected function resolveTargetAgencySlots(?int $requested, int $currentIncluded, Tier $targetTier): int
    {
        if ($requested !== null) {
            return self::clampAgencyHubs($requested);
        }

        if ($currentIncluded > 0) {
            return self::clampAgencyHubs($currentIncluded);
        }

        $limits = $this->tierResolver->limits($targetTier);
        $default = (int) ($limits['agency_hub_slots'] ?? self::minAgencyHubs());

        return self::clampAgencyHubs($default);
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    protected function activeHubInventory(User $agencyUser): Collection
    {
        if (!Schema::hasTable('agency_hubs')) {
            return collect();
        }

        $hubs = AgencyHub::query()
            ->with('managedUser:id,littlelink_name,name')
            ->where('agency_user_id', $agencyUser->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get();

        if ($hubs->isEmpty()) {
            return collect();
        }

        $managedIds = $hubs->pluck('managed_user_id')->map(fn ($id) => (int) $id)->all();
        $linksPerUser = Link::withDisabled()
            ->selectRaw('user_id, COUNT(*) as aggregate_count')
            ->whereIn('user_id', $managedIds)
            ->groupBy('user_id')
            ->pluck('aggregate_count', 'user_id');

        return $hubs->map(function (AgencyHub $hub) use ($linksPerUser): array {
            $managedId = (int) $hub->managed_user_id;
            return [
                'hub_id' => (int) $hub->id,
                'managed_user_id' => $managedId,
                'display_name' => (string) $hub->display_name,
                'slug' => (string) ($hub->managedUser?->littlelink_name ?? ''),
                'managed_user_name' => (string) ($hub->managedUser?->name ?? ''),
                'links_count' => (int) ($linksPerUser[$managedId] ?? 0),
                'created_at_ts' => (int) optional($hub->created_at)->timestamp,
                'created_at' => optional($hub->created_at)?->toDateTimeString(),
            ];
        })->values();
    }

    /**
     * @param array<int,array<string,mixed>> $victims
     * @return array{removed:int,removed_hubs:array<int,array<string,mixed>>}
     */
    protected function executePlannedHubPrune(User $agencyUser, array $victims): array
    {
        $toRemove = collect($victims)
            ->map(function (array $row) use ($agencyUser): array {
                $row['managed_user_id'] = (int) ($row['managed_user_id'] ?? 0);
                return $row;
            })
            ->filter(fn (array $row) => (int) $row['managed_user_id'] > 0 && (int) $row['managed_user_id'] !== (int) $agencyUser->id)
            ->values();

        if ($toRemove->isEmpty()) {
            return [
                'removed' => 0,
                'removed_hubs' => [],
            ];
        }

        $managedIds = $toRemove
            ->pluck('managed_user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $updates = [
            'status' => 'suspended',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('agency_hubs', 'lifecycle_reason')) {
            $updates['lifecycle_reason'] = 'downgrade';
        }
        if (Schema::hasColumn('agency_hubs', 'suspended_at')) {
            $updates['suspended_at'] = now();
        }

        AgencyHub::query()
            ->where('agency_user_id', $agencyUser->id)
            ->whereIn('managed_user_id', $managedIds)
            ->where('status', 'active')
            ->update($updates);

        return [
            'removed' => $toRemove->count(),
            'removed_hubs' => $toRemove->all(),
        ];
    }

    protected function supportsHubColumns(): bool
    {
        static $supports = null;

        if ($supports !== null) {
            return $supports;
        }

        if (!Schema::hasTable('user_subscriptions')) {
            $supports = false;
            return false;
        }

        $supports = Schema::hasColumn('user_subscriptions', 'hub_slots_included')
            && Schema::hasColumn('user_subscriptions', 'hub_slots_addon');

        return $supports;
    }
}
