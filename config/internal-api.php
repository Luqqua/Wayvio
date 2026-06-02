<?php

$partnerAllowedCountries = array_values(array_unique(array_filter(
    array_map(
        static fn ($country) => strtoupper(trim((string) $country)),
        explode(',', (string) env('PARTNERS_ONBOARDING_ALLOWED_COUNTRIES', 'DE,AT')),
    ),
    static fn ($country) => preg_match('/^[A-Z]{2}$/', $country) === 1,
)));

if ($partnerAllowedCountries === []) {
    $partnerAllowedCountries = ['DE', 'AT'];
}

return [
    'base_url' => env('INTERNAL_API_BASE_URL', env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001')),
    'api_key' => env('INTERNAL_API_KEY', env('ANALYTICS_INTERNAL_API_KEY')),
    'timeout' => (float) env('INTERNAL_API_TIMEOUT', 2.0),

    'billing' => [
        'enabled' => filter_var(env('BILLING_MS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('BILLING_MS_BASE_URL', env('INTERNAL_API_BASE_URL', env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001'))),
        'api_key' => env('BILLING_MS_INTERNAL_API_KEY', env('INTERNAL_API_KEY', env('ANALYTICS_INTERNAL_API_KEY'))),
        'timeout' => (float) env('BILLING_MS_TIMEOUT', env('INTERNAL_API_TIMEOUT', 2.0)),
        'checkout_timeout' => (float) env('BILLING_MS_CHECKOUT_TIMEOUT', 10.0),
        'portal_timeout' => (float) env('BILLING_MS_PORTAL_TIMEOUT', 10.0),
        'change_timeout' => (float) env('BILLING_MS_CHANGE_TIMEOUT', 20.0),
        'delete_timeout' => (float) env('BILLING_MS_DELETE_TIMEOUT', 20.0),
        'confirm_timeout' => (float) env('BILLING_MS_CONFIRM_TIMEOUT', 10.0),
        'webhook_timeout' => (float) env('BILLING_MS_WEBHOOK_TIMEOUT', 15.0),
        'state_timeout' => (float) env('BILLING_MS_STATE_TIMEOUT', 5.0),
    ],

    'domains' => [
        'enabled' => filter_var(env('DOMAINS_MS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('DOMAINS_MS_BASE_URL', env('INTERNAL_API_BASE_URL', env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001'))),
        'api_key' => env('DOMAINS_MS_INTERNAL_API_KEY', env('INTERNAL_API_KEY', env('ANALYTICS_INTERNAL_API_KEY'))),
        'timeout' => (float) env('DOMAINS_MS_TIMEOUT', env('INTERNAL_API_TIMEOUT', 2.0)),
        'create_timeout' => (float) env('DOMAINS_MS_CREATE_TIMEOUT', 15.0),
        'delete_timeout' => (float) env('DOMAINS_MS_DELETE_TIMEOUT', 20.0),
        'verify_timeout' => (float) env('DOMAINS_MS_VERIFY_TIMEOUT', 15.0),
        'sync_timeout' => (float) env('DOMAINS_MS_SYNC_TIMEOUT', 15.0),
        'cleanup_timeout' => (float) env('DOMAINS_MS_CLEANUP_TIMEOUT', 15.0),
        'reconcile_timeout' => (float) env('DOMAINS_MS_RECONCILE_TIMEOUT', 20.0),
    ],

    'partners' => [
        'enabled' => filter_var(env('PARTNERS_MS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('PARTNERS_MS_BASE_URL', env('INTERNAL_API_BASE_URL', env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001'))),
        'api_key' => env('PARTNERS_MS_INTERNAL_API_KEY', env('INTERNAL_API_KEY', env('ANALYTICS_INTERNAL_API_KEY'))),
        'timeout' => (float) env('PARTNERS_MS_TIMEOUT', env('INTERNAL_API_TIMEOUT', 2.0)),
        'webhook_timeout' => (float) env('PARTNERS_MS_WEBHOOK_TIMEOUT', 15.0),
        'settle_verify_timeout' => (float) env('PARTNERS_MS_SETTLE_VERIFY_TIMEOUT', 10.0),
        'default_country' => env('PARTNERS_ONBOARDING_DEFAULT_COUNTRY', 'DE'),
        'allowed_countries' => $partnerAllowedCountries,
    ],
];
