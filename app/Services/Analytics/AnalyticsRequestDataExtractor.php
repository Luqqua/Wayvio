<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

namespace App\Services\Analytics;

use Illuminate\Http\Request;

class AnalyticsRequestDataExtractor
{
    /**
     * Extracts server-side context for analytics payloads.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Request $request): array
    {
        if (config('analytics.privacy_mode', true)) {
            return [
                'ip' => $this->clientIp($request),
                'referrer' => $request->headers->get('referer'),
                'utm' => $this->utmParams($request),
            ];
        }

        return [
            'ip' => $this->clientIp($request),
            'referrer' => $request->headers->get('referer'),
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'path' => '/' . ltrim($request->path(), '/'),
            'url' => $request->fullUrl(),
            'utm' => $this->utmParams($request),
        ];
    }

    protected function clientIp(Request $request): ?string
    {
        if ($request->headers->has('CF-Connecting-IP')) {
            return $request->headers->get('CF-Connecting-IP');
        }

        $forwarded = $request->headers->get('X-Forwarded-For');
        if ($forwarded) {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return $request->ip();
    }

    /**
     * Extract UTM query parameters only (no cookies required).
     *
     * @return array<string,string>
     */
    protected function utmParams(Request $request): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content'];
        $utm = [];

        foreach ($keys as $key) {
            $value = $request->query($key);
            if ($value) {
                $utm[$key] = $value;
            }
        }

        return $utm;
    }
}
