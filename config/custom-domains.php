<?php

$queueBackoff = array_values(array_filter(array_map(
    static fn ($item) => (int) trim((string) $item),
    explode(',', (string) env('CUSTOM_DOMAIN_JOB_BACKOFF_SECONDS', '60,300,900,1800,3600,7200')),
), static fn ($item) => $item > 0));

return [
    'provider' => trim((string) env('CUSTOM_DOMAIN_PROVIDER', 'cloudflare')) ?: 'cloudflare',

    // Customer DNS records should CNAME to this Cloudflare-for-SaaS target.
    'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', ''),

    'queue' => [
        'name' => trim((string) env('CUSTOM_DOMAIN_QUEUE', 'domains')) ?: 'domains',
        'job_max_attempts' => max(1, (int) env('CUSTOM_DOMAIN_JOB_MAX_ATTEMPTS', 8)),
        'job_backoff_seconds' => $queueBackoff !== [] ? $queueBackoff : [60, 300, 900, 1800, 3600, 7200],
        'job_retry_window_hours' => max(1, (int) env('CUSTOM_DOMAIN_JOB_RETRY_WINDOW_HOURS', 24)),
        'job_timeout_seconds' => max(30, (int) env('CUSTOM_DOMAIN_JOB_TIMEOUT_SECONDS', 300)),
    ],

    // Cloudflare API credentials intentionally live only in internal-apis.
];
