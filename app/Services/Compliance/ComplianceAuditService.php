<?php

namespace App\Services\Compliance;

use App\Models\UserData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ComplianceAuditService
{
    private const SUPPORTED_AGREEMENT_TYPES = ['agb', 'avv'];
    private const USERDATA_PENDING_ACCEPTANCE_KEY = 'legal_pending_acceptance';

    /**
     * @param array<string,mixed> $metadata
     */
    public function record(
        string $eventType,
        ?Request $request = null,
        ?int $userId = null,
        array $metadata = [],
        string $status = 'success',
        ?int $actorUserId = null,
        string $source = 'web',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $requestId = null,
    ): void {
        $normalizedEvent = $this->clip($eventType, 80);
        if ($normalizedEvent === null) {
            return;
        }

        $request = $request ?: request();
        $ipAddress = $this->clip($ipAddress ?: $this->extractIp($request), 64);
        $userAgent = $this->clip($userAgent ?: ($request?->userAgent() ?? null), 255);
        $requestId = $this->clip(
            $requestId
                ?: ($request?->headers->get('X-Request-Id') ?: $request?->headers->get('X-Correlation-Id')),
            64
        );
        $tenantOwnerUserId = $this->resolveTenantOwnerForAudit($userId, $actorUserId);

        if (!$this->tableReady()) {
            Log::info('Compliance audit event (table missing)', [
                'event_type' => $normalizedEvent,
                'status' => $this->clip($status, 32) ?? 'success',
                'source' => $this->clip($source, 32) ?? 'web',
                'user_id' => $userId,
                'actor_user_id' => $actorUserId,
                'tenant_owner_user_id' => $tenantOwnerUserId,
                'ip_address' => $ipAddress,
                'request_id' => $requestId,
                'metadata' => $metadata,
            ]);
            return;
        }

        $encodedMetadata = $this->encodeMetadata($metadata);

        try {
            $insert = [
                'user_id' => $this->positiveIntOrNull($userId),
                'actor_user_id' => $this->positiveIntOrNull($actorUserId),
                'event_type' => $normalizedEvent,
                'status' => $this->clip($status, 32) ?? 'success',
                'source' => $this->clip($source, 32) ?? 'web',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_id' => $requestId,
                'metadata' => $encodedMetadata,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('compliance_audit_log', 'tenant_owner_user_id')) {
                $insert['tenant_owner_user_id'] = $this->positiveIntOrNull($tenantOwnerUserId);
            }

            DB::table('compliance_audit_log')->insert($insert);
        } catch (\Throwable $e) {
            Log::warning('Compliance audit insert failed', [
                'event_type' => $normalizedEvent,
                'source' => $source,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function hashEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    /**
     * @return array<int,string>
     */
    public function agreementTypes(): array
    {
        $configured = array_map('strtolower', array_keys((array) config('legal.agreements', [])));
        $resolved = array_values(array_intersect(self::SUPPORTED_AGREEMENT_TYPES, $configured));

        return $resolved !== [] ? $resolved : self::SUPPORTED_AGREEMENT_TYPES;
    }

    /**
     * @return array{type:string,label:string,version:string,url:string}
     */
    public function currentAgreementSnapshot(string $agreementType): array
    {
        $type = strtolower(trim($agreementType));
        if (!$this->isSupportedAgreementType($type)) {
            $type = 'agb';
        }

        $config = (array) config("legal.agreements.{$type}", []);

        $label = trim((string) ($config['label'] ?? strtoupper($type)));
        if ($label === '') {
            $label = strtoupper($type);
        }

        $version = trim((string) ($config['version'] ?? 'unknown'));
        if ($version === '') {
            $version = 'unknown';
        }

        $url = trim((string) ($config['url'] ?? ('/pages/' . $type)));
        if ($url === '') {
            $url = '/pages/' . $type;
        }

        return [
            'type' => $type,
            'label' => $label,
            'version' => $version,
            'url' => $this->resolveLegalUrl($url),
        ];
    }

    /**
     * @return array<string,array{type:string,label:string,version:string,url:string}>
     */
    public function currentAgreementSnapshots(): array
    {
        $snapshots = [];

        foreach ($this->agreementTypes() as $type) {
            $snapshots[$type] = $this->currentAgreementSnapshot($type);
        }

        return $snapshots;
    }

    /**
     * @return array<int,string>
     */
    public function requiredAgreementTypes(User $user): array
    {
        if (!$this->usersLegalColumnsReady()) {
            return [];
        }

        $required = [];

        foreach ($this->agreementTypes() as $type) {
            $columnMap = $this->agreementColumnMap($type);
            if ($columnMap === null) {
                continue;
            }

            $acceptedAtColumn = $columnMap['accepted_at'];
            $versionColumn = $columnMap['version'];
            $acceptedAt = $user->{$acceptedAtColumn} ?? null;
            $savedVersion = trim((string) ($user->{$versionColumn} ?? ''));
            $currentVersion = $this->currentAgreementSnapshot($type)['version'];

            if ($acceptedAt === null || $savedVersion === '') {
                $required[] = $type;
                continue;
            }

            if ($savedVersion === $currentVersion) {
                $this->clearDeferredAgreementEnforcement((int) $user->id, $type);
                continue;
            }

            if (!$this->isAgreementEnforcementDue($user, $type, $currentVersion)) {
                continue;
            }

            $required[] = $type;
        }

        return $required;
    }

    public function requiresCurrentLegalAcceptance(User $user): bool
    {
        return $this->requiredAgreementTypes($user) !== [];
    }

    public function recordAgreementAcceptance(
        User $user,
        string $agreementType,
        ?Request $request = null,
        ?int $actorUserId = null,
        string $source = 'web',
    ): void {
        $type = strtolower(trim($agreementType));
        $columnMap = $this->agreementColumnMap($type);
        if ($columnMap === null || !$this->usersLegalColumnsReady()) {
            return;
        }

        $snapshot = $this->currentAgreementSnapshot($type);
        $acceptedAt = now();
        $updates = [
            $columnMap['accepted_at'] => $acceptedAt,
            $columnMap['version'] => $snapshot['version'],
        ];

        // Keep legacy Terms columns in sync when AGB is accepted.
        if ($type === 'agb') {
            if (Schema::hasColumn('users', 'tos_accepted_at')) {
                $updates['tos_accepted_at'] = $acceptedAt;
            }
            if (Schema::hasColumn('users', 'tos_version')) {
                $updates['tos_version'] = $snapshot['version'];
            }
            if (Schema::hasColumn('users', 'tos_content_hash')) {
                $updates['tos_content_hash'] = null;
            }
        }

        $user->forceFill($updates)->save();
        $this->clearDeferredAgreementEnforcement((int) $user->id, $type);

        $request = $request ?: request();
        $actor = $actorUserId ?? (int) $user->id;

        $this->recordAgreementAcceptanceHistory(
            userId: (int) $user->id,
            actorUserId: $actor,
            agreementType: $type,
            agreementVersion: $snapshot['version'],
            acceptedAt: $acceptedAt,
            source: $source,
            request: $request,
        );

        $metadata = [
            'agreement_type' => $type,
            'agreement_label' => $snapshot['label'],
            'agreement_version' => $snapshot['version'],
            'agreement_url' => $snapshot['url'],
            'accepted_checkbox' => true,
        ];

        $this->record(
            $type . '_accepted',
            request: $request,
            userId: (int) $user->id,
            actorUserId: $actor,
            source: $source,
            metadata: $metadata,
        );

        if ($type === 'agb') {
            // Backward compatible event expected by older reports.
            $this->record(
                'tos_accepted',
                request: $request,
                userId: (int) $user->id,
                actorUserId: $actor,
                source: $source,
                metadata: [
                    'tos_version' => $snapshot['version'],
                    'tos_url' => $snapshot['url'],
                    'tos_content_hash' => null,
                    'accepted_checkbox' => true,
                ]
            );
        }
    }

    public function requiresTosAcceptance(User $user): bool
    {
        if ($this->usersLegalColumnsReady()) {
            return $this->requiresCurrentLegalAcceptance($user);
        }

        if (!$this->usersTosColumnsReady()) {
            return false;
        }

        return $user->tos_accepted_at === null;
    }

    /**
     * @return array{version:string,content_hash:?string,url:string}
     */
    public function currentTosSnapshot(): array
    {
        $agb = $this->currentAgreementSnapshot('agb');

        return [
            'version' => $agb['version'],
            'content_hash' => null,
            'url' => $agb['url'],
        ];
    }

    public function scheduleDeferredAgreementEnforcement(
        int $userId,
        string $agreementType,
        string $targetVersion,
        mixed $enforceAfter,
        mixed $notifiedAt = null,
        mixed $emailSentAt = null,
    ): void {
        $type = strtolower(trim($agreementType));
        if ($userId <= 0 || $this->agreementColumnMap($type) === null) {
            return;
        }

        $resolvedTargetVersion = trim($targetVersion);
        if ($resolvedTargetVersion === '') {
            return;
        }

        $enforceAfterAt = $this->toCarbon($enforceAfter);
        if (!$enforceAfterAt) {
            return;
        }

        $notifiedAtValue = $this->toCarbon($notifiedAt) ?? now();
        $emailSentAtValue = $this->toCarbon($emailSentAt);

        $existing = UserData::getData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY);
        $pending = is_array($existing) ? $existing : [];
        $pending[$type] = [
            'target_version' => $resolvedTargetVersion,
            'notified_at' => $notifiedAtValue->toIso8601String(),
            'enforce_after' => $enforceAfterAt->toIso8601String(),
            'email_sent_at' => $emailSentAtValue?->toIso8601String(),
        ];

        UserData::saveData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY, $pending);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function deferredAgreementEnforcement(int $userId, string $agreementType): ?array
    {
        $type = strtolower(trim($agreementType));
        if ($userId <= 0 || $this->agreementColumnMap($type) === null) {
            return null;
        }

        $existing = UserData::getData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY);
        if (!is_array($existing)) {
            return null;
        }

        $entry = $existing[$type] ?? null;

        return is_array($entry) ? $entry : null;
    }

    public function clearDeferredAgreementEnforcement(int $userId, string $agreementType): void
    {
        $type = strtolower(trim($agreementType));
        if ($userId <= 0 || $this->agreementColumnMap($type) === null) {
            return;
        }

        $existing = UserData::getData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY);
        if (!is_array($existing) || !array_key_exists($type, $existing)) {
            return;
        }

        unset($existing[$type]);
        if ($existing === []) {
            UserData::removeData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY);
            return;
        }

        UserData::saveData($userId, self::USERDATA_PENDING_ACCEPTANCE_KEY, $existing);
    }

    private function tableReady(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('compliance_audit_log');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('compliance_audit_log');

        return $ready;
    }

    private function usersLegalColumnsReady(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        if (!Schema::hasTable('users')) {
            $ready = false;
            return $ready;
        }

        foreach ($this->agreementTypes() as $type) {
            $columnMap = $this->agreementColumnMap($type);
            if ($columnMap === null) {
                continue;
            }

            if (!Schema::hasColumn('users', $columnMap['accepted_at']) || !Schema::hasColumn('users', $columnMap['version'])) {
                $ready = false;
                return $ready;
            }
        }

        $ready = true;

        return $ready;
    }

    private function usersTosColumnsReady(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('users')
            && Schema::hasColumn('users', 'tos_accepted_at');

        return $ready;
    }

    private function recordAgreementAcceptanceHistory(
        int $userId,
        int $actorUserId,
        string $agreementType,
        string $agreementVersion,
        mixed $acceptedAt,
        string $source,
        ?Request $request = null,
    ): void {
        if (!Schema::hasTable('user_agreement_acceptances')) {
            return;
        }

        $request = $request ?: request();

        try {
            DB::table('user_agreement_acceptances')->insert([
                'user_id' => $this->positiveIntOrNull($userId),
                'actor_user_id' => $this->positiveIntOrNull($actorUserId),
                'agreement_type' => $this->clip($agreementType, 16),
                'agreement_version' => $this->clip($agreementVersion, 40) ?? 'unknown',
                'accepted_at' => $acceptedAt,
                'source' => $this->clip($source, 32) ?? 'web',
                'ip_address' => $this->clip($this->extractIp($request), 64),
                'user_agent' => $this->clip($request?->userAgent(), 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Agreement acceptance history insert failed', [
                'agreement_type' => $agreementType,
                'user_id' => $userId,
                'source' => $source,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{accepted_at:string,version:string}|null
     */
    private function agreementColumnMap(string $agreementType): ?array
    {
        return match (strtolower(trim($agreementType))) {
            'agb' => [
                'accepted_at' => 'agb_accepted_at',
                'version' => 'agb_version',
            ],
            'avv' => [
                'accepted_at' => 'avv_accepted_at',
                'version' => 'avv_version',
            ],
            default => null,
        };
    }

    private function isSupportedAgreementType(string $agreementType): bool
    {
        return in_array(strtolower(trim($agreementType)), self::SUPPORTED_AGREEMENT_TYPES, true);
    }

    private function isAgreementEnforcementDue(User $user, string $agreementType, string $currentVersion): bool
    {
        $entry = $this->deferredAgreementEnforcement((int) $user->id, $agreementType);
        if ($entry === null) {
            return true;
        }

        $targetVersion = trim((string) ($entry['target_version'] ?? ''));
        if ($targetVersion !== '' && $targetVersion !== $currentVersion) {
            return true;
        }

        $enforceAfter = $this->toCarbon($entry['enforce_after'] ?? null);
        if (!$enforceAfter) {
            return true;
        }

        return now()->greaterThanOrEqualTo($enforceAfter);
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        try {
            return Carbon::parse($stringValue);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTenantOwnerForAudit(?int $userId, ?int $actorUserId): ?int
    {
        $contextTenantOwnerId = function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null;
        if (is_int($contextTenantOwnerId) && $contextTenantOwnerId > 0) {
            return $contextTenantOwnerId;
        }

        $resolvedByUser = $this->resolveTenantOwnerFromUserId($userId);
        if ($resolvedByUser !== null) {
            return $resolvedByUser;
        }

        return $this->resolveTenantOwnerFromUserId($actorUserId);
    }

    private function resolveTenantOwnerFromUserId(?int $userId): ?int
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        if (
            !Schema::hasTable('agency_hubs')
            || !Schema::hasColumn('agency_hubs', 'agency_user_id')
            || !Schema::hasColumn('agency_hubs', 'managed_user_id')
        ) {
            return $userId;
        }

        $query = DB::table('agency_hubs')
            ->where('managed_user_id', $userId)
            ->select('agency_user_id')
            ->distinct();

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        $owners = $query
            ->pluck('agency_user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if (count($owners) === 1) {
            return (int) ($owners[0] ?? $userId);
        }

        if (count($owners) > 1) {
            Log::warning('Compliance audit tenant owner resolution inconsistent', [
                'resource_user_id' => $userId,
                'owner_candidates' => $owners,
            ]);

            return null;
        }

        return $userId;
    }

    private function extractIp(?Request $request): ?string
    {
        if (!$request) {
            return null;
        }

        if ($request->headers->has('CF-Connecting-IP')) {
            return (string) $request->headers->get('CF-Connecting-IP');
        }

        $forwarded = $request->headers->get('X-Forwarded-For');
        if ($forwarded) {
            $parts = explode(',', $forwarded);
            return trim((string) ($parts[0] ?? ''));
        }

        return $request->ip();
    }

    private function resolveLegalUrl(string $configuredPath): string
    {
        if (preg_match('/^https?:\/\//i', $configuredPath) === 1) {
            return $configuredPath;
        }

        $path = '/' . ltrim($configuredPath, '/');

        try {
            return url($path);
        } catch (\Throwable $e) {
            return $path;
        }
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function encodeMetadata(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }

        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : null;
    }

    private function clip(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return substr($stringValue, 0, $max);
    }

    private function positiveIntOrNull(?int $value): ?int
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return $value;
    }
}
