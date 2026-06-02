<?php

$agencyMinHubs = max(2, (int) env('AGENCY_MIN_HUBS', 2));
$agencyMaxHubs = max($agencyMinHubs, min(10, (int) env('AGENCY_MAX_HUBS', 10)));
$agencyDefaultHubs = (int) env('AGENCY_HUB_SLOTS', $agencyMinHubs);
$agencyDefaultHubs = max($agencyMinHubs, min($agencyMaxHubs, $agencyDefaultHubs));
$extraBillingPeriods = array_values(array_unique(array_filter(
    array_map(
        static fn ($value): int => (int) trim((string) $value),
        explode(',', (string) env('BILLING_ENABLED_PERIODS', '1'))
    ),
    static fn (int $months): bool => in_array($months, [3, 6], true)
)));
sort($extraBillingPeriods);
$enabledBillingPeriods = array_values(array_unique(array_merge([1], $extraBillingPeriods)));
$periodPrice = static function (int $months, int $amount) use ($enabledBillingPeriods): int {
    return in_array($months, $enabledBillingPeriods, true) ? $amount : 0;
};
$basicAnalyticsHistoryDays = max(90, (int) env('BASIC_ANALYTICS_RETENTION_DAYS', 90));
$proAnalyticsHistoryDays = max(365, (int) env('PRO_ANALYTICS_RETENTION_DAYS', 365));
$agencyAnalyticsHistoryDays = max($proAnalyticsHistoryDays, (int) env('AGENCY_ANALYTICS_RETENTION_DAYS', 365));

return [
    // Slug to treat admins as when resolving tier benefits
    'admin_tier_slug' => 'agency',

    // Slug for free/default tier fallbacks
    'default_free_slug' => 'free',

    // Order matters for upgrade/downgrade logic
    'order' => ['free', 'basic', 'pro', 'agency'],

    // Map legacy slugs to the canonical set (free, basic, pro, agency)
    'legacy_slug_map' => [
        'tier1' => 'free',
        'free' => 'free',
        'tier2' => 'pro',
        'basic' => 'basic',
        'pro' => 'pro',
        'tier3' => 'agency',
        'premium' => 'pro',
        'business' => 'agency',
        'enterprise' => 'agency',
        'agency' => 'agency',
    ],

    // Shared agency hub bounds for checkout + management workflows.
    'agency' => [
        'min_hubs' => $agencyMinHubs,
        'max_hubs' => $agencyMaxHubs,
        'default_hubs' => $agencyDefaultHubs,
    ],

    // Only monthly billing is enabled by default. Additional periods can be
    // re-enabled later via BILLING_ENABLED_PERIODS=1,3,6 without schema changes.
    'billing' => [
        'enabled_periods' => $enabledBillingPeriods,
    ],

    // Tiers managed via config file (no UI editing)
    'plans' => [
        [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Free tier with one managed hub and core templates.',
            'price_1m' => 0,
            'price_3m' => 0,
            'price_6m' => 0,
            'limits' => [
                'max_pages' => 1,
                'max_links_per_page' => 10,
                'analytics_history_days' => 7,
                'agency_hub_slots' => 1,
            ],
            'features' => [
                'branding' => [
                    'remove_branding' => false,
                    'platform_branding' => true,
                ],
                'agency' => [
                    'managed_hubs' => false,
                    'white_label' => false,
                ],
                'design' => [
                    'templates' => 'all',
                    'header_image' => true,
                    'background_image' => false,
                    'link_styling' => true,
                    'custom_colors' => true,
                ],
                'domains' => [
                    'custom_domain' => false,
                ],
                'analytics' => [
                    'enabled' => true,
                    'page_views' => true,
                    'clicks' => true,
                    'top_links' => true,
                    'time_series' => true,
                    'history_days' => 7,
                    'unique_visitors' => false,
                    'referrer' => false,
                    'browser' => false,
                    'os' => false,
                    'geo' => false,
                    'utm' => false,
                    'recurring' => false,
                    'export' => false,
                ],
                'marketing' => [
                    'utm_analyzer' => false,
                    'campaign_insights' => false,
                ],
                'profile' => [
                    'checkmark' => false,
                ],
                'seo' => [
                    'custom_meta' => false,
                ],
            ],
        ],
        [
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Starter plan with one managed hub and improved design options.',
            'price_1m' => $periodPrice(1, (int) env('BASIC_PRICE_1M', 599)),
            'price_3m' => $periodPrice(3, (int) env('BASIC_PRICE_3M', 1797)),
            'price_6m' => $periodPrice(6, (int) env('BASIC_PRICE_6M', 3594)),
            'limits' => [
                'max_pages' => 1,
                'max_links_per_page' => 25,
                'analytics_history_days' => $basicAnalyticsHistoryDays,
                'agency_hub_slots' => 1,
            ],
            'features' => [
                'branding' => [
                    'remove_branding' => true,
                    'platform_branding' => false,
                ],
                'agency' => [
                    'managed_hubs' => false,
                    'white_label' => false,
                ],
                'design' => [
                    'templates' => 'all',
                    'header_image' => true,
                    'background_image' => true,
                    'link_styling' => true,
                    'custom_colors' => true,
                ],
                'domains' => [
                    'custom_domain' => false,
                ],
                'analytics' => [
                    'enabled' => true,
                    'page_views' => true,
                    'clicks' => true,
                    'top_links' => true,
                    'unique_visitors' => false,
                    'referrer' => true,
                    'browser' => false,
                    'os' => false,
                    'time_series' => true,
                    'history_days' => $basicAnalyticsHistoryDays,
                    'geo' => true,
                    'utm' => false,
                    'recurring' => false,
                    'export' => false,
                ],
                'marketing' => [
                    'utm_analyzer' => false,
                    'campaign_insights' => false,
                ],
                'profile' => [
                    'checkmark' => true,
                ],
                'seo' => [
                    'custom_meta' => false,
                ],
            ],
        ],
        [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Professional tier with one hub, advanced analytics and full design control.',
            'price_1m' => $periodPrice(1, (int) env('PRO_PRICE_1M', 1499)),
            'price_3m' => $periodPrice(3, (int) env('PRO_PRICE_3M', 4497)),
            'price_6m' => $periodPrice(6, (int) env('PRO_PRICE_6M', 8994)),
            'limits' => [
                'max_pages' => 1,
                'max_links_per_page' => 25,
                'analytics_history_days' => $proAnalyticsHistoryDays,
                'agency_hub_slots' => 1,
            ],
            'features' => [
                'branding' => [
                    'remove_branding' => true,
                    'platform_branding' => false,
                ],
                'agency' => [
                    'managed_hubs' => false,
                    'white_label' => false,
                ],
                'design' => [
                    'templates' => 'all',
                    'header_image' => true,
                    'background_image' => true,
                    'link_styling' => true,
                    'custom_colors' => true,
                ],
                'domains' => [
                    'custom_domain' => true,
                ],
                'analytics' => [
                    'enabled' => true,
                    'page_views' => true,
                    'clicks' => true,
                    'top_links' => true,
                    'unique_visitors' => true,
                    'referrer' => true,
                    'browser' => true,
                    'os' => true,
                    'time_series' => true,
                    'history_days' => $proAnalyticsHistoryDays,
                    'geo' => true,
                    'utm' => true,
                    'recurring' => true,
                    'export' => true,
                ],
                'marketing' => [
                    'utm_analyzer' => true,
                    'campaign_insights' => true,
                ],
                'profile' => [
                    'checkmark' => true,
                ],
                'seo' => [
                    'custom_meta' => true,
                ],
            ],
        ],
        [
            'name' => 'Agency',
            'slug' => 'agency',
            'description' => 'Enterprise managed-service tier with scalable hub slots and domain controls.',
            'price_1m' => $periodPrice(1, (int) env('AGENCY_PRICE_1M', env('BUSINESS_PRICE_1M', 3999))),
            'price_3m' => $periodPrice(3, (int) env('AGENCY_PRICE_3M', env('BUSINESS_PRICE_3M', 11997))),
            'price_6m' => $periodPrice(6, (int) env('AGENCY_PRICE_6M', env('BUSINESS_PRICE_6M', 23994))),
            'included_hubs' => $agencyMinHubs,
            'extra_hub_price_1m' => (int) env('AGENCY_ADDON_PRICE_1M', 900),
            'limits' => [
                'max_pages' => $agencyDefaultHubs,
                'agency_hub_slots' => $agencyDefaultHubs,
                'max_links_per_page' => 30,
                'analytics_history_days' => $agencyAnalyticsHistoryDays,
            ],
            'features' => [
                'branding' => [
                    'remove_branding' => true,
                    'platform_branding' => false,
                ],
                'agency' => [
                    'managed_hubs' => true,
                    'white_label' => true,
                ],
                'design' => [
                    'templates' => 'all',
                    'header_image' => true,
                    'background_image' => true,
                    'link_styling' => true,
                    'custom_colors' => true,
                ],
                'domains' => [
                    'custom_domain' => true,
                ],
                'analytics' => [
                    'enabled' => true,
                    'page_views' => true,
                    'clicks' => true,
                    'top_links' => true,
                    'unique_visitors' => true,
                    'referrer' => true,
                    'browser' => true,
                    'os' => true,
                    'time_series' => true,
                    'history_days' => $agencyAnalyticsHistoryDays,
                    'geo' => true,
                    'utm' => true,
                    'recurring' => true,
                    'export' => true,
                ],
                'marketing' => [
                    'utm_analyzer' => true,
                    'campaign_insights' => true,
                ],
                'profile' => [
                    'checkmark' => true,
                ],
                'seo' => [
                    'custom_meta' => true,
                ],
            ],
        ],
    ],
];
