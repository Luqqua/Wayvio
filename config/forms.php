<?php

return [
    'api_base' => env('FORMS_API_BASE', env('ANALYTICS_API_BASE', 'http://127.0.0.1:8001')),
    'api_key' => env('FORMS_MS_INTERNAL_API_KEY'),
    'maintenance_secret' => env('FORMS_MAINTENANCE_SECRET', ''),
    'http_timeout' => (float) env('FORMS_HTTP_TIMEOUT', 5.0),
    'enabled' => (bool) env('FORMS_ENABLED', true),

    'turnstile_required' => (bool) env('FORMS_TURNSTILE_REQUIRED', true),
    'bot_protection' => [
        'provider' => trim((string) env('FORMS_BOT_PROTECTION_PROVIDER', '')) !== ''
            ? strtolower(trim((string) env('FORMS_BOT_PROTECTION_PROVIDER')))
            : (env('CAP_FORMS_SECRET') && env('CAP_FORMS_SITE_KEY') && env('CAP_FORMS_BASE_URL')
                ? 'cap'
                : ((bool) env('FORMS_TURNSTILE_REQUIRED', true) ? 'turnstile' : 'none')),
        'required' => (bool) env('FORMS_BOT_PROTECTION_REQUIRED', env('FORMS_TURNSTILE_REQUIRED', true)),
    ],
    'cap' => [
        'base_url' => env('CAP_FORMS_BASE_URL', 'http://127.0.0.1:3030'),
        'verify_base_url' => env('CAP_FORMS_VERIFY_BASE_URL', env('CAP_FORMS_BASE_URL', 'http://127.0.0.1:3030')),
        'site_key' => env('CAP_FORMS_SITE_KEY'),
        'secret' => env('CAP_FORMS_SECRET'),
        'http_timeout' => (float) env('CAP_FORMS_HTTP_TIMEOUT', 5.0),
    ],
    'honeypot_field' => 'wayvio_company',
    'min_submit_seconds' => max(0, (int) env('FORMS_MIN_SUBMIT_SECONDS', 2)),
    // Fallback only. Normal retention follows the same tier rules as analytics.
    'retention_days' => max(1, (int) env('FORMS_RETENTION_DAYS', 180)),
    'dashboard_per_page' => max(1, min(100, (int) env('FORMS_DASHBOARD_PER_PAGE', 25))),
    'dashboard_timezone' => env('FORMS_DASHBOARD_TIMEZONE', 'Europe/Berlin'),

    // Optional owner for platform legal pages. If empty, platform pages skip the form.
    'platform_hub_user_id' => (int) env('FORMS_PLATFORM_HUB_USER_ID', 0),

    'allowed_tiers' => ['basic', 'pro', 'agency'],

    'catalog' => [
        'imprint_contact' => [
            'form_key' => 'imprint_contact',
            'source_context' => 'imprint',
            'title' => 'Contact form',
            'description' => '',
            'submit_label' => 'messages.submit_button',
        ],
        'hub_contact_block' => [
            'form_key' => 'hub_contact_block',
            'source_context' => 'hub_block',
            'title' => 'Contact form',
            'description' => '',
            'submit_label' => 'messages.submit_button',
        ],
    ],
];
