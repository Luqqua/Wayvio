<?php

namespace Modules\Tiers\Services;

use App\Services\Agency\AgencyHubQuotaManager;
use Carbon\Carbon;
use Modules\Tiers\Helpers\SubscriptionHelper;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Models\UserSubscription;
use App\Models\User;

class SubscriptionManager
{
    public function __construct(
        private TierResolver $tierResolver,
        private AgencyHubQuotaManager $agencyHubQuotaManager,
    )
    {
    }

    public function getUserTier(User $user): ?Tier
    {
        // Admins always treated as premium tier
        if ($user->role === 'admin') {
            $adminSlug = config('tiers.admin_tier_slug', 'business');
            $premium = Tier::where('slug', $adminSlug)->first();
            if ($premium) {
                return $this->withNormalizedSlug($premium);
            }
            // Fallback: highest priced tier
            $fallback = Tier::orderByDesc('price_1m')->first();
            return $fallback ? $this->withNormalizedSlug($fallback) : null;
        }

        $sub = $this->getUserSubscriptionRecord($user);
        if (!$sub) {
            return $this->resolvedFreeTier();
        }

        if ($this->subscriptionExpired($sub)) {
            return $this->resolvedFreeTier();
        }

        return $sub->tier ? $this->withNormalizedSlug($sub->tier) : null;
    }

    public function getUserSubscriptionRecord(User $user): ?UserSubscription
    {
        return UserSubscription::with(['tier', 'pendingTier'])->where('user_id', $user->id)->first();
    }

    public function isExpired(User $user): bool
    {
        $sub = UserSubscription::where('user_id', $user->id)->first();
        // No subscription => treat as free, not expired
        if (!$sub) {
            return false;
        }
        return $this->subscriptionExpired($sub);
    }

    public function isInGracePeriod(User $user, int $graceDays = 0): bool
    {
        $sub = UserSubscription::where('user_id', $user->id)->first();
        if (!$sub || !$sub->expires_at) {
            return false;
        }
        $expiry = Carbon::parse($sub->expires_at);
        return $expiry->isPast() && $expiry->diffInDays(now()) <= $graceDays;
    }

    public function renewSubscription(User $user, Tier $tier, int $periodMonths): UserSubscription
    {
        return $this->assignTier($user, $tier, $periodMonths);
    }

    /**
     * @param array<int,int> $preferredDeleteManagedUserIds
     */
    public function assignTier(
        User $user,
        Tier $tier,
        ?int $periodMonths = null,
        ?int $agencyHubSlots = null,
        array $preferredDeleteManagedUserIds = [],
        string $pruneStrategy = AgencyHubQuotaManager::STRATEGY_LEAST_LINKS,
    ): UserSubscription
    {
        $expiresAt = $periodMonths ? SubscriptionHelper::getExpirationDateByPlanLength($periodMonths) : null;

        $this->agencyHubQuotaManager->applyTierTransition(
            $user,
            $tier,
            $expiresAt,
            $agencyHubSlots,
            $preferredDeleteManagedUserIds,
            $pruneStrategy,
            false
        );

        return UserSubscription::query()->where('user_id', $user->id)->firstOrFail();
    }

    public function downgradeToFree(User $user): void
    {
        $freeSlug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));
        $free = Tier::where('slug', $freeSlug)->first();
        if ($free) {
            $this->assignTier($user, $free, null);
        } else {
            UserSubscription::where('user_id', $user->id)->delete();
        }
    }

    public function hasStripeBillingProfile(User $user): bool
    {
        $sub = UserSubscription::query()->where('user_id', $user->id)->first();

        return $sub !== null && !empty($sub->stripe_customer_id);
    }

    public function hasPendingChange(User $user): bool
    {
        $sub = UserSubscription::query()->where('user_id', $user->id)->first();
        if (!$sub) {
            return false;
        }

        return (bool) $sub->cancel_at_period_end
            || $sub->pending_tier_id !== null
            || $sub->pending_hub_slots_included !== null;
    }

    public function userLimitStatus(User $user): array
    {
        $tier = $this->getUserTier($user);
        $limits = $this->tierResolver->limits($tier);

        return [
            'max_pages' => $limits['max_pages'],
            'max_links_per_page' => $limits['max_links_per_page'],
            'agency_hub_slots' => $limits['agency_hub_slots'] ?? $limits['max_pages'],
            'analytics_enabled' => $this->tierResolver->featureEnabled($tier, 'analytics.enabled'),
            'analytics_history_days' => $limits['analytics_history_days'],
            'custom_domain_enabled' => $this->tierResolver->featureEnabled($tier, 'domains.custom_domain'),
            'agency_managed_hubs_enabled' => $this->tierResolver->featureEnabled($tier, 'agency.managed_hubs'),
            'design_customization_enabled' => $this->tierResolver->featureEnabled($tier, 'design.link_styling')
                || $this->tierResolver->featureEnabled($tier, 'design.custom_colors'),
        ];
    }

    public function featureEnabled(User $user, string $featureKey): bool
    {
        $tier = $this->getUserTier($user);
        return $this->tierResolver->featureEnabled($tier, $featureKey);
    }

    protected function withNormalizedSlug(Tier $tier): Tier
    {
        $tier->slug = $this->tierResolver->normalizeSlug($tier->slug);
        return $tier;
    }

    protected function subscriptionExpired(UserSubscription $subscription): bool
    {
        // Perpetual (null expires_at) => not expired
        if (!$subscription->expires_at) {
            return false;
        }

        return Carbon::parse($subscription->expires_at)->isPast();
    }

    protected function freeTier(): ?Tier
    {
        $freeSlug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));

        return Tier::where('slug', $freeSlug)->first();
    }

    protected function resolvedFreeTier(): ?Tier
    {
        $free = $this->freeTier();
        if ($free) {
            return $this->withNormalizedSlug($free);
        }

        $slug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));
        $fallback = new Tier();
        $fallback->slug = $slug;
        $fallback->name = $this->tierResolver->displayName($slug);

        return $fallback;
    }
}
