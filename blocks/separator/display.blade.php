<?php use App\Models\UserData; ?>
@php
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);
    $accentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
@endphp

@once
<style>
    .ls-separator-block {
        width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
        box-sizing: border-box;
        margin: 0 auto var(--ls-content-block-gap, 41.4px);
        padding-inline: 10px;
    }

    .ls-separator-block__line {
        display: block;
        width: 96%;
        margin: 0 auto;
        height: 1px;
        background: linear-gradient(
            90deg,
            transparent 0%,
            var(--ls-separator-color, currentColor) 18%,
            var(--ls-separator-color, currentColor) 82%,
            transparent 100%
        );
        border-radius: 999px;
        opacity: 0.28;
        transform: scaleY(0.7);
        transform-origin: center;
    }

    @media (max-width: 768px) {
        .ls-separator-block {
            width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            margin-bottom: var(--ls-content-block-gap-mobile, 36.8px);
            padding-inline: 8px;
        }
    }
</style>
@endonce

<div
    style="--delay: {{ $initial }}s; --ls-separator-color: {{ $accentColor }};"
    class="ls-separator-block fadein"
    role="separator"
    aria-hidden="true"
>
    <span class="ls-separator-block__line"></span>
</div>
