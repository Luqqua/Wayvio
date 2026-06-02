<?php use App\Models\UserData; ?>
        <!-- Short Bio -->
        @php
            $themeForCapabilities = empty($info->theme) ? 'default' : (string) $info->theme;
            $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
            $textColorSettings = resolveUserTextColorSettings($userinfo->id);
            $globalTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : null;
            $rawDescription = $info->littlelink_description ?? '';
            $hasDescription = trim(strip_tags($rawDescription)) !== '';
        @endphp
        @if($hasDescription)
        <style>
            .description-parent {
                margin-bottom: 5px;
                padding-bottom: 0;
            }

            .description-parent p {
                margin: 0;
            }
        </style>
        <center>
            <div class="fadein description-parent dynamic-contrast" @if($globalTextColor) style="color: {{$globalTextColor}};" @endif>
                <p class="fadein">
                    {{ $rawDescription }}
                </p>
            </div>
        </center>
        @endif
