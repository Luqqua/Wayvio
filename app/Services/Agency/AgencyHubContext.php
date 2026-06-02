<?php

namespace App\Services\Agency;

use App\Models\AgencyHub;
use App\Models\User;
use App\Models\UserData;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Tiers\Models\UserSubscription;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;

class AgencyHubContext
{
    public const SESSION_KEY = 'agency.active_hub_user_id';

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly TierResolver $tierResolver,
    ) {
    }

    public function isAgencyAccount(User $user): bool
    {
        if (!$this->agencyTableReady()) {
            return false;
        }
        if (!Schema::hasTable('user_subscriptions') || !Schema::hasTable('tiers')) {
            return false;
        }

        $tier = $this->subscriptionManager->getUserTier($user);
        $slug = $this->tierResolver->normalizeSlug($tier?->slug);

        return $this->tierResolver->featureEnabled($tier, 'agency.managed_hubs')
            || in_array($slug, ['agency', 'enterprise'], true);
    }

    public function editingUserId(User $user, ?Request $request = null): int
    {
        if (!$this->isAgencyAccount($user)) {
            return (int) $user->id;
        }

        $hubs = $this->hubs($user);
        if ($hubs->isEmpty()) {
            return (int) $user->id;
        }

        $request = $this->resolveRequest($request);
        $requestedPageId = (int) $request->query('page_id', 0);
        if ($requestedPageId > 0 && $hubs->contains(fn (AgencyHub $hub) => (int) $hub->managed_user_id === $requestedPageId)) {
            $request->session()->put(self::SESSION_KEY, $requestedPageId);
            return $requestedPageId;
        }

        $candidate = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($candidate > 0 && $hubs->contains(fn (AgencyHub $hub) => (int) $hub->managed_user_id === $candidate)) {
            return $candidate;
        }

        $fallback = (int) ($hubs->first()?->managed_user_id ?? $user->id);
        $request->session()->put(self::SESSION_KEY, $fallback);

        return $fallback;
    }

    public function activeHub(User $user, ?Request $request = null): ?AgencyHub
    {
        if (!$this->isAgencyAccount($user)) {
            return null;
        }

        $activeUserId = $this->editingUserId($user, $request);
        if ($activeUserId === (int) $user->id) {
            return null;
        }

        return AgencyHub::query()
            ->with('managedUser')
            ->where('agency_user_id', $user->id)
            ->where('managed_user_id', $activeUserId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * @return Collection<int,AgencyHub>
     */
    public function hubs(User $user): Collection
    {
        if (!$this->isAgencyAccount($user)) {
            return collect();
        }

        $hubs = AgencyHub::query()
            ->with('managedUser')
            ->where('agency_user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get();

        $ownerHub = $this->ownerHubOption($user);
        if ($ownerHub !== null) {
            $hubs->prepend($ownerHub);
        }

        return $hubs;
    }

    public function switchHub(User $user, int $managedUserId, ?Request $request = null): bool
    {
        if (!$this->isAgencyAccount($user)) {
            return false;
        }

        if ((int) $managedUserId === (int) $user->id) {
            $this->resolveRequest($request)->session()->put(self::SESSION_KEY, (int) $user->id);
            return true;
        }

        $exists = AgencyHub::query()
            ->where('agency_user_id', $user->id)
            ->where('managed_user_id', $managedUserId)
            ->where('status', 'active')
            ->exists();

        if (!$exists) {
            return false;
        }

        $this->resolveRequest($request)->session()->put(self::SESSION_KEY, $managedUserId);
        return true;
    }

    public function slotSummary(User $user): array
    {
        $isAgency = $this->isAgencyAccount($user);
        $activeManaged = $isAgency ? $this->currentManagedHubCount($user) : 0;
        $totalManaged = $isAgency ? $this->currentManagedHubTotalCount($user) : 0;
        $used = $isAgency
            ? $activeManaged + 1 // Owner page consumes one slot.
            : 1;

        $total = $this->slotLimit($user);
        $available = max(0, $total - $used);
        $managedLimit = max(0, $total - 1);

        return [
            'used' => (int) $used,
            'total' => (int) $total,
            'available' => (int) $available,
            'managed_active' => (int) $activeManaged,
            'managed_total' => (int) $totalManaged,
            'managed_limit' => (int) $managedLimit,
            'create_blocked' => (bool) ($isAgency && $totalManaged >= $managedLimit),
            'included' => $this->includedSlotLimit($user),
            'addon' => $this->addonSlotLimit($user),
        ];
    }

    public function createHub(User $agencyUser, array $payload, ?Request $request = null): AgencyHub
    {
        if (!$this->isAgencyAccount($agencyUser)) {
            throw ValidationException::withMessages([
                'tier' => 'Managed hubs are only available on the Agency plan.',
            ]);
        }

        $displayName = trim((string) ($payload['display_name'] ?? ''));
        $slug = trim((string) ($payload['littlelink_name'] ?? ''));
        $description = trim(strip_tags((string) ($payload['littlelink_description'] ?? '')));

        return DB::transaction(function () use ($agencyUser, $displayName, $slug, $description, $request): AgencyHub {
            User::query()
                ->whereKey($agencyUser->id)
                ->lockForUpdate()
                ->value('id');

            $totalLimit = $this->slotLimit($agencyUser);
            $managedLimit = max(0, $totalLimit - 1);
            $totalManaged = $this->currentManagedHubTotalCount($agencyUser);
            if ($totalManaged >= $managedLimit) {
                throw ValidationException::withMessages([
                    'slots' => 'Hub inventory limit reached. Delete suspended hubs or increase your Agency slot quota first.',
                ]);
            }

            try {
                $managedUser = User::create([
                    'name' => $this->generateManagedUserName($displayName !== '' ? $displayName : $slug),
                    'email' => $this->generateManagedUserEmail($agencyUser->id),
                    'password' => Hash::make(Str::random(80)),
                    'role' => User::ROLE_AGENCY_HUB,
                    'block' => 'no',
                    'littlelink_name' => $slug,
                    'littlelink_description' => $description,
                    'theme' => 'Wayvio',
                    'email_verified_at' => now(),
                    // Default hub locale inherits from the agency account until explicitly overridden.
                    'locale' => null,
                ]);

                UserData::saveData($managedUser->id, 'template', 'wayvio');
                UserData::saveData($managedUser->id, 'theme_template_id', 'wayvio');
                UserData::saveData($managedUser->id, 'theme_variant_id', 'default');
                UserData::saveData($managedUser->id, 'background_mode', 'template');
                UserData::saveData($managedUser->id, 'profile_header_layout', 'business');
                applyWayvioDefaultDesignSettings((int) $managedUser->id);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw ValidationException::withMessages([
                        'littlelink_name' => 'The hub slug is already in use.',
                    ]);
                }

                throw $e;
            }

            $hub = (new AgencyHub)->forceFill([
                'agency_user_id' => $agencyUser->id,
                'managed_user_id' => $managedUser->id,
                'display_name' => $displayName !== '' ? $displayName : $slug,
                'status' => 'active',
            ]);
            $hub->save();

            $this->resolveRequest($request)->session()->put(self::SESSION_KEY, (int) $managedUser->id);

            return $hub->load('managedUser');
        });
    }

    public function sidebarData(User $user, ?Request $request = null): array
    {
        $isAgency = $this->isAgencyAccount($user);
        $activeUserId = $this->editingUserId($user, $request);
        $hubs = $this->hubs($user);
        $activeUserQuery = User::query()
            ->whereKey($activeUserId)
            ->select('id', 'name', 'littlelink_name');

        if (Schema::hasColumn('users', 'is_published')) {
            $activeUserQuery->addSelect('is_published');
        }

        return [
            'is_agency' => $isAgency,
            'active_user_id' => $activeUserId,
            'active_user' => $activeUserQuery->first(),
            'hubs' => $hubs,
            'slots' => $this->slotSummary($user),
        ];
    }

    private function slotLimit(User $user): int
    {
        if (!$this->isAgencyAccount($user)) {
            return 1;
        }

        return max(1, $this->includedSlotLimit($user) + $this->addonSlotLimit($user));
    }

    private function includedSlotLimit(User $user): int
    {
        if (!$this->isAgencyAccount($user)) {
            return 1;
        }

        $subscription = UserSubscription::query()->where('user_id', $user->id)->first();
        if ($subscription && $subscription->hub_slots_included !== null) {
            return AgencyHubQuotaManager::clampAgencyHubs((int) $subscription->hub_slots_included);
        }

        $tier = $this->subscriptionManager->getUserTier($user);
        $limits = $this->tierResolver->limits($tier);

        return AgencyHubQuotaManager::clampAgencyHubs(
            (int) ($limits['agency_hub_slots'] ?? config('tiers.agency.default_hubs', AgencyHubQuotaManager::minAgencyHubs()))
        );
    }

    private function addonSlotLimit(User $user): int
    {
        if (!$this->isAgencyAccount($user)) {
            return 0;
        }

        $subscription = UserSubscription::query()->where('user_id', $user->id)->first();
        if (!$subscription) {
            return 0;
        }

        return max(0, (int) ($subscription->hub_slots_addon ?? 0));
    }

    private function agencyTableReady(): bool
    {
        return cache()->remember('agency_hub_table_ready', 60, fn () => Schema::hasTable('agency_hubs'));
    }

    private function resolveRequest(?Request $request): Request
    {
        return $request ?? request();
    }

    private function ownerHubOption(User $user): ?AgencyHub
    {
        $displayName = trim((string) ($user->name ?? ''));
        $slug = trim((string) ($user->littlelink_name ?? ''));

        if ($displayName === '' && $slug === '') {
            return null;
        }

        $hub = (new AgencyHub)->forceFill([
            'agency_user_id' => (int) $user->id,
            'managed_user_id' => (int) $user->id,
            'display_name' => $displayName !== '' ? $displayName : $slug,
            'status' => 'active',
        ]);
        $hub->setRelation('managedUser', $user);

        return $hub;
    }

    private function currentManagedHubCount(User $user): int
    {
        return AgencyHub::query()
            ->where('agency_user_id', $user->id)
            ->where('status', 'active')
            ->count();
    }

    private function currentManagedHubTotalCount(User $user): int
    {
        $query = AgencyHub::query()
            ->where('agency_user_id', $user->id);

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', '!=', 'deleted');
        }

        return $query->count();
    }

    private function generateManagedUserName(string $displayName): string
    {
        $base = preg_replace('/\s+/', ' ', trim($displayName));
        if (!is_string($base) || $base === '') {
            $base = 'Managed Hub';
        }
        return Str::limit($base, 255, '');
    }

    private function generateManagedUserEmail(int $agencyId): string
    {
        do {
            $candidate = sprintf(
                'hub-%d-%s@managed.local',
                $agencyId,
                Str::lower(Str::random(14))
            );
        } while (User::query()->where('email', $candidate)->exists());

        return $candidate;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        if ($sqlState === '23000') {
            return true;
        }

        return $driverCode === '19';
    }
}
