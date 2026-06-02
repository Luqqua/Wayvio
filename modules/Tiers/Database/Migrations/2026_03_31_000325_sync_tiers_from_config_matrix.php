<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tiers')) {
            return;
        }

        $defaultFreeSlug = (string) config('tiers.default_free_slug', 'free');
        $legacySlugMap = (array) config('tiers.legacy_slug_map', []);
        $plans = (array) config('tiers.plans', []);
        $now = now();

        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }

            $rawSlug = strtolower(trim((string) ($plan['slug'] ?? $defaultFreeSlug)));
            if ($rawSlug === '') {
                $rawSlug = $defaultFreeSlug;
            }
            $slug = (string) ($legacySlugMap[$rawSlug] ?? $rawSlug);

            $limits = (array) ($plan['limits'] ?? []);
            $features = (array) ($plan['features'] ?? []);

            $payload = [
                'name' => (string) ($plan['name'] ?? ucfirst($slug)),
                'slug' => $slug,
                'description' => (string) ($plan['description'] ?? ''),
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
                'updated_at' => $now,
            ];

            if (!DB::table('tiers')->where('slug', $slug)->exists()) {
                $payload['created_at'] = $now;
            }

            DB::table('tiers')->updateOrInsert(
                ['slug' => $slug],
                $payload
            );
        }
    }

    public function down(): void
    {
        // no-op: this migration only aligns persisted tier rows with config.
    }
};

