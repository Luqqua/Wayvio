<?php use App\Models\UserData; ?>

@php
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $allowCustomButtons = templateCapability($themeForCapabilities, 'custom_buttons', $themeForCapabilities === 'default');
    $allowTextAccent = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $canUseButtonStylingByTier = pageOwnerHasTierFeature($userinfo->id ?? null, 'design.link_styling')
        || pageOwnerHasTierFeature($userinfo->id ?? null, 'design.custom_colors');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);
    $adultBgColor = $allowTextAccent ? ($textColorSettings['color'] ?? '#FFFFFF') : '#FFFFFF';
    $adultTextColor = ($allowTextAccent && ($textColorSettings['is_dark'] ?? false)) ? '#FFFFFF' : '#000000';
    $rawGlobalCustomCss = UserData::getData($userinfo->id ?? null, 'global_custom_button_css');
    $globalCustomCss = is_string($rawGlobalCustomCss) ? trim($rawGlobalCustomCss) : '';
    if (strtolower($globalCustomCss) === 'null') {
        $globalCustomCss = '';
    }
    $themeKey = strtolower(trim($themeForCapabilities));
    if ($globalCustomCss !== '' && !in_array($themeKey, ['default', 'wayvio'], true) && isWayvioDefaultCustomButtonCss($globalCustomCss)) {
        $globalCustomCss = '';
    }
    $hasGlobalCustomCss = ($globalCustomCss !== '') && $allowCustomButtons && $canUseButtonStylingByTier;
@endphp

@once
<style>
    :root {
        --ls-page-content-width: clamp(260px, 92vw, 520px);
        --ls-content-block-gap: 41.4px;
        --ls-content-block-gap-mobile: 36.8px;
    }

    /* Center each button container */
    .button-entrance {
        display: flex;
        justify-content: center;
        width: 100%;
        margin-bottom: var(--ls-content-block-gap);
    }

    /* Keep text centered in the full button width, independent from icon/badge */
    .button-entrance > .ls-link-interactive,
    .ls-link-interactive {
        --ls-icon-space: 50px;
        --ls-side-space: 12px;
        --ls-badge-space: 60px;
        position: relative;
        display: grid !important;
        align-items: center;
        box-sizing: border-box;
        text-align: center;
        width: var(--ls-page-content-width);
        margin: 0 auto;
        font-size: var(--ls-type-button-size, 1rem);
        line-height: var(--ls-type-button-line, 1.35);
        font-weight: var(--ls-type-button-weight, 700);
        letter-spacing: 0;
    }

    .button-entrance > .ls-link-interactive > .icon,
    .ls-link-interactive > .icon {
        grid-area: 1 / 1;
        justify-self: start;
        align-self: center;
        margin-left: 12px;
        padding: 0;
        width: 25px;
        height: 25px;
        flex-shrink: 0;
        z-index: 1;
    }

    .button-entrance > .ls-link-interactive > .icon.fa,
    .button-entrance > .ls-link-interactive > .icon.bi,
    .ls-link-interactive > .icon.fa,
    .ls-link-interactive > .icon.bi {
        font-size: 25px;
        line-height: 1;
    }

    .button-entrance > .ls-link-interactive > .button-text-wrapper,
    .ls-link-interactive > .button-text-wrapper {
        grid-area: 1 / 1;
        display: block;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        line-height: var(--ls-type-button-line, 1.35);
        text-align: center;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        padding-left: max(var(--ls-icon-space), var(--ls-side-space));
        padding-right: max(var(--ls-icon-space), var(--ls-side-space));
        font: inherit;
        letter-spacing: 0;
    }

    .button-entrance > .ls-link-interactive > .button-text-wrapper:first-child,
    .ls-link-interactive > .button-text-wrapper:first-child {
        padding-left: var(--ls-side-space);
        padding-right: var(--ls-side-space);
    }

    .button-entrance > .ls-link-interactive.adult-flagged > .button-text-wrapper,
    .ls-link-interactive.adult-flagged > .button-text-wrapper {
        padding-left: var(--ls-badge-space);
        padding-right: var(--ls-badge-space);
    }

    /* The badge stays on the right but does not influence text centering */
    .adult-badge {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: {{ $adultBgColor }};
        color: {{ $adultTextColor }};
        padding: 6px 10px;
        border-radius: 999px;
        font-size: var(--ls-type-meta-size, 12px);
        font-weight: var(--ls-type-section-weight, 700);
        letter-spacing: 0;
        line-height: var(--ls-type-meta-line, 1);
        text-transform: uppercase;
        border: none;
        pointer-events: none;
        z-index: 2;
        flex-shrink: 0;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .button-entrance > .ls-link-interactive,
        .ls-link-interactive {
            width: min(var(--ls-page-content-width), 90vw);
            margin: 0 auto;
        }
        
        .button-entrance {
            margin-bottom: var(--ls-content-block-gap-mobile);
        }
    }

</style>
@endonce

        @php 
        $initial = 1; 
        @endphp

        @include('wayvio.modules.block-libraries', ['links' => $links])

        @foreach($links as $link)
        @continue((string) ($link->type ?? '') === 'imprint')
        @php
            $hideIcon = ($link->custom_icon ?? '') === 'ls-hidden-icon';
            $isAdultLink = !empty($link->is_adult);
            $adultClass = $isAdultLink ? ' adult-flagged' : '';
            $rawCustomIcon = trim((string) ($link->custom_icon ?? ''));
            $legacyFaMap = [
                'fa-newspaper-o' => 'fa-newspaper',
                'fa-file-text-o' => 'fa-file-lines',
                'fa-lightbulb-o' => 'fa-lightbulb',
            ];
            $normalizedRawIcon = strtolower($rawCustomIcon);
            if (isset($legacyFaMap[$normalizedRawIcon])) {
                $rawCustomIcon = $legacyFaMap[$normalizedRawIcon];
            }
            $customFaIcon = (preg_match('/^fa-[a-z0-9-]+$/i', $rawCustomIcon) === 1) ? strtolower($rawCustomIcon) : null;
            $customBiIcon = (preg_match('/^bi-[a-z0-9-]+$/i', $rawCustomIcon) === 1) ? strtolower($rawCustomIcon) : null;
            $useLegacyWebsiteFallback = $rawCustomIcon === 'fa-external-link';
            $legacyCustomCssRaw = is_string($link->custom_css ?? null) ? trim((string) $link->custom_css) : '';
            $legacyCustomCss = strtolower($legacyCustomCssRaw) === 'null' ? '' : $legacyCustomCssRaw;
            $legacyHasCustomCss = $legacyCustomCss !== '';
            $inlineCustomButtonCss = '';
            if ($allowCustomButtons && $canUseButtonStylingByTier) {
                if ($hasGlobalCustomCss) {
                    $inlineCustomButtonCss = $globalCustomCss;
                } elseif ($legacyHasCustomCss) {
                    $inlineCustomButtonCss = $legacyCustomCss;
                }
            }
        @endphp
        @if(isset($link->custom_html) && $link->custom_html)
            @if(isset($link->ignore_container) && $link->ignore_container)
            </div></div></div>
            @endif
                @php setBlockAssetContext($link->type); @endphp
                @include('blocks::' . $link->type . '.display', ['link' => $link, 'initial' => $initial++])
            @if(isset($link->ignore_container) && $link->ignore_container)
            <div class="container"><div class="row"><div class="column">
            @endif
        @else
            @switch($link->name)
                @case('icon')
                    @break
                @case('vcard')
                    <div style="--delay: {{ $initial++ }}s" class="button-entrance"><a id="{{ $link->id }}" class="button button-default button-click button-hover icon-hover ls-link-interactive{{ $adultClass }}" rel="noopener noreferrer nofollow noindex" href="{{ route('vcard') . '/' . $link->id }}"><img alt="{{ $link->name }}" class="icon hvr-icon" src="@if(theme('use_custom_icons') == "true"){{ url('themes/' . $GLOBALS['themeName'] . '/extra/custom-icons')}}/vcard{{theme('custom_icon_extension')}} @else{{ asset('\/assets/wayvio/icons\/')}}vcard.svg @endif"><span class="button-text-wrapper">{{ $link->title }}</span>@if($isAdultLink)<span class="adult-badge" aria-label="Adults only">18+</span>@endif</a></div>
                        @break
                @case('phone')
                <div style="--delay: {{ $initial++ }}s" class="button-entrance"><a id="{{ $link->id }}" class="button button-default button-click button-hover icon-hover ls-link-interactive{{ $adultClass }}" rel="noopener noreferrer nofollow noindex" href="{{ $link->link }}"><img alt="{{ $link->name }}" class="icon hvr-icon" src="@if(theme('use_custom_icons') == "true"){{ url('themes/' . $GLOBALS['themeName'] . '/extra/custom-icons')}}/phone{{theme('custom_icon_extension')}} @else{{ asset('\/assets/wayvio/icons\/')}}phone.svg @endif"><span class="button-text-wrapper">{{ $link->title }}</span>@if($isAdultLink)<span class="adult-badge" aria-label="Adults only">18+</span>@endif</a></div>
                    @break
                @case('custom')
                   <div style="--delay: {{ $initial++ }}s" class="button-entrance"><a id="{{ $link->id }}" class="button button-custom button-click button-hover icon-hover ls-link-interactive{{ $adultClass }}" @if($inlineCustomButtonCss !== '')style="{{ $inlineCustomButtonCss }}"@endif rel="noopener noreferrer nofollow noindex" href="{{ $link->link }}" @if((UserData::getData($userinfo->id, 'links-new-tab') != false))target="_blank"@endif >@if(!$hideIcon) @if($customBiIcon)<i class="icon hvr-icon bi {{ $customBiIcon }}"></i>@else<i class="icon hvr-icon fa {{ $customFaIcon ?: 'fa-external-link' }}"></i>@endif @endif<span class="button-text-wrapper">{{ $link->title }}</span>@if($isAdultLink)<span class="adult-badge" aria-label="Adults only">18+</span>@endif</a></div>
                      @break
                @case('custom_website')
                    <div style="--delay: {{ $initial++ }}s" class="button-entrance"><a id="{{ $link->id }}" class="button button-custom_website button-click button-hover icon-hover ls-link-interactive{{ $adultClass }}" @if($inlineCustomButtonCss !== '')style="{{ $inlineCustomButtonCss }}"@endif rel="noopener noreferrer nofollow noindex" href="{{ $link->link }}" @if((UserData::getData($userinfo->id, 'links-new-tab') != false))target="_blank"@endif >@if(!$hideIcon) @if(!$useLegacyWebsiteFallback && $customBiIcon)<i class="icon hvr-icon bi {{ $customBiIcon }}"></i>@elseif(!$useLegacyWebsiteFallback && $customFaIcon)<i class="icon hvr-icon fa {{ $customFaIcon }}"></i>@else<img alt="{{ $link->name }}" class="icon hvr-icon" src="{{ file_exists(base_path('assets/favicon/icons/').localIcon($link->id)) ? url('assets/favicon/icons/'.localIcon($link->id)) : getFavIcon($link->id) }}" onerror="this.onerror=null; this.src='{{asset('assets/wayvio/icons/website.svg')}}';">@endif @endif<span class="button-text-wrapper">{{ $link->title }}</span>@if($isAdultLink)<span class="adult-badge" aria-label="Adults only">18+</span>@endif</a></div>
                     @break
                   @default
                @php
                    $normalizedButtonName = strtolower(trim(str_replace('default ', '', (string) $link->name)));
                    $coreButtonIconUrl = asset('assets/wayvio/icons/' . $normalizedButtonName . '.svg');
                    $themeButtonIconUrl = url('themes/' . $GLOBALS['themeName'] . '/extra/custom-icons') . '/' . $normalizedButtonName . theme('custom_icon_extension');
                    $resolvedButtonIconUrl = theme('use_custom_icons') == "true" ? $themeButtonIconUrl : $coreButtonIconUrl;
                @endphp
                <div style="--delay: {{ $initial++ }}s" class="button-entrance"><a id="{{ $link->id }}" class="button button-default button-{{ $normalizedButtonName }} button-click button-hover icon-hover ls-link-interactive{{ $adultClass }}" rel="noopener noreferrer nofollow noindex" href="{{ $link->link }}" @if((UserData::getData($userinfo->id, 'links-new-tab') != false))target="_blank"@endif ><img alt="{{ $link->name }}" class="icon hvr-icon" src="{{ $resolvedButtonIconUrl }}" onerror="if(!this.dataset.iconFallback){this.dataset.iconFallback='1';this.src='{{ $coreButtonIconUrl }}';return;}this.onerror=null;this.src='{{ asset('assets/wayvio/icons/website.svg') }}';"><span class="button-text-wrapper">{{ $link->title }}</span>@if($isAdultLink)<span class="adult-badge" aria-label="Adults only">18+</span>@endif</a></div>
            @endswitch
        @endif
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function handleClickOrTouch(event) {
                var buttonEl = event.target.closest('.button-click');
                if (buttonEl) {
                    var id = buttonEl.id;
                    if (!sessionStorage.getItem('clicked-' + id)) {
                        var url = '{{ route("clickNumber") }}/' + id;
                        fetch(url, {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                        });
                        sessionStorage.setItem('clicked-' + id, 'true');
                    }
                }
            }
    
            document.addEventListener('mousedown', function (event) {
                if (event.button === 0 || event.button === 1) {
                    handleClickOrTouch(event);
                }
            });
    
            document.addEventListener('touchstart', handleClickOrTouch);
        });
    </script>
