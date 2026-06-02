<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AnalyticsDispatcher
{
    public function __construct(
        private readonly AnalyticsClient $client,
        private readonly AnalyticsTierResolver $tierResolver,
        private readonly AnalyticsRequestDataExtractor $extractor,
    ) {
    }

    public function recordPageView(Request $request, User $owner, array $metadata = []): bool
    {
        if ($this->isEmbeddedFrameRequest($request)) {
            return false;
        }

        return $this->recordEvent('view', $request, $owner, $metadata);
    }

    public function recordLinkClick(Request $request, User $owner, int $linkId, array $metadata = []): bool
    {
        return $this->recordEvent('click', $request, $owner, array_merge($metadata, [
            'link_id' => $linkId,
        ]));
    }

    /**
     * Records a generic analytics event after tier gating.
     */
    public function recordEvent(string $eventType, Request $request, User $owner, array $metadata = []): bool
    {
        $level = $this->tierResolver->tierLevel($owner);
        if (!$this->tierResolver->allowsEvent($level, $eventType)) {
            return false;
        }

        $context = $this->extractor->buildContext($request);
        $linkId = $metadata['link_id'] ?? null;
        $utm = $metadata['utm'] ?? $context['utm'] ?? null;
        // Remove link_id / utm from metadata to avoid duplication
        unset($metadata['link_id'], $metadata['utm'], $context['utm']);

        $payload = [
            'event_type' => $eventType,
            'site_id' => $owner->id,
            'actor_user_id' => (int) $owner->id,
            'site_slug' => $owner->{config('analytics.owner_slug_column', 'littlelink_name')} ?? null,
            'occurred_at' => now()->toIso8601String(),
            'tier_level' => $level,
            'link_id' => $linkId,
            'utm' => $utm,
            'metadata' => array_filter(array_merge($context, $metadata), fn ($value) => $value !== null && $value !== ''),
        ];

        $payload = $this->filterPayloadForTier($payload, $level);

        $sent = $this->client->sendEvent($payload);
        if (!$sent) {
            Log::debug('Analytics event was not forwarded', [
                'type' => $eventType,
                'tier' => $level,
                'site_id' => $owner->id,
            ]);
        }

        return $sent;
    }

    protected function isEmbeddedFrameRequest(Request $request): bool
    {
        $destination = strtolower(trim((string) $request->headers->get('Sec-Fetch-Dest', '')));

        return in_array($destination, ['iframe', 'frame'], true);
    }

    /**
     * Strip advanced fields for lower tiers so the payload matches what their
     * plan allows without leaking additional attributes.
     */
    protected function filterPayloadForTier(array $payload, string $level): array
    {
        $features = $this->tierResolver->featuresFor($level);
        $metadata = $payload['metadata'] ?? [];

        if (!in_array('utm', $features, true)) {
            $metadata = Arr::except($metadata, ['utm']);
            $payload['utm'] = null;
        }

        if (!in_array('heatmaps', $features, true)) {
            $metadata = Arr::except($metadata, ['viewport', 'click_coordinates']);
        }

        $payload['metadata'] = $metadata;

        return $payload;
    }
}
