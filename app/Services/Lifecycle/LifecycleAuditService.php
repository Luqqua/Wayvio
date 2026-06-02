<?php

namespace App\Services\Lifecycle;

use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LifecycleAuditService
{
    public function __construct(private readonly ComplianceAuditService $complianceAudit)
    {
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function statusChange(
        int $userId,
        string $eventType,
        ?string $oldStatus,
        ?string $newStatus,
        string $reason,
        array $metadata = []
    ): void {
        $payload = array_merge($metadata, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        $this->insertAuditRow($userId, $eventType, $oldStatus, $newStatus, $reason, $metadata);
        $this->complianceAudit->record(
            eventType: $eventType,
            userId: $userId,
            actorUserId: $userId,
            source: 'billing.lifecycle',
            metadata: $payload,
        );
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function event(int $userId, string $eventType, string $reason = 'manual', array $metadata = []): void
    {
        $this->insertAuditRow($userId, $eventType, null, null, $reason, $metadata);
        $this->complianceAudit->record(
            eventType: $eventType,
            userId: $userId,
            actorUserId: $userId,
            source: 'billing.lifecycle',
            metadata: array_merge($metadata, ['reason' => $reason]),
        );
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function insertAuditRow(
        int $userId,
        string $eventType,
        ?string $oldStatus,
        ?string $newStatus,
        string $reason,
        array $metadata = []
    ): void {
        if (!Schema::hasTable('audit_log')) {
            return;
        }

        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedMetadata)) {
            $encodedMetadata = null;
        }

        DB::table('audit_log')->insert([
            'user_id' => $userId > 0 ? $userId : null,
            'event_type' => substr(trim($eventType), 0, 80),
            'old_status' => $oldStatus !== null ? substr(trim($oldStatus), 0, 64) : null,
            'new_status' => $newStatus !== null ? substr(trim($newStatus), 0, 64) : null,
            'reason' => substr(trim($reason), 0, 32),
            'metadata' => $encodedMetadata,
            'created_at' => now(),
        ]);
    }
}
