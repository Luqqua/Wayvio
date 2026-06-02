<?php

namespace Modules\AnalyticsPremium\Services;

use Modules\AnalyticsPremium\Models\AnalyticsEventExtended;
use App\Models\User;
use App\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AnalyticsPremiumService
{
    public function enrichEventWithUA(Request $request): array
    {
        $ua = $request->userAgent() ?? '';
        return [
            'device_type' => $this->detectDeviceType($ua),
            'browser' => $this->detectBrowser($ua),
            'operating_system' => $this->detectOS($ua),
        ];
    }

    public function enrichEventWithGeoIP(Request $request): array
    {
        try {
            $record = geoip()->getLocation($request->ip());
            return [
                'country' => $record->iso_code ?? null,
            ];
        } catch (\Throwable $e) {
            return ['country' => null];
        }
    }

    public function storePremiumEvent(array $data): AnalyticsEventExtended
    {
        if (Schema::hasColumn('analytics_events_extended', 'tenant_owner_user_id')) {
            $tenantOwnerUserId = isset($data['tenant_owner_user_id']) ? (int) $data['tenant_owner_user_id'] : 0;
            if ($tenantOwnerUserId <= 0) {
                $tenantOwnerUserId = $this->resolveTenantOwnerUserIdForEvent(
                    isset($data['user_id']) ? (int) $data['user_id'] : 0
                );
            }

            if ($tenantOwnerUserId > 0) {
                $data['tenant_owner_user_id'] = $tenantOwnerUserId;
            }
        }

        return AnalyticsEventExtended::create($data);
    }

    public function getPremiumStats(int $userId): array
    {
        return [
            'devices' => $this->getDeviceBreakdown($userId),
            'countries' => $this->getCountryBreakdown($userId),
            'browsers' => $this->getBrowserBreakdown($userId),
            'referrers' => $this->getTrafficSources($userId),
        ];
    }

    public function getTrafficSources(int $userId)
    {
        return $this->scopedEventsQuery($userId)
            ->select('referrer', DB::raw('count(*) as total'))
            ->whereNotNull('referrer')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->get();
    }

    public function getDeviceBreakdown(int $userId)
    {
        return $this->scopedEventsQuery($userId)
            ->select('device_type', DB::raw('count(*) as total'))
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get();
    }

    public function getCountryBreakdown(int $userId)
    {
        return $this->scopedEventsQuery($userId)
            ->select('country', DB::raw('count(*) as total'))
            ->groupBy('country')
            ->orderByDesc('total')
            ->get();
    }

    public function getBrowserBreakdown(int $userId)
    {
        return $this->scopedEventsQuery($userId)
            ->select('browser', DB::raw('count(*) as total'))
            ->groupBy('browser')
            ->orderByDesc('total')
            ->get();
    }

    public function getTimelineStats(int $userId, string $bucket = 'day', ?int $days = null)
    {
        $format = match ($bucket) {
            'hour' => '%Y-%m-%d %H:00:00',
            'week' => '%x-%v',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $query = $this->scopedEventsQuery($userId);

        if ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        return $query
            ->select(DB::raw("DATE_FORMAT(created_at, '{$format}') as bucket"), DB::raw('count(*) as total'))
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();
    }

    protected function detectDeviceType(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }
        return 'desktop';
    }

    protected function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);
        return match (true) {
            str_contains($ua, 'edg') => 'edge',
            str_contains($ua, 'chrome') => 'chrome',
            str_contains($ua, 'safari') && !str_contains($ua, 'chrome') => 'safari',
            str_contains($ua, 'firefox') => 'firefox',
            str_contains($ua, 'opera') || str_contains($ua, 'opr') => 'opera',
            default => 'other',
        };
    }

    protected function detectOS(string $ua): string
    {
        $ua = strtolower($ua);
        return match (true) {
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macos',
            str_contains($ua, 'android') => 'android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'ios',
            str_contains($ua, 'linux') => 'linux',
            default => 'other',
        };
    }

    public function getOSBreakdown(int $userId)
    {
        return $this->scopedEventsQuery($userId)
            ->select('operating_system', DB::raw('count(*) as total'))
            ->groupBy('operating_system')
            ->orderByDesc('total')
            ->get();
    }

    public function getLinkClickBreakdown(int $userId)
    {
        $rows = $this->scopedEventsQuery($userId)
            ->whereNotNull('link_id')
            ->select('link_id', DB::raw('count(*) as clicks'))
            ->groupBy('link_id')
            ->orderByDesc('clicks')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $linkIds = $rows->pluck('link_id')->filter()->map(fn ($id) => (int) $id)->all();
        $links = Link::withDisabled()
            ->whereIn('id', $linkIds)
            ->get(['id', 'title', 'link'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($links) {
            $linkId = (int) $row->link_id;
            $link = $links->get($linkId);
            $name = $link?->title ?: $link?->link ?: ('Link #' . $linkId);

            return [
                'id' => $linkId,
                'name' => $name,
                'clicks' => (int) $row->clicks,
            ];
        })->values();
    }

    /**
     * Tier-aware stats aggregation.
     *
     * @param User $user
     * @param string $tierLevel free|pro|business
     */
    public function getStats(User $user, string $tierLevel = 'free'): array
    {
        $littlelink = $user->littlelink_name;

        $totalViews = visits('App\Models\User', $littlelink)->count();
        $totalClicks = $this->scopedEventsQuery((int) $user->id)
            ->whereNotNull('link_id')
            ->count();

        $base = [
            'tier' => $tierLevel,
            'views_total' => $totalViews,
            'clicks_total' => $totalClicks,
        ];

        if ($tierLevel === 'free') {
            return $base;
        }

        $base['link_clicks'] = $this->getLinkClickBreakdown($user->id);

        $base['devices'] = $this->getDeviceBreakdown($user->id);
        $base['browsers'] = $this->getBrowserBreakdown($user->id);
        $base['os'] = $this->getOSBreakdown($user->id);

        if ($tierLevel === 'business') {
            $base['countries'] = $this->getCountryBreakdown($user->id);
            $base['referrers'] = $this->getTrafficSources($user->id);
            $base['timeline'] = $this->getTimelineStats($user->id, 'day');
            $base['heatmap'] = []; // placeholder
        }

        return $base;
    }

    private function scopedEventsQuery(int $userId)
    {
        $query = AnalyticsEventExtended::where('user_id', $userId);

        if (Schema::hasColumn('analytics_events_extended', 'tenant_owner_user_id')) {
            $tenantOwnerId = function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null;
            if (is_int($tenantOwnerId) && $tenantOwnerId > 0) {
                $query->where('tenant_owner_user_id', $tenantOwnerId);
            }
        }

        return $query;
    }

    private function resolveTenantOwnerUserIdForEvent(int $resourceUserId): int
    {
        $tenantOwnerId = function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null;
        if (is_int($tenantOwnerId) && $tenantOwnerId > 0) {
            return $tenantOwnerId;
        }

        if ($resourceUserId <= 0) {
            return 0;
        }

        if (
            !Schema::hasTable('agency_hubs')
            || !Schema::hasColumn('agency_hubs', 'agency_user_id')
            || !Schema::hasColumn('agency_hubs', 'managed_user_id')
        ) {
            return $resourceUserId;
        }

        $query = DB::table('agency_hubs')
            ->where('managed_user_id', $resourceUserId)
            ->select('agency_user_id')
            ->distinct();

        if (Schema::hasColumn('agency_hubs', 'status')) {
            $query->where('status', 'active');
        }

        $ownerIds = $query
            ->pluck('agency_user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if (count($ownerIds) === 1) {
            return (int) ($ownerIds[0] ?? $resourceUserId);
        }

        if (count($ownerIds) > 1) {
            return 0;
        }

        return $resourceUserId;
    }
}
