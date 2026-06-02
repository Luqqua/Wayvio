<?php

namespace App\Services\Tenancy;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantResolver
{
    public function __construct(
        private readonly AgencyHubContext $agencyContext,
    ) {
    }

    public function resolveForRequest(Request $request, bool $failClosed = true): ?TenantContext
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            $actorId = (int) Auth::id();
            $actor = $actorId > 0 ? User::query()->find($actorId) : null;
        }

        if (!$actor instanceof User) {
            return null;
        }

        try {
            return $this->resolveForActor($actor, $request);
        } catch (TenantResolutionException $e) {
            if ($failClosed) {
                throw $e;
            }

            $this->logFailure($e->reasonCode(), $e->context(), $request);
            return null;
        }
    }

    /**
     * @throws TenantResolutionException
     */
    public function resolveForActor(User $actor, ?Request $request = null): TenantContext
    {
        $request = $request ?: request();
        $actorUserId = (int) $actor->id;
        if ($actorUserId <= 0) {
            throw $this->failure(
                TenantResolutionException::forbidden('actor_user_missing', [
                    'actor_user_id' => $actorUserId,
                ]),
                $request,
            );
        }

        $activeResourceUserId = (int) $this->agencyContext->editingUserId($actor, $request);
        if ($activeResourceUserId <= 0) {
            throw $this->failure(
                TenantResolutionException::unprocessable('resource_user_unresolved', [
                    'actor_user_id' => $actorUserId,
                    'active_resource_user_id' => $activeResourceUserId,
                ]),
                $request,
            );
        }

        $managedOwnerId = $this->managedOwnerIdForUser($actorUserId);
        $isManagedActor = $managedOwnerId !== null && $managedOwnerId !== $actorUserId;

        if ($isManagedActor) {
            if ($activeResourceUserId !== $actorUserId) {
                throw $this->failure(
                    TenantResolutionException::forbidden('managed_actor_resource_mismatch', [
                        'actor_user_id' => $actorUserId,
                        'active_resource_user_id' => $activeResourceUserId,
                        'tenant_owner_user_id' => $managedOwnerId,
                    ]),
                    $request,
                );
            }

            $tenantOwnerUserId = (int) $managedOwnerId;
            $isAgencyAccount = true;
            $isOwnerActor = false;
        } else {
            $tenantOwnerUserId = $actorUserId;
            $isOwnerActor = true;
            $isAgencyAccount = $this->agencyContext->isAgencyAccount($actor);

            if ($activeResourceUserId !== $actorUserId) {
                if (!$isAgencyAccount) {
                    throw $this->failure(
                        TenantResolutionException::forbidden('non_agency_actor_resource_mismatch', [
                            'actor_user_id' => $actorUserId,
                            'active_resource_user_id' => $activeResourceUserId,
                        ]),
                        $request,
                    );
                }

                if (!$this->ownerControlsManagedHub($actorUserId, $activeResourceUserId)) {
                    throw $this->failure(
                        TenantResolutionException::forbidden('resource_not_managed_by_actor', [
                            'actor_user_id' => $actorUserId,
                            'active_resource_user_id' => $activeResourceUserId,
                            'tenant_owner_user_id' => $tenantOwnerUserId,
                        ]),
                        $request,
                    );
                }
            }
        }

        $resourceOwnerUserId = $this->ownerIdForUserId($activeResourceUserId);
        if ($resourceOwnerUserId === null || $resourceOwnerUserId <= 0) {
            throw $this->failure(
                TenantResolutionException::unprocessable('resource_owner_unresolved', [
                    'actor_user_id' => $actorUserId,
                    'active_resource_user_id' => $activeResourceUserId,
                    'tenant_owner_user_id' => $tenantOwnerUserId,
                ]),
                $request,
            );
        }

        if ($resourceOwnerUserId !== $tenantOwnerUserId) {
            throw $this->failure(
                TenantResolutionException::forbidden('tenant_owner_mismatch', [
                    'actor_user_id' => $actorUserId,
                    'active_resource_user_id' => $activeResourceUserId,
                    'tenant_owner_user_id' => $tenantOwnerUserId,
                    'resolved_resource_owner_user_id' => $resourceOwnerUserId,
                ]),
                $request,
            );
        }

        return new TenantContext(
            tenantOwnerUserId: $tenantOwnerUserId,
            actorUserId: $actorUserId,
            activeResourceUserId: $activeResourceUserId,
            isAgencyAccount: $isAgencyAccount,
            isOwnerActor: $isOwnerActor,
        );
    }

    /**
     * @throws TenantResolutionException
     */
    public function ownerIdForUserId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        if (!$this->agencyTableUsable()) {
            return $userId;
        }

        $ownerCandidates = $this->ownerCandidatesForManagedUser($userId);
        if ($ownerCandidates === []) {
            return $userId;
        }

        if (count($ownerCandidates) > 1) {
            throw TenantResolutionException::unprocessable('inconsistent_agency_hubs', [
                'resource_user_id' => $userId,
                'owner_candidates' => $ownerCandidates,
            ]);
        }

        return (int) ($ownerCandidates[0] ?? 0);
    }

    /**
     * @throws TenantResolutionException
     */
    public function resourceBelongsToTenant(int $resourceUserId, int $tenantOwnerUserId): bool
    {
        if ($resourceUserId <= 0 || $tenantOwnerUserId <= 0) {
            return false;
        }

        $owner = $this->ownerIdForUserId($resourceUserId);
        if ($owner === null || $owner <= 0) {
            return false;
        }

        return $owner === $tenantOwnerUserId;
    }

    /**
     * @throws TenantResolutionException
     */
    public function actorCanAccessResourceUser(User $actor, int $resourceUserId, ?TenantContext $context = null): bool
    {
        if ($resourceUserId <= 0) {
            return false;
        }

        $resolvedContext = $context;
        if (!$resolvedContext || $resolvedContext->actorUserId() !== (int) $actor->id) {
            $resolvedContext = $this->resolveForActor($actor, request());
        }

        if (!$resolvedContext->isOwnerActor() && $resourceUserId !== $resolvedContext->activeResourceUserId()) {
            return false;
        }

        return $this->resourceBelongsToTenant($resourceUserId, $resolvedContext->tenantOwnerUserId());
    }

    /**
     * @throws TenantResolutionException
     */
    private function managedOwnerIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !$this->agencyTableUsable()) {
            return null;
        }

        $ownerCandidates = $this->ownerCandidatesForManagedUser($userId);
        if ($ownerCandidates === []) {
            return null;
        }

        if (count($ownerCandidates) > 1) {
            throw TenantResolutionException::unprocessable('inconsistent_agency_hubs', [
                'resource_user_id' => $userId,
                'owner_candidates' => $ownerCandidates,
            ]);
        }

        return (int) ($ownerCandidates[0] ?? 0);
    }

    private function ownerControlsManagedHub(int $ownerUserId, int $managedUserId): bool
    {
        if ($ownerUserId <= 0 || $managedUserId <= 0 || !$this->agencyTableUsable()) {
            return false;
        }

        $query = DB::table('agency_hubs')
            ->where('agency_user_id', $ownerUserId)
            ->where('managed_user_id', $managedUserId);

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        return $query->exists();
    }

    /**
     * @return array<int,int>
     */
    private function ownerCandidatesForManagedUser(int $managedUserId): array
    {
        if ($managedUserId <= 0 || !$this->agencyTableUsable()) {
            return [];
        }

        $query = DB::table('agency_hubs')
            ->where('managed_user_id', $managedUserId)
            ->select('agency_user_id')
            ->distinct();

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        return $query
            ->pluck('agency_user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();
    }

    private function agencyTableUsable(): bool
    {
        if (!Schema::hasTable('agency_hubs')) {
            return false;
        }

        return Schema::hasColumn('agency_hubs', 'agency_user_id')
            && Schema::hasColumn('agency_hubs', 'managed_user_id');
    }

    private function failure(TenantResolutionException $exception, ?Request $request = null): TenantResolutionException
    {
        $this->logFailure($exception->reasonCode(), $exception->context(), $request);

        return $exception;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logFailure(string $reasonCode, array $context = [], ?Request $request = null): void
    {
        $request = $request ?: request();

        Log::warning('Tenant resolution denied', array_merge([
            'reason_code' => $reasonCode,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'resource_user_id' => $context['active_resource_user_id'] ?? $context['resource_user_id'] ?? null,
            'tenant_owner_user_id' => $context['tenant_owner_user_id'] ?? null,
            'route' => $this->routeLabel($request),
        ], $context));
    }

    private function routeLabel(?Request $request): string
    {
        if (!$request) {
            return 'unknown';
        }

        $route = $request->route();
        if (!$route) {
            return trim((string) $request->path(), '/');
        }

        return (string) ($route->getName() ?: $route->uri());
    }
}
