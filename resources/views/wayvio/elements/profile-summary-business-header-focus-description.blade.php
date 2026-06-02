<?php use App\Models\UserData; ?>
@php
    $rawShowProfileImage = UserData::getData($userinfo->id, 'show_profile_image');
    $showAvatar = !in_array($rawShowProfileImage, [false, 'false', 0, '0'], true);
    $insideHero = !empty($insideHero);
    $themeForCapabilities = empty($info->theme) ? 'default' : (string) $info->theme;
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id);
    $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
    $rawDescription = $info->littlelink_description ?? '';
    $hasDescription = trim(strip_tags($rawDescription)) !== '';
@endphp

<div class="ls-profile-summary-core ls-profile-summary-core--business ls-profile-summary-core--description-focus {{ !$hasDescription ? 'ls-profile-summary-core--description-focus-empty' : '' }} {{ $insideHero ? 'ls-profile-summary-core--inside-hero' : '' }} fadein">
    <div class="ls-profile-summary-core__brand ls-profile-summary-core__brand--eyebrow {{ $showAvatar ? '' : 'ls-profile-summary-core__brand--no-avatar' }}">
        @if($showAvatar)
            <div class="ls-profile-summary-core__brand-mark">
                @include('wayvio.elements.avatar')
            </div>
        @endif
        <div class="ls-profile-summary-core__title ls-profile-summary-core__title--eyebrow">
            @include('wayvio.elements.heading', ['headingBottomMargin' => '0'])
        </div>
    </div>
    @if($hasDescription)
        <div class="ls-profile-summary-core__description">
            <div class="description-parent dynamic-contrast" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif>
                <p class="fadein">{{ $rawDescription }}</p>
            </div>
        </div>
    @endif
</div>
