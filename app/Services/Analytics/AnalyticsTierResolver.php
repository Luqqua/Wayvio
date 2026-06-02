<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

namespace App\Services\Analytics;

use App\Models\AgencyHub;
use Modules\Tiers\Services\SubscriptionManager;
use App\Models\User;
use Modules\Tiers\Services\TierResolver;
use Illuminate\Support\Facades\Schema;

class AnalyticsTierResolver
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly TierResolver $tierResolver,
    )
    {
    }

    public function tierLevel(?User $user): string
    {
        if (!$user) {
            return config('analytics.default_tier', config('tiers.default_free_slug', 'free'));
        }

        $featureOwner = $this->resolveFeatureOwner($user);
        $tier = $this->subscriptionManager->getUserTier($featureOwner);
        return $this->tierResolver->normalizeSlug($tier?->slug);
    }

    /**
     * @return array<int,string>
     */
    public function featuresFor(string $level): array
    {
        return $this->tierResolver->analyticsFeatureList($level);
    }

    public function allowsEvent(string $level, string $eventType): bool
    {
        $features = $this->featuresFor($level);

        return match ($eventType) {
            'click' => in_array('clicks', $features, true),
            default => in_array('views', $features, true),
        };
    }

    public function displayName(string $level): string
    {
        return $this->tierResolver->displayName($level);
    }

    protected function resolveFeatureOwner(User $user): User
    {
        if (!$user->isAgencyHubAccount() || !Schema::hasTable('agency_hubs')) {
            return $user;
        }

        $agencyUserId = (int) AgencyHub::query()
            ->where('managed_user_id', $user->id)
            ->where('status', 'active')
            ->value('agency_user_id');

        if ($agencyUserId <= 0 || $agencyUserId === (int) $user->id) {
            return $user;
        }

        return User::query()->find($agencyUserId) ?? $user;
    }
}
