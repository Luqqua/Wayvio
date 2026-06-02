@php
    $capBaseUrl = rtrim((string) config('forms.cap.base_url'), '/');
    $capSiteKey = trim((string) config('forms.cap.site_key'));
@endphp

@if($capBaseUrl !== '' && $capSiteKey !== '')
  <div class="col-lg-12 mb-3">
    <cap-widget data-cap-api-endpoint="{{ $capBaseUrl }}/{{ $capSiteKey }}/"></cap-widget>
    @once
      <script src="{{ $capBaseUrl }}/assets/widget.js" async defer></script>
    @endonce
  </div>
@endif
