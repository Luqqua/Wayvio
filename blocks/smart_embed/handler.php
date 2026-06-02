<?php

if (!function_exists('smartEmbedServiceCatalog')) {
    function smartEmbedServiceCatalog(): array
    {
        return [
            'youtube' => [
                'label' => 'YouTube',
                'hosts' => ['youtube.com', 'youtu.be'],
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'hosts' => ['instagram.com'],
                'allow' => '',
            ],
            'google_maps' => [
                'label' => 'Google Maps',
                'hosts' => ['google.com', 'maps.google.com', 'maps.app.goo.gl'],
                'allow' => 'geolocation',
            ],
            'spotify' => [
                'label' => 'Spotify',
                'hosts' => ['open.spotify.com', 'spotify.com'],
                'allow' => 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
            ],
            'calendly' => [
                'label' => 'Calendly',
                'hosts' => ['calendly.com'],
                'allow' => '',
            ],
            'tally' => [
                'label' => 'Tally',
                'hosts' => ['tally.so'],
                'allow' => '',
            ],
            'gumroad' => [
                'label' => 'Gumroad',
                'hosts' => ['gumroad.com'],
                'allow' => '',
            ],
            'kit' => [
                'label' => 'Kit',
                'hosts' => ['kit.com'],
                'allow' => '',
            ],
            'resmio_booking' => [
                'label' => 'Resmio - Buchung',
                'hosts' => ['app.resmio.com', 'resmio.com'],
                'allow' => '',
            ],
            'resmio_menu' => [
                'label' => 'Resmio - Speisekarte',
                'hosts' => ['app.resmio.com', 'resmio.com'],
                'allow' => '',
            ],
        ];
    }
}

if (!function_exists('smartEmbedHostMatches')) {
    function smartEmbedHostMatches(?string $host, array $allowedHosts): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = strtolower($allowedHost);
            if (str_starts_with($allowedHost, 'www.')) {
                $allowedHost = substr($allowedHost, 4);
            }

            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('smartEmbedNormalizeUrl')) {
    function smartEmbedNormalizeUrl(string $rawUrl): ?string
    {
        $url = trim($rawUrl);
        if ($url === '') {
            return null;
        }

        // Accept pasted iframe snippets by extracting src.
        if (preg_match('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/i', $url, $matches) === 1) {
            $url = trim((string) ($matches[2] ?? ''));
        }

        // Accept pasted link-based embed snippets by extracting href.
        if (preg_match('/<a\b[^>]*\bhref=(["\'])(.*?)\1/i', $url, $matches) === 1) {
            $url = trim((string) ($matches[2] ?? ''));
        }

        // Handle accidentally concatenated URLs (e.g. "url1url2").
        if (preg_match('/^(https?:\/\/.+?)(?=https?:\/\/)/i', $url, $matches) === 1) {
            $url = trim((string) ($matches[1] ?? $url));
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (empty($parts['host'])) {
            return null;
        }

        return $url;
    }
}

if (!function_exists('smartEmbedIsBlockedKitSubdomain')) {
    function smartEmbedIsBlockedKitSubdomain(string $subdomain): bool
    {
        $reserved = [
            'app',
            'www',
            'forms',
            'api',
            'help',
            'support',
            'developers',
            'status',
            'cdn',
            'assets',
            'blog',
            'docs',
            'mail',
        ];

        return in_array(strtolower(trim($subdomain)), $reserved, true);
    }
}

if (!function_exists('smartEmbedDecMultiplyAdd')) {
    function smartEmbedDecMultiplyAdd(string $decimal, int $multiplier, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($index = strlen($decimal) - 1; $index >= 0; $index--) {
            $value = ((int) $decimal[$index]) * $multiplier + $carry;
            $result = (string) ($value % 10) . $result;
            $carry = intdiv($value, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        $result = ltrim($result, '0');
        return $result === '' ? '0' : $result;
    }
}

if (!function_exists('smartEmbedHexToDecString')) {
    function smartEmbedHexToDecString(string $hex): ?string
    {
        $hex = strtolower(trim($hex));
        $hex = preg_replace('/^0x/i', '', $hex);
        if (!is_string($hex) || $hex === '' || preg_match('/^[0-9a-f]+$/', $hex) !== 1) {
            return null;
        }

        $decimal = '0';
        $map = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
            '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
            'a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15,
        ];

        for ($index = 0; $index < strlen($hex); $index++) {
            $digit = $map[$hex[$index]] ?? null;
            if ($digit === null) {
                return null;
            }
            $decimal = smartEmbedDecMultiplyAdd($decimal, 16, $digit);
        }

        return $decimal;
    }
}

if (!function_exists('smartEmbedResolveRedirectUrl')) {
    function smartEmbedResolveRedirectUrl(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        foreach ([true, false] as $headOnly) {
            $curlHandle = curl_init($url);
            if ($curlHandle === false) {
                return null;
            }

            curl_setopt_array($curlHandle, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 6,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_USERAGENT => 'Wayvio Smart Embed/1.0',
                CURLOPT_NOBODY => $headOnly,
            ]);

            $response = curl_exec($curlHandle);
            $effectiveUrl = (string) curl_getinfo($curlHandle, CURLINFO_EFFECTIVE_URL);
            curl_close($curlHandle);

            if ($response !== false && $effectiveUrl !== '') {
                return smartEmbedNormalizeUrl($effectiveUrl);
            }
        }

        return null;
    }
}

if (!function_exists('smartEmbedExtractResmioSlug')) {
    function smartEmbedExtractResmioSlug(string $rawValue): ?string
    {
        $rawValue = trim($rawValue);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,80}$/', $rawValue) !== 1) {
            return null;
        }

        return strtolower($rawValue);
    }
}

if (!function_exists('smartEmbedBuildUrlsFromId')) {
    function smartEmbedBuildUrlsFromId(string $service, string $embedId): ?array
    {
        $service = strtolower(trim($service));
        $embedId = trim($embedId);

        if ($service === '' || $embedId === '') {
            return null;
        }

        if ($service === 'youtube') {
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $embedId) === 1) {
                return [
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $embedId,
                    'source_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($embedId),
                ];
            }

            if (preg_match('/^playlist:([A-Za-z0-9_-]{10,})$/', $embedId, $matches) === 1) {
                $playlistId = $matches[1];
                return [
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/videoseries?list=' . rawurlencode($playlistId),
                    'source_url' => 'https://www.youtube.com/playlist?list=' . rawurlencode($playlistId),
                ];
            }

            return null;
        }

        if ($service === 'google_maps') {
            if (preg_match('/^pb:(.+)$/', $embedId, $matches) === 1) {
                $pb = trim($matches[1]);
                if ($pb === '') {
                    return null;
                }

                return [
                    'embed_url' => 'https://www.google.com/maps/embed?pb=' . rawurlencode($pb),
                    'source_url' => 'https://www.google.com/maps/embed?pb=' . rawurlencode($pb),
                ];
            }

            if (preg_match('/^place_id:([A-Za-z0-9_-]{8,220})$/', $embedId, $matches) === 1) {
                $placeId = $matches[1];
                return [
                    'embed_url' => 'https://www.google.com/maps?q=' . rawurlencode('place_id:' . $placeId) . '&output=embed',
                    'source_url' => 'https://www.google.com/maps/search/?api=1&query_place_id=' . rawurlencode($placeId),
                ];
            }

            if (preg_match('/^cid:([0-9]{6,25})$/', $embedId, $matches) === 1) {
                $cid = $matches[1];
                return [
                    'embed_url' => 'https://www.google.com/maps?cid=' . rawurlencode($cid) . '&output=embed',
                    'source_url' => 'https://www.google.com/maps?cid=' . rawurlencode($cid),
                ];
            }

            if (preg_match('/^short:([A-Za-z0-9_-]{8,64})$/', $embedId, $matches) === 1) {
                $shortCode = $matches[1];
                $shortUrl = 'https://maps.app.goo.gl/' . $shortCode;
                return [
                    'embed_url' => 'https://www.google.com/maps?q=' . rawurlencode($shortUrl) . '&output=embed',
                    'source_url' => $shortUrl,
                ];
            }

            return null;
        }

        if ($service === 'spotify') {
            if (preg_match('/^(track|album|artist|playlist|episode|show):([A-Za-z0-9]{10,64})$/', $embedId, $matches) !== 1) {
                return null;
            }

            $type = $matches[1];
            $resourceId = $matches[2];

            return [
                'embed_url' => 'https://open.spotify.com/embed/' . $type . '/' . $resourceId,
                'source_url' => 'https://open.spotify.com/' . $type . '/' . $resourceId,
            ];
        }

        if ($service === 'instagram') {
            if (preg_match('/^[A-Za-z0-9_-]{5,64}$/', $embedId) !== 1) {
                return null;
            }

            return [
                'embed_url' => 'https://www.instagram.com/p/' . rawurlencode($embedId) . '/embed/',
                'source_url' => 'https://www.instagram.com/p/' . rawurlencode($embedId) . '/',
            ];
        }

        if ($service === 'calendly') {
            if (preg_match('/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+){0,3}$/', $embedId) !== 1) {
                return null;
            }

            return [
                'embed_url' => 'https://calendly.com/' . $embedId,
                'source_url' => 'https://calendly.com/' . $embedId,
            ];
        }

        if ($service === 'tally') {
            if (preg_match('/^(r|form|forms|embed):([A-Za-z0-9]{4,64})$/', $embedId, $matches) === 1) {
                $pathType = $matches[1];
                $resourceId = $matches[2];
                return [
                    'embed_url' => 'https://tally.so/' . $pathType . '/' . $resourceId,
                    'source_url' => 'https://tally.so/' . $pathType . '/' . $resourceId,
                ];
            }

            // Legacy IDs that stored only the form ID.
            if (preg_match('/^[A-Za-z0-9]{4,64}$/', $embedId) === 1) {
                return [
                    'embed_url' => 'https://tally.so/r/' . $embedId,
                    'source_url' => 'https://tally.so/r/' . $embedId,
                ];
            }

            return null;
        }

        if ($service === 'gumroad') {
            if (preg_match('/^([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)\/l\/([A-Za-z0-9_-]{2,120})$/', $embedId, $matches) !== 1) {
                return null;
            }

            $shopHost = strtolower($matches[1]);
            $productSlug = $matches[2];
            $productUrl = 'https://' . $shopHost . '/l/' . rawurlencode($productSlug);

            return [
                'embed_url' => $productUrl,
                'source_url' => $productUrl,
            ];
        }

        if ($service === 'kit') {
            if (preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?):([a-f0-9]{8,64})$/', $embedId, $matches) !== 1) {
                return null;
            }

            $accountHandle = strtolower($matches[1]);
            $formUid = strtolower($matches[2]);
            if (smartEmbedIsBlockedKitSubdomain($accountHandle)) {
                return null;
            }

            $canonicalUrl = 'https://' . $accountHandle . '.kit.com/' . $formUid;

            return [
                'embed_url' => $canonicalUrl,
                'source_url' => $canonicalUrl,
            ];
        }

        if ($service === 'resmio_booking') {
            $restaurantSlug = smartEmbedExtractResmioSlug($embedId);
            if ($restaurantSlug === null) {
                return null;
            }

            $bookingUrl = 'https://app.resmio.com/' . rawurlencode($restaurantSlug) . '/widget';
            return [
                'embed_url' => $bookingUrl,
                'source_url' => $bookingUrl,
            ];
        }

        if ($service === 'resmio_menu') {
            $restaurantSlug = smartEmbedExtractResmioSlug($embedId);
            if ($restaurantSlug === null) {
                return null;
            }

            $menuUrl = 'https://app.resmio.com/' . rawurlencode($restaurantSlug) . '/menu-widget';
            return [
                'embed_url' => $menuUrl,
                'source_url' => $menuUrl,
            ];
        }

        return null;
    }
}

if (!function_exists('smartEmbedPackFromId')) {
    function smartEmbedPackFromId(string $service, string $embedId): ?array
    {
        $urls = smartEmbedBuildUrlsFromId($service, $embedId);
        if (!is_array($urls) || empty($urls['embed_url']) || empty($urls['source_url'])) {
            return null;
        }

        return [
            'service' => $service,
            'embed_id' => $embedId,
            'embed_url' => $urls['embed_url'],
            'source_url' => $urls['source_url'],
        ];
    }
}

if (!function_exists('smartEmbedParse')) {
    function smartEmbedParse(string $service, string $rawUrl): ?array
    {
        $catalog = smartEmbedServiceCatalog();
        $service = strtolower(trim($service));

        if (!isset($catalog[$service])) {
            return null;
        }

        $trimmedRawUrl = trim($rawUrl);

        if ($service === 'kit' && (str_contains($trimmedRawUrl, '<') || str_contains($trimmedRawUrl, '>'))) {
            if (
                preg_match('/https?:\/\/([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.kit\.com\/([a-f0-9]{8,64})(?:\/index\.js)?/i', $trimmedRawUrl, $matches) === 1
            ) {
                $accountHandle = strtolower($matches[1]);
                if (!smartEmbedIsBlockedKitSubdomain($accountHandle)) {
                    return smartEmbedPackFromId($service, $accountHandle . ':' . strtolower($matches[2]));
                }
            }

            if (
                preg_match('/\bdata-uid=(["\'])([a-f0-9]{8,64})\1/i', $trimmedRawUrl, $uidMatches) === 1
                && preg_match('/https?:\/\/([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.kit\.com\//i', $trimmedRawUrl, $hostMatches) === 1
            ) {
                $accountHandle = strtolower($hostMatches[1]);
                if (!smartEmbedIsBlockedKitSubdomain($accountHandle)) {
                    return smartEmbedPackFromId($service, $accountHandle . ':' . strtolower($uidMatches[2]));
                }
            }
        }

        if (in_array($service, ['resmio_booking', 'resmio_menu'], true) && (str_contains($trimmedRawUrl, '<') || str_contains($trimmedRawUrl, '>'))) {
            if (preg_match('/\bdata-resmio-menu=(["\'])([A-Za-z0-9][A-Za-z0-9_-]{1,80})\1/i', $trimmedRawUrl, $matches) === 1) {
                return smartEmbedPackFromId('resmio_menu', strtolower($matches[2]));
            }

            if (preg_match('/\bid=(["\'])resmio-([A-Za-z0-9][A-Za-z0-9_-]{1,80})\1/i', $trimmedRawUrl, $matches) === 1) {
                return smartEmbedPackFromId('resmio_booking', strtolower($matches[2]));
            }

            if (preg_match('/widget\.js#(?:[^"\']*&)?id=([A-Za-z0-9][A-Za-z0-9_-]{1,80})(?:&|["\']|$)/i', $trimmedRawUrl, $matches) === 1) {
                return smartEmbedPackFromId('resmio_booking', strtolower($matches[1]));
            }
        }

        $normalizedUrl = smartEmbedNormalizeUrl($trimmedRawUrl);
        if ($normalizedUrl === null) {
            return null;
        }

        $parts = parse_url($normalizedUrl);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($service === 'youtube') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $videoId = '';

            if (smartEmbedHostMatches($host, ['youtu.be'])) {
                $videoId = (string) ($segments[0] ?? '');
            } elseif (($segments[0] ?? '') === 'watch') {
                $videoId = (string) ($query['v'] ?? '');
            } elseif (in_array(($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                $videoId = (string) ($segments[1] ?? '');
            }

            if ($videoId !== '' && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1) {
                return smartEmbedPackFromId($service, $videoId);
            }

            $playlistId = (string) ($query['list'] ?? '');
            if ($playlistId !== '' && preg_match('/^[A-Za-z0-9_-]{10,}$/', $playlistId) === 1) {
                return smartEmbedPackFromId($service, 'playlist:' . $playlistId);
            }

            return null;
        }

        if ($service === 'google_maps') {
            $googleHost = preg_match('/(^|\.)google\./', $host) === 1;
            $isMapsShortUrl = smartEmbedHostMatches($host, ['maps.app.goo.gl']);
            if (!$googleHost && !smartEmbedHostMatches($host, $catalog[$service]['hosts']) && !$isMapsShortUrl) {
                return null;
            }

            if ($isMapsShortUrl) {
                $segments = array_values(array_filter(explode('/', $path), 'strlen'));
                $shortCodeCandidate = (string) ($segments[0] ?? '');
                $shortCode = '';
                if (preg_match('/^([A-Za-z0-9_-]{8,64})/', $shortCodeCandidate, $matches) === 1) {
                    $shortCode = $matches[1];
                }

                $resolvedUrl = smartEmbedResolveRedirectUrl($normalizedUrl);
                if ($resolvedUrl !== null && strcasecmp($resolvedUrl, $normalizedUrl) !== 0) {
                    return smartEmbedParse($service, $resolvedUrl);
                }

                if ($shortCode !== '') {
                    return smartEmbedPackFromId($service, 'short:' . $shortCode);
                }

                return null;
            }

            if (str_starts_with($path, 'maps/embed') && !empty($query['pb'])) {
                $pb = trim((string) $query['pb']);
                if ($pb === '') {
                    return null;
                }

                return smartEmbedPackFromId($service, 'pb:' . $pb);
            }

            $placeId = trim((string) ($query['query_place_id'] ?? ''));
            if ($placeId === '' && isset($query['q']) && preg_match('/^place_id:([A-Za-z0-9_-]{8,220})$/', trim((string) $query['q']), $matches) === 1) {
                $placeId = $matches[1];
            }

            if ($placeId !== '' && preg_match('/^[A-Za-z0-9_-]{8,220}$/', $placeId) === 1) {
                return smartEmbedPackFromId($service, 'place_id:' . $placeId);
            }

            $cid = trim((string) ($query['cid'] ?? ''));
            if ($cid !== '' && preg_match('/^[0-9]{6,25}$/', $cid) === 1) {
                return smartEmbedPackFromId($service, 'cid:' . $cid);
            }

            $decodedPathAndQuery = urldecode($path . ' ' . (string) ($parts['query'] ?? ''));
            $hexCandidate = '';

            // Prefer the place token from Maps URLs ("!1s0x...:0x..."), not style/theme tokens like "!5s...".
            if (preg_match('/(?:^|[!&])1s0x[0-9a-fA-F]+:0x([0-9a-fA-F]+)/', $decodedPathAndQuery, $matches) === 1) {
                $hexCandidate = (string) ($matches[1] ?? '');
            } elseif (preg_match_all('/0x[0-9a-fA-F]+:0x([0-9a-fA-F]+)/', $decodedPathAndQuery, $matches) > 0 && !empty($matches[1])) {
                // Fallback: if structure differs, use the last pair (usually the actual place).
                $hexCandidate = (string) end($matches[1]);
            }

            if ($hexCandidate !== '') {
                $cidFromHex = smartEmbedHexToDecString($hexCandidate);
                if (is_string($cidFromHex) && preg_match('/^[0-9]{6,25}$/', $cidFromHex) === 1) {
                    return smartEmbedPackFromId($service, 'cid:' . $cidFromHex);
                }
            }

            return null;
        }

        if ($service === 'instagram') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $contentType = strtolower((string) ($segments[0] ?? ''));
            $shortcode = trim((string) ($segments[1] ?? ''));

            if ($contentType !== 'p') {
                return null;
            }

            if (preg_match('/^[A-Za-z0-9_-]{5,64}$/', $shortcode) !== 1) {
                return null;
            }

            return smartEmbedPackFromId($service, $shortcode);
        }

        if ($service === 'spotify') {
            $type = '';
            $resourceId = '';

            if (preg_match('/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{10,64})$/', trim($rawUrl), $matches) === 1) {
                $type = $matches[1];
                $resourceId = $matches[2];
            } else {
                if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                    return null;
                }

                $segments = array_values(array_filter(explode('/', $path), 'strlen'));
                if (str_starts_with((string) ($segments[0] ?? ''), 'intl-')) {
                    array_shift($segments);
                }
                if (($segments[0] ?? '') === 'embed') {
                    array_shift($segments);
                }

                if (($segments[0] ?? '') === 'user' && ($segments[2] ?? '') === 'playlist') {
                    $type = 'playlist';
                    $resourceId = (string) ($segments[3] ?? '');
                } else {
                    $type = (string) ($segments[0] ?? '');
                    $resourceId = (string) ($segments[1] ?? '');
                }
            }

            if (!in_array($type, ['track', 'album', 'artist', 'playlist', 'episode', 'show'], true)) {
                return null;
            }

            if (preg_match('/^[A-Za-z0-9]{10,64}$/', $resourceId) !== 1) {
                return null;
            }

            return smartEmbedPackFromId($service, $type . ':' . $resourceId);
        }

        if ($service === 'calendly') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            if ($path === '') {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            if (empty($segments)) {
                return null;
            }

            $normalizedSegments = [];
            foreach ($segments as $segment) {
                $segment = trim(urldecode((string) $segment));
                if (preg_match('/^[A-Za-z0-9._-]{1,80}$/', $segment) !== 1) {
                    return null;
                }
                $normalizedSegments[] = $segment;
            }

            return smartEmbedPackFromId($service, implode('/', array_slice($normalizedSegments, 0, 4)));
        }

        if ($service === 'tally') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $firstSegment = (string) ($segments[0] ?? '');
            $secondSegment = (string) ($segments[1] ?? '');

            if (!in_array($firstSegment, ['r', 'form', 'forms', 'embed'], true) || $secondSegment === '') {
                return null;
            }

            if (preg_match('/^[A-Za-z0-9]{4,64}$/', $secondSegment) !== 1) {
                return null;
            }

            return smartEmbedPackFromId($service, $firstSegment . ':' . $secondSegment);
        }

        if ($service === 'gumroad') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $firstSegment = strtolower((string) ($segments[0] ?? ''));
            $secondSegment = trim((string) ($segments[1] ?? ''));

            if ($firstSegment !== 'l' || $secondSegment === '') {
                return null;
            }

            if (preg_match('/^[A-Za-z0-9_-]{2,120}$/', $secondSegment) !== 1) {
                return null;
            }

            return smartEmbedPackFromId($service, strtolower($host) . '/l/' . $secondSegment);
        }

        if ($service === 'kit') {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $normalizedHost = strtolower($host);
            if (str_starts_with($normalizedHost, 'www.')) {
                $normalizedHost = substr($normalizedHost, 4);
            }

            if (preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.kit\.com$/', $normalizedHost, $hostMatches) !== 1) {
                return null;
            }

            $accountHandle = strtolower($hostMatches[1]);
            if (smartEmbedIsBlockedKitSubdomain($accountHandle)) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $uidCandidate = trim((string) ($segments[0] ?? ''));

            if ($uidCandidate === '' && preg_match('/\bdata-uid=(["\'])([a-f0-9]{8,64})\1/i', $trimmedRawUrl, $uidMatches) === 1) {
                $uidCandidate = (string) ($uidMatches[2] ?? '');
            }

            if (preg_match('/^[a-f0-9]{8,64}$/i', $uidCandidate) !== 1) {
                return null;
            }

            return smartEmbedPackFromId($service, $accountHandle . ':' . strtolower($uidCandidate));
        }

        if (in_array($service, ['resmio_booking', 'resmio_menu'], true)) {
            if (!smartEmbedHostMatches($host, $catalog[$service]['hosts'])) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', $path), 'strlen'));
            $restaurantSlug = smartEmbedExtractResmioSlug((string) ($segments[0] ?? ''));
            $widgetType = strtolower((string) ($segments[1] ?? ''));

            if ($restaurantSlug === null) {
                return null;
            }

            if ($service === 'resmio_booking' && $widgetType === 'widget') {
                return smartEmbedPackFromId($service, $restaurantSlug);
            }

            if ($service === 'resmio_menu' && $widgetType === 'menu-widget') {
                return smartEmbedPackFromId($service, $restaurantSlug);
            }

            return null;
        }

        return null;
    }
}

if (!function_exists('handleLinkType_smart_embed')) {
    /**
     * Handles the logic for "smart_embed" link type.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $linkType
     * @return array
     */
    function handleLinkType_smart_embed($request, $linkType)
    {
        $catalog = smartEmbedServiceCatalog();
        $selectedService = strtolower((string) $request->input('embed_service', 'youtube'));
        $parsedEmbed = smartEmbedParse($selectedService, (string) $request->input('embed_source_url', ''));

        $rules = [
            'internal_title' => [
                'nullable',
                'string',
                'max:120',
            ],
            'embed_service' => [
                'required',
                'in:youtube,instagram,google_maps,spotify,calendly,tally,gumroad,kit,resmio_booking,resmio_menu',
            ],
            'embed_source_url' => [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) use ($request) {
                    $service = strtolower((string) $request->input('embed_service', ''));
                    if (smartEmbedParse($service, (string) $value) === null) {
                        if ($service === 'kit') {
                            $fail('Please provide a valid Kit hosted URL (for example: https://youraccount.kit.com/4b70f698c6).');
                            return;
                        }

                        if ($service === 'resmio_booking') {
                            $fail('Please provide a valid Resmio booking URL (for example: https://app.resmio.com/your-restaurant/widget).');
                            return;
                        }

                        if ($service === 'resmio_menu') {
                            $fail('Please provide a valid Resmio menu URL (for example: https://app.resmio.com/your-restaurant/menu-widget).');
                            return;
                        }

                        $fail('Please provide a valid URL for the selected service.');
                    }
                },
            ],
        ];

        $finalService = $parsedEmbed['service'] ?? $selectedService;
        $serviceLabel = $catalog[$finalService]['label'] ?? 'Embed';
        $internalTitle = trim(strip_tags((string) $request->input('internal_title', '')));
        $locale = strtolower((string) app()->getLocale());
        $fallbackTitle = str_starts_with($locale, 'de')
            ? ($serviceLabel . ' Inhalt')
            : ($serviceLabel . ' content');

        $linkData = [
            'title' => $fallbackTitle,
            'internal_title' => $internalTitle,
            'title_is_fallback' => true,
            'title_fallback_source' => 'service_label',
            'button_id' => '1',
            'link' => null,
            'embed_service' => $finalService,
            'embed_service_label' => $serviceLabel,
            'embed_id' => $parsedEmbed['embed_id'] ?? null,
            'embed_url' => $parsedEmbed['embed_url'] ?? null,
            'embed_source_url' => $parsedEmbed['source_url'] ?? null,
            'embed_allow' => $catalog[$finalService]['allow'] ?? '',
        ];

        return ['rules' => $rules, 'linkData' => $linkData];
    }
}

if (!function_exists('handleLinkType')) {
    function handleLinkType($request, $linkType)
    {
        return handleLinkType_smart_embed($request, $linkType);
    }
}
