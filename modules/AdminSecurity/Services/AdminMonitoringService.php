<?php

namespace Modules\AdminSecurity\Services;

use Modules\Tiers\Models\UserSubscription;
use Modules\CustomDomains\Models\UserCustomDomain;
use Modules\AnalyticsPremium\Models\AnalyticsEventExtended;
use Modules\Billing\Models\BillingRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminMonitoringService
{
    public function subscriptionOverview(): array
    {
        $active = UserSubscription::whereNotNull('expires_at')->count();
        $expiringSoon = UserSubscription::where('expires_at', '<=', Carbon::now()->addDays(7))->count();
        return [
            'active' => $active,
            'expiring_7d' => $expiringSoon,
        ];
    }

    public function analyticsOverview(): array
    {
        $today = AnalyticsEventExtended::whereDate('created_at', Carbon::today())->count();
        $week = AnalyticsEventExtended::where('created_at', '>=', Carbon::now()->subWeek())->count();
        return [
            'events_today' => $today,
            'events_week' => $week,
        ];
    }

    public function domainOverview(): array
    {
        return [
            'total' => UserCustomDomain::count(),
            'pending' => UserCustomDomain::where('status', 'pending')->count(),
            'verified' => UserCustomDomain::where('status', 'verified')->count(),
            'failed' => UserCustomDomain::where('status', 'failed')->count(),
        ];
    }

    public function billingOverview(): array
    {
        $count = BillingRecord::count();
        $last10 = BillingRecord::latest()->take(10)->get();
        $revenue = BillingRecord::sum('amount');
        return [
            'count' => $count,
            'revenue_cents' => $revenue,
            'recent' => $last10,
        ];
    }

    public function logsOverview(): array
    {
        $path = storage_path('logs/laravel.log');
        if (!file_exists($path)) {
            return ['entries' => []];
        }
        $lines = array_slice(file($path), -50);
        return ['entries' => $lines];
    }
}
