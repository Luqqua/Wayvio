<?php

namespace App\Services\Forms;

use App\Models\User;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Schema;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;

class FormsAccess
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly TierResolver $tierResolver,
    ) {
    }

    public function tenantOwnerUserIdForHub(User $hub): ?int
    {
        try {
            return $this->tenantResolver->ownerIdForUserId((int) $hub->id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function actorUserIdForPublicHub(User $hub): ?int
    {
        return $this->tenantOwnerUserIdForHub($hub);
    }

    public function featureOwnerForHub(User $hub): ?User
    {
        $tenantOwnerUserId = $this->tenantOwnerUserIdForHub($hub);
        if (!$tenantOwnerUserId) {
            return null;
        }

        return User::query()->find($tenantOwnerUserId);
    }

    public function formsAllowedForHub(User $hub): bool
    {
        if (!(bool) config('forms.enabled', true)) {
            return false;
        }
        if (trim((string) config('forms.api_key')) === '' || trim((string) config('forms.api_base')) === '') {
            return false;
        }

        $owner = $this->featureOwnerForHub($hub);
        if (!$owner) {
            return false;
        }

        $tier = $this->subscriptionManager->getUserTier($owner);
        $slug = $this->tierResolver->normalizeSlug($tier?->slug);

        return in_array($slug, (array) config('forms.allowed_tiers', ['basic', 'pro', 'agency']), true);
    }

    public function tierLevelForHub(User $hub): string
    {
        $owner = $this->featureOwnerForHub($hub);
        if (!$owner) {
            return 'tier1';
        }

        $tier = $this->subscriptionManager->getUserTier($owner);
        $slug = $this->tierResolver->normalizeSlug($tier?->slug);

        return match ($slug) {
            'basic' => 'tier2',
            'pro', 'agency' => 'tier3',
            default => 'tier1',
        };
    }

    public function retentionDaysForHub(User $hub): int
    {
        $owner = $this->featureOwnerForHub($hub);
        if (!$owner) {
            return (int) config('forms.retention_days', 180);
        }

        $tier = $this->subscriptionManager->getUserTier($owner);
        $days = (int) $this->tierResolver->analyticsRetention($tier);

        return $days > 0 ? $days : (int) config('forms.retention_days', 180);
    }

    public function canActorAccessHub(User $actor, User $hub): bool
    {
        if ((int) $actor->id === (int) $hub->id) {
            return true;
        }

        if (!Schema::hasTable('agency_hubs')) {
            return false;
        }

        return \App\Models\AgencyHub::query()
            ->where('agency_user_id', $actor->id)
            ->where('managed_user_id', $hub->id)
            ->where('status', 'active')
            ->exists();
    }
}
