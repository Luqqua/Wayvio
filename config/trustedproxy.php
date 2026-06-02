<?php

use Illuminate\Http\Request;

$trustedProxies = array_values(array_filter(array_map(
    static fn ($item) => trim((string) $item),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
), static fn ($item) => $item !== ''));

return [
    'proxies' => $trustedProxies === [] ? null : $trustedProxies,
    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB,
];
