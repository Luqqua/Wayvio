@php
    $imprintUrl = url($userinfo->littlelink_name . '/imprint');
    $linkLabel = $link->title ?: 'Impressum';
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $allowCustomButtons = templateCapability($themeForCapabilities, 'custom_buttons', $themeForCapabilities === 'default');
    $rawGlobalCustomCss = \App\Models\UserData::getData($userinfo->id ?? null, 'global_custom_button_css');
    $globalCustomCss = is_string($rawGlobalCustomCss) ? trim($rawGlobalCustomCss) : '';
    if (strtolower($globalCustomCss) === 'null') {
        $globalCustomCss = '';
    }
    $useGlobalCustomStyle = ($globalCustomCss !== '') && $allowCustomButtons;
@endphp

<div style="--delay: {{ $initial }}s" class="button-entrance">
    <a id="{{ $link->id }}" class="button {{ $useGlobalCustomStyle ? 'button-custom' : 'button-default' }} button-click button-hover ls-link-interactive" @if($useGlobalCustomStyle) style="{{ $globalCustomCss }}" @endif href="{{ $imprintUrl }}">
        <span class="button-text-wrapper">{{ $linkLabel }}</span>
    </a>
</div>
