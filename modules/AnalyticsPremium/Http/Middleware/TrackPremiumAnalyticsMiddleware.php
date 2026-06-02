<?php

namespace Modules\AnalyticsPremium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AnalyticsPremium\Services\AnalyticsPremiumService;
use Modules\Tiers\Services\SubscriptionManager;
use App\Models\User;
use App\Models\Link;

class TrackPremiumAnalyticsMiddleware
{
    public function __construct(private AnalyticsPremiumService $service, private SubscriptionManager $subscriptionManager)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (config('analytics.privacy_mode', true)) {
            return $next($request);
        }

        $response = $next($request);

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return $response;
        }

        $user = $this->resolveUserFromRoute($request, $routeName);
        if (!$user) {
            return $response;
        }

        if (!$this->subscriptionManager->featureEnabled($user, 'analytics.enabled')) {
            return $response;
        }

        if ($routeName === 'littlelink' && $this->isEmbeddedFrameRequest($request)) {
            return $response;
        }

        $uaData = $this->service->enrichEventWithUA($request);
        $geoData = $this->service->enrichEventWithGeoIP($request);

        $data = array_merge([
            'user_id' => $user->id,
            'page_id' => null,
            'link_id' => null,
            'referrer' => $request->headers->get('referer'),
            'base_event_id' => null,
        ], $uaData, [
            'operating_system' => $uaData['operating_system'],
        ], $geoData);

        if ($routeName === 'littlelink') {
            $data['page_id'] = $user->id;
        }

        if ($routeName === 'clickNumber') {
            $linkId = $request->route('id');
            if ($linkId) {
                $data['link_id'] = (int) $linkId;
            }
        }

        $this->service->storePremiumEvent($data);

        return $response;
    }

    protected function isEmbeddedFrameRequest(Request $request): bool
    {
        $destination = strtolower(trim((string) $request->headers->get('Sec-Fetch-Dest', '')));

        return in_array($destination, ['iframe', 'frame'], true);
    }

    protected function resolveUserFromRoute(Request $request, string $routeName): ?User
    {
        if ($routeName === 'littlelink' || $routeName === 'theme') {
            $slug = $request->route('littlelink');
            if (!$slug) {
                return null;
            }
            return User::where('littlelink_name', $slug)->first();
        }

        if ($routeName === 'clickNumber' || $routeName === 'redirectInfo') {
            $linkId = $request->route('id');
            if ($linkId) {
                $link = Link::find($linkId);
                if ($link) {
                    return User::find($link->user_id);
                }
            }
        }

        return null;
    }
}
