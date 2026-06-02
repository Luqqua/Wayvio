        <!-- Your Image Here -->
        @if(userAvatarExists($userinfo->id))
        <img alt="avatar" id="avatar" class="rounded-avatar fadein" src="{{ userAvatarUrl($userinfo->id) }}" height="128px" width="128px" style="object-fit: cover;">
        @elseif(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
        <img alt="avatar" id="avatar" class="fadein" src="{{ url("assets/wayvio/images/")."/".findFile('avatar') }}" height="128px" width="128px" style="object-fit: cover;">
        @else
        <img alt="avatar" id="avatar" class="fadein" src="{{ asset('assets/wayvio/images/logo.svg') }}" height="128px" style="width:auto;min-width:128px;object-fit: cover;">
        @endif
