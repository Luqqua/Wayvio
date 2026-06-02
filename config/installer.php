<?php

$allowedIps = array_values(array_filter(array_map(
    static fn ($item) => trim((string) $item),
    explode(',', (string) env('INSTALLER_ALLOWED_IPS', '127.0.0.1,::1')),
), static fn ($item) => $item !== ''));

return [
    'enabled' => filter_var(env('INSTALLER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'one_time_secret' => (string) env('INSTALLER_ONE_TIME_SECRET', ''),
    'allowed_ips' => $allowedIps,
    'require_loopback' => filter_var(env('INSTALLER_REQUIRE_LOOPBACK', true), FILTER_VALIDATE_BOOLEAN),
];
