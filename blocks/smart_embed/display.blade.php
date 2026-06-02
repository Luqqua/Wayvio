@php
    $currentLocale = strtolower((string) app()->getLocale());
    $isEnglishLocale = str_starts_with($currentLocale, 'en');

    $serviceDefinitions = [
        'youtube' => [
            'name' => 'YouTube',
            'badge' => 'YT',
            'ratio' => '16 / 9',
            'min_height' => 220,
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'consent_provider' => 'youtube',
        ],
        'instagram' => [
            'name' => 'Instagram',
            'badge' => 'IG',
            'ratio' => '3 / 5',
            'min_height' => 760,
            'allow' => '',
            'consent_provider' => 'instagram',
        ],
        'google_maps' => [
            'name' => 'Google Maps',
            'badge' => 'MAP',
            'ratio' => '16 / 9',
            'min_height' => 240,
            'allow' => 'geolocation',
            'consent_provider' => 'google_maps',
        ],
        'spotify' => [
            'name' => 'Spotify',
            'badge' => 'SP',
            'ratio' => '16 / 9',
            'min_height' => 352,
            'allow' => 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
            'consent_provider' => 'spotify',
        ],
        'calendly' => [
            'name' => 'Calendly',
            'badge' => 'CA',
            'ratio' => '3 / 5',
            'min_height' => 780,
            'allow' => '',
            'consent_provider' => 'calendly',
        ],
        'tally' => [
            'name' => 'Tally',
            'badge' => 'TA',
            'ratio' => '4 / 5',
            'min_height' => 520,
            'allow' => '',
            'consent_provider' => 'tally',
        ],
        'gumroad' => [
            'name' => 'Gumroad',
            'badge' => 'GR',
            'ratio' => '4 / 5',
            'min_height' => 520,
            'allow' => '',
            'consent_provider' => 'gumroad',
        ],
        'kit' => [
            'name' => 'Kit',
            'badge' => 'KT',
            'ratio' => '4 / 5',
            'min_height' => 300,
            'allow' => '',
            'consent_provider' => 'kit',
        ],
        'resmio_booking' => [
            'name' => 'Resmio Buchung',
            'badge' => 'RS',
            'ratio' => '43 / 60',
            'min_height' => 600,
            'allow' => '',
            'consent_provider' => 'resmio',
        ],
        'resmio_menu' => [
            'name' => 'Resmio Speisekarte',
            'badge' => 'RM',
            'ratio' => '4 / 5',
            'min_height' => 805,
            'allow' => '',
            'consent_provider' => 'resmio',
        ],
    ];

    $serviceKey = is_string($link->embed_service ?? null) ? strtolower(trim($link->embed_service)) : '';
    $service = $serviceDefinitions[$serviceKey] ?? null;
    $embedId = is_string($link->embed_id ?? null) ? trim($link->embed_id) : '';

    $buildUrlsFromId = static function (string $service, string $embedId): ?array {
        if ($service === 'youtube') {
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $embedId) === 1) {
                return [
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $embedId,
                    'source_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($embedId),
                ];
            }

            if (preg_match('/^playlist:([A-Za-z0-9_-]{10,})$/', $embedId, $matches) === 1) {
                return [
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/videoseries?list=' . rawurlencode($matches[1]),
                    'source_url' => 'https://www.youtube.com/playlist?list=' . rawurlencode($matches[1]),
                ];
            }

            return null;
        }

        if ($service === 'google_maps') {
            if (preg_match('/^pb:(.+)$/', $embedId, $matches) === 1) {
                return [
                    'embed_url' => 'https://www.google.com/maps/embed?pb=' . rawurlencode(trim($matches[1])),
                    'source_url' => 'https://www.google.com/maps/embed?pb=' . rawurlencode(trim($matches[1])),
                ];
            }

            if (preg_match('/^place_id:([A-Za-z0-9_-]{8,220})$/', $embedId, $matches) === 1) {
                return [
                    'embed_url' => 'https://www.google.com/maps?q=' . rawurlencode('place_id:' . $matches[1]) . '&output=embed',
                    'source_url' => 'https://www.google.com/maps/search/?api=1&query_place_id=' . rawurlencode($matches[1]),
                ];
            }

            if (preg_match('/^cid:([0-9]{6,25})$/', $embedId, $matches) === 1) {
                return [
                    'embed_url' => 'https://www.google.com/maps?cid=' . rawurlencode($matches[1]) . '&output=embed',
                    'source_url' => 'https://www.google.com/maps?cid=' . rawurlencode($matches[1]),
                ];
            }

            if (preg_match('/^short:([A-Za-z0-9_-]{8,64})$/', $embedId, $matches) === 1) {
                $shortUrl = 'https://maps.app.goo.gl/' . $matches[1];
                return [
                    'embed_url' => 'https://www.google.com/maps?q=' . rawurlencode($shortUrl) . '&output=embed',
                    'source_url' => $shortUrl,
                ];
            }

            return null;
        }

        if ($service === 'spotify' && preg_match('/^(track|album|artist|playlist|episode|show):([A-Za-z0-9]{10,64})$/', $embedId, $matches) === 1) {
            return [
                'embed_url' => 'https://open.spotify.com/embed/' . $matches[1] . '/' . $matches[2],
                'source_url' => 'https://open.spotify.com/' . $matches[1] . '/' . $matches[2],
            ];
        }

        if ($service === 'instagram' && preg_match('/^[A-Za-z0-9_-]{5,64}$/', $embedId) === 1) {
            return [
                'embed_url' => 'https://www.instagram.com/p/' . rawurlencode($embedId) . '/embed/',
                'source_url' => 'https://www.instagram.com/p/' . rawurlencode($embedId) . '/',
            ];
        }

        if ($service === 'calendly' && preg_match('/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+){0,3}$/', $embedId) === 1) {
            return [
                'embed_url' => 'https://calendly.com/' . $embedId,
                'source_url' => 'https://calendly.com/' . $embedId,
            ];
        }

        if ($service === 'tally' && preg_match('/^(r|form|forms|embed):([A-Za-z0-9]{4,64})$/', $embedId, $matches) === 1) {
            return [
                'embed_url' => 'https://tally.so/' . $matches[1] . '/' . $matches[2],
                'source_url' => 'https://tally.so/' . $matches[1] . '/' . $matches[2],
            ];
        }

        if ($service === 'tally' && preg_match('/^[A-Za-z0-9]{4,64}$/', $embedId) === 1) {
            return [
                'embed_url' => 'https://tally.so/r/' . $embedId,
                'source_url' => 'https://tally.so/r/' . $embedId,
            ];
        }

        if ($service === 'gumroad' && preg_match('/^([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)\/l\/([A-Za-z0-9_-]{2,120})$/', $embedId, $matches) === 1) {
            $productUrl = 'https://' . strtolower($matches[1]) . '/l/' . rawurlencode($matches[2]);
            return [
                'embed_url' => $productUrl,
                'source_url' => $productUrl,
            ];
        }

        if ($service === 'kit' && preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?):([a-f0-9]{8,64})$/', $embedId, $matches) === 1) {
            $kitUrl = 'https://' . strtolower($matches[1]) . '.kit.com/' . strtolower($matches[2]);
            return [
                'embed_url' => $kitUrl,
                'source_url' => $kitUrl,
            ];
        }

        if ($service === 'resmio_booking' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,80}$/', $embedId) === 1) {
            $slug = strtolower($embedId);
            $bookingUrl = 'https://app.resmio.com/' . rawurlencode($slug) . '/widget';
            return [
                'embed_url' => $bookingUrl,
                'source_url' => $bookingUrl,
            ];
        }

        if ($service === 'resmio_menu' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,80}$/', $embedId) === 1) {
            $slug = strtolower($embedId);
            $menuUrl = 'https://app.resmio.com/' . rawurlencode($slug) . '/menu-widget';
            return [
                'embed_url' => $menuUrl,
                'source_url' => $menuUrl,
            ];
        }

        return null;
    };

    $resolvedUrls = $service !== null && $embedId !== '' ? $buildUrlsFromId($serviceKey, $embedId) : null;
    $embedUrl = is_array($resolvedUrls) ? (string) ($resolvedUrls['embed_url'] ?? '') : '';
    $sourceUrl = is_array($resolvedUrls) ? (string) ($resolvedUrls['source_url'] ?? '') : '';
    $isSecureEmbed = $embedUrl !== '' && preg_match('/^https:\/\//i', $embedUrl) === 1;

    $isRenderable = $service !== null && $isSecureEmbed;
    $embedAllow = is_string($link->embed_allow ?? null) && $link->embed_allow !== ''
        ? trim($link->embed_allow)
        : ($service['allow'] ?? '');

    $consentProvider = is_string($service['consent_provider'] ?? null) ? $service['consent_provider'] : '';
    $requiresCentralConsent = $consentProvider !== '';

    $displayTitle = trim((string) ($link->title ?? ''));
    if ($displayTitle === '') {
        $displayTitle = ($service['name'] ?? 'Externer Inhalt') . ' Inhalt';
    }

    $externalUrl = $sourceUrl !== '' ? $sourceUrl : $embedUrl;
    $serviceName = $service['name'] ?? 'Externer Dienst';
    $placeholderDescription = $requiresCentralConsent
        ? 'Externer Inhalt von ' . $serviceName . '. Zur Aktivierung ist Ihre Zustimmung erforderlich.'
        : 'Externer Inhalt von ' . $serviceName . '. Wird automatisch geladen, sobald der Block sichtbar ist.';
    $rawGlobalCustomCss = \App\Models\UserData::getData($userinfo->id ?? null, 'global_custom_button_css');
    $globalCustomCss = is_string($rawGlobalCustomCss) ? trim($rawGlobalCustomCss) : '';
    if (strtolower($globalCustomCss) === 'null') {
        $globalCustomCss = '';
    }
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $allowCustomButtons = templateCapability($themeForCapabilities, 'custom_buttons', $themeForCapabilities === 'default');
    $useGlobalCustomStyle = ($globalCustomCss !== '') && $allowCustomButtons;

    $privacyPolicyUrl = '';
    try {
        $domainResolver = app(\App\Services\Domains\DomainUrlResolver::class);
        $profileOwner = $domainResolver->ownerForPageUser($userinfo);
        $profileUrl = $domainResolver->profileUrlForEditor($profileOwner, $userinfo);
        if (is_string($profileUrl) && trim($profileUrl) !== '') {
            $privacyPolicyUrl = rtrim($profileUrl, '/') . '/privacy';
        }
    } catch (\Throwable $exception) {
        $privacyPolicyUrl = '';
    }

    if ($privacyPolicyUrl === '') {
        $littlelink = trim((string) ($userinfo->littlelink ?? ''));
        if ($littlelink !== '' && \Illuminate\Support\Facades\Route::has('privacy')) {
            $privacyPolicyUrl = route('privacy', ['littlelink' => $littlelink]);
        }
    }

    if ($privacyPolicyUrl === '') {
        $privacyPolicyUrl = route('pagesPrivacy');
    }

    $privacyDetailsLabel = $isEnglishLocale ? 'Privacy details' : 'Datenschutzdetails';
    $adjustSelectionLabel = $isEnglishLocale ? 'Adjust selection' : 'Auswahl anpassen';
@endphp

@once
<style>
    .ls-smart-embed-card {
        --ls-smart-embed-card-radius: 16px;
        width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
        box-sizing: border-box;
        margin: 0 auto;
        border-radius: var(--ls-smart-embed-card-radius);
        border: 1px solid rgba(255, 255, 255, 0.22);
        background: rgba(0, 0, 0, 0.16);
        backdrop-filter: blur(2px);
        overflow: hidden;
    }

    .ls-smart-embed__frame {
        width: 100%;
        min-height: var(--ls-smart-embed-min-height, 220px);
        aspect-ratio: var(--ls-smart-embed-ratio, 16 / 9);
        position: relative;
        background: #dde1e4;
    }

    .ls-smart-embed__placeholder {
        height: 100%;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        text-align: center;
        color: #1f2933;
        background: linear-gradient(180deg, #eceff1 0%, #d9dee2 100%);
    }

    .ls-smart-embed__placeholder[hidden] {
        display: none !important;
    }

    .ls-smart-embed__icon {
        width: 54px;
        height: 54px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: var(--ls-type-meta-size, 0.84rem);
        font-weight: var(--ls-type-section-weight, 700);
        letter-spacing: 0;
        background: rgba(31, 41, 51, 0.12);
        color: #1f2933;
    }

    .ls-smart-embed__title {
        margin: 12px 0 8px;
        font-size: var(--ls-type-section-size, 0.98rem);
        font-weight: var(--ls-type-section-weight, 700);
        line-height: var(--ls-type-section-line, 1.3);
    }

    .ls-smart-embed__description {
        margin: 0;
        font-size: var(--ls-type-small-size, 0.9rem);
        line-height: var(--ls-type-small-line, 1.5);
        color: #33404b;
        max-width: 440px;
    }

    .ls-smart-embed__button {
        margin-top: 12px;
        border: 0;
        border-radius: 999px;
        padding: 9px 18px;
        background: #1f2933;
        color: #ffffff;
        font-size: var(--ls-type-small-size, 0.84rem);
        line-height: var(--ls-type-small-line, 1.45);
        font-weight: 600;
        letter-spacing: 0;
        cursor: pointer;
        transition: transform 0.16s ease, opacity 0.16s ease;
    }

    .ls-smart-embed__button:hover,
    .ls-smart-embed__button:focus-visible {
        transform: translateY(-1px);
        opacity: 0.95;
    }

    .ls-smart-embed__external-link {
        margin-top: 10px;
        display: inline-block;
        color: #1f2933;
        font-size: var(--ls-type-small-size, 0.84rem);
        line-height: var(--ls-type-small-line, 1.45);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
        word-break: break-word;
    }

    .ls-smart-embed__notice {
        margin: 10px 0 0;
        font-size: var(--ls-type-meta-size, 0.83rem);
        line-height: var(--ls-type-meta-line, 1.25);
        color: #4a5560;
        max-width: 440px;
    }

    .ls-smart-embed__iframe {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        min-height: var(--ls-smart-embed-min-height, 220px);
        border: 0;
    }

    .ls-smart-embed__gumroad {
        position: relative;
        background: #ffffff;
        overflow: hidden;
        width: 100%;
        min-height: var(--ls-smart-embed-min-height, 520px);
    }

    .ls-smart-embed__gumroad .gumroad-product-embed {
        width: 100%;
        height: auto;
        overflow: hidden;
    }

    .ls-smart-embed__gumroad iframe {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        border: 0 !important;
        overflow: hidden !important;
    }

    .ls-smart-embed__kit {
        width: 100%;
        min-height: var(--ls-smart-embed-min-height, 300px);
        background: #ffffff;
        overflow: hidden;
    }

    .ls-smart-embed__kit .formkit-form {
        margin: 0 auto !important;
        max-width: 100% !important;
    }

    .ls-smart-embed__resmio {
        width: 100%;
        min-height: var(--ls-smart-embed-min-height, 600px);
        display: flex;
        justify-content: center;
        align-items: flex-start;
        overflow: hidden;
        background: #ffffff;
    }

    .ls-smart-embed-card--gumroad {
        --ls-smart-embed-gumroad-top-cap: 12px;
        position: relative;
        isolation: isolate;
    }

    .ls-smart-embed-card--gumroad .ls-smart-embed__frame {
        aspect-ratio: auto;
        min-height: calc(var(--ls-smart-embed-min-height, 520px) + var(--ls-smart-embed-gumroad-top-cap));
        height: auto;
        overflow: hidden;
        background: #ffffff;
        position: relative;
        padding-top: var(--ls-smart-embed-gumroad-top-cap);
        box-sizing: border-box;
        border-top-left-radius: var(--ls-smart-embed-card-radius, 16px);
        border-top-right-radius: var(--ls-smart-embed-card-radius, 16px);
    }

    .ls-smart-embed-card--gumroad .ls-smart-embed__frame::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        height: var(--ls-smart-embed-gumroad-top-cap);
        background: #ffffff;
        pointer-events: none;
        z-index: 2;
    }

    .ls-smart-embed-card--gumroad .ls-smart-embed__gumroad {
        position: relative;
        z-index: 1;
    }

    .ls-smart-embed-card--gumroad .ls-smart-embed__frame,
    .ls-smart-embed-card--gumroad .ls-smart-embed__gumroad,
    .ls-smart-embed-card--gumroad .ls-smart-embed__gumroad .gumroad-product-embed,
    .ls-smart-embed-card--gumroad .ls-smart-embed__gumroad iframe {
        -webkit-transform: translateZ(0);
        transform: translateZ(0);
        backface-visibility: hidden;
    }

    .ls-smart-embed-card--kit .ls-smart-embed__frame {
        aspect-ratio: auto;
        min-height: var(--ls-smart-embed-min-height, 300px);
        height: auto;
        overflow: visible;
        background: #ffffff;
    }

    .ls-smart-embed-card--resmio .ls-smart-embed__frame {
        aspect-ratio: auto;
        min-height: var(--ls-smart-embed-min-height, 600px);
        height: auto;
        overflow: visible;
        background: #ffffff;
    }

    .ls-smart-embed__fallback {
        padding: 16px;
        color: #1f2933;
        background: #eceff1;
        text-align: center;
    }

    .ls-smart-embed__fallback p {
        margin: 0;
        font-size: var(--ls-type-body-size, 0.95rem);
        line-height: var(--ls-type-body-line, 1.5);
    }

    .ls-smart-embed__fallback a {
        margin-top: 10px;
        display: inline-block;
        color: #1f2933;
        font-weight: 600;
        text-decoration: underline;
    }

    .ls-smart-embed-link[hidden] {
        display: none !important;
    }

    .ls-consent-banner {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
        padding: 9px 10px calc(9px + env(safe-area-inset-bottom));
        background: #ffffff;
        color: #1c2630;
        border-top: 1px solid #d3dbe3;
        box-shadow: 0 -8px 20px rgba(13, 24, 35, 0.18);
        max-height: 60vh;
        overflow-y: auto;
    }

    .ls-consent-banner h2 {
        margin: 0;
        font-size: var(--ls-type-section-size, 0.92rem);
        line-height: var(--ls-type-section-line, 1.28);
        font-weight: var(--ls-type-section-weight, 700);
    }

    .ls-consent-banner p {
        margin: 0;
        font-size: var(--ls-type-small-size, 0.82rem);
        line-height: var(--ls-type-small-line, 1.4);
    }

    .ls-consent-banner a {
        color: #0f4773;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .ls-consent-banner__copy {
        margin-top: 6px;
    }

    .ls-consent-banner__actions {
        margin-top: 8px;
        display: grid;
        gap: 6px;
    }

    .ls-consent-banner__actions button,
    .ls-consent-banner__confirm {
        width: 100%;
        min-height: 46px;
        box-sizing: border-box;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #1f2933;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: var(--ls-type-small-size, 0.83rem);
        font-weight: 600;
        line-height: var(--ls-type-small-line, 1.45);
        background: #ffffff;
        color: #1f2933;
        cursor: pointer;
    }

    .ls-consent-banner__actions button[data-consent-action='accept-all'] {
        background: #1f2933;
        color: #ffffff;
    }

    .ls-consent-banner [hidden] {
        display: none !important;
    }

    .ls-consent-banner__actions button:hover,
    .ls-consent-banner__actions button:focus-visible {
        opacity: 0.9;
    }

    .ls-consent-banner__selection {
        margin-top: 8px;
        border-top: 1px solid #d8e0e7;
        padding-top: 10px;
    }

    .ls-consent-banner__checks {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 8px;
    }

    .ls-consent-banner__checks li {
        margin: 0;
    }

    .ls-consent-banner__checks label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: var(--ls-type-small-size, 0.84rem);
        line-height: var(--ls-type-small-line, 1.35);
        cursor: pointer;
    }

    .ls-consent-banner__checks input[type='checkbox'] {
        margin: 0;
        width: 18px;
        height: 18px;
        flex: 0 0 18px;
        accent-color: #1f2933;
    }

    .ls-consent-banner__check-text {
        display: inline-block;
        line-height: var(--ls-type-meta-line, 1.25);
    }

    .ls-consent-banner__confirm {
        margin-top: 10px;
        background: #ffffff;
        color: #1f2933;
    }

    .ls-consent-banner__confirm:hover,
    .ls-consent-banner__confirm:focus-visible {
        opacity: 0.9;
    }

    .ls-consent-banner__meta {
        margin-top: 7px;
    }

    .ls-consent-banner__status {
        margin-top: 7px;
        min-height: 1.1em;
        font-size: var(--ls-type-meta-size, 0.79rem);
        color: #36526b;
    }

    @media (min-width: 768px) {
        .ls-smart-embed-card {
            width: clamp(260px, 92vw, 520px);
        }

        .ls-consent-banner {
            left: 50%;
            right: auto;
            bottom: 12px;
            transform: translateX(-50%);
            width: min(510px, calc(100vw - 24px));
            border: 1px solid #d3dbe3;
            border-radius: 12px;
            padding: 12px;
            max-height: 70vh;
            box-shadow: 0 14px 32px rgba(13, 24, 35, 0.25);
        }
    }

    @media (max-width: 768px) {
        .ls-smart-embed-card {
            width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
        }
    }
</style>

@push('wayvio-body-end')
<section
    class="ls-consent-banner"
    data-consent-banner
    role="region"
    aria-labelledby="ls-consent-title"
    aria-describedby="ls-consent-description"
    hidden
>
    <h2 id="ls-consent-title">Externe Medien nur nach Zustimmung</h2>
    <div class="ls-consent-banner__copy">
        <p id="ls-consent-description">
            Ohne Einwilligung bleiben externe Inhalte als normale Links sichtbar.
        </p>
        <p data-consent-used-providers hidden></p>
    </div>

    <div class="ls-consent-banner__actions">
        <button type="button" data-consent-action="accept-all">Alle akzeptieren</button>
        <button type="button" data-consent-action="adjust-selection">{{ $adjustSelectionLabel }}</button>
        <button type="button" data-consent-action="reject-all">Alle ablehnen</button>
    </div>

    <div class="ls-consent-banner__selection" data-consent-selection hidden>
        <ul class="ls-consent-banner__checks" data-consent-provider-list></ul>
        <button type="button" class="ls-consent-banner__confirm" data-consent-action="confirm-selection">Auswahl bestätigen</button>
    </div>

    <div class="ls-consent-banner__meta">
        <p><a href="{{ $privacyPolicyUrl }}" target="_blank" rel="noopener noreferrer nofollow">{{ $privacyDetailsLabel }}</a></p>
    </div>

    <p class="ls-consent-banner__status" data-consent-status aria-live="polite"></p>
</section>
@endpush

@php
    $consentScopeSegments = [];
    $consentScopeUserId = isset($userinfo->id) ? (int) $userinfo->id : (isset($link->user_id) ? (int) $link->user_id : 0);
    $consentScopeSlug = trim((string) ($littlelink_name ?? ($userinfo->littlelink_name ?? '')));
    if ($consentScopeUserId > 0) {
        $consentScopeSegments[] = 'user:' . $consentScopeUserId;
    }
    if ($consentScopeSlug !== '') {
        $consentScopeSegments[] = 'slug:' . strtolower($consentScopeSlug);
    }
    $consentScopeToken = implode('|', $consentScopeSegments);
@endphp
<script>
(function () {
    if (window.__wayvioSmartEmbedReady) {
        return;
    }
    window.__wayvioSmartEmbedReady = true;

    function init() {
        // Consent payload is versioned, so text/policy updates can force a fresh choice.
        var CONSENT_STORAGE_KEY_BASE = 'wayvio_external_media_consent';
        var CONSENT_SCOPE_TOKEN = @json($consentScopeToken);
        var CONSENT_VERSION = '2026-03-31.1';
        var GUMROAD_EMBED_SCRIPT_URL = 'https://gumroad.com/js/gumroad-embed.js';
        var RESMIO_BOOKING_SCRIPT_BASE = 'https://static.resmio.com/static/de/widget.js';
        var EAGER_EMBED_LIMIT = 4;
        var LAZY_EMBED_ROOT_MARGIN = '200px 0px';
        var MANAGED_PROVIDERS = ['youtube', 'instagram', 'google_maps', 'spotify', 'calendly', 'tally', 'gumroad', 'kit', 'resmio'];
        var PROVIDER_LABELS = {
            youtube: 'YouTube',
            instagram: 'Instagram',
            google_maps: 'Google Maps',
            spotify: 'Spotify',
            calendly: 'Calendly',
            tally: 'Tally',
            gumroad: 'Gumroad',
            kit: 'Kit',
            resmio: 'Resmio',
        };
        var supportsIntersectionObserver = typeof window.IntersectionObserver === 'function';
        var lazyEmbedObserver = null;

        function toArray(nodeList) {
            return Array.prototype.slice.call(nodeList || []);
        }

        function nowIso() {
            return new Date().toISOString();
        }

        function normalizeScopeToken(rawScopeToken) {
            if (typeof rawScopeToken !== 'string') {
                return '';
            }

            var scopeToken = rawScopeToken.trim().toLowerCase();
            return scopeToken.replace(/\s+/g, '');
        }

        function normalizePathForScope(pathname) {
            if (typeof pathname !== 'string') {
                return '/';
            }

            var normalizedPath = pathname.trim().toLowerCase();
            if (normalizedPath === '') {
                return '/';
            }

            normalizedPath = normalizedPath.replace(/\/+$/, '');
            return normalizedPath === '' ? '/' : normalizedPath;
        }

        function resolveConsentStorageKey() {
            var normalizedScopeToken = normalizeScopeToken(CONSENT_SCOPE_TOKEN);
            if (normalizedScopeToken !== '') {
                return CONSENT_STORAGE_KEY_BASE + ':' + normalizedScopeToken;
            }

            var fallbackPath = normalizePathForScope(window.location && window.location.pathname);
            return CONSENT_STORAGE_KEY_BASE + ':path:' + encodeURIComponent(fallbackPath);
        }

        var CONSENT_STORAGE_KEY = resolveConsentStorageKey();

        function assignEmbedLoadingModes() {
            toArray(document.querySelectorAll('[data-smart-embed-root]')).forEach(function (embedRoot, index) {
                var shouldLazyLoad = supportsIntersectionObserver && index >= EAGER_EMBED_LIMIT;
                embedRoot.dataset.embedLoadMode = shouldLazyLoad ? 'lazy' : 'eager';
                embedRoot.dataset.embedIndex = String(index);
                embedRoot.dataset.observing = '0';
            });
        }

        function baseConsentState() {
            return {
                version: CONSENT_VERSION,
                decisionMade: false,
                decidedAt: null,
                updatedAt: null,
                selection: 'unset',
                providers: {
                    youtube: false,
                    instagram: false,
                    google_maps: false,
                    spotify: false,
                    calendly: false,
                    tally: false,
                    gumroad: false,
                    kit: false,
                    resmio: false,
                },
            };
        }

        function normalizeConsent(raw) {
            var normalized = baseConsentState();
            if (!raw || typeof raw !== 'object') {
                return normalized;
            }

            var providers = raw.providers && typeof raw.providers === 'object' ? raw.providers : {};
            MANAGED_PROVIDERS.forEach(function (provider) {
                normalized.providers[provider] = providers[provider] === true;
            });

            normalized.version = typeof raw.version === 'string' ? raw.version : CONSENT_VERSION;
            normalized.decisionMade = raw.decisionMade === true;
            normalized.decidedAt = typeof raw.decidedAt === 'string' ? raw.decidedAt : null;
            normalized.updatedAt = typeof raw.updatedAt === 'string' ? raw.updatedAt : null;
            normalized.selection = typeof raw.selection === 'string' ? raw.selection : 'unset';

            if (normalized.version !== CONSENT_VERSION) {
                return baseConsentState();
            }

            return normalized;
        }

        var memoryConsent = null;

        function readConsent() {
            try {
                var stored = window.localStorage.getItem(CONSENT_STORAGE_KEY);
                if (!stored) {
                    return memoryConsent ? normalizeConsent(memoryConsent) : baseConsentState();
                }
                return normalizeConsent(JSON.parse(stored));
            } catch (error) {
                return memoryConsent ? normalizeConsent(memoryConsent) : baseConsentState();
            }
        }

        function writeConsent(consentState) {
            var payload = normalizeConsent(consentState);
            memoryConsent = payload;

            try {
                window.localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify(payload));
            } catch (error) {
                // localStorage blocked: in-memory fallback remains active
            }
        }

        function clearConsent() {
            memoryConsent = null;
            try {
                window.localStorage.removeItem(CONSENT_STORAGE_KEY);
            } catch (error) {
                // localStorage blocked
            }
        }

        function clearDynamicEmbedContent(mountNode) {
            if (!mountNode) {
                return;
            }

            toArray(mountNode.querySelectorAll('[data-smart-embed-dynamic]')).forEach(function (dynamicNode) {
                dynamicNode.remove();
            });
        }

        function setPlaceholderVisibility(rootElement, shouldShow) {
            if (!rootElement) {
                return;
            }

            var placeholder = rootElement.querySelector('[data-smart-embed-placeholder]');
            if (!placeholder) {
                return;
            }

            if (shouldShow) {
                placeholder.hidden = false;
                placeholder.style.removeProperty('display');
                return;
            }

            placeholder.hidden = true;
            placeholder.style.setProperty('display', 'none', 'important');
        }

        function ensureGumroadEmbedScript() {
            if (window.GumroadEmbed) {
                return Promise.resolve();
            }

            if (window.__wayvioGumroadEmbedPromise) {
                return window.__wayvioGumroadEmbedPromise;
            }

            window.__wayvioGumroadEmbedPromise = new Promise(function (resolve) {
                var existingScript = document.querySelector('script[src="' + GUMROAD_EMBED_SCRIPT_URL + '"]');
                if (existingScript) {
                    if (existingScript.dataset.wayvioLoaded === '1' || window.GumroadEmbed) {
                        existingScript.dataset.wayvioLoaded = '1';
                        resolve();
                        return;
                    }

                    existingScript.addEventListener('load', function () {
                        existingScript.dataset.wayvioLoaded = '1';
                        resolve();
                    }, { once: true });

                    existingScript.addEventListener('error', function () {
                        resolve();
                    }, { once: true });

                    return;
                }

                var scriptElement = document.createElement('script');
                scriptElement.src = GUMROAD_EMBED_SCRIPT_URL;
                scriptElement.async = true;
                scriptElement.addEventListener('load', function () {
                    scriptElement.dataset.wayvioLoaded = '1';
                    resolve();
                }, { once: true });
                scriptElement.addEventListener('error', function () {
                    resolve();
                }, { once: true });
                document.body.appendChild(scriptElement);
            });

            return window.__wayvioGumroadEmbedPromise;
        }

        function loadIframeInto(rootElement) {
            if (!rootElement) {
                return;
            }

            var serviceKey = (rootElement.dataset.serviceKey || '').toLowerCase();

            if (rootElement.dataset.loaded === '1') {
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            var embedUrl = rootElement.dataset.embedUrl || '';
            if (!mountNode || !/^https:\/\//i.test(embedUrl)) {
                return;
            }

            var existingIframe = mountNode.querySelector('iframe[data-smart-embed-dynamic="iframe"]');
            if (existingIframe) {
                rootElement.dataset.loaded = '1';
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            clearDynamicEmbedContent(mountNode);

            var iframe = document.createElement('iframe');
            iframe.className = 'ls-smart-embed__iframe';
            iframe.src = embedUrl;
            iframe.loading = rootElement.dataset.embedLoadMode === 'lazy' ? 'lazy' : 'eager';
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            iframe.setAttribute('data-smart-embed-dynamic', 'iframe');
            iframe.title = (rootElement.dataset.serviceName || 'Embedded content') + ' content';
            if (serviceKey === 'instagram' || serviceKey === 'calendly') {
                iframe.setAttribute('scrolling', 'no');
                iframe.style.overflow = 'hidden';
            }

            var allowValue = rootElement.dataset.embedAllow || '';
            if (allowValue) {
                iframe.setAttribute('allow', allowValue);
            }

            iframe.setAttribute('allowfullscreen', 'allowfullscreen');
            mountNode.appendChild(iframe);
            rootElement.dataset.loaded = '1';
            setPlaceholderVisibility(rootElement, false);
        }

        function applyNoScrollToGumroadFrames(rootElement) {
            if (!rootElement) {
                return;
            }

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            if (!mountNode) {
                return;
            }

            toArray(mountNode.querySelectorAll('.ls-smart-embed__gumroad iframe')).forEach(function (iframe) {
                iframe.setAttribute('scrolling', 'no');
                iframe.style.overflow = 'hidden';
                iframe.style.display = 'block';
                iframe.style.width = '100%';
                iframe.style.maxWidth = '100%';
                iframe.style.border = '0';
            });
        }

        function scheduleGumroadNoScroll(rootElement) {
            var attempts = 0;
            var maxAttempts = 14;

            function tick() {
                applyNoScrollToGumroadFrames(rootElement);
                attempts += 1;

                if (attempts < maxAttempts && rootElement && rootElement.dataset.loaded === '1') {
                    window.setTimeout(tick, 140);
                }
            }

            tick();
        }

        function watchGumroadFrames(rootElement, gumroadWrapper) {
            if (!rootElement || !gumroadWrapper || typeof MutationObserver === 'undefined') {
                return;
            }

            if (rootElement.__wayvioGumroadObserver && typeof rootElement.__wayvioGumroadObserver.disconnect === 'function') {
                rootElement.__wayvioGumroadObserver.disconnect();
            }

            var observer = new MutationObserver(function () {
                applyNoScrollToGumroadFrames(rootElement);
            });

            observer.observe(gumroadWrapper, {
                childList: true,
                subtree: true,
            });

            rootElement.__wayvioGumroadObserver = observer;
        }

        function loadGumroadInto(rootElement) {
            if (!rootElement) {
                return;
            }

            if (rootElement.dataset.loaded === '1') {
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            var embedUrl = rootElement.dataset.embedUrl || '';
            if (!mountNode || !/^https:\/\//i.test(embedUrl)) {
                return;
            }

            var existingGumroadEmbed = mountNode.querySelector('[data-smart-embed-dynamic="gumroad"]');
            if (existingGumroadEmbed) {
                rootElement.dataset.loaded = '1';
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            clearDynamicEmbedContent(mountNode);

            var gumroadWrapper = document.createElement('div');
            gumroadWrapper.className = 'ls-smart-embed__gumroad';
            gumroadWrapper.setAttribute('data-smart-embed-dynamic', 'gumroad');

            var gumroadEmbed = document.createElement('div');
            gumroadEmbed.className = 'gumroad-product-embed';

            var gumroadLink = document.createElement('a');
            gumroadLink.href = embedUrl;
            gumroadLink.textContent = 'Loading...';

            gumroadEmbed.appendChild(gumroadLink);
            gumroadWrapper.appendChild(gumroadEmbed);
            mountNode.appendChild(gumroadWrapper);
            rootElement.dataset.loaded = '1';
            watchGumroadFrames(rootElement, gumroadWrapper);
            scheduleGumroadNoScroll(rootElement);
            setPlaceholderVisibility(rootElement, false);

            ensureGumroadEmbedScript().then(function () {
                if (window.GumroadEmbed && typeof window.GumroadEmbed.reload === 'function') {
                    window.GumroadEmbed.reload();
                    return;
                }

                if (window.GumroadEmbed && typeof window.GumroadEmbed.init === 'function') {
                    window.GumroadEmbed.init();
                }

                scheduleGumroadNoScroll(rootElement);
                setPlaceholderVisibility(rootElement, false);
            });
        }

        function normalizeKitFormMargins(rootElement) {
            if (!rootElement) {
                return;
            }

            toArray(rootElement.querySelectorAll('.ls-smart-embed__kit .formkit-form')).forEach(function (formElement) {
                formElement.style.marginTop = '0';
                formElement.style.marginBottom = '0';
                formElement.style.marginLeft = 'auto';
                formElement.style.marginRight = 'auto';
                formElement.style.maxWidth = '100%';
            });
        }

        function watchKitEmbeds(rootElement, kitWrapper) {
            if (!rootElement || !kitWrapper || typeof MutationObserver === 'undefined') {
                return;
            }

            if (rootElement.__wayvioKitObserver && typeof rootElement.__wayvioKitObserver.disconnect === 'function') {
                rootElement.__wayvioKitObserver.disconnect();
            }

            var observer = new MutationObserver(function () {
                normalizeKitFormMargins(rootElement);
            });

            observer.observe(kitWrapper, {
                childList: true,
                subtree: true,
            });

            rootElement.__wayvioKitObserver = observer;
        }

        function loadKitInto(rootElement) {
            if (!rootElement) {
                return;
            }

            if (rootElement.dataset.loaded === '1') {
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            if (!mountNode) {
                return;
            }

            var embedId = (rootElement.dataset.embedId || '').toLowerCase();
            var idMatch = embedId.match(/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?):([a-f0-9]{8,64})$/);
            if (!idMatch) {
                loadIframeInto(rootElement);
                return;
            }

            var accountHandle = idMatch[1];
            var formUid = idMatch[2];
            var scriptUrl = 'https://' + accountHandle + '.kit.com/' + formUid + '/index.js';

            var existingKitEmbed = mountNode.querySelector('[data-smart-embed-dynamic="kit"]');
            if (existingKitEmbed) {
                rootElement.dataset.loaded = '1';
                normalizeKitFormMargins(rootElement);
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            clearDynamicEmbedContent(mountNode);

            var kitWrapper = document.createElement('div');
            kitWrapper.className = 'ls-smart-embed__kit';
            kitWrapper.setAttribute('data-smart-embed-dynamic', 'kit');

            var kitScript = document.createElement('script');
            kitScript.async = true;
            kitScript.src = scriptUrl;
            kitScript.setAttribute('data-uid', formUid);
            kitScript.setAttribute('data-smart-embed-dynamic', 'kit-script');
            kitScript.addEventListener('error', function () {
                rootElement.dataset.loaded = '0';
                clearDynamicEmbedContent(mountNode);
                loadIframeInto(rootElement);
            }, { once: true });

            kitWrapper.appendChild(kitScript);
            mountNode.appendChild(kitWrapper);
            rootElement.dataset.loaded = '1';
            watchKitEmbeds(rootElement, kitWrapper);
            normalizeKitFormMargins(rootElement);
            setPlaceholderVisibility(rootElement, false);
        }

        function normalizeResmioSlug(rawEmbedId) {
            var embedId = (rawEmbedId || '').trim().toLowerCase();
            if (!/^[a-z0-9][a-z0-9_-]{1,80}$/.test(embedId)) {
                return '';
            }

            return embedId;
        }

        function loadResmioBookingInto(rootElement) {
            if (!rootElement) {
                return;
            }

            if (rootElement.dataset.loaded === '1') {
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            var embedUrl = rootElement.dataset.embedUrl || '';
            var embedId = normalizeResmioSlug(rootElement.dataset.embedId || '');
            if (!mountNode || embedId === '') {
                return;
            }

            var existingBookingEmbed = mountNode.querySelector('[data-smart-embed-dynamic="resmio-booking"]');
            if (existingBookingEmbed) {
                rootElement.dataset.loaded = '1';
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            clearDynamicEmbedContent(mountNode);

            var bookingWrapper = document.createElement('div');
            bookingWrapper.className = 'ls-smart-embed__resmio';
            bookingWrapper.setAttribute('data-smart-embed-dynamic', 'resmio-booking');

            var bookingContainer = document.createElement('div');
            bookingContainer.id = 'resmio-' + embedId;
            bookingContainer.setAttribute('data-smart-embed-dynamic', 'resmio-booking-target');
            bookingWrapper.appendChild(bookingContainer);
            mountNode.appendChild(bookingWrapper);

            var bookingScript = document.createElement('script');
            bookingScript.async = true;
            bookingScript.src = RESMIO_BOOKING_SCRIPT_BASE + '#id=' + encodeURIComponent(embedId) + '&height=600&width=430&fontSize=14px';
            bookingScript.setAttribute('data-smart-embed-dynamic', 'resmio-booking-script');
            bookingScript.addEventListener('error', function () {
                rootElement.dataset.loaded = '0';
                clearDynamicEmbedContent(mountNode);
                if (/^https:\/\//i.test(embedUrl)) {
                    loadIframeInto(rootElement);
                }
            }, { once: true });

            mountNode.appendChild(bookingScript);
            rootElement.dataset.loaded = '1';
            setPlaceholderVisibility(rootElement, false);
        }

        function loadEmbedInto(rootElement) {
            var serviceKey = (rootElement && rootElement.dataset ? rootElement.dataset.serviceKey : '') || '';
            if (serviceKey.toLowerCase() === 'gumroad') {
                loadGumroadInto(rootElement);
                return;
            }

            if (serviceKey.toLowerCase() === 'kit') {
                loadKitInto(rootElement);
                return;
            }

            if (serviceKey.toLowerCase() === 'resmio_booking') {
                loadResmioBookingInto(rootElement);
                return;
            }

            if (serviceKey.toLowerCase() === 'resmio_menu') {
                // Resmio menu.js appends source=<current-page>; this can be denied by CloudFront
                // on localhost/private/unlisted domains. Direct iframe avoids this hard 403.
                loadIframeInto(rootElement);
                return;
            }

            loadIframeInto(rootElement);
        }

        function stopObservingEmbed(rootElement) {
            if (!rootElement) {
                return;
            }

            if (lazyEmbedObserver) {
                lazyEmbedObserver.unobserve(rootElement);
            }

            rootElement.dataset.observing = '0';
        }

        function ensureLazyObserver() {
            if (!supportsIntersectionObserver || lazyEmbedObserver) {
                return;
            }

            lazyEmbedObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting && entry.intersectionRatio <= 0) {
                        return;
                    }

                    var embedRoot = entry.target;
                    stopObservingEmbed(embedRoot);
                    loadEmbedInto(embedRoot);
                });
            }, {
                root: null,
                rootMargin: LAZY_EMBED_ROOT_MARGIN,
                threshold: 0.01,
            });
        }

        function scheduleEmbedLoad(rootElement) {
            if (!rootElement) {
                return;
            }

            if (rootElement.dataset.loaded === '1') {
                stopObservingEmbed(rootElement);
                setPlaceholderVisibility(rootElement, false);
                return;
            }

            if (!supportsIntersectionObserver || rootElement.dataset.embedLoadMode !== 'lazy') {
                stopObservingEmbed(rootElement);
                loadEmbedInto(rootElement);
                return;
            }

            setPlaceholderVisibility(rootElement, true);
            ensureLazyObserver();
            if (lazyEmbedObserver && rootElement.dataset.observing !== '1') {
                lazyEmbedObserver.observe(rootElement);
                rootElement.dataset.observing = '1';
            }
        }

        function unloadEmbedFrom(rootElement) {
            if (!rootElement) {
                return;
            }

            if (rootElement.__wayvioGumroadObserver && typeof rootElement.__wayvioGumroadObserver.disconnect === 'function') {
                rootElement.__wayvioGumroadObserver.disconnect();
            }
            rootElement.__wayvioGumroadObserver = null;
            if (rootElement.__wayvioKitObserver && typeof rootElement.__wayvioKitObserver.disconnect === 'function') {
                rootElement.__wayvioKitObserver.disconnect();
            }
            rootElement.__wayvioKitObserver = null;
            stopObservingEmbed(rootElement);

            var mountNode = rootElement.querySelector('[data-smart-embed-frame]');
            if (mountNode) {
                clearDynamicEmbedContent(mountNode);
            }

            rootElement.dataset.loaded = '0';
            setPlaceholderVisibility(rootElement, true);
        }

        function applyManagedEmbeds(consentState) {
            var managedEmbeds = toArray(document.querySelectorAll('[data-smart-embed-root][data-consent-provider]'));
            managedEmbeds.forEach(function (embedRoot) {
                var provider = embedRoot.dataset.consentProvider || '';
                var providerAllowed = consentState.decisionMade === true && consentState.providers[provider] === true;

                var wrapper = embedRoot.closest('[data-smart-embed-wrapper]');
                var card = wrapper ? wrapper.querySelector('[data-smart-embed-card]') : null;
                var linkFallback = wrapper ? wrapper.querySelector('[data-smart-embed-link]') : null;

                if (providerAllowed) {
                    if (linkFallback) {
                        linkFallback.hidden = true;
                    }
                    if (card) {
                        card.hidden = false;
                    }
                    scheduleEmbedLoad(embedRoot);
                    return;
                }

                unloadEmbedFrom(embedRoot);
                if (linkFallback) {
                    linkFallback.hidden = false;
                }
                if (card && linkFallback) {
                    card.hidden = true;
                }
            });
        }

        function applyUnmanagedEmbeds() {
            toArray(document.querySelectorAll('[data-smart-embed-root]:not([data-consent-provider])')).forEach(function (embedRoot) {
                var wrapper = embedRoot.closest('[data-smart-embed-wrapper]');
                var card = wrapper ? wrapper.querySelector('[data-smart-embed-card]') : null;
                if (card) {
                    card.hidden = false;
                }

                scheduleEmbedLoad(embedRoot);
            });
        }

        function applyLegacyClickToLoad() {
            // Manual override for non-managed providers that expose a local "load now" button.
            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-smart-embed-load]');
                if (!trigger) {
                    return;
                }

                var embedRoot = trigger.closest('[data-smart-embed-root]');
                if (!embedRoot || embedRoot.hasAttribute('data-consent-provider')) {
                    return;
                }

                stopObservingEmbed(embedRoot);
                loadEmbedInto(embedRoot);
            });
        }

        function collectUsedProviders() {
            var used = [];
            var seen = {};

            toArray(document.querySelectorAll('[data-smart-embed-root][data-consent-provider]')).forEach(function (embedRoot) {
                var provider = (embedRoot.dataset.consentProvider || '').trim();
                if (MANAGED_PROVIDERS.indexOf(provider) === -1 || seen[provider]) {
                    return;
                }

                seen[provider] = true;
                used.push(provider);
            });

            return used;
        }

        var usedProviders = collectUsedProviders();
        var consentBanner = document.querySelector('[data-consent-banner]');
        var consentStatus = consentBanner ? consentBanner.querySelector('[data-consent-status]') : null;
        var consentSelection = consentBanner ? consentBanner.querySelector('[data-consent-selection]') : null;
        var providerList = consentBanner ? consentBanner.querySelector('[data-consent-provider-list]') : null;
        var usedProvidersInfo = consentBanner ? consentBanner.querySelector('[data-consent-used-providers]') : null;

        var acceptAllButton = consentBanner
            ? consentBanner.querySelector('[data-consent-action="accept-all"]')
            : null;

        var adjustSelectionButton = consentBanner
            ? consentBanner.querySelector('[data-consent-action="adjust-selection"]')
            : null;

        var rejectAllButton = consentBanner
            ? consentBanner.querySelector('[data-consent-action="reject-all"]')
            : null;

        var confirmSelectionButton = consentBanner
            ? consentBanner.querySelector('[data-consent-action="confirm-selection"]')
            : null;

        var settingsLinks = toArray(document.querySelectorAll('[data-open-consent-settings]'));
        var hasManagedEmbeds = usedProviders.length > 0;

        if (usedProvidersInfo) {
            if (hasManagedEmbeds) {
                var providerNames = usedProviders.map(function (provider) {
                    return PROVIDER_LABELS[provider] || provider;
                });
                usedProvidersInfo.textContent = 'Auf dieser Seite genutzt: ' + providerNames.join(', ');
                usedProvidersInfo.hidden = false;
            } else {
                usedProvidersInfo.hidden = true;
            }
        }

        function setStatus(message) {
            if (!consentStatus) {
                return;
            }
            consentStatus.textContent = message;
        }

        function renderProviderList(consentState) {
            if (!providerList) {
                return;
            }

            providerList.innerHTML = '';

            usedProviders.forEach(function (provider) {
                var item = document.createElement('li');
                var label = document.createElement('label');
                var input = document.createElement('input');
                var text = document.createElement('span');

                input.type = 'checkbox';
                input.setAttribute('data-consent-provider-toggle', provider);
                input.checked = consentState.providers[provider] === true;
                text.className = 'ls-consent-banner__check-text';
                text.textContent = PROVIDER_LABELS[provider] || provider;

                label.appendChild(input);
                label.appendChild(text);
                item.appendChild(label);
                providerList.appendChild(item);
            });
        }

        function openSelection(consentState) {
            if (!consentSelection) {
                return;
            }

            renderProviderList(consentState);
            consentSelection.hidden = false;
            if (adjustSelectionButton) {
                adjustSelectionButton.hidden = true;
            }
        }

        function closeSelection() {
            if (consentSelection) {
                consentSelection.hidden = true;
            }

            if (adjustSelectionButton) {
                adjustSelectionButton.hidden = false;
            }
        }

        function focusPrimaryAction() {
            if (acceptAllButton) {
                acceptAllButton.focus();
            }
        }

        function openBanner() {
            if (!consentBanner) {
                return;
            }

            closeSelection();
            consentBanner.hidden = false;
            setTimeout(focusPrimaryAction, 0);
        }

        function closeBanner() {
            if (!consentBanner) {
                return;
            }

            closeSelection();
            consentBanner.hidden = true;
        }

        function buildSelectionFromToggles(previousState) {
            var nextState = normalizeConsent(previousState);
            nextState.decisionMade = true;
            nextState.decidedAt = nextState.decidedAt || nowIso();
            nextState.updatedAt = nowIso();

            var acceptedCount = 0;
            usedProviders.forEach(function (provider) {
                var toggle = providerList
                    ? providerList.querySelector('[data-consent-provider-toggle="' + provider + '"]')
                    : null;
                var checked = !!(toggle && toggle.checked);
                nextState.providers[provider] = checked;
                if (checked) {
                    acceptedCount += 1;
                }
            });

            if (acceptedCount === usedProviders.length && usedProviders.length > 0) {
                nextState.selection = 'accept_all';
            } else if (acceptedCount === 0) {
                nextState.selection = 'reject_all';
            } else {
                nextState.selection = 'custom';
            }

            return nextState;
        }

        function acceptAllConsent(currentState) {
            var nextState = baseConsentState();
            var timestamp = nowIso();

            nextState.decisionMade = true;
            nextState.decidedAt = currentState.decidedAt || timestamp;
            nextState.updatedAt = timestamp;
            nextState.selection = 'accept_all';
            MANAGED_PROVIDERS.forEach(function (provider) {
                nextState.providers[provider] = true;
            });

            return nextState;
        }

        function rejectAllConsent(currentState) {
            var nextState = baseConsentState();
            var timestamp = nowIso();

            nextState.decisionMade = true;
            nextState.decidedAt = currentState.decidedAt || timestamp;
            nextState.updatedAt = timestamp;
            nextState.selection = 'reject_all';

            return nextState;
        }

        assignEmbedLoadingModes();
        applyLegacyClickToLoad();
        applyUnmanagedEmbeds();

        if (!consentBanner) {
            applyManagedEmbeds(baseConsentState());
            return;
        }

        var currentConsent = readConsent();

        if (currentConsent.decisionMade) {
            setStatus('Gespeichert am ' + (currentConsent.updatedAt || currentConsent.decidedAt || 'unbekannt') + '. Version: ' + currentConsent.version + '.');
        }

        applyManagedEmbeds(currentConsent);

        if (hasManagedEmbeds && currentConsent.decisionMade !== true) {
            openBanner();
        }

        if (acceptAllButton) {
            acceptAllButton.addEventListener('click', function () {
                currentConsent = acceptAllConsent(currentConsent);
                writeConsent(currentConsent);
                applyManagedEmbeds(currentConsent);
                setStatus('Alle externen Medien aktiviert.');
                closeBanner();
            });
        }

        if (adjustSelectionButton) {
            adjustSelectionButton.addEventListener('click', function () {
                openSelection(currentConsent);
            });
        }

        if (rejectAllButton) {
            rejectAllButton.addEventListener('click', function () {
                currentConsent = rejectAllConsent(currentConsent);
                writeConsent(currentConsent);
                applyManagedEmbeds(currentConsent);
                setStatus('Alle externen Medien deaktiviert.');
                closeBanner();
            });
        }

        if (confirmSelectionButton) {
            confirmSelectionButton.addEventListener('click', function () {
                currentConsent = buildSelectionFromToggles(currentConsent);
                writeConsent(currentConsent);
                applyManagedEmbeds(currentConsent);
                setStatus('Auswahl gespeichert.');
                closeBanner();
            });
        }

        settingsLinks.forEach(function (settingsLink) {
            settingsLink.addEventListener('click', function (event) {
                event.preventDefault();
                // Requirement: revocation must clear stored consent and unload all active iframes.
                clearConsent();
                currentConsent = baseConsentState();
                applyManagedEmbeds(currentConsent);
                setStatus('Einwilligung widerrufen. Bitte neue Auswahl treffen.');
                openBanner();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
        return;
    }

    init();
})();
</script>
@endonce

<div style="--delay: {{ $initial }}s" class="button-entrance" data-smart-embed-wrapper>
    @if($isRenderable)
        @if($requiresCentralConsent && $externalUrl !== '')
            <a
                id="{{ $link->id }}"
                class="button button-custom button-click button-hover icon-hover ls-link-interactive ls-smart-embed-link"
                data-smart-embed-link
                href="{{ $externalUrl }}"
                @if($useGlobalCustomStyle) style="{{ $globalCustomCss }}" @endif
                target="_blank"
                rel="noopener noreferrer nofollow noindex"
                aria-label="{{ $displayTitle }} extern öffnen"
            >
                <span class="button-text-wrapper">{{ $displayTitle }}</span>
            </a>
        @endif

        <section
            class="ls-smart-embed-card fadein @if($serviceKey === 'gumroad') ls-smart-embed-card--gumroad @endif @if($serviceKey === 'kit') ls-smart-embed-card--kit @endif @if(in_array($serviceKey, ['resmio_booking', 'resmio_menu'], true)) ls-smart-embed-card--resmio @endif"
            data-smart-embed-card
            @if($requiresCentralConsent && $externalUrl !== '')
                hidden
            @endif
            style="--ls-smart-embed-ratio: {{ $service['ratio'] ?? '16 / 9' }}; --ls-smart-embed-min-height: {{ $service['min_height'] ?? 220 }}px;"
        >
            <div
                data-smart-embed-root
                data-embed-url="{{ $embedUrl }}"
                data-embed-id="{{ $embedId }}"
                data-service-key="{{ $serviceKey }}"
                data-service-name="{{ $service['name'] }}"
                data-embed-allow="{{ $embedAllow }}"
                data-loaded="0"
                @if($requiresCentralConsent)
                    data-consent-provider="{{ $consentProvider }}"
                @endif
            >
                <div class="ls-smart-embed__frame" data-smart-embed-frame>
                    <div class="ls-smart-embed__placeholder" data-smart-embed-placeholder role="group" aria-label="{{ $service['name'] }} embed placeholder">
                        <div class="ls-smart-embed__icon" aria-hidden="true">{{ $service['badge'] }}</div>
                        <p class="ls-smart-embed__title">{{ $displayTitle }}</p>
                        <p class="ls-smart-embed__description">{{ $placeholderDescription }}</p>

                        @if(!$requiresCentralConsent)
                            <button type="button" class="ls-smart-embed__button" data-smart-embed-load>
                                Inhalt laden
                            </button>
                        @endif

                        @if($externalUrl !== '')
                            <a
                                class="ls-smart-embed__external-link"
                                href="{{ $externalUrl }}"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                            >
                                Zum Original
                            </a>
                        @endif

                        <p class="ls-smart-embed__notice">
                            @if($requiresCentralConsent)
                                Externer Inhalt - Zustimmung erforderlich.
                            @else
                                Lädt automatisch beim Scrollen in den sichtbaren Bereich.
                            @endif
                        </p>
                    </div>
                </div>
                <noscript>
                    <div class="ls-smart-embed__fallback">
                        <p>JavaScript ist deaktiviert. Bitte öffnen Sie den Inhalt im neuen Tab.</p>
                        @if($externalUrl !== '')
                            <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer nofollow">Inhalt extern öffnen</a>
                        @endif
                    </div>
                </noscript>
            </div>
        </section>
    @else
        <div class="ls-smart-embed__fallback">
            <p>Dieser Embed-Inhalt ist aktuell nicht verfügbar.</p>
            @if($externalUrl !== '')
                <a class="button button-custom button-click button-hover icon-hover ls-link-interactive" @if($useGlobalCustomStyle) style="{{ $globalCustomCss }}" @endif href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer nofollow">
                    <span class="button-text-wrapper">{{ $displayTitle }}</span>
                </a>
            @endif
        </div>
    @endif
</div>
