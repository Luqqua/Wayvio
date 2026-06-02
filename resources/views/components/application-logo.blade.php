@if(file_exists(base_path("/assets/wayvio/images/avatar.png" )))
    <img class="mb-5" src="{{ asset('/assets/wayvio/images/avatar.png') }}"  style="width: 150px;">
@else
    <img class="mb-5" src="{{ asset('/assets/wayvio/images/avatar@2x.png') }}">
@endif
