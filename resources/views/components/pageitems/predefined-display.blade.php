@php
    $normalizedButtonName = strtolower(trim(str_replace('default ', '', (string) ($params->button ?? ''))));
    $coreButtonIconUrl = asset('assets/wayvio/icons/' . $normalizedButtonName . '.svg');
    $themeButtonIconUrl = url('themes/' . $GLOBALS['themeName'] . '/extra/custom-icons') . '/' . $normalizedButtonName . theme('custom_icon_extension');
    $resolvedButtonIconUrl = theme('use_custom_icons') == "true" ? $themeButtonIconUrl : $coreButtonIconUrl;
@endphp
<a class="button button-default button-{{ $normalizedButtonName }} button button-hover icon-hover ls-link-interactive" rel="noopener noreferrer nofollow" href="{{ route('clickNumber') . '/' . $link->id . '/' . $link->link}}" @if(theme('open_links_in_same_tab') !="true" )target="_blank" @endif>
    <img alt="button-icon" class="icon hvr-icon" src="{{ $resolvedButtonIconUrl }}" onerror="if(!this.dataset.iconFallback){this.dataset.iconFallback='1';this.src='{{ $coreButtonIconUrl }}';return;}this.onerror=null;this.src='{{ asset('assets/wayvio/icons/website.svg') }}';">

    {{ ucfirst($link->title) }}
</a>
