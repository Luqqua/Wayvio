<?php

namespace Modules\Tiers\Services;

use Illuminate\Support\Arr;
use Modules\Tiers\Models\Tier;

class TierResolver
{
    /**
     * Return all plans keyed by canonical slug.
     *
     * @return \Illuminate\Support\Collection<string,array>
     */
    public function plans()
    {
        return collect(config('tiers.plans', []))
            ->mapWithKeys(function (array $plan) {
                $slug = $plan['slug'] ?? config('tiers.default_free_slug', 'free');
                $plan['slug'] = $slug;
                return [$slug => $plan];
            });
    }

    public function normalizeSlug(?string $slug): string
    {
        $slug = $slug ?: config('tiers.default_free_slug', 'free');
        $map = config('tiers.legacy_slug_map', []);

        return $map[$slug] ?? $slug;
    }

    /**
     * @return array<string,mixed>
     */
    public function configForSlug(?string $slug): array
    {
        $slug = $this->normalizeSlug($slug);
        $plans = $this->plans();
        if ($plans->has($slug)) {
            return $plans->get($slug);
        }

        $fallback = $plans->get(config('tiers.default_free_slug', 'free'));
        if ($fallback) {
            return $fallback;
        }

        return [
            'name' => 'Free',
            'slug' => config('tiers.default_free_slug', 'free'),
            'limits' => ['max_pages' => 1, 'max_links_per_page' => 10, 'analytics_history_days' => 7],
            'features' => [],
        ];
    }

    public function configForTier(?Tier $tier): array
    {
        return $this->configForSlug($tier?->slug);
    }

    public function displayName(string $slug): string
    {
        $config = $this->configForSlug($slug);
        return $config['name'] ?? ucfirst($slug);
    }

    public function featureEnabled(?Tier $tier, string $featureKey): bool
    {
        $config = $this->configForTier($tier);
        return (bool) Arr::get($config['features'] ?? [], $featureKey, false);
    }

    /**
     * @return array{max_pages:int,max_links_per_page:int,analytics_history_days:int,agency_hub_slots:int}
     */
    public function limits(?Tier $tier): array
    {
        $config = $this->configForTier($tier);
        $limits = $config['limits'] ?? [];
        $analyticsDays = Arr::get($config, 'features.analytics.history_days');

        return [
            'max_pages' => (int) ($limits['max_pages'] ?? 1),
            'max_links_per_page' => (int) ($limits['max_links_per_page'] ?? 10),
            'analytics_history_days' => (int) ($limits['analytics_history_days'] ?? $analyticsDays ?? 7),
            'agency_hub_slots' => (int) ($limits['agency_hub_slots'] ?? $limits['max_pages'] ?? 1),
        ];
    }

    /**
     * @return array<int,string>
     */
    public function analyticsFeatureList(Tier|string|null $tier): array
    {
        $config = is_string($tier) ? $this->configForSlug($tier) : $this->configForTier($tier);
        $analytics = $config['features']['analytics'] ?? [];
        if (empty($analytics['enabled'])) {
            return [];
        }

        $map = [
            'page_views' => 'views',
            'clicks' => 'clicks',
            'top_links' => 'top_links',
            'unique_visitors' => 'unique_visitors',
            'referrer' => 'referrers',
            'browser' => 'browsers',
            'os' => 'os',
            'geo' => 'geo',
            'utm' => 'utm',
            'recurring' => 'returning',
            'time_series' => 'time_series',
        ];

        $features = [];
        foreach ($map as $flag => $label) {
            if (!empty($analytics[$flag])) {
                $features[] = $label;
            }
        }

        if (!empty($analytics['browser']) || !empty($analytics['os'])) {
            $features[] = 'devices';
        }

        if (!empty($analytics['export'])) {
            $features[] = 'export';
        }

        return array_values(array_unique($features));
    }

    public function analyticsRetention(Tier|string|null $tier): int
    {
        $config = is_string($tier) ? $this->configForSlug($tier) : $this->configForTier($tier);
        $analytics = $config['features']['analytics'] ?? [];

        return (int) ($analytics['history_days'] ?? Arr::get($config, 'limits.analytics_history_days', 0));
    }

    /**
     * Flattened payload for syncing plan data to the tiers table.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function databasePayload(array $plan): array
    {
        $limits = $plan['limits'] ?? [];
        $features = $plan['features'] ?? [];

        return [
            'name' => $plan['name'],
            'slug' => $this->normalizeSlug($plan['slug'] ?? ''),
            'description' => $plan['description'] ?? '',
            'max_pages' => (int) ($limits['max_pages'] ?? 1),
            'max_links_per_page' => (int) ($limits['max_links_per_page'] ?? 10),
            'analytics_enabled' => (bool) Arr::get($features, 'analytics.enabled', false),
            'custom_domain_enabled' => (bool) Arr::get($features, 'domains.custom_domain', false),
            'design_customization_enabled' => (bool) Arr::get($features, 'design.link_styling', false)
                || (bool) Arr::get($features, 'design.custom_colors', false)
                || (bool) Arr::get($features, 'design.header_image', false)
                || (bool) Arr::get($features, 'design.background_image', false),
            'price_1m' => (int) ($plan['price_1m'] ?? 0),
            'price_3m' => (int) ($plan['price_3m'] ?? 0),
            'price_6m' => (int) ($plan['price_6m'] ?? 0),
        ];
    }
}
