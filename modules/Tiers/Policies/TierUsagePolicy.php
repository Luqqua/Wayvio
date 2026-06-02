<?php

namespace Modules\Tiers\Policies;

use App\Models\User;
use App\Models\Page;
use Modules\Tiers\Services\SubscriptionManager;

class TierUsagePolicy
{
    public function createPage(User $user, SubscriptionManager $manager): bool
    {
        // MODULE: Single-page model enforced elsewhere; pages table has no user_id
        return true;
    }
}
