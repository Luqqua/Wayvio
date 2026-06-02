<?php

namespace App\Services\Hubs;

use App\Models\AgencyHub;
use App\Models\User;
use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubPublicationService
{
    public function __construct(
        private readonly ComplianceAuditService $complianceAudit,
    ) {
    }

    public function canRenderPublic(User $hubUser, ?User $viewer): bool
    {
        if (!$this->publicationColumnsReady()) {
            return true;
        }

        if ((bool) ($hubUser->is_published ?? false)) {
            return true;
        }

        if (!$viewer) {
            return false;
        }

        if ((int) $viewer->id === (int) $hubUser->id) {
            return true;
        }

        return $this->isAgencyOwnerOfHub($viewer, (int) $hubUser->id, false);
    }

    public function canManagePublication(User $actor, User $hubUser): bool
    {
        if ((int) $actor->id === (int) $hubUser->id) {
            return true;
        }

        return $this->isAgencyOwnerOfHub($actor, (int) $hubUser->id, true);
    }

    public function setPublicationState(
        User $actor,
        User $hubUser,
        bool $published,
        ?Request $request = null,
        string $source = 'hub.publication',
    ): bool {
        if (!$this->publicationColumnsReady()) {
            return false;
        }

        if (!$this->canManagePublication($actor, $hubUser)) {
            return false;
        }

        return DB::transaction(function () use ($actor, $hubUser, $published, $request, $source): bool {
            $lockedHub = User::query()
                ->whereKey((int) $hubUser->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedHub) {
                return false;
            }

            $current = (bool) ($lockedHub->is_published ?? false);
            if ($current === $published) {
                return false;
            }

            $lockedHub->is_published = $published;
            $lockedHub->published_at = $published ? now() : null;
            $lockedHub->save();

            $event = $published ? 'hub_published' : 'hub_unpublished';
            $this->complianceAudit->record(
                $event,
                request: $request,
                userId: (int) $actor->id,
                actorUserId: (int) $actor->id,
                source: $source,
                metadata: [
                    'hub_id' => (int) $lockedHub->id,
                    'target_user_id' => (int) $lockedHub->id,
                    'target_slug' => (string) ($lockedHub->littlelink_name ?? ''),
                    'is_published' => (bool) $published,
                    'published_at' => $lockedHub->published_at?->toIso8601String(),
                ]
            );

            return true;
        });
    }

    public function publicationColumnsReady(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('users')
            && Schema::hasColumn('users', 'is_published')
            && Schema::hasColumn('users', 'published_at');

        return $ready;
    }

    private function isAgencyOwnerOfHub(User $actor, int $hubUserId, bool $onlyActive): bool
    {
        if ($hubUserId <= 0 || !Schema::hasTable('agency_hubs')) {
            return false;
        }

        $query = AgencyHub::query()
            ->where('agency_user_id', (int) $actor->id)
            ->where('managed_user_id', $hubUserId);

        if (Schema::hasColumn('agency_hubs', 'status')) {
            if ($onlyActive) {
                $query->where('status', 'active');
            } else {
                $query->where('status', '!=', 'deleted');
            }
        }

        return $query->exists();
    }
}

