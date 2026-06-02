<?php use App\Models\UserData; ?>
@php
    $themeForCapabilities = empty($info->theme) ? 'default' : (string) $info->theme;
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id);
    $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
    $headingBottomMargin = trim((string) ($headingBottomMargin ?? '5px'));
    $displayName = trim((string) ($info->name ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) ($info->littlelink_name ?? ''));
    }
    if ($headingBottomMargin === '') {
        $headingBottomMargin = '5px';
    }
@endphp
<!-- Your Name -->
        <h1 class="fadein dynamic-contrast profile-heading" style="@if($globalTextColor)color: {{$globalTextColor}}; @endif margin: 0 0 {{ $headingBottomMargin }} !important;">{{ $displayName }}@if(($userinfo->role == 'vip' or $userinfo->role == 'admin') and theme('disable_verification_badge') != "true" and env('HIDE_VERIFICATION_CHECKMARK') != true and UserData::getData($userinfo->id, 'checkmark') != false)<span title="{{__('messages.Verified user')}}">@include('components.verify-svg')@endif</span></h1>
