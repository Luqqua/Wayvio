<?php use App\Models\UserData; ?>
@php
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);
    $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
@endphp

@once
<style>
    .ls-text-block {
        width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
        box-sizing: border-box;
        margin: 0 auto var(--ls-content-block-gap, 41.4px);
        text-align: center;
    }

    .ls-text-block__content {
        display: block;
        margin: 0;
        font-size: var(--ls-type-body-size, 0.96rem);
        line-height: var(--ls-type-body-line, 1.5);
        font-weight: var(--ls-type-body-weight, 400);
        letter-spacing: 0;
        text-wrap: pretty;
        word-break: normal;
        overflow-wrap: break-word;
        hyphens: none;
    }

    @media (max-width: 768px) {
        .ls-text-block {
            width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            margin-bottom: var(--ls-content-block-gap-mobile, 36.8px);
        }
    }
</style>
@endonce

<div style="--delay: {{ $initial }}s" class="ls-text-block fadein">
    <span class="ls-text-block__content" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif>{{ $link->title }}</span>
</div>
