<?php

namespace App\Services\Meta;

use App\Models\User;
use App\Models\AgencyHub;
use Illuminate\Support\Facades\Schema;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;

class CustomMetaPolicyService
{
    private const STATUS_ACTIVE = 'active';

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly TierResolver $tierResolver,
    ) {
    }

    public function canDeliverCustomMeta(User $user): bool
    {
        if (!$this->isFeatureIncludedByTier($user)) {
            return false;
        }

        return $this->lifecycleStatus($user) === self::STATUS_ACTIVE;
    }

    public function isFeatureIncludedByTier(User $user): bool
    {
        $featureOwner = $this->resolveFeatureOwner($user);
        $tier = $this->subscriptionManager->getUserTier($featureOwner);

        return $this->tierResolver->featureEnabled($tier, 'seo.custom_meta');
    }

    public function lifecycleStatus(User $user): string
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'meta_tags_status')) {
            return self::STATUS_ACTIVE;
        }

        $status = strtolower(trim((string) ($user->meta_tags_status ?? self::STATUS_ACTIVE)));

        return $status !== '' ? $status : self::STATUS_ACTIVE;
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
