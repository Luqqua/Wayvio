@php
    $themeForCapabilities = empty($info->theme) ? 'default' : (string) $info->theme;
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id);
    $booleanSettingEnabled = static fn ($value): bool => in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    $hideCreditRequested = $booleanSettingEnabled(\App\Models\UserData::getData($userinfo->id, 'hide_credit'));

    $brandingOwnerId = (int) $userinfo->id;
    $hubOwner = \App\Models\AgencyHub::query()
        ->select('agency_user_id')
        ->where('managed_user_id', $userinfo->id)
        ->where('status', 'active')
        ->first();
    if ($hubOwner) {
        $brandingOwnerId = (int) $hubOwner->agency_user_id;
    }

    $brandingOwnerUser = $brandingOwnerId > 0
        ? \App\Models\User::query()->where('id', $brandingOwnerId)->first()
        : null;
    $agencyContext = app(\App\Services\Agency\AgencyHubContext::class);
    $canUseAgencyBranding = $brandingOwnerUser ? $agencyContext->isAgencyAccount($brandingOwnerUser) : false;

    $featureOwnerUser = $brandingOwnerUser ?: $userinfo;
    $subscriptionManager = class_exists(\Modules\Tiers\Services\SubscriptionManager::class)
        ? app(\Modules\Tiers\Services\SubscriptionManager::class)
        : null;
    $tierResolver = class_exists(\Modules\Tiers\Services\TierResolver::class)
        ? app(\Modules\Tiers\Services\TierResolver::class)
        : null;
    $ownerTier = null;
    if (
        $featureOwnerUser
        && $subscriptionManager
        && $tierResolver
        && \Illuminate\Support\Facades\Schema::hasTable('tiers')
        && \Illuminate\Support\Facades\Schema::hasTable('user_subscriptions')
    ) {
        $ownerTier = $subscriptionManager->getUserTier($featureOwnerUser);
    }
    $featureOwnerRole = (string) ($featureOwnerUser->role ?? '');
    $featureRoleBypass = in_array($featureOwnerRole, ['vip', 'admin'], true);
    $canHideCredit = $featureRoleBypass || (
        $tierResolver
        && $ownerTier
        && $tierResolver->featureEnabled($ownerTier, 'branding.remove_branding')
    );
    $canUseTextAccentTier = $featureRoleBypass || (
        $tierResolver
        && $ownerTier
        && (
            $tierResolver->featureEnabled($ownerTier, 'design.custom_colors')
            || $tierResolver->featureEnabled($ownerTier, 'design.link_styling')
        )
    );
    $applyUserTextColors = $applyUserTextColors && $canUseTextAccentTier;
    $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
    $hideCredit = $hideCreditRequested && $canHideCredit;
    $footerCreditEnabled = config('display.credit') === true || config('display.credit_footer') === true;
    $footerTheme = empty($info->theme) ? (string) ($userinfo->theme ?? 'default') : (string) $info->theme;
    $rawFooterProfileHeaderLayout = \App\Models\UserData::getData($userinfo->id, 'profile_header_layout');
    $footerProfileHeaderLayout = is_string($rawFooterProfileHeaderLayout)
        ? strtolower(trim($rawFooterProfileHeaderLayout))
        : 'standard';
    if (!in_array($footerProfileHeaderLayout, ['standard', 'business', 'business_header_focus_description'], true)) {
        $footerProfileHeaderLayout = 'standard';
    }
    $footerProfileLayoutSwitcherEnabled = templateCapability($footerTheme, 'profile_layout_switcher', true);
    $showBusinessFooterSocial = $footerProfileLayoutSwitcherEnabled
        && in_array($footerProfileHeaderLayout, ['business', 'business_header_focus_description'], true);
    $footerSocialIcons = collect();
    if ($showBusinessFooterSocial && \Illuminate\Support\Facades\Schema::hasTable('links')) {
        $footerSocialIconQuery = \App\Models\Link::where('user_id', $userinfo->id)->where('button_id', 94);
        if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
            $footerSocialIconQuery->where('is_disabled', false);
        }
        $footerSocialIcons = $footerSocialIconQuery->get();
    }

    $agencyBrandingAsset = $canUseAgencyBranding
        ? \App\Models\UserData::getData($brandingOwnerId, 'agency_branding_asset')
        : null;
    $agencyBrandingLinkRaw = $canUseAgencyBranding
        ? trim((string) (\App\Models\UserData::getData($brandingOwnerId, 'agency_branding_link') ?? ''))
        : '';

    $agencyBrandingLink = '';
    if (filter_var($agencyBrandingLinkRaw, FILTER_VALIDATE_URL)) {
        $brandingLinkScheme = strtolower((string) parse_url($agencyBrandingLinkRaw, PHP_URL_SCHEME));
        if (in_array($brandingLinkScheme, ['http', 'https'], true)) {
            $agencyBrandingLink = $agencyBrandingLinkRaw;
        }
    }
    $agencyBrandingUrl = null;
    if (is_string($agencyBrandingAsset) && $agencyBrandingAsset !== '' && mediaPathExists($agencyBrandingAsset)) {
        $agencyBrandingUrl = mediaPathUrl($agencyBrandingAsset);
    }

    $currentLocale = app()->getLocale();
    $normalizedLocale = strtolower(trim((string) $currentLocale));
    $footerCookieLabel = __('messages.Cookie settings');
    $footerReportLabel = __('messages.report_short');
    if ($footerReportLabel === 'messages.report_short') {
        $footerReportLabel = __('messages.report_violation');
    }
    $embedLinkQuery = \App\Models\Link::query()
        ->where('user_id', (int) $userinfo->id)
        ->where('type', 'smart_embed');

    if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
        $embedLinkQuery->where('is_disabled', false);
    }

    $hasSmartEmbeds = $embedLinkQuery->exists();
    $publicLegalLinks = legalDocumentLinks((string) $currentLocale);
    $privacyConsentUrl = $publicLegalLinks['privacy'] !== ''
        ? $publicLegalLinks['privacy']
        : (\Illuminate\Support\Facades\Route::has('pagesPrivacy') ? route('pagesPrivacy') : '#');

    $imprintLinkQuery = \App\Models\Link::query()
        ->where('user_id', (int) $userinfo->id)
        ->where('type', 'imprint');

    if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
        $imprintLinkQuery->where('is_disabled', false);
    }

    $hasImprint = $imprintLinkQuery->exists();
    $storedUserPrivacyModel = \App\Models\UserData::getData((int) $userinfo->id, 'legal_privacy_layer_model');
    $hasUserPrivacy = is_array($storedUserPrivacyModel);
    $userImprintUrl = null;
    $userImprintLabel = __('messages.imprint.default_title');
    $userPrivacyUrl = null;
    $userPrivacyLabel = __('messages.footer.Privacy');
    if ($hasImprint || $hasUserPrivacy) {
        $domainResolver = app(\App\Services\Domains\DomainUrlResolver::class);
        $profileOwner = $domainResolver->ownerForPageUser($userinfo);
        $profileUrl = $domainResolver->profileUrlForEditor($profileOwner, $userinfo);

        $supportedLocales = array_values(array_filter(
            (array) config('app.supported_locales', []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        $supportedMap = [];
        foreach ($supportedLocales as $supportedLocale) {
            $normalizedSupported = strtolower(trim((string) $supportedLocale));
            if ($normalizedSupported !== '') {
                $supportedMap[$normalizedSupported] = trim((string) $supportedLocale);
            }
        }

        $normalizeLocale = static function (?string $locale) use ($supportedMap): ?string {
            $candidate = strtolower(trim((string) $locale));
            if ($candidate === '') {
                return null;
            }
            $candidate = str_replace('_', '-', $candidate);
            $resolved = null;

            if (isset($supportedMap[$candidate])) {
                $resolved = $supportedMap[$candidate];
            }

            if ($resolved === null) {
                $base = strtok($candidate, '-');
                if (is_string($base) && $base !== '' && isset($supportedMap[$base])) {
                    $resolved = $supportedMap[$base];
                }
            }

            if ($resolved === null) {
                $direct = trim(str_replace('_', '-', (string) $locale));
                if ($direct !== '' && is_dir(lang_path($direct))) {
                    $resolved = $direct;
                }
            }

            if ($resolved === null) {
                return null;
            }

            $resolved = trim((string) $resolved);
            if ($resolved === '') {
                return null;
            }

            if (is_dir(lang_path($resolved))) {
                return $resolved;
            }

            $lower = strtolower($resolved);
            if (is_dir(lang_path($lower))) {
                return $lower;
            }

            return $resolved;
        };

        $userImprintLocale = $normalizeLocale(is_string($userinfo->locale ?? null) ? (string) $userinfo->locale : null)
            ?? $normalizeLocale(is_string($profileOwner->locale ?? null) ? (string) $profileOwner->locale : null)
            ?? $normalizeLocale((string) app()->getLocale())
            ?? $normalizeLocale((string) config('app.fallback_locale', 'en'))
            ?? ($supportedMap['de'] ?? (array_values($supportedMap)[0] ?? 'de'));

        if ($hasImprint) {
            $userImprintUrl = rtrim($profileUrl, '/') . '/imprint';
            $userImprintLabel = __('messages.imprint.default_title', [], $userImprintLocale);
        }

        if ($hasUserPrivacy) {
            $userPrivacyUrl = rtrim($profileUrl, '/') . '/privacy';
            $userPrivacyLabel = __('messages.footer.Privacy', [], $userImprintLocale);
            $privacyConsentUrl = $userPrivacyUrl;
        }
    }
@endphp
<div class="container ls-footer-shell">
    @if($showBusinessFooterSocial && count($footerSocialIcons) > 0)
        <div class="ls-footer-social fadein" aria-label="Social links">
            @include('wayvio.elements.icons', ['icons' => $footerSocialIcons])
        </div>
    @endif
	<div class="footer fadein ls-footer-links">
        @if($userImprintUrl)<a class="footer-hover spacing" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif href="{{ $userImprintUrl }}">{{ $userImprintLabel }}</a>@endif
        @if($userPrivacyUrl)<a class="footer-hover spacing" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif href="{{ $userPrivacyUrl }}">{{ $userPrivacyLabel }}</a>@endif
        @if($hasSmartEmbeds)
		    <a class="footer-hover spacing" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif href="{{ $privacyConsentUrl }}" data-open-consent-settings>{{ $footerCookieLabel }}</a>
        @endif
        <a class="footer-hover spacing" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif href="{{ wayvioReportUrl((int) $userinfo->id) }}">{{ $footerReportLabel }}</a>
		<a class="footer-hover spacing" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">License</a>
		</div>

    <style>
        .ls-footer-shell {
            --ls-footer-links-top: 5%;
            --ls-footer-links-bottom: 44px;
            --ls-footer-links-row-gap: 10px;
            --ls-footer-links-column-gap: 6px;
            --ls-footer-credit-bottom: calc(56px + env(safe-area-inset-bottom, 0px));
            --ls-footer-social-top: clamp(30px, 5vw, 50px);
            --ls-footer-social-bottom: clamp(18px, 3vw, 28px);
            --ls-social-icons-gap: clamp(18px, 2.2vw, 26px);
            --ls-social-icon-size: clamp(18px, 1.8vw, 22px);
        }

        .ls-footer-shell .ls-footer-social {
            margin: var(--ls-footer-social-top) 0 var(--ls-footer-social-bottom);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .ls-footer-shell .ls-footer-social .social-icon-div {
            display: flex;
            gap: var(--ls-social-icons-gap);
            margin: 0 !important;
            padding: 0 !important;
            justify-content: center !important;
            align-items: center;
        }

        .ls-footer-shell .ls-footer-social .social-link {
            margin: 0 !important;
            opacity: 0.78;
            transition: opacity 160ms ease, transform 160ms ease;
        }

        .ls-footer-shell .ls-footer-social .social-icon {
            font-size: var(--ls-social-icon-size) !important;
            line-height: 1;
        }

        .ls-footer-shell .ls-footer-social .social-link:hover,
        .ls-footer-shell .ls-footer-social .social-link:focus {
            opacity: 1;
        }

        .ls-footer-shell .ls-footer-links {
            margin: var(--ls-footer-links-top) 0 var(--ls-footer-links-bottom) 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            row-gap: var(--ls-footer-links-row-gap);
            column-gap: var(--ls-footer-links-column-gap);
        }

        .ls-footer-shell .ls-footer-links .footer-hover.spacing {
            margin: 0 !important;
            padding: 0 10px;
            font-size: var(--ls-type-footer-size, var(--ls-type-small-size, 0.84rem));
            line-height: var(--ls-type-small-line, 1.45);
            font-weight: var(--ls-type-body-weight, 400);
            letter-spacing: 0;
        }

        .ls-footer-shell .footer a {
            @if($globalTextColor)color: {{$globalTextColor}} !important;@endif
        }

        .agency-branding-slot {
            vertical-align: middle;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 128px;
            height: 128px;
            padding-bottom: var(--ls-footer-credit-bottom);
            box-sizing: content-box;
        }

        .agency-branding-image {
            display: block;
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .wayvio-branding {
            vertical-align: middle;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding-bottom: var(--ls-footer-credit-bottom);
        }

        .wayvio-branding__logo {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        .wayvio-branding__label {
            font-size: var(--ls-type-footer-size, 13px);
            font-weight: 600;
            letter-spacing: 0;
            @if($globalTextColor)color: {{$globalTextColor}};@else color: inherit;@endif
        }

        .ls-footer-shell .ls-footer-credit-spacer {
            padding-bottom: var(--ls-footer-credit-bottom);
        }

        .ls-footer-shell .ls-footer-credit {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        @media (max-width: 540px) {
            .ls-footer-shell {
                --ls-footer-links-top: 8%;
                --ls-footer-links-bottom: 52px;
                --ls-footer-links-row-gap: 12px;
                --ls-footer-credit-bottom: calc(68px + env(safe-area-inset-bottom, 0px));
                --ls-footer-social-top: 34px;
                --ls-footer-social-bottom: 22px;
                --ls-social-icons-gap: 24px;
                --ls-social-icon-size: 21px;
            }
        }
    </style>

	@if($footerCreditEnabled && !$hideCredit)
        <div class="ls-footer-credit">
        @if($agencyBrandingUrl)
            @if($agencyBrandingLink !== '')
                <a style="text-decoration: none;" href="{{ $agencyBrandingLink }}" target="_blank" rel="noreferrer" title="Agency branding">
                    <div class="agency-branding-slot credit-hover hvr-grow fadein">
                        <img class="agency-branding-image" src="{{ $agencyBrandingUrl }}" alt="Agency branding">
                    </div>
                </a>
            @else
                <div class="agency-branding-slot fadein">
                    <img class="agency-branding-image" src="{{ $agencyBrandingUrl }}" alt="Agency branding">
                </div>
            @endif
        @else
            <a style="text-decoration: none;" href="https://wayvio.example" target="_blank" rel="noreferrer" title="{{__('messages.Learn more about Wayvio')}}">
                <div class="wayvio-branding credit-hover hvr-grow fadein">
                    <img class="wayvio-branding__logo" src="{{ asset('assets/wayvio/images/logo.svg') }}" alt="Wayvio logo">
                    <span class="wayvio-branding__label">WAYVIO</span>
                </div>
            </a>
        @endif
        </div>
		@else
    <div class="ls-footer-credit-spacer"></div>
		@endif
		</div>
