<?php

namespace Modules\Partners\Services;

use App\Models\User;
use App\Services\Partners\PartnersClient;
use App\Services\Tenancy\TenantResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Models\PartnerAccount;
use Modules\Partners\Models\PartnerAttribution;
use Modules\Partners\Models\PartnerCommissionLedger;
use Modules\Partners\Models\PartnerInviteCode;
use Modules\Partners\Models\PartnerPayoutBatch;

class PartnerManager
{
    private const REFERRAL_COOKIE = 'partner_referral_code';
    private const DASHBOARD_ALLOWED_STATUSES = ['active', 'restricted', 'pending'];
    private const MIN_PAYOUT_PAYMENT_COUNT = 2;

    public function activeAccountFor(?User $user): ?PartnerAccount
    {
        if (!$this->schemaReady()) {
            return null;
        }

        if (!$user) {
            return null;
        }

        return PartnerAccount::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function isActivePartner(?User $user): bool
    {
        return $this->activeAccountFor($user) !== null;
    }

    public function dashboardAccountFor(?User $user): ?PartnerAccount
    {
        if (!$this->schemaReady() || !$user) {
            return null;
        }

        return PartnerAccount::query()
            ->where('user_id', $user->id)
            ->whereIn('status', self::DASHBOARD_ALLOWED_STATUSES)
            ->first();
    }

    public function activatePartner(int $userId, int $rateBps = 3000, ?string $connectAccountId = null): PartnerAccount
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $normalizedConnectId = $connectAccountId ? trim($connectAccountId) : null;
        $partner = PartnerAccount::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'active',
                'default_commission_rate_bps' => max(1, min(10000, $rateBps)),
                'stripe_connect_account_id' => $normalizedConnectId,
            ],
        );

        if (
            $normalizedConnectId !== null
            && $partner->onboarding_started_at === null
            && $this->supportsPartnerAccountConnectColumns()
        ) {
            $partner->onboarding_started_at = now();
            $partner->save();
        }

        $this->ensureDefaultInviteCode($partner);
        $this->logPartnerAudit(
            (int) $partner->user_id,
            'partner_activated',
            [
                'rate_bps' => (int) $partner->default_commission_rate_bps,
                'has_connect_account' => $normalizedConnectId !== null,
            ],
            'artisan',
        );

        return $partner;
    }

    public function deactivatePartner(int $userId): PartnerAccount
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $partner = PartnerAccount::query()
            ->where('user_id', $userId)
            ->first();

        if (!$partner) {
            throw ValidationException::withMessages([
                'user_id' => 'Partner account not found.',
            ]);
        }

        $partner->status = 'suspended';
        $partner->save();

        $this->logPartnerAudit(
            (int) $partner->user_id,
            'partner_deactivated',
            [],
            'artisan',
        );

        return $partner;
    }

    public function ensureDefaultInviteCode(PartnerAccount $partner): PartnerInviteCode
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $existing = PartnerInviteCode::query()
            ->where('partner_user_id', $partner->user_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createInviteCodeForPartner($partner->user_id);
    }

    public function createInviteCodeForPartner(int $partnerUserId, ?string $requestedCode = null): PartnerInviteCode
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $account = PartnerAccount::query()
            ->where('user_id', $partnerUserId)
            ->where('status', 'active')
            ->firstOrFail();

        $existing = PartnerInviteCode::query()
            ->where('partner_user_id', $partnerUserId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $code = $requestedCode ? $this->normalizeCode($requestedCode) : $this->generateUniqueCode();
        if ($requestedCode && !$this->isCodeAvailable($code)) {
            throw ValidationException::withMessages([
                'partner_code' => 'This partner code is already in use.',
            ]);
        }

        $inviteCode = PartnerInviteCode::query()->create([
            'partner_user_id' => $account->user_id,
            'code' => $code,
            'status' => 'active',
        ]);

        $this->logPartnerAudit(
            (int) $account->user_id,
            'invite_code_created',
            [
                'invite_code_id' => (int) $inviteCode->id,
                'requested_custom_code' => $requestedCode !== null,
                'max_uses' => $inviteCode->max_uses,
                'expires_at' => $inviteCode->expires_at?->toDateTimeString(),
            ],
            'partner_dashboard',
        );

        return $inviteCode;
    }

    public function validateSignupCodeOrThrow(?string $code): void
    {
        if (!$this->schemaReady()) {
            return;
        }

        $normalized = $this->normalizeCode((string) $code);
        if ($normalized === '') {
            return;
        }

        if (!$this->findValidInviteCode($normalized)) {
            throw ValidationException::withMessages([
                'partner_code' => 'Invalid or expired partner code.',
            ]);
        }
    }

    public function captureReferralCode(Request $request, ?string $code): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }

        $normalized = $this->normalizeCode((string) $code);
        if ($normalized === '') {
            return false;
        }

        if ($this->currentReferralCode($request) !== null) {
            return false;
        }

        if (!$this->findValidInviteCode($normalized)) {
            return false;
        }

        Cookie::queue(Cookie::make(
            self::REFERRAL_COOKIE,
            $normalized,
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        return true;
    }

    public function clearReferralCookie(): void
    {
        Cookie::queue(Cookie::forget(self::REFERRAL_COOKIE));
    }

    public function currentReferralCode(Request $request): ?string
    {
        $raw = $request->cookie(self::REFERRAL_COOKIE);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $this->normalizeCode($raw);
    }

    public function applyAttributionForNewUser(User $user, ?string $explicitCode, Request $request): ?PartnerAttribution
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $existing = PartnerAttribution::query()
            ->where('referred_user_id', $user->id)
            ->first();

        if ($existing) {
            $this->clearReferralCookie();
            return $existing;
        }

        $normalizedExplicit = $this->normalizeCode((string) $explicitCode);
        $selectedCode = $normalizedExplicit !== '' ? $normalizedExplicit : ($this->currentReferralCode($request) ?? '');
        if ($selectedCode === '') {
            return null;
        }

        $inviteCode = $this->findValidInviteCode($selectedCode);
        if (!$inviteCode || (int) $inviteCode->partner_user_id === (int) $user->id) {
            $this->clearReferralCookie();
            return null;
        }

        $partner = PartnerAccount::query()
            ->where('user_id', $inviteCode->partner_user_id)
            ->where('status', 'active')
            ->first();

        if (!$partner) {
            $this->clearReferralCookie();
            return null;
        }

        $now = CarbonImmutable::now();
        $attributionPayload = [
            'referred_user_id' => $user->id,
            'partner_user_id' => $inviteCode->partner_user_id,
            'invite_code_id' => $inviteCode->id,
            'source' => $normalizedExplicit !== '' ? 'code' : 'link',
            'commission_rate_bps' => max(1, min(10000, (int) $partner->default_commission_rate_bps)),
            'starts_at' => $now,
            'ends_at' => $now->addMonthsNoOverflow(6),
            'attributed_at' => $now,
        ];

        if (Schema::hasColumn('partner_attributions', 'tenant_owner_user_id')) {
            $attributionPayload['tenant_owner_user_id'] = $this->resolveTenantOwnerForUserId((int) $user->id);
        }

        $attribution = PartnerAttribution::query()->create($attributionPayload);

        $inviteCode->increment('uses_count');
        $this->clearReferralCookie();

        $this->logPartnerAudit(
            (int) $inviteCode->partner_user_id,
            'attribution_created',
            [
                'referred_user_id' => (int) $user->id,
                'invite_code_id' => (int) $inviteCode->id,
                'source' => (string) $attribution->source,
                'commission_rate_bps' => (int) $attribution->commission_rate_bps,
                'starts_at' => $attribution->starts_at?->toDateTimeString(),
                'ends_at' => $attribution->ends_at?->toDateTimeString(),
            ],
            'auth.register',
        );

        return $attribution;
    }

    public function inviteCodesForPartner(int $partnerUserId)
    {
        if (!$this->schemaReady()) {
            return collect();
        }

        return PartnerInviteCode::query()
            ->where('partner_user_id', $partnerUserId)
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(1)
            ->get();
    }

    public function approveMaturedCommissions(): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        $pendingEligibleQuery = PartnerCommissionLedger::query()
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->whereRaw('available_at <= CURRENT_TIMESTAMP');

        $this->applyMinimumPaymentGate($pendingEligibleQuery);

        $approvalsByPartner = [];
        $updated = 0;
        $batchSize = 1000;

        (clone $pendingEligibleQuery)
            ->select(['id', 'partner_user_id', 'commission_amount_cents'])
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use (&$approvalsByPartner, &$updated): void {
                $ids = $rows
                    ->pluck('id')
                    ->filter(static fn ($id): bool => is_numeric($id))
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                if ($ids === []) {
                    return;
                }

                $affected = PartnerCommissionLedger::query()
                    ->whereIn('id', $ids)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'approved',
                        'updated_at' => now(),
                    ]);

                if ($affected <= 0) {
                    return;
                }

                $updated += $affected;

                foreach ($rows as $row) {
                    $partnerUserId = (int) ($row->partner_user_id ?? 0);
                    if ($partnerUserId <= 0) {
                        continue;
                    }

                    if (!isset($approvalsByPartner[$partnerUserId])) {
                        $approvalsByPartner[$partnerUserId] = [
                            'entries_count' => 0,
                            'amount_cents' => 0,
                        ];
                    }

                    $approvalsByPartner[$partnerUserId]['entries_count']++;
                    $approvalsByPartner[$partnerUserId]['amount_cents'] += (int) ($row->commission_amount_cents ?? 0);
                }
            });

        if ($updated > 0) {
            foreach ($approvalsByPartner as $partnerUserId => $summary) {
                if ($partnerUserId <= 0) {
                    continue;
                }

                $this->logPartnerAudit(
                    $partnerUserId,
                    'commission_approved',
                    [
                        'entries_count' => (int) ($summary['entries_count'] ?? 0),
                        'amount_cents' => (int) ($summary['amount_cents'] ?? 0),
                        'approval_mode' => 'matured_release',
                        'minimum_payment_gate' => self::MIN_PAYOUT_PAYMENT_COUNT,
                    ],
                    'artisan',
                );
            }
        }

        return $updated;
    }

    /**
     * @return array<string,mixed>
     */
    public function manualPayoutPreview(int $partnerUserId): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $partner = $this->partnerAccountForPayoutOrThrow($partnerUserId);
        $openBatch = PartnerPayoutBatch::query()
            ->where('partner_user_id', $partnerUserId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        return [
            'partner' => $partner,
            'open_batch' => $openBatch,
            'groups' => $this->manualPayoutGroups($partnerUserId),
        ];
    }

    /**
     * @return array<int,PartnerPayoutBatch>
     */
    public function prepareManualPayoutBatches(int $partnerUserId): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        return DB::transaction(function () use ($partnerUserId): array {
            $partner = $this->partnerAccountForPayoutOrThrow($partnerUserId, true);
            $openBatch = PartnerPayoutBatch::query()
                ->where('partner_user_id', $partnerUserId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($openBatch) {
                throw ValidationException::withMessages([
                    'partner_id' => "Partner already has an open payout batch (#{$openBatch->id}). Settle or cancel it first.",
                ]);
            }

            $entriesQuery = PartnerCommissionLedger::query()
                ->where('partner_user_id', $partnerUserId)
                ->where('status', 'approved')
                ->whereNull('payout_batch_id')
                ->orderBy('id');

            $this->applyMinimumPaymentGate($entriesQuery);

            $entries = $entriesQuery
                ->lockForUpdate()
                ->get(['id', 'currency', 'commission_amount_cents']);

            $preparedBatches = [];
            $groups = $entries->groupBy(fn ($entry) => strtolower((string) $entry->currency));

            foreach ($groups as $currency => $group) {
                $netAmount = (int) $group->sum(fn ($entry) => (int) $entry->commission_amount_cents);
                if ($netAmount <= 0 || $netAmount < (int) $partner->payout_minimum_cents) {
                    continue;
                }

                $batchPayload = [
                    'partner_user_id' => $partner->user_id,
                    'currency' => $currency,
                    'net_amount_cents' => $netAmount,
                    'status' => 'pending',
                ];

                $batch = PartnerPayoutBatch::query()->create($batchPayload);

                if ($this->supportsTransferGroupColumn()) {
                    $batch->forceFill([
                        'transfer_group' => 'partner_batch_' . $batch->id,
                    ])->save();
                }

                PartnerCommissionLedger::query()
                    ->whereIn('id', $group->pluck('id')->all())
                    ->update([
                        'payout_batch_id' => $batch->id,
                        'updated_at' => now(),
                    ]);

                $this->logPartnerAudit(
                    (int) $partner->user_id,
                    'payout_batch_prepared',
                    [
                        'batch_id' => (int) $batch->id,
                        'amount_cents' => (int) $batch->net_amount_cents,
                        'currency' => (string) $batch->currency,
                        'entry_count' => (int) $group->count(),
                    ],
                    'artisan',
                );

                $preparedBatches[] = $batch;
            }

            if ($preparedBatches === []) {
                throw ValidationException::withMessages([
                    'partner_id' => 'No payout-ready balance is available for this partner.',
                ]);
            }

            return $preparedBatches;
        });
    }

    public function settleManualPayoutBatch(
        int $batchId,
        string $reference,
        ?CarbonImmutable $paidAt = null,
        string $actor = 'manual'
    ): PartnerPayoutBatch
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        $normalizedReference = trim($reference);
        if ($normalizedReference === '') {
            throw ValidationException::withMessages([
                'reference' => 'A payout reference is required (recommended: Stripe transfer or payout ID).',
            ]);
        }

        $this->verifyPayoutTransferOrThrow($batchId, $normalizedReference);

        return DB::transaction(function () use ($batchId, $normalizedReference, $paidAt, $actor): PartnerPayoutBatch {
            $batch = PartnerPayoutBatch::query()
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Payout batch not found.',
                ]);
            }

            if ($batch->status === 'paid') {
                $existingReference = trim((string) $batch->stripe_transfer_id);
                if ($existingReference !== '' && hash_equals($existingReference, $normalizedReference)) {
                    return $batch->fresh();
                }

                throw ValidationException::withMessages([
                    'batch_id' => 'This payout batch is already settled with a different reference.',
                ]);
            }

            if ($batch->status !== 'pending') {
                throw ValidationException::withMessages([
                    'batch_id' => 'Only pending payout batches can be settled.',
                ]);
            }

            $existingReference = trim((string) $batch->stripe_transfer_id);
            if ($existingReference !== '' && !hash_equals($existingReference, $normalizedReference)) {
                throw ValidationException::withMessages([
                    'reference' => 'This payout batch is already linked to a different Stripe transfer.',
                ]);
            }

            $entries = PartnerCommissionLedger::query()
                ->where('payout_batch_id', $batch->id)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->get(['id', 'commission_amount_cents']);

            if ($entries->isEmpty()) {
                throw ValidationException::withMessages([
                    'batch_id' => 'This payout batch has no attached approved ledger entries.',
                ]);
            }

            $netAmount = (int) $entries->sum(fn ($entry) => (int) $entry->commission_amount_cents);
            if ($netAmount <= 0) {
                throw ValidationException::withMessages([
                    'batch_id' => 'This payout batch is zero or negative after adjustments. Cancel it instead of settling it.',
                ]);
            }

            $batchUpdate = [
                'net_amount_cents' => $netAmount,
                'stripe_transfer_id' => Str::limit($normalizedReference, 191, ''),
                'status' => 'paid',
                'paid_at' => $paidAt?->toDateTimeString() ?? now(),
            ];
            if ($this->supportsSettledAtColumn()) {
                $batchUpdate['settled_at'] = $paidAt?->toDateTimeString() ?? now();
            }

            $batch->forceFill($batchUpdate)->save();

            PartnerCommissionLedger::query()
                ->whereIn('id', $entries->pluck('id')->all())
                ->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);

            $this->logPartnerAudit(
                (int) $batch->partner_user_id,
                'payout_batch_settled',
                [
                    'batch_id' => (int) $batch->id,
                    'reference' => (string) $batch->stripe_transfer_id,
                    'amount_cents' => (int) $batch->net_amount_cents,
                    'currency' => (string) $batch->currency,
                ],
                $actor,
            );

            return $batch->fresh();
        });
    }

    private function verifyPayoutTransferOrThrow(int $batchId, string $reference): void
    {
        $requireVerification = (bool) config('partners.payout_settle.require_verified_transfer', true);
        if (!$requireVerification) {
            return;
        }

        $partnersClient = app(PartnersClient::class);
        if (!$partnersClient->enabled()) {
            throw ValidationException::withMessages([
                'reference' => 'Partner payout transfer verification requires partners internal API to be enabled.',
            ]);
        }

        $verification = $partnersClient->verifyPayoutTransfer($batchId, $reference);
        if (!is_array($verification)) {
            throw ValidationException::withMessages([
                'reference' => 'Stripe transfer verification is unavailable. Try again when internal APIs are reachable.',
            ]);
        }

        $statusCode = (int) ($verification['_status'] ?? 200);
        $errorCode = data_get($verification, 'error.code');
        $errorMessage = data_get($verification, 'error.message');
        if ($statusCode >= 400 || (is_string($errorCode) && $errorCode !== '')) {
            $details = is_string($errorCode) && $errorCode !== ''
                ? " [{$errorCode}]"
                : '';
            $message = is_string($errorMessage) && $errorMessage !== ''
                ? $errorMessage
                : 'Stripe transfer verification failed.';

            throw ValidationException::withMessages([
                'reference' => "Stripe transfer verification failed{$details}: {$message}",
            ]);
        }

        if (($verification['verified'] ?? false) !== true) {
            $mismatchCodes = collect($verification['mismatches'] ?? [])
                ->filter(static fn ($item) => is_string($item) && trim($item) !== '')
                ->map(static fn ($item) => trim((string) $item))
                ->take(5)
                ->values()
                ->all();

            $mismatchHint = $mismatchCodes !== [] ? implode(', ', $mismatchCodes) : 'unknown_mismatch';

            throw ValidationException::withMessages([
                'reference' => "Stripe transfer verification mismatch: {$mismatchHint}",
            ]);
        }
    }

    public function cancelManualPayoutBatch(int $batchId): PartnerPayoutBatch
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Partner schema is not ready.');
        }

        return DB::transaction(function () use ($batchId): PartnerPayoutBatch {
            $batch = PartnerPayoutBatch::query()
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Payout batch not found.',
                ]);
            }

            if ($batch->status !== 'pending') {
                throw ValidationException::withMessages([
                    'batch_id' => 'Only pending payout batches can be canceled.',
                ]);
            }

            PartnerCommissionLedger::query()
                ->where('payout_batch_id', $batch->id)
                ->lockForUpdate()
                ->update([
                    'payout_batch_id' => null,
                    'updated_at' => now(),
                ]);

            $batch->forceFill([
                'status' => 'canceled',
            ])->save();

            $this->logPartnerAudit(
                (int) $batch->partner_user_id,
                'payout_batch_canceled',
                [
                    'batch_id' => (int) $batch->id,
                ],
                'artisan',
            );

            return $batch->fresh();
        });
    }

    public function validateDashboardPartnerAccess(User $user): PartnerAccount
    {
        if (!$this->schemaReady()) {
            abort(503, 'Partner schema is not ready.');
        }

        $account = $this->dashboardAccountFor($user);
        if (!$account) {
            abort(403, 'Partner access is not enabled for this account.');
        }

        return $account;
    }

    private function findValidInviteCode(string $code): ?PartnerInviteCode
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === '') {
            return null;
        }

        $now = now();

        return PartnerInviteCode::query()
            ->where('code', $normalized)
            ->where('status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->where(function ($query): void {
                $query->whereNull('max_uses')
                    ->orWhereColumn('uses_count', '<', 'max_uses');
            })
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('partner_accounts')
                    ->whereColumn('partner_accounts.user_id', 'partner_invite_codes.partner_user_id')
                    ->where('partner_accounts.status', 'active');
            })
            ->first();
    }

    private function normalizeCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9_-]/', '', $normalized) ?: '';

        return Str::limit($normalized, 64, '');
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (!$this->isCodeAvailable($code));

        return $code;
    }

    private function isCodeAvailable(string $code): bool
    {
        return !PartnerInviteCode::query()
            ->where('code', $code)
            ->exists();
    }

    private function partnerAccountForPayoutOrThrow(int $partnerUserId, bool $lockForUpdate = false): PartnerAccount
    {
        $query = PartnerAccount::query()
            ->where('user_id', $partnerUserId)
            ->where('status', 'active');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $partner = $query->first();
        if (!$partner) {
            throw ValidationException::withMessages([
                'partner_id' => 'Partner account not found or not active.',
            ]);
        }

        if (!$partner->stripe_connect_account_id) {
            throw ValidationException::withMessages([
                'partner_id' => 'Partner has no Stripe Connect account configured.',
            ]);
        }

        return $partner;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function manualPayoutGroups(int $partnerUserId): array
    {
        $partner = $this->partnerAccountForPayoutOrThrow($partnerUserId);
        $entriesQuery = PartnerCommissionLedger::query()
            ->where('partner_user_id', $partnerUserId)
            ->where('status', 'approved')
            ->whereNull('payout_batch_id')
            ->orderBy('id');

        $this->applyMinimumPaymentGate($entriesQuery);

        $entries = $entriesQuery
            ->get(['id', 'currency', 'commission_amount_cents']);

        return $entries
            ->groupBy(fn ($entry) => strtolower((string) $entry->currency))
            ->map(function ($group, $currency) use ($partner): array {
                $netAmount = (int) $group->sum(fn ($entry) => (int) $entry->commission_amount_cents);

                return [
                    'currency' => (string) $currency,
                    'entry_count' => $group->count(),
                    'net_amount_cents' => $netAmount,
                    'eligible' => $netAmount > 0 && $netAmount >= (int) $partner->payout_minimum_cents,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Require at least two successful commission-bearing invoices before payouts are eligible.
     */
    private function applyMinimumPaymentGate($query): void
    {
        $allowLegacyNullMeta = (bool) config('partners.payment_gate.allow_legacy_null_meta', false);
        $billingReasonExpression = $this->paymentGateBillingReasonExpression();

        $query->where(function ($gate) use ($allowLegacyNullMeta, $billingReasonExpression): void {
            $gate
                ->where('commission_amount_cents', '<=', 0)
                ->orWhereIn('referred_user_id', function ($subQuery) use ($allowLegacyNullMeta, $billingReasonExpression): void {
                    $subQuery
                        ->from('partner_commission_ledger')
                        ->select('referred_user_id')
                        ->where('entry_type', 'commission')
                        ->where('gross_amount_cents', '>', 0)
                        ->whereIn('status', ['pending', 'approved', 'paid'])
                        ->where(function ($billingReasonGate) use ($allowLegacyNullMeta, $billingReasonExpression): void {
                            if ($allowLegacyNullMeta) {
                                $billingReasonGate->whereNull('meta')->orWhereRaw(
                                    "{$billingReasonExpression} IN (?, ?)",
                                    ['subscription_create', 'subscription_cycle']
                                );

                                return;
                            }

                            $billingReasonGate->whereRaw(
                                "{$billingReasonExpression} IN (?, ?)",
                                ['subscription_create', 'subscription_cycle']
                            );
                        })
                        ->groupBy('referred_user_id')
                        ->havingRaw(
                            'COUNT(DISTINCT source_invoice_id) >= ?',
                            [self::MIN_PAYOUT_PAYMENT_COUNT]
                        );
                });
        });
    }

    private function paymentGateBillingReasonExpression(): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'sqlite' => "LOWER(json_extract(meta, '$.invoice_billing_reason'))",
            'pgsql' => "LOWER(meta->>'invoice_billing_reason')",
            default => "LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.invoice_billing_reason')))",
        };
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function logPartnerAudit(
        ?int $partnerUserId,
        string $action,
        array $metadata = [],
        string $actor = 'system',
        string $status = 'success',
        ?string $source = null
    ): void
    {
        if (!$this->auditTableReady()) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();
        $encodedMetadata = null;
        if ($metadata !== []) {
            $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
            if (!is_string($encodedMetadata)) {
                $encodedMetadata = null;
            }
        }

        $actorUserId = $this->auditContextInt('audit.actor_admin_user_id');
        $requestId = $this->auditContextString('audit.request_id', 64)
            ?: $this->requestHeader($request, 'X-Request-Id', 64)
            ?: $this->requestHeader($request, 'X-Correlation-Id', 64);
        $ipAddress = $this->requestIp($request);
        $userAgent = $request ? Str::limit((string) $request->userAgent(), 255, '') : null;
        $actorUsername = $this->auditContextString('audit.actor_admin_username', 64);
        if ($actorUsername && str_starts_with($actor, 'internal_admin')) {
            $actor = 'internal_admin:' . $actorUsername;
        }

        $payload = [
            'partner_user_id' => $partnerUserId,
            'actor_user_id' => $actorUserId,
            'action' => Str::limit($action, 80, ''),
            'actor' => Str::limit($actor, 64, ''),
            'status' => Str::limit($status, 32, '') ?: 'success',
            'source' => Str::limit(
                $source
                    ?: $this->auditContextString('audit.source', 64)
                    ?: (str_starts_with($actor, 'internal_admin') ? 'internal_admin' : 'partners'),
                64,
                ''
            ) ?: 'partners',
            'request_id' => $requestId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'metadata' => $encodedMetadata,
            'created_at' => now(),
        ];

        if (!$this->supportsPartnerAuditContextColumns()) {
            unset(
                $payload['actor_user_id'],
                $payload['status'],
                $payload['source'],
                $payload['request_id'],
                $payload['ip_address'],
                $payload['user_agent'],
            );
        }

        DB::table('partner_audit_log')->insert($payload);
    }

    private function auditContextInt(string $key): ?int
    {
        $value = config($key);
        if (!is_numeric($value)) {
            return null;
        }

        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }

    private function auditContextString(string $key, int $max): ?string
    {
        $value = config($key);
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, $max, '');
    }

    private function requestHeader(?Request $request, string $header, int $max): ?string
    {
        if (!$request) {
            return null;
        }

        $value = trim((string) $request->headers->get($header, ''));
        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }

    private function requestIp(?Request $request): ?string
    {
        if (!$request) {
            return null;
        }

        $cloudflare = trim((string) $request->headers->get('CF-Connecting-IP', ''));
        if ($cloudflare !== '') {
            return Str::limit($cloudflare, 64, '');
        }

        $forwarded = trim((string) $request->headers->get('X-Forwarded-For', ''));
        if ($forwarded !== '') {
            $first = trim((string) explode(',', $forwarded)[0]);
            if ($first !== '') {
                return Str::limit($first, 64, '');
            }
        }

        $fallback = trim((string) $request->ip());
        return $fallback !== '' ? Str::limit($fallback, 64, '') : null;
    }

    private function auditTableReady(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_audit_log');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('partner_audit_log');

        return $ready;
    }

    private function supportsPartnerAuditContextColumns(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_audit_log')
                && Schema::hasColumn('partner_audit_log', 'actor_user_id')
                && Schema::hasColumn('partner_audit_log', 'status')
                && Schema::hasColumn('partner_audit_log', 'source')
                && Schema::hasColumn('partner_audit_log', 'request_id')
                && Schema::hasColumn('partner_audit_log', 'ip_address')
                && Schema::hasColumn('partner_audit_log', 'user_agent');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasColumn('partner_audit_log', 'actor_user_id')
            && Schema::hasColumn('partner_audit_log', 'status')
            && Schema::hasColumn('partner_audit_log', 'source')
            && Schema::hasColumn('partner_audit_log', 'request_id')
            && Schema::hasColumn('partner_audit_log', 'ip_address')
            && Schema::hasColumn('partner_audit_log', 'user_agent');

        return $ready;
    }

    private function supportsPartnerAccountConnectColumns(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_accounts')
                && Schema::hasColumn('partner_accounts', 'onboarding_started_at')
                && Schema::hasColumn('partner_accounts', 'activated_at')
                && Schema::hasColumn('partner_accounts', 'stripe_requirements');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasColumn('partner_accounts', 'onboarding_started_at')
            && Schema::hasColumn('partner_accounts', 'activated_at')
            && Schema::hasColumn('partner_accounts', 'stripe_requirements');

        return $ready;
    }

    private function supportsTransferGroupColumn(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_payout_batches')
                && Schema::hasColumn('partner_payout_batches', 'transfer_group');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasColumn('partner_payout_batches', 'transfer_group');

        return $ready;
    }

    private function supportsSettledAtColumn(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_payout_batches')
                && Schema::hasColumn('partner_payout_batches', 'settled_at');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasColumn('partner_payout_batches', 'settled_at');

        return $ready;
    }

    private function resolveTenantOwnerForUserId(int $resourceUserId): int
    {
        if ($resourceUserId <= 0) {
            return 0;
        }

        try {
            $resolved = app(TenantResolver::class)->ownerIdForUserId($resourceUserId);
            return is_int($resolved) && $resolved > 0 ? $resolved : $resourceUserId;
        } catch (\Throwable) {
            return $resourceUserId;
        }
    }

    private function schemaReady(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('partner_accounts')
                && Schema::hasTable('partner_invite_codes')
                && Schema::hasTable('partner_attributions')
                && Schema::hasTable('partner_commission_ledger')
                && Schema::hasTable('partner_payout_batches');
        }

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('partner_accounts')
            && Schema::hasTable('partner_invite_codes')
            && Schema::hasTable('partner_attributions')
            && Schema::hasTable('partner_commission_ledger')
            && Schema::hasTable('partner_payout_batches');

        return $ready;
    }
}
