<?php

namespace Modules\AnalyticsPremium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AnalyticsPremium\Services\AnalyticsPremiumService;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;
use App\Models\User;

class PremiumAnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsPremiumService $service,
        private SubscriptionManager $subscriptionManager,
        private TierResolver $tierResolver,
    )
    {
    }

    protected function tier(User $user): ?\Modules\Tiers\Models\Tier
    {
        return $this->subscriptionManager->getUserTier($user);
    }

    protected function ensureFeature(User $user, string $featureKey, string $message = 'Analytics not enabled for this tier'): void
    {
        if (!$this->subscriptionManager->featureEnabled($user, $featureKey)) {
            abort(403, $message);
        }
    }

    public function getDeviceStats(Request $request)
    {
        $user = $request->user();
        $tier = $this->tier($user);
        if (!$this->subscriptionManager->featureEnabled($user, 'analytics.enabled')) {
            abort(403, 'Analytics not enabled for this tier');
        }
        if (
            !$this->tierResolver->featureEnabled($tier, 'analytics.browser')
            && !$this->tierResolver->featureEnabled($tier, 'analytics.os')
        ) {
            abort(403, 'Device analytics not enabled for this tier');
        }
        return response()->json([
            'devices' => $this->service->getDeviceBreakdown($user->id),
            'browsers' => $this->service->getBrowserBreakdown($user->id),
            'os' => $this->service->getOSBreakdown($user->id),
        ]);
    }

    public function getCountryStats(Request $request)
    {
        $user = $request->user();
        $this->ensureFeature($user, 'analytics.geo', 'Geo analytics not enabled for this tier');
        return response()->json($this->service->getCountryBreakdown($user->id));
    }

    public function getReferrerStats(Request $request)
    {
        $user = $request->user();
        $this->ensureFeature($user, 'analytics.referrer', 'Referrer analytics not enabled for this tier');
        return response()->json($this->service->getTrafficSources($user->id));
    }

    public function getTimelineStats(Request $request)
    {
        $user = $request->user();
        $this->ensureFeature($user, 'analytics.time_series', 'Timeline analytics not enabled for this tier');
        $range = $request->query('range', '1m');
        $window = match ($range) {
            '1d' => ['bucket' => 'hour', 'days' => 1],
            '1w' => ['bucket' => 'day', 'days' => 7],
            '6m' => ['bucket' => 'week', 'days' => 180],
            '1y' => ['bucket' => 'month', 'days' => 365],
            default => ['bucket' => 'day', 'days' => 30],
        };
        return response()->json($this->service->getTimelineStats($user->id, $window['bucket'], $window['days']));
    }

    public function getLinksStats(Request $request)
    {
        $user = $request->user();
        $this->ensureFeature($user, 'analytics.top_links', 'Link analytics not enabled for this tier');
        return response()->json($this->service->getLinkClickBreakdown($user->id));
    }
}
