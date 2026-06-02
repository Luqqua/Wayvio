<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;
use App\Services\Tenancy\TenantResolver;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantResolutionException;
use Illuminate\Support\Facades\Log;
use Modules\CustomDomains\Models\UserCustomDomain;

class TenantResourcePolicy
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {
    }

    public function view(User $user, Link|UserCustomDomain $resource): bool
    {
        return $this->checkModelAccess($user, $resource, 'view');
    }

    public function update(User $user, Link|UserCustomDomain $resource): bool
    {
        return $this->checkModelAccess($user, $resource, 'update');
    }

    public function delete(User $user, Link|UserCustomDomain $resource): bool
    {
        return $this->checkModelAccess($user, $resource, 'delete');
    }

    public function accessResource(User $user, int $resourceUserId): bool
    {
        return $this->checkResourceAccess($user, $resourceUserId, 'access_resource');
    }

    public function accessMeta(User $user, int $resourceUserId): bool
    {
        return $this->checkResourceAccess($user, $resourceUserId, 'access_meta');
    }

    public function accessAnalytics(User $user, int $resourceUserId): bool
    {
        return $this->checkResourceAccess($user, $resourceUserId, 'access_analytics');
    }

    private function checkModelAccess(User $user, Link|UserCustomDomain $resource, string $ability): bool
    {
        $context = $this->contextFor($user);
        if (!$context) {
            return $this->deny(
                'tenant_context_missing',
                [
                    'actor_user_id' => (int) $user->id,
                    'ability' => $ability,
                    'resource_type' => $this->resourceType($resource),
                    'resource_id' => (int) ($resource->id ?? 0),
                ]
            );
        }

        $resourceUserId = (int) ($resource->user_id ?? 0);
        if ($resource instanceof UserCustomDomain) {
            $resourceUserId = (int) ($resource->user_id ?? 0);
        }

        $storedOwnerId = (int) ($resource->tenant_owner_user_id ?? 0);

        return $this->checkResolvedResource(
            context: $context,
            actor: $user,
            resourceUserId: $resourceUserId,
            storedTenantOwnerId: $storedOwnerId > 0 ? $storedOwnerId : null,
            reasonSuffix: $ability,
            resourceType: $this->resourceType($resource),
            resourceId: (int) ($resource->id ?? 0),
        );
    }

    private function checkResourceAccess(User $user, int $resourceUserId, string $ability): bool
    {
        $context = $this->contextFor($user);
        if (!$context) {
            return $this->deny(
                'tenant_context_missing',
                [
                    'actor_user_id' => (int) $user->id,
                    'resource_user_id' => $resourceUserId,
                    'ability' => $ability,
                ]
            );
        }

        return $this->checkResolvedResource(
            context: $context,
            actor: $user,
            resourceUserId: $resourceUserId,
            storedTenantOwnerId: null,
            reasonSuffix: $ability,
            resourceType: 'resource_user',
            resourceId: $resourceUserId,
        );
    }

    private function checkResolvedResource(
        TenantContext $context,
        User $actor,
        int $resourceUserId,
        ?int $storedTenantOwnerId,
        string $reasonSuffix,
        string $resourceType,
        int $resourceId,
    ): bool {
        if ($resourceUserId <= 0) {
            return $this->deny('tenant_resource_user_missing', [
                'actor_user_id' => (int) $actor->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'resource_user_id' => $resourceUserId,
                'tenant_owner_user_id' => $context->tenantOwnerUserId(),
                'reason_code' => 'tenant_resource_user_missing_' . $reasonSuffix,
            ]);
        }

        if (!$context->isOwnerActor() && $resourceUserId !== $context->activeResourceUserId()) {
            return $this->deny('managed_actor_scope_violation', [
                'actor_user_id' => (int) $actor->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'resource_user_id' => $resourceUserId,
                'active_resource_user_id' => $context->activeResourceUserId(),
                'tenant_owner_user_id' => $context->tenantOwnerUserId(),
                'reason_code' => 'managed_actor_scope_violation_' . $reasonSuffix,
            ]);
        }

        if ($storedTenantOwnerId !== null) {
            if ($storedTenantOwnerId !== $context->tenantOwnerUserId()) {
                return $this->deny('tenant_owner_mismatch', [
                    'actor_user_id' => (int) $actor->id,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'resource_user_id' => $resourceUserId,
                    'tenant_owner_user_id' => $context->tenantOwnerUserId(),
                    'resource_tenant_owner_user_id' => $storedTenantOwnerId,
                    'reason_code' => 'tenant_owner_mismatch_' . $reasonSuffix,
                ]);
            }

            return true;
        }

        try {
            if (!$this->tenantResolver->resourceBelongsToTenant($resourceUserId, $context->tenantOwnerUserId())) {
                return $this->deny('tenant_owner_legacy_check_failed', [
                    'actor_user_id' => (int) $actor->id,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'resource_user_id' => $resourceUserId,
                    'tenant_owner_user_id' => $context->tenantOwnerUserId(),
                    'reason_code' => 'tenant_owner_legacy_check_failed_' . $reasonSuffix,
                ]);
            }
        } catch (TenantResolutionException $e) {
            return $this->deny($e->reasonCode(), array_merge($e->context(), [
                'actor_user_id' => (int) $actor->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'resource_user_id' => $resourceUserId,
                'tenant_owner_user_id' => $context->tenantOwnerUserId(),
                'reason_code' => $e->reasonCode() . '_' . $reasonSuffix,
            ]));
        }

        return true;
    }

    private function contextFor(User $user): ?TenantContext
    {
        $context = function_exists('currentTenantContext') ? currentTenantContext() : null;
        if ($context instanceof TenantContext && $context->actorUserId() === (int) $user->id) {
            return $context;
        }

        try {
            return $this->tenantResolver->resolveForActor($user, request());
        } catch (TenantResolutionException $e) {
            $this->deny($e->reasonCode(), array_merge($e->context(), [
                'actor_user_id' => (int) $user->id,
                'reason_code' => $e->reasonCode() . '_context_resolve',
            ]));

            return null;
        }
    }

    private function resourceType(Link|UserCustomDomain $resource): string
    {
        if ($resource instanceof Link) {
            return 'link';
        }

        return 'custom_domain';
    }

    /**
     * @param array<string,mixed> $context
     */
    private function deny(string $reasonCode, array $context): bool
    {
        Log::warning('Tenant policy denied access', array_merge([
            'reason_code' => $reasonCode,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'resource_user_id' => $context['resource_user_id'] ?? null,
            'tenant_owner_user_id' => $context['tenant_owner_user_id'] ?? null,
            'route' => (string) (request()?->route()?->getName() ?: request()?->path() ?: ''),
        ], $context));

        return false;
    }
}
