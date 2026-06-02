@php
  $__faviconUrl = null;
  if (isset($userinfo)) {
    $__metaPolicy = class_exists(\App\Services\Meta\CustomMetaPolicyService::class)
      ? app(\App\Services\Meta\CustomMetaPolicyService::class)
      : null;
    if ($__metaPolicy && $__metaPolicy->canDeliverCustomMeta($userinfo)) {
      $__faviconUrl = userFaviconUrl($userinfo->id);
    }
  }
@endphp
@if($__faviconUrl)
<link rel="icon" href="{{ $__faviconUrl }}">
@elseif(file_exists(base_path("assets/wayvio/images/").findFile('favicon')))
<link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
@else
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
@endif
