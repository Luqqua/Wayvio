<?php use App\Models\UserData; ?>
@php
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);
    $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
@endphp

@once
<style>
    .ls-heading-block {
        width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
        box-sizing: border-box;
        margin: 0 auto var(--ls-content-block-gap, 41.4px);
        text-align: center;
    }

    .ls-heading-block__title {
        margin: 0;
        font-size: var(--ls-type-block-title-size, clamp(1.2rem, 1.08rem + 0.55vw, 1.48rem));
        line-height: var(--ls-type-block-title-line, 1.16);
        font-weight: var(--ls-type-block-title-weight, 760);
        letter-spacing: 0;
        text-wrap: balance;
        word-break: normal;
        overflow-wrap: break-word;
        hyphens: none;
    }

    @media (max-width: 768px) {
        .ls-heading-block {
            width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            margin-bottom: var(--ls-content-block-gap-mobile, 36.8px);
        }
    }
</style>
@endonce

<div style="--delay: {{ $initial }}s" class="ls-heading-block fadein">
    <h2 class="ls-heading-block__title" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif>{{ $link->title }}</h2>
</div>
