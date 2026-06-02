<?php use App\Models\UserData; ?>

        @php
            $icons = $icons ?? null;
            if ($icons === null) {
                $iconQuery = \App\Models\Link::where('user_id', $userinfo->id)->where('button_id', 94);
                if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
                    $iconQuery->where('is_disabled', false);
                }
                $icons = $iconQuery->get();
            }
        @endphp
        @if(count($icons) > 0)
        @php
            $themeForCapabilities = empty($info->theme) ? 'default' : (string) $info->theme;
            $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
            $textColorSettings = resolveUserTextColorSettings($userinfo->id);
            $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
        @endphp
        <div class="row fadein social-icon-div" style="margin: 0; padding: 0;">
        @foreach($icons as $icon)
        <a class="social-hover social-link" href="{{ $icon->link }}" title="{{ucfirst($icon->title)}}" aria-label="{{ucfirst($icon->title)}}" @if((UserData::getData($userinfo->id, 'links-new-tab') != false))target="_blank"@endif><i id="{{ $icon->id }}" class="button-click dynamic-contrast social-icon fa-brands fa-{{$icon->title}}" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif></i></a>
        @endforeach
        </div>
        @endif

@push('wayvio-head-end')
<style>
    .social-icon-div {
        display: flex;
        justify-content: center;
        gap: var(--ls-social-icons-gap, 14px);
        margin: 0 !important;
        padding: 0 !important; /* Override theme padding for consistent spacing */
    }

    .social-icon-div .social-link {
        margin: 0 !important;
    }

    .social-icon-div .social-icon {
        font-size: var(--ls-social-icon-size, 30px);
        line-height: 1;
    }
</style>
@endpush
