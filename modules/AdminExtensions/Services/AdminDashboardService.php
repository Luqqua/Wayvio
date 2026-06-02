<?php

namespace Modules\AdminExtensions\Services;

use Modules\AdminExtensions\Models\StripeWebhookLog;
use Modules\AdminExtensions\Models\SystemEvent;
use Modules\CustomDomains\Models\UserCustomDomain;
use Modules\Tiers\Models\UserSubscription;
use Modules\AnalyticsPremium\Models\AnalyticsEventExtended;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    public function users()
    {
        return User::query()
            ->withoutAgencyHubAccounts()
            ->leftJoin('partner_accounts', 'partner_accounts.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.role', 'users.created_at')
            ->selectRaw('partner_accounts.status as partner_status')
            ->selectRaw('partner_accounts.default_commission_rate_bps as partner_commission_rate_bps')
            ->latest('users.created_at')
            ->limit(50)
            ->get();
    }

    public function domains()
    {
        return UserCustomDomain::latest()->limit(50)->get();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function webhookLogs(array $filters = [])
    {
        $query = StripeWebhookLog::query()->latest();

        $eventId = isset($filters['event_id']) ? trim((string) $filters['event_id']) : '';
        if ($eventId !== '') {
            $query->where('event_id', $eventId);
        }

        $type = isset($filters['type']) ? trim((string) $filters['type']) : '';
        if ($type !== '') {
            $query->where('type', $type);
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $query->where('status', $status);
        }

        $userId = isset($filters['user_id']) && is_numeric($filters['user_id'])
            ? (int) $filters['user_id']
            : null;
        if ($userId) {
            $query->where(function ($inner) use ($userId) {
                $inner->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.object.metadata.user_id')) = ?",
                    [(string) $userId]
                )->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.object.client_reference_id')) = ?",
                    [(string) $userId]
                );
            });
        }

        $sessionId = isset($filters['session_id']) ? trim((string) $filters['session_id']) : '';
        if ($sessionId !== '') {
            $query->where(function ($inner) use ($sessionId) {
                $inner->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.object.id')) = ?",
                    [$sessionId]
                )->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.object.checkout_session')) = ?",
                    [$sessionId]
                );
            });
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? (int) $filters['limit']
            : 50;
        $limit = max(1, min(200, $limit));

        return $query->limit($limit)->get();
    }

    public function systemEvents()
    {
        return SystemEvent::latest('created_at')->limit(100)->get();
    }

    public function securityOverview(): array
    {
        return [
            'active_subscriptions' => UserSubscription::count(),
            'domains_pending' => UserCustomDomain::where('status', 'pending')->count(),
            'analytics_events_24h' => AnalyticsEventExtended::where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}
