<?php

$allowedIps = array_values(array_filter(array_map(
    static fn ($item) => trim((string) $item),
    explode(',', (string) env('INTERNAL_PARTNER_WEBHOOK_ALLOWED_IPS', '127.0.0.1,::1')),
)));

return [
    'enabled' => (bool) env('INTERNAL_PARTNER_WEBHOOK_ENABLED', false),
    'token' => env('INTERNAL_PARTNER_WEBHOOK_TOKEN'),
    'allowed_ips' => $allowedIps,
    'require_loopback' => (bool) env('INTERNAL_PARTNER_WEBHOOK_REQUIRE_LOOPBACK', true),
    'rate_limit_per_minute' => max(60, (int) env('INTERNAL_PARTNER_WEBHOOK_RATE_LIMIT_PER_MINUTE', 240)),
];
