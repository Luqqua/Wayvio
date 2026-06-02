<?php

// This file is part of Wayvio and is licensed under the AGPL-3.0-or-later.

$basicRange = ['1d', '7d', '30d', '90d'];
$proRange = ['1d', '7d', '30d', '90d', '180d', '365d'];

return [
    'api_base' => env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001'),
    'api_key' => env('ANALYTICS_INTERNAL_API_KEY'),
    'internal_key' => env('ANALYTICS_INTERNAL_API_KEY'),
    'http_timeout' => (float) env('ANALYTICS_HTTP_TIMEOUT', 2.5),
    'privacy_mode' => (bool) env('ANALYTICS_PRIVACY_MODE', true),
    'landing_enabled' => (bool) env('ANALYTICS_LANDING_ENABLED', false),
    'landing_site_id' => (int) env('ANALYTICS_LANDING_SITE_ID', 0),
    'landing_actor_user_id' => (int) env('ANALYTICS_LANDING_ACTOR_USER_ID', env('ANALYTICS_LANDING_SITE_ID', 0)),
    'landing_tier_level' => env('ANALYTICS_LANDING_TIER_LEVEL', 'business'),

    'user_model' => \App\Models\User::class, // Allow swapping to a custom owner model.
    'owner_slug_column' => 'littlelink_name',
    'default_tier' => 'free',

    // Map subscription slugs to the tier levels used by this bridge.
    'tier_slug_map' => [],

    'tier_labels' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'business' => 'Business',
    ],

    // Feature matrix controls what is forwarded per tier.
    'feature_matrix' => [
        'free' => ['views', 'clicks', 'top_links', 'time_series'],
        'pro' => ['views', 'clicks', 'top_links', 'referrers', 'geo', 'time_series'],
        'business' => ['views', 'clicks', 'top_links', 'referrers', 'geo', 'utm', 'time_series'],
    ],

    // Allow customizable ranges for aggregate queries.
    'ranges' => $proRange,

    // Range limits per tier (must be a subset of `ranges`).
    'range_matrix' => [
        'free' => ['1d', '7d'],
        'basic' => $basicRange,
        'pro' => $proRange,
        'agency' => $proRange,
        // Legacy aliases for older tier slugs still seen in traffic.
        'tier1' => ['1d', '7d'],
        'tier2' => $basicRange,
        'tier3' => $proRange,
        'business' => $proRange,
    ],
];
