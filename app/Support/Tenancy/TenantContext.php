<?php

namespace App\Support\Tenancy;

final class TenantContext
{
    public function __construct(
        private readonly int $tenantOwnerUserId,
        private readonly int $actorUserId,
        private readonly int $activeResourceUserId,
        private readonly bool $isAgencyAccount,
        private readonly bool $isOwnerActor,
    ) {
    }

    public function tenantOwnerUserId(): int
    {
        return $this->tenantOwnerUserId;
    }

    public function actorUserId(): int
    {
        return $this->actorUserId;
    }

    public function activeResourceUserId(): int
    {
        return $this->activeResourceUserId;
    }

    public function isAgencyAccount(): bool
    {
        return $this->isAgencyAccount;
    }

    public function isOwnerActor(): bool
    {
        return $this->isOwnerActor;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_owner_user_id' => $this->tenantOwnerUserId,
            'actor_user_id' => $this->actorUserId,
            'active_resource_user_id' => $this->activeResourceUserId,
            'is_agency_account' => $this->isAgencyAccount,
            'is_owner_actor' => $this->isOwnerActor,
        ];
    }
}
