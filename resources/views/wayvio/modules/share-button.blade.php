<?php use App\Models\UserData; ?>

@php
    $ShowShrBtn = config('advanced-config.display_share_button');
    $themeName = $userinfo->theme ?? ($information[0]->theme ?? 'default');
    $themeForCapabilities = empty($themeName) ? 'default' : (string) $themeName;
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id);
    $textMode = $textColorSettings['mode'] ?? 'white';
    $resolvedTextColor = $textColorSettings['color'] ?? '#FFFFFF';
    $textColorIsDark = $textColorSettings['is_dark'] ?? false;
    $shareButtonCustomStyle = null;
    if ($applyUserTextColors && $textMode === 'custom') {
        $shareButtonBackground = $textColorIsDark ? 'rgba(255, 255, 255, 0.88)' : 'rgba(0, 0, 0, 0.58)';
        $shareButtonCustomStyle = "color: {$resolvedTextColor}; border: 1px solid {$resolvedTextColor}; background-color: {$shareButtonBackground};";
    }

    if ($ShowShrBtn === 'false') {
        $ShowShrBtn = 'false';
    } elseif ($ShowShrBtn === 'user') {
        $ShowShrBtn = !empty($userinfo->littlelink_name) ? 'true' : 'false';
    } elseif (UserData::getData($userinfo->id, 'disable-sharebtn') == "true") {
        $ShowShrBtn = 'false';
    } else {
        $ShowShrBtn = 'true';
    }

@endphp

<div align="right" @if($ShowShrBtn == 'false') style="visibility:hidden" @endif class="sharediv">
  <div>
    <span class="sharebutton button-hover icon-hover share-button @if($applyUserTextColors && $textMode === 'black') sharebutton-dark @elseif($applyUserTextColors && $textMode === 'custom') sharebutton-custom @endif" @if($shareButtonCustomStyle) style="{{ $shareButtonCustomStyle }}" @endif data-share="{{url()->current()}}" tabindex="0" role="button" aria-label="{{__('messages.Share this page')}}">
      <i class="fa-solid fa-share sharebutton-img share-icon hvr-icon"></i>
      <span class="sharebutton-mb">{{__('messages.Share')}}</span>
    </span>
  </div>
</div>
<span class="copy-icon" tabindex="0" role="button" aria-label="{{__('messages.Copy URL to clipboard')}}"></span>

@if($ShowShrBtn == 'true')
<script>const shareButtons=document.querySelectorAll(".share-button");shareButtons.forEach((e=>{e.addEventListener("click",(()=>{const r=e.dataset.share;navigator.share?navigator.share({title:"{{__('messages.Share this page')}}",url:r}).catch((e=>console.error("Error:",e))):navigator.clipboard.writeText(r).then((()=>{alert("{{__('messages.URL has been copied to your clipboard!')}}")})).catch((e=>{alert("Error",e)}))}))}));</script>
@endif

@push('wayvio-head')
<style>
    .sharebutton-dark {
        background-color: #000000 !important;
        color: #FFFFFF !important;
        border-color: #000000 !important;
    }
    .sharebutton-dark .share-icon,
    .sharebutton-dark .sharebutton-mb {
        color: #FFFFFF !important;
    }
    .sharebutton-custom .share-icon,
    .sharebutton-custom .sharebutton-mb {
        color: inherit !important;
    }
</style>
@endpush
@push('wayvio-head-end')
<style>
    /* Inline share button sits in the flow above the avatar */
    .sharediv {
        position: sticky !important;
        top: 16px;
        right: 0 !important;
        left: auto;
        display: flex;
        justify-content: flex-end;
        width: 100%;
        max-width: 100vw;
        box-sizing: border-box;
        margin: 0 0 10px 0 !important;
        padding: 8px calc(20px + env(safe-area-inset-right, 0px)) 0 12px !important;
        pointer-events: none;
        z-index: 12;
    }

    .sharediv .share-button {
        pointer-events: auto;
    }

    /* Remove reserved space when the share button is hidden */
    .sharediv[style*="visibility:hidden"] {
        display: none !important;
    }

    /* When a cover header exists, float the share button over it */
    .sharediv.sharediv--over-header {
        position: absolute !important;
        top: 16px;
        right: calc(20px + env(safe-area-inset-right, 0px)) !important;
        left: auto;
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }
</style>
@endpush
