<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyticsClient
{
    public function __construct(
        private readonly string $apiBase = '',
        private readonly ?string $apiKey = null,
        private readonly ?float $timeout = null,
    ) {
    }

    /**
     * Send an analytics event to the internal microservice.
     *
     * The microservice contract is intentionally minimal and replaceable.
     */
    public function sendEvent(array $payload): bool
    {
        $base = $this->apiBase ?: config('analytics.api_base');
        if (!$base) {
            return false;
        }

        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout($this->timeout())
                ->acceptJson()
                ->post(rtrim($base, '/') . '/api/event', $payload);

            if ($response->failed()) {
                Log::warning('Analytics microservice rejected event', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            // Ingest endpoint acknowledges async processing with 202 Accepted.
            return $response->successful();
        } catch (Throwable $e) {
            Log::debug('Analytics microservice unreachable', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch aggregated analytics data for display.
     *
     * @return array<string,mixed>
     */
    public function fetchAggregates(int|string $siteId, string $range, array $params = []): array
    {
        $base = $this->apiBase ?: config('analytics.api_base');
        if (!$base) {
            return [];
        }

        try {
            $query = array_merge([
                'site_id' => $siteId,
                'range' => $range,
            ], $params);

            $response = Http::withHeaders($this->buildHeaders())
                ->timeout($this->timeout())
                ->acceptJson()
                ->get(rtrim($base, '/') . '/api/aggregates', $query);

            if ($response->failed()) {
                Log::warning('Analytics microservice returned non-success aggregate response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (Throwable $e) {
            Log::debug('Analytics aggregate request failed', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Headers shared across analytics microservice calls.
     */
    private function buildHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Wayvio-Analytics-Bridge',
        ];

        $key = $this->apiKey ?: config('analytics.api_key');
        if ($key) {
            $headers['X-API-KEY'] = $key;
        }

        return $headers;
    }

    private function timeout(): float
    {
        if ($this->timeout !== null) {
            return $this->timeout;
        }

        return (float) config('analytics.http_timeout', 2.5);
    }
}
