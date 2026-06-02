<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingAnalyticsDispatcher
{
    public function __construct(
        private readonly AnalyticsClient $client,
        private readonly AnalyticsRequestDataExtractor $extractor,
    ) {
    }

    /**
     * Records a landing page view into the internal analytics rollups.
     */
    public function recordLandingView(Request $request, array $metadata = []): bool
    {
        $enabled = (bool) config('analytics.landing_enabled', false);
        $siteId = (int) config('analytics.landing_site_id', 0);
        $actorUserId = (int) config('analytics.landing_actor_user_id', $siteId);
        $tierLevel = (string) config('analytics.landing_tier_level', 'business');

        if (!$enabled || $siteId <= 0 || $actorUserId <= 0) {
            return false;
        }

        $context = $this->extractor->buildContext($request);
        $utm = $context['utm'] ?? null;
        unset($context['utm']);

        $payload = [
            'event_type' => 'view',
            'site_id' => $siteId,
            'actor_user_id' => $actorUserId,
            'site_slug' => 'landing-page',
            'occurred_at' => now()->toIso8601String(),
            'tier_level' => $tierLevel,
            'utm' => is_array($utm) ? $utm : null,
            'metadata' => array_filter(array_merge($context, $metadata), static fn ($value) => $value !== null && $value !== ''),
        ];

        $sent = $this->client->sendEvent($payload);
        if (!$sent) {
            Log::debug('Landing analytics event was not forwarded', [
                'site_id' => $siteId,
            ]);
        }

        return $sent;
    }
}
