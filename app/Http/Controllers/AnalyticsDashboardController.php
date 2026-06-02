<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\AnalyticsTierResolver;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsClient $client,
        private readonly AnalyticsTierResolver $tierResolver,
        private readonly AgencyHubContext $agencyContext,
    ) {
    }

    public function show(Request $request)
    {
        $owner = $request->user();
        $siteId = $this->agencyContext->editingUserId($owner, $request);
        $this->denyTenantAnalyticsContextMismatch($request, $siteId);
        Gate::forUser($owner)->authorize('tenant.access-analytics', $siteId);
        $siteUser = User::query()->select('id', 'littlelink_name')->find($siteId);

        $tierLevel = $this->tierResolver->tierLevel($owner);
        $rangeOptions = $this->rangeOptionsForTier($tierLevel);
        $range = $this->normalizeRange($request->query('range', '7d'), $rangeOptions);
        $features = $this->tierResolver->featuresFor($tierLevel);

        $aggregates = [];
        if (in_array('views', $features, true)) {
            $aggregates = $this->client->fetchAggregates($siteId, $range, [
                'tier_level' => $tierLevel,
                'actor_user_id' => (int) $owner->id,
            ]);
            $aggregates = $this->hydrateTopLinkTitles($aggregates, $siteId);
        }

        return view('analytics.dashboard', [
            'tierLevel' => $tierLevel,
            'tierLabel' => $this->tierResolver->displayName($tierLevel),
            'features' => $features,
            'range' => $range,
            'rangeOptions' => $rangeOptions,
            'aggregates' => $aggregates,
            'analyticsSiteSlug' => $siteUser?->littlelink_name,
        ]);
    }

    /**
     * @param array<int,string> $allowed
     */
    protected function normalizeRange(string $range, array $allowed): string
    {
        if (in_array($range, $allowed, true)) {
            return $range;
        }

        return $allowed[count($allowed) - 1] ?? '7d';
    }

    /**
     * @return array<int,string>
     */
    protected function rangeOptionsForTier(string $tierLevel): array
    {
        $ranges = config('analytics.range_matrix', []);
        $allowed = $ranges[$tierLevel] ?? [];
        if (!$allowed) {
            $allowed = config('analytics.ranges', []);
        }
        return $allowed ?: ['7d'];
    }

    /**
     * Fill link titles from the Link table (never from analytics events).
     *
     * @param array<string,mixed> $aggregates
     * @return array<string,mixed>
     */
    protected function hydrateTopLinkTitles(array $aggregates, int $userId): array
    {
        $rows = $aggregates['top_links'] ?? null;
        if (!is_array($rows) || $rows === []) {
            return $aggregates;
        }

        $ids = collect($rows)
            ->map(fn ($row) => is_array($row) ? ($row['link_id'] ?? null) : ($row->link_id ?? null))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $aggregates;
        }

        $linksQuery = Link::withDisabled()
            ->where('user_id', $userId)
            ->whereIn('id', $ids->all());

        if (Schema::hasColumn('links', 'tenant_owner_user_id')) {
            $tenantOwnerId = currentTenantOwnerId();
            if ($tenantOwnerId !== null) {
                $linksQuery->where('tenant_owner_user_id', $tenantOwnerId);
            }
        }

        $links = $linksQuery->get(['id', 'title', 'link'])->keyBy('id');

        $aggregates['top_links'] = array_map(function ($row) use ($links) {
            $id = is_array($row) ? ($row['link_id'] ?? null) : ($row->link_id ?? null);
            $link = $id ? $links->get($id) : null;
            if ($link) {
                if (is_array($row)) {
                    $row['title'] = $link->title ?: $link->link;
                } else {
                    $row->title = $link->title ?: $link->link;
                }
            }
            return $row;
        }, $rows);

        return $aggregates;
    }

    private function denyTenantAnalyticsContextMismatch(Request $request, int $activeSiteId): void
    {
        $queryKeys = ['site_id', 'page_id', 'user_id'];
        foreach ($queryKeys as $key) {
            if (!$request->query->has($key)) {
                continue;
            }

            $rawValue = $request->query($key);
            if (is_array($rawValue)) {
                $this->logAndAbortContextMismatch($request, $activeSiteId, $key, null);
            }

            $suppliedSiteId = (int) $rawValue;
            if ($suppliedSiteId <= 0 || $suppliedSiteId !== $activeSiteId) {
                $this->logAndAbortContextMismatch($request, $activeSiteId, $key, $suppliedSiteId);
            }
        }
    }

    private function logAndAbortContextMismatch(
        Request $request,
        int $activeSiteId,
        string $queryKey,
        ?int $suppliedSiteId,
    ): void {
        Log::warning('Tenant analytics context denied', [
            'reason_code' => 'tenant_page_context_mismatch_analytics_dashboard',
            'actor_user_id' => (int) ($request->user()?->id ?? 0),
            'resource_user_id' => $suppliedSiteId,
            'active_resource_user_id' => $activeSiteId,
            'tenant_owner_user_id' => function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null,
            'query_key' => $queryKey,
            'route' => (string) ($request->route()?->getName() ?: $request->path()),
        ]);

        abort(403, 'Forbidden');
    }
}
