<?php

namespace Modules\Partners\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Partners\Models\PartnerAccount;

class PartnerDashboardService
{
    private const MIN_SUCCESSFUL_PAYMENTS_FOR_PAYOUT = 2;
    private const DEFAULT_COMMISSION_PENDING_DAYS = 121;

    public function __construct(
        private readonly PartnerManager $partnerManager,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboardPayload(User $user): array
    {
        $partner = $this->partnerManager->validateDashboardPartnerAccess($user);
        $partnerId = (int) $partner->user_id;
        $summary = $this->summary($partnerId);

        return [
            'partner' => [
                'user_id' => $partnerId,
                'status' => $partner->status,
                'default_commission_rate_bps' => (int) $partner->default_commission_rate_bps,
                'payout_minimum_cents' => (int) $partner->payout_minimum_cents,
                'primary_referral_url' => $this->primaryReferralUrl($partner),
                'next_payout_date' => (string) ($summary['next_payout_date'] ?? $this->nextPayoutDate()),
                'onboarding_started_at' => optional($partner->onboarding_started_at)->toISOString(),
                'activated_at' => optional($partner->activated_at)->toISOString(),
                'legal_country' => $partner->legal_country ? strtoupper((string) $partner->legal_country) : null,
                'legal_country_locked' => !empty($partner->stripe_connect_account_id),
                'connect_status' => $this->connectStatus($partner),
                'connect_ready' => $partner->activated_at !== null && $partner->status === 'active',
                'connect_requirements' => is_array($partner->stripe_requirements) ? $partner->stripe_requirements : null,
            ],
            'invite_codes' => $this->partnerManager->inviteCodesForPartner($partnerId)
                ->map(fn ($code) => [
                    'id' => (int) $code->id,
                    'code' => $code->code,
                    'uses_count' => (int) $code->uses_count,
                    'max_uses' => $code->max_uses !== null ? (int) $code->max_uses : null,
                    'expires_at' => optional($code->expires_at)->toISOString(),
                    'referral_url' => url('/ref/' . $code->code),
                ])
                ->values()
                ->all(),
            'summary' => $summary,
            'referrals' => $this->referrals($partnerId),
            'ledger' => $this->ledger($partnerId),
            'payouts' => $this->payouts($partnerId),
            'timeseries' => $this->timeseries($partnerId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(int $partnerUserId): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $totals = DB::table('partner_attributions')
            ->where('partner_user_id', $partnerUserId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN ends_at >= ? THEN 1 ELSE 0 END) AS active', [$now])
            ->selectRaw('SUM(CASE WHEN attributed_at >= ? THEN 1 ELSE 0 END) AS new_this_month', [$monthStart])
            ->first();

        $ledger = DB::table('partner_commission_ledger')
            ->where('partner_user_id', $partnerUserId)
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_amount_cents ELSE 0 END), 0) AS pending_cents', ['pending'])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND payout_batch_id IS NULL THEN commission_amount_cents ELSE 0 END), 0) AS approved_cents', ['approved'])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_amount_cents ELSE 0 END), 0) AS paid_cents', ['paid'])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? AND entry_type = ? THEN gross_amount_cents ELSE 0 END), 0) AS month_gross_cents', [$monthStart, 'commission'])
            ->first();

        $allowLegacyNullMeta = (bool) config('partners.payment_gate.allow_legacy_null_meta', false);
        $billingReasonExpression = $this->paymentGateBillingReasonExpression();

        $paymentCounts = DB::table('partner_commission_ledger')
            ->selectRaw('referred_user_id, COUNT(DISTINCT source_invoice_id) AS payment_count')
            ->where('partner_user_id', $partnerUserId)
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
            ->groupBy('referred_user_id');

        $pendingBreakdown = DB::table('partner_commission_ledger as pcl')
            ->leftJoinSub($paymentCounts, 'pc', function ($join): void {
                $join->on('pc.referred_user_id', '=', 'pcl.referred_user_id');
            })
            ->where('pcl.partner_user_id', $partnerUserId)
            ->where('pcl.status', 'pending')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pcl.available_at IS NOT NULL AND pcl.available_at > ? THEN pcl.commission_amount_cents ELSE 0 END), 0) AS pending_hold_cents',
                [$now]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN (pcl.available_at IS NULL OR pcl.available_at <= ?) AND COALESCE(pc.payment_count, 0) < ? AND pcl.commission_amount_cents > 0 THEN pcl.commission_amount_cents ELSE 0 END), 0) AS pending_second_payment_cents',
                [$now, self::MIN_SUCCESSFUL_PAYMENTS_FOR_PAYOUT]
            )
            ->selectRaw(
                'COALESCE(COUNT(DISTINCT CASE WHEN (pcl.available_at IS NULL OR pcl.available_at <= ?) AND COALESCE(pc.payment_count, 0) < ? AND pcl.commission_amount_cents > 0 THEN pcl.referred_user_id ELSE NULL END), 0) AS pending_second_payment_users',
                [$now, self::MIN_SUCCESSFUL_PAYMENTS_FOR_PAYOUT]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN (pcl.available_at IS NULL OR pcl.available_at <= ?) AND (COALESCE(pc.payment_count, 0) >= ? OR pcl.commission_amount_cents <= 0) THEN pcl.commission_amount_cents ELSE 0 END), 0) AS pending_ready_cents',
                [$now, self::MIN_SUCCESSFUL_PAYMENTS_FOR_PAYOUT]
            )
            ->selectRaw(
                'MIN(CASE WHEN pcl.available_at IS NOT NULL AND pcl.available_at > ? THEN pcl.available_at ELSE NULL END) AS next_hold_release_at',
                [$now]
            )
            ->first();

        $nextPayoutDate = $this->nextPayoutDate($pendingBreakdown?->next_hold_release_at);

        return [
            'referred_total' => (int) ($totals?->total ?? 0),
            'referred_active' => (int) ($totals?->active ?? 0),
            'new_this_month' => (int) ($totals?->new_this_month ?? 0),
            'month_gross_cents' => (int) ($ledger?->month_gross_cents ?? 0),
            'pending_cents' => (int) ($ledger?->pending_cents ?? 0),
            'pending_hold_cents' => (int) ($pendingBreakdown?->pending_hold_cents ?? 0),
            'pending_second_payment_cents' => (int) ($pendingBreakdown?->pending_second_payment_cents ?? 0),
            'pending_second_payment_users' => (int) ($pendingBreakdown?->pending_second_payment_users ?? 0),
            'pending_ready_cents' => (int) ($pendingBreakdown?->pending_ready_cents ?? 0),
            'next_hold_release_at' => $pendingBreakdown?->next_hold_release_at
                ? Carbon::parse($pendingBreakdown->next_hold_release_at)->toISOString()
                : null,
            'approved_cents' => (int) ($ledger?->approved_cents ?? 0),
            'paid_cents' => (int) ($ledger?->paid_cents ?? 0),
            'next_payout_cents' => (int) ($ledger?->approved_cents ?? 0),
            'next_payout_date' => $nextPayoutDate,
            'commission_pending_days' => max(1, (int) env('PARTNER_COMMISSION_PENDING_DAYS', self::DEFAULT_COMMISSION_PENDING_DAYS)),
            'minimum_successful_payments_for_payout' => self::MIN_SUCCESSFUL_PAYMENTS_FOR_PAYOUT,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function referrals(int $partnerUserId, int $limit = 25): array
    {
        $rows = DB::table('partner_attributions as pa')
            ->leftJoin('user_subscriptions as us', 'us.user_id', '=', 'pa.referred_user_id')
            ->leftJoin('tiers as t', 't.id', '=', 'us.tier_id')
            ->leftJoin(DB::raw('(SELECT referred_user_id, MAX(id) AS max_id FROM partner_commission_ledger GROUP BY referred_user_id) last_rows'), function ($join): void {
                $join->on('last_rows.referred_user_id', '=', 'pa.referred_user_id');
            })
            ->leftJoin('partner_commission_ledger as pcl', 'pcl.id', '=', 'last_rows.max_id')
            ->where('pa.partner_user_id', $partnerUserId)
            ->orderByDesc('pa.attributed_at')
            ->limit(max(1, min(100, $limit)))
            ->get([
                'pa.referred_user_id',
                'pa.ends_at',
                'pa.attributed_at',
                'us.expires_at as subscription_expires_at',
                't.name as tier_name',
                'pcl.gross_amount_cents',
                'pcl.commission_amount_cents',
                'pcl.status as ledger_status',
                'pcl.created_at as ledger_created_at',
            ]);

        return $rows->map(function ($row) {
            $now = now();
            $status = 'Free';
            if (!empty($row->tier_name) && strtolower((string) $row->tier_name) !== 'free') {
                $status = !$row->subscription_expires_at || Carbon::parse($row->subscription_expires_at)->greaterThanOrEqualTo($now)
                    ? 'Active'
                    : 'Expired';
            }

            return [
                'user_label' => 'U' . (int) $row->referred_user_id,
                'status' => $status,
                'plan_name' => $row->tier_name ?: 'Free',
                'amount_cents' => $row->gross_amount_cents !== null ? (int) $row->gross_amount_cents : null,
                'commission_cents' => $row->commission_amount_cents !== null ? (int) $row->commission_amount_cents : null,
                'commission_status' => $row->ledger_status,
                'last_payment_at' => $row->ledger_created_at ? Carbon::parse($row->ledger_created_at)->toISOString() : null,
                'attributed_at' => Carbon::parse($row->attributed_at)->toISOString(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function ledger(int $partnerUserId, int $limit = 50): array
    {
        return DB::table('partner_commission_ledger')
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'user_label' => 'U' . (int) $row->referred_user_id,
                'entry_type' => $row->entry_type,
                'gross_amount_cents' => (int) $row->gross_amount_cents,
                'commission_amount_cents' => (int) $row->commission_amount_cents,
                'currency' => $row->currency,
                'status' => $row->status,
                'available_at' => $row->available_at ? Carbon::parse($row->available_at)->toISOString() : null,
                'created_at' => Carbon::parse($row->created_at)->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function payouts(int $partnerUserId, int $limit = 25): array
    {
        return DB::table('partner_payout_batches')
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'currency' => $row->currency,
                'net_amount_cents' => (int) $row->net_amount_cents,
                'reference' => $row->stripe_transfer_id,
                'status' => $row->status,
                'paid_at' => $row->paid_at ? Carbon::parse($row->paid_at)->toISOString() : null,
                'created_at' => Carbon::parse($row->created_at)->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function timeseries(int $partnerUserId, int $days = 30): array
    {
        $start = now()->copy()->subDays(max(1, min(180, $days)) - 1)->startOfDay();
        $rows = DB::table('partner_commission_ledger')
            ->where('partner_user_id', $partnerUserId)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS bucket_date')
            ->selectRaw('COALESCE(SUM(commission_amount_cents), 0) AS net_cents')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('bucket_date')
            ->get();

        $byDate = $rows->keyBy('bucket_date');
        $series = [];
        $cursor = Carbon::parse($start);
        $end = now()->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'net_cents' => (int) ($byDate[$key]->net_cents ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
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

    private function primaryReferralUrl(PartnerAccount $partner): ?string
    {
        $code = $this->partnerManager
            ->inviteCodesForPartner((int) $partner->user_id)
            ->first();

        if (!$code) {
            return null;
        }

        return url('/ref/' . $code->code);
    }

    private function nextPayoutDate(mixed $nextHoldReleaseAt = null): string
    {
        if ($nextHoldReleaseAt) {
            try {
                return Carbon::parse($nextHoldReleaseAt)->toDateString();
            } catch (\Throwable) {
                // Fallback to recurring review date below.
            }
        }

        return now()->copy()->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    private function connectStatus(PartnerAccount $partner): string
    {
        if (!$partner->stripe_connect_account_id) {
            return 'not_started';
        }

        if ($partner->status === 'restricted') {
            return 'restricted';
        }

        if ($partner->activated_at !== null) {
            return 'ready';
        }

        return 'pending';
    }
}
