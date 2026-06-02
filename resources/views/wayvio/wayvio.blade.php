@extends('wayvio.layout')

@section('content')
    @push('wayvio-head')
        @include('wayvio.modules.meta')
        @include('wayvio.modules.assets')
    @endpush

    @push('wayvio-head-end')
        @foreach($information as $info)
            @include('wayvio.modules.theme')
        @endforeach
    @endpush

    @push('wayvio-body-start')
        @include('wayvio.modules.share-button')
    @endpush

    @push('wayvio-content')
        @foreach($information as $info)
            @php
                $rawHeaderEnabled = \App\Models\UserData::getData($userinfo->id, 'header_enabled');
                $headerEnabled = !in_array($rawHeaderEnabled, [null, 'null', false, 'false', 0, '0'], true);
                $rawHeaderHeroEnabled = \App\Models\UserData::getData($userinfo->id, 'header_hero_enabled');
                $headerHeroEnabled = !in_array($rawHeaderHeroEnabled, [null, 'null', false, 'false', 0, '0'], true);
                $headerImagePath = \App\Models\UserData::getData($userinfo->id, 'header_image');
                $mediaStorage = app(\App\Services\Uploads\MediaStorageService::class);
                $hasHeaderImage = is_string($headerImagePath) && !empty($headerImagePath) && $mediaStorage->exists($headerImagePath);

                $currentTheme = $info->theme ?? 'default';
                $supportsHeader = templateCapability($currentTheme, 'header', $currentTheme === 'default');
                $canUseHeaderByTier = pageOwnerHasTierFeature($userinfo->id, 'design.header_image');
                $showHeaderHero = $headerHeroEnabled
                    && $canUseHeaderByTier
                    && $supportsHeader
                    && $headerEnabled
                    && $hasHeaderImage;
                $rawProfileHeaderLayout = \App\Models\UserData::getData($userinfo->id, 'profile_header_layout');
                $profileHeaderLayout = is_string($rawProfileHeaderLayout)
                    ? strtolower(trim($rawProfileHeaderLayout))
                    : 'standard';
                if (!in_array($profileHeaderLayout, ['standard', 'business', 'business_header_focus_description'], true)) {
                    $profileHeaderLayout = 'standard';
                }
                $profileLayoutSwitcherEnabled = templateCapability($currentTheme, 'profile_layout_switcher', true);
                $useBusinessProfileLayout = $profileLayoutSwitcherEnabled
                    && in_array($profileHeaderLayout, ['business', 'business_header_focus_description'], true);
                $useBusinessHeaderFocusDescriptionLayout = $profileLayoutSwitcherEnabled
                    && $profileHeaderLayout === 'business_header_focus_description';
            @endphp
            @include('wayvio.elements.profile-header', [
                'suppressStandardAvatar' => $useBusinessProfileLayout,
                'useBusinessProfileLayout' => $useBusinessProfileLayout,
                'useBusinessHeaderFocusDescriptionLayout' => $useBusinessHeaderFocusDescriptionLayout,
            ])
            @if(!$showHeaderHero)
                @if($useBusinessHeaderFocusDescriptionLayout)
                    @include('wayvio.elements.profile-summary-business-header-focus-description')
                @elseif($useBusinessProfileLayout)
                    @include('wayvio.elements.profile-summary-business')
                @else
                    @php
                        $rawStandardDescription = $info->littlelink_description ?? '';
                        $standardHasDescription = trim(strip_tags((string) $rawStandardDescription)) !== '';
                    @endphp
                    <div class="ls-profile-summary-core ls-profile-summary-core--title-focus fadein">
                        <div class="ls-profile-summary-core__title">
                            @include('wayvio.elements.heading', ['headingBottomMargin' => '0'])
                        </div>
                        @if($standardHasDescription)
                            <div class="ls-profile-summary-core__description">
                                @include('wayvio.elements.bio')
                            </div>
                        @endif
                        <div class="ls-profile-summary-core__icons {{ $standardHasDescription ? '' : 'ls-profile-summary-core__icons--no-description' }}">
                            @include('wayvio.elements.icons')
                        </div>
                    </div>
                @endif
            @endif
        @endforeach
        @include('wayvio.elements.buttons')
        @php
            $hasAdultLink = collect($links ?? [])->contains(function ($link) {
                return !empty($link->is_adult);
            });
            $themeForCapabilities = (string) ($userinfo->theme ?? 'default');
            $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
            $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);
            $disclaimerColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : '#FFFFFF';
        @endphp
        @if($hasAdultLink)
            <div class="container" style="margin-top: 12px;">
                <p style="font-size: var(--ls-type-meta-size, 12px); line-height: var(--ls-type-small-line, 1.5); opacity: 0.8; margin-bottom: 0; color: {{ $disclaimerColor }}; padding: 0 12px;">
                    The website is not affiliated with any linked content or external sites. Links marked with the 18+ badge may include material that is not suitable for persons under 18.
                </p>
            </div>
        @endif
        @yield('content')
        @include('wayvio.modules.footer')
    @endpush
@endsection
