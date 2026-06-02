<?php

namespace Modules\Tiers\Helpers;

use Carbon\Carbon;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\TierResolver;

class SubscriptionHelper
{
    public static function getExpirationDateByPlanLength(int $months): Carbon
    {
        return now()->addMonths($months);
    }

    public static function isFeatureEnabled(?Tier $tier, string $feature): bool
    {
        $resolver = app(TierResolver::class);

        return match ($feature) {
            'analytics' => $resolver->featureEnabled($tier, 'analytics.enabled'),
            'custom_domain' => $resolver->featureEnabled($tier, 'domains.custom_domain'),
            'design' => $resolver->featureEnabled($tier, 'design.link_styling')
                || $resolver->featureEnabled($tier, 'design.custom_colors'),
            default => $resolver->featureEnabled($tier, $feature),
        };
    }
}
