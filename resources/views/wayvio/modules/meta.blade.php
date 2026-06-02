<meta charset="utf-8">

{{-- Fediverse rel="me" links --}}
@php
  $relMe = "mastodon, firefish, streams";
  $relMeList = explode(', ', $relMe);

  $customMetaPolicy = class_exists(\App\Services\Meta\CustomMetaPolicyService::class)
    ? app(\App\Services\Meta\CustomMetaPolicyService::class)
    : null;
  $canCustomMeta = $customMetaPolicy ? $customMetaPolicy->canDeliverCustomMeta($userinfo) : false;
  $metaOverrides = ($canCustomMeta && is_array($userinfo->meta_overrides ?? null)) ? $userinfo->meta_overrides : [];
  $metaConfig = config('meta.defaults', []);
  $displayName = $userinfo->name ?: ($userinfo->littlelink_name ?? '');

  $metaDefaultsService = class_exists(\App\Services\Meta\MetaDefaultsService::class)
    ? app(\App\Services\Meta\MetaDefaultsService::class)
    : null;

  if ($metaDefaultsService) {
    $metaDefaults = $metaDefaultsService->defaultsForPage(
      $userinfo,
      $displayName,
      (string) ($userinfo->littlelink_description ?? '')
    );
  } else {
    $fallbackDescription = trim(strip_tags((string) ($userinfo->littlelink_description ?? '')));
    $defaultTitlePattern = trim((string) ($metaConfig['title_pattern'] ?? ':username'));
    $defaultDescriptionPattern = trim((string) ($metaConfig['description_pattern'] ?? ''));
    $defaultKeywordsPattern = trim((string) ($metaConfig['keywords_pattern'] ?? ''));
    $applyPattern = function (?string $pattern) use ($displayName): string {
      return trim(str_replace(':username', $displayName, (string) ($pattern ?? '')));
    };

    $metaDefaults = [
      'title' => $applyPattern($defaultTitlePattern !== '' ? $defaultTitlePattern : ':username'),
      'description' => $defaultDescriptionPattern !== ''
        ? $applyPattern($defaultDescriptionPattern)
        : trim($displayName . ($fallbackDescription !== '' ? ' - ' . $fallbackDescription : '')),
      'keywords' => $defaultKeywordsPattern !== ''
        ? $applyPattern($defaultKeywordsPattern)
        : trim($displayName . ', ' . config('app.name', env('APP_NAME', 'Wayvio'))),
      'robots' => $metaConfig['robots'] ?? 'index,follow',
      'twitter_card' => $metaConfig['twitter_card'] ?? 'summary_large_image',
      'og_locale' => $metaConfig['og_locale'] ?? app()->getLocale(),
    ];
  }

  $defaultTitle = (string) ($metaDefaults['title'] ?? $displayName);
  $defaultDescription = (string) ($metaDefaults['description'] ?? $displayName);
  $defaultKeywords = trim((string) ($metaDefaults['keywords'] ?? ''));

  $metaValues = [
    'title' => $metaOverrides['title'] ?? $defaultTitle,
    'description' => $metaOverrides['description'] ?? $defaultDescription,
    'keywords' => $metaOverrides['keywords'] ?? $defaultKeywords,
    'og_title' => $metaOverrides['og_title'] ?? ($metaOverrides['title'] ?? $defaultTitle),
    'og_description' => $metaOverrides['og_description'] ?? ($metaOverrides['description'] ?? $defaultDescription),
    'robots' => $metaOverrides['robots'] ?? ($metaDefaults['robots'] ?? ($metaConfig['robots'] ?? 'index,follow')),
    'twitter_card' => $metaOverrides['twitter_card'] ?? ($metaDefaults['twitter_card'] ?? ($metaConfig['twitter_card'] ?? 'summary_large_image')),
    'og_locale' => $metaOverrides['og_locale'] ?? ($metaDefaults['og_locale'] ?? ($metaConfig['og_locale'] ?? app()->getLocale())),
  ];

  $metaValues['canonical'] = $metaOverrides['canonical_url'] ?? ((isset($metaConfig['canonical_base']) && $metaConfig['canonical_base'])
    ? rtrim($metaConfig['canonical_base'], '/') . '/' . '@' . $littlelink_name
    : null);
@endphp

@foreach($links as $link)
  @if(in_array($link->name, $relMeList))
    <link href="{{ $link->link }}" rel="me">
  @endif
@endforeach

<meta name="description" content="{{ e($metaValues['description']) }}">
@if(!empty($metaValues['keywords']))
<meta name="keywords" content="{{ e($metaValues['keywords']) }}">
@endif
<meta name="author" content="{{ e($userinfo->name) }}">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
@if(!empty($metaValues['robots']))
<meta name="robots" content="{{ e($metaValues['robots']) }}">
@endif
@if(!empty($metaValues['canonical']))
<link rel="canonical" href="{{ e($metaValues['canonical']) }}">
@endif

<!--#### BEGIN Meta Tags social media preview images  ####-->
  <!-- This shows a preview for title, description and avatar image of users profiles if shared on social media sites -->

    <!-- Facebook Meta Tags -->
    <meta property="og:url" content="{{ url('') }}/{{ '@' . $littlelink_name }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ e($metaValues['og_locale']) }}">
    <meta property="og:title" content="{{ e($metaValues['og_title']) }}">
    <meta property="og:description" content="{{ e($metaValues['og_description']) }}">
    @php
      $ogImage = null;
    @endphp
    @if(userAvatarExists($userinfo->id))
      @php $ogImage = userAvatarUrl($userinfo->id); @endphp
    @elseif(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
      @php $ogImage = url("assets/wayvio/images/")."/".findFile('avatar'); @endphp
    @else
      @php $ogImage = asset('assets/wayvio/images/logo.svg'); @endphp
    @endif
    <meta property="og:image" content="{{ $ogImage }}">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="{{ e($metaValues['twitter_card']) }}">
    <meta property="twitter:domain" content="{{ url('') }}/{{ '@' . $littlelink_name }}">
    <meta property="twitter:url" content="{{ url('') }}/{{ '@' . $littlelink_name }}">
    <meta name="twitter:title" content="{{ e($metaValues['title']) }}">
    <meta name="twitter:description" content="{{ e($metaValues['og_description']) }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

<!--#### END Meta Tags social media preview images  ####-->

@php
  $computedTitle = $metaValues['title'];
  if(config('advanced-config.linkstack_title') != '' and env('HOME_URL') === '') {
    $computedTitle = $userinfo->name . ' ' . config('advanced-config.linkstack_title');
  } elseif(env('CUSTOM_META_TAGS') == 'true' and config('advanced-config.title') != '') {
    $computedTitle = ($userinfo->name ?: $metaValues['title']) . ' ' . config('advanced-config.title');
  } elseif(env('HOME_URL') != '') {
    $computedTitle = $userinfo->name;
  }
@endphp
<title>{{ e($computedTitle) }}</title>

@if($canCustomMeta && config('meta.structured_data.enabled'))
  @php
    $structuredData = [
      '@context' => 'https://schema.org',
      '@type' => config('meta.structured_data.type', 'Person'),
      'name' => $userinfo->name ?? $userinfo->littlelink_name,
      'url' => url('@' . $littlelink_name),
      'description' => $metaValues['description'],
      'image' => $ogImage,
    ];
  @endphp
  <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif

@include('components.favicon')
@include('components.favicon-extension')
@include('wayvio.modules.favicon')

@include('layouts.analytics')
