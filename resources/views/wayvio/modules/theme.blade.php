@php
use App\Models\UserData;

// Theme Config
if (!function_exists('theme')) {
  function theme($key){
$key = trim($key);
$file = base_path('themes/' . $GLOBALS['themeName'] . '/config.php');
  if (file_exists($file)) {
    $config = include $file;
  if (isset($config[$key])) {
    return $config[$key];
}}
return null;}
}

// Theme Custom Asset
if (!function_exists('themeAsset')) {
function themeAsset($path){
$path = url('themes/' . $GLOBALS['themeName'] . '/extra/custom-assets/' . $path);
return $path;}
}

if (!function_exists('themeDefaultBackgroundColor')) {
  function themeDefaultBackgroundColor($themeName, $fallback = '#111827') {
    $paths = [
      base_path("themes/{$themeName}/skeleton-auto.css"),
      base_path("themes/{$themeName}/brands.css"),
    ];

    foreach ($paths as $path) {
      if (!file_exists($path)) {
        continue;
      }

      $content = file_get_contents($path);

      if (preg_match('/--background-color:\s*([^;]+);/i', $content, $match)) {
        return trim($match[1]);
      }

      if (preg_match('/--bgColor:\s*([^;]+);/i', $content, $match)) {
        return trim($match[1]);
      }
    }

    return $fallback;
  }
}

$brandingConfig = config('branding.public', []);
$brandingTheme = $brandingConfig['theme'] ?? null;

$customBackgroundExists = false;
@endphp

@foreach($information as $info) @php $GLOBALS['themeName'] = $info->theme ?: ($brandingTheme ?? 'default'); @endphp @endforeach
@php
$activeTemplate = resolveTemplateForTheme($GLOBALS['themeName'] ?? 'default');
$rawThemeVariantId = UserData::getData($userinfo->id, 'theme_variant_id');
$themeVariantId = is_string($rawThemeVariantId) ? $rawThemeVariantId : null;
$activeThemeVariant = $activeTemplate
  ? templateCatalog()->variantForTemplateId((string) ($activeTemplate['id'] ?? ''), $themeVariantId)
  : null;
$activeThemeVariantCssUrl = is_array($activeThemeVariant) ? ($activeThemeVariant['css_url'] ?? null) : null;
$templateDesignSettings = resolveTemplateDesignSettings($userinfo->id ?? null, $GLOBALS['themeName'] ?? 'default');
$applyTemplateDesignSettings = templateCapability($GLOBALS['themeName'] ?? 'default', 'text_accent', ($GLOBALS['themeName'] ?? 'default') === 'default');
$canUseBackgroundImageByTier = pageOwnerHasTierFeature($userinfo->id ?? null, 'design.background_image');
$canUseBackgroundColorByTier = pageOwnerHasTierFeature($userinfo->id ?? null, 'design.custom_colors');
@endphp

@if(theme('allow_custom_background') != "false")
@php
$customBackgroundPath = userBackgroundMediaPath($userinfo->id);
$customBackgroundURL = $customBackgroundPath ? mediaPathUrl($customBackgroundPath) : null;
$customBackgroundExists = $customBackgroundPath !== null && $customBackgroundURL !== null;
$valueOrDefault = function ($value, $default) {
  return ($value === null || $value === "null") ? $default : $value;
};

$backgroundMode = $valueOrDefault(UserData::getData($userinfo->id, 'background_mode'), ($customBackgroundExists ? 'image' : 'color'));
$backgroundMode = $backgroundMode === 'image' && (!$customBackgroundExists || !$canUseBackgroundImageByTier) ? 'color' : $backgroundMode;
$pageTheme = $GLOBALS['themeName'] ?? 'default';
if (!empty($pageTheme) && $pageTheme !== 'default') {
  $backgroundMode = 'template';
}
$backgroundColor = $valueOrDefault(UserData::getData($userinfo->id, 'background_color'), '#111827');
$overlaySettings = UserData::getData($userinfo->id, 'background_overlay');
if ($overlaySettings === "null" || $overlaySettings === null) {
  $overlaySettings = [];
}
$overlayColor = is_array($overlaySettings) && !empty($overlaySettings['color']) ? $overlaySettings['color'] : '#000000';
$overlayOpacity = is_array($overlaySettings) && isset($overlaySettings['opacity']) ? $overlaySettings['opacity'] : 0;
$gradientSettings = UserData::getData($userinfo->id, 'background_gradient');
if ($gradientSettings === "null" || $gradientSettings === null) {
  $gradientSettings = [];
}
$gradientEnabled = is_array($gradientSettings) ? ($gradientSettings['enabled'] ?? false) : false;
$rawGradientStops = is_array($gradientSettings) ? ($gradientSettings['stops'] ?? []) : [];
$sanitizeStop = function ($stop, $fallbackPosition = null) {
  $color = is_array($stop) ? ($stop['color'] ?? null) : null;
  if (!$color) {
    return null;
  }
  $position = is_array($stop) && array_key_exists('position', $stop) ? (int)$stop['position'] : $fallbackPosition;
  $position = $position === null ? null : max(0, min(100, $position));
  return ['color' => $color, 'position' => $position ?? 0];
};

$gradientStops = [];
if (is_array($rawGradientStops) && !empty($rawGradientStops)) {
  foreach ($rawGradientStops as $index => $stop) {
    $gradientStops[] = $sanitizeStop($stop, $index === 0 ? 0 : ($index === 1 ? 100 : 50));
  }
  $gradientStops = array_values(array_filter($gradientStops));
}

if (empty($gradientStops)) {
  $legacyColors = is_array($gradientSettings) ? ($gradientSettings['colors'] ?? []) : [];
  $gradientStops = array_values(array_filter([
    ['color' => $legacyColors[0] ?? $backgroundColor, 'position' => 0],
    ['color' => $legacyColors[1] ?? '#0ea5e9', 'position' => 100],
    isset($legacyColors[2]) ? ['color' => $legacyColors[2], 'position' => 50] : null,
  ]));
}

$disableCustomizations = $backgroundMode === 'template';
if ($disableCustomizations) {
  $gradientEnabled = false;
  $gradientStops = [];
  $overlayOpacity = 0;
}
$overlayAlpha = max(0, min(($overlayOpacity ?? 0), 100)) / 100;
$hasGradient = $gradientEnabled && count($gradientStops) >= 2;
$templateDefaultColor = themeDefaultBackgroundColor($GLOBALS['themeName'] ?? 'default', '#111827');

$useCustomImage = $customBackgroundExists && $backgroundMode === 'image' && $canUseBackgroundImageByTier;
$useSolid = !$useCustomImage && $backgroundMode === 'color' && $canUseBackgroundColorByTier;

$resolvedType = 'template-default';
if ($useCustomImage) {
  $resolvedType = 'image';
} elseif ($useSolid) {
  $resolvedType = 'solid';
}

$backgroundLayerStyle = '';
$overlayActive = false;

if ($resolvedType === 'image') {
  $backgroundLayerStyle = "background-image: url('{$customBackgroundURL}'); background-size: cover; background-position: center; background-repeat: no-repeat; background-color: transparent;";
  $overlayActive = $overlayAlpha > 0;
} elseif ($resolvedType === 'solid') {
  if ($hasGradient) {
    $renderGradientStops = $gradientStops;
    $lastStopIndex = count($renderGradientStops) - 1;
    $minTransitionGap = 12;
    $lastPosition = 0;

    foreach ($renderGradientStops as $index => $stop) {
      $position = max(0, min(100, (int) ($stop['position'] ?? 0)));

      $remainingStops = $lastStopIndex - $index;
      $maxAllowed = 100 - ($remainingStops * $minTransitionGap);
      $maxAllowed = max(0, min(100, $maxAllowed));

      if ($index === 0) {
        $position = min($position, $maxAllowed);
      } else {
        $minAllowed = min(100, $lastPosition + $minTransitionGap);
        if ($minAllowed > $maxAllowed) {
          $position = $maxAllowed;
        } else {
          $position = max($minAllowed, min($position, $maxAllowed));
        }
      }

      $renderGradientStops[$index]['position'] = $position;
      $lastPosition = $position;
    }

    $gradientBaseColor = $renderGradientStops[0]['color'] ?? '#111827';
    $accentRaw = $renderGradientStops[1]['color'] ?? null;
    $gradientAccentColor = ($accentRaw && $accentRaw !== $gradientBaseColor) ? $accentRaw : autoAccentColor($gradientBaseColor);
    $gradientDarkenedColor = darkenHex($gradientBaseColor, 12);

    $gradientCss = "radial-gradient(ellipse 200% 140% at 50% 20%, {$gradientAccentColor} 0%, {$gradientBaseColor} 92%)";
    $gradientSoftCss = "radial-gradient(ellipse 160% 90% at 50% -10%, {$gradientAccentColor} 0%, {$gradientBaseColor} 85%)";
    $gradientSoftAltCss = "radial-gradient(ellipse 120% 100% at 50% 30%, {$gradientBaseColor} 20%, {$gradientDarkenedColor} 100%)";

    $backgroundLayerStyle = "--ls-user-gradient: {$gradientCss}; --ls-user-gradient-soft: {$gradientSoftCss}; --ls-user-gradient-soft-alt: {$gradientSoftAltCss}; --ls-user-gradient-base: {$gradientBaseColor}; --ls-user-gradient-c1: {$gradientBaseColor}; --ls-user-gradient-c2: {$gradientAccentColor}; --ls-user-gradient-c3: {$gradientDarkenedColor}; background-image: {$gradientCss}; background-color: {$gradientBaseColor}; background-repeat: no-repeat;";
  } else {
    $backgroundLayerStyle = "background-color: {$backgroundColor};";
  }
  $overlayActive = $overlayAlpha > 0;
} else {
  $backgroundLayerStyle = "background-color: {$templateDefaultColor};";
}

$overlayColorCss = hexToRgba($overlayColor, $overlayAlpha);
$isTemplateDefault = ($resolvedType === 'template-default');
$shouldRenderBackground = $resolvedType !== 'template-default';
$fallbackBackgroundColor = $hasGradient && !empty($gradientStops[0]['color'])
    ? $gradientStops[0]['color']
    : ($backgroundColor ?? '#000000');
$pageBackgroundStartColor = $templateDefaultColor;
if ($resolvedType === 'solid') {
  $pageBackgroundStartColor = $hasGradient && !empty($gradientStops[0]['color'])
    ? $gradientStops[0]['color']
    : ($backgroundColor ?? $templateDefaultColor);
} elseif ($resolvedType === 'image') {
  $pageBackgroundStartColor = $backgroundColor ?? $templateDefaultColor;
}
@endphp

<style>
  :root {
    --ls-page-background-start: {{$pageBackgroundStartColor}};
    --hero-fade-color: var(--ls-page-background-start);
  }
</style>

@if($shouldRenderBackground)
<style>
  html {
    height: 100%;
    min-height: 100%;
    @if($isTemplateDefault)
    background: {{$templateDefaultColor}} !important;
    @else
    @if($hasGradient && !empty($gradientStops))
    background: {{ reset($gradientStops)['color'] }} !important;
    @else
    background: {{ $fallbackBackgroundColor }} !important;
    @endif
    @endif
    background-image: none !important;
    background-attachment: scroll;
    -webkit-overflow-scrolling: touch;
  }
  
  body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100vh;
    min-height: -webkit-fill-available;
    background: transparent !important;
    background-image: none !important;
    position: relative;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
  }
  
  @if($isTemplateDefault)
  :root {
    --template-background: {{$templateDefaultColor}};
  }
  @endif
  
  /* Fixed background layer - sits behind everything */
  .fullscreen-background {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    min-height: 100vh;
    min-height: 100dvh;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: scroll;
    z-index: -2;
    pointer-events: none;
    @if($isTemplateDefault)
    background: var(--template-background, transparent);
    @endif
    /* iOS optimization */
    -webkit-transform: translate3d(0, 0, 0);
    transform: translate3d(0, 0, 0);
    will-change: auto;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    /* Extend beyond viewport to prevent gaps */
    margin: -1px;
    padding: 1px;
  }

  .fullscreen-background.is-user-gradient {
    background-image: var(--ls-user-gradient) !important;
    background-color: var(--ls-user-gradient-base, transparent);
    background-size: 100% 100%;
    background-position: center center;
  }

  .fullscreen-background.is-user-gradient::before {
    content: "";
    position: absolute;
    inset: -6%;
    background-image: var(--ls-user-gradient-soft);
    background-size: 125% 125%;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.1;
    filter: blur(22px) saturate(1.02);
    transform: translateZ(0);
    pointer-events: none;
  }

  .fullscreen-background.is-user-gradient::after {
    content: "";
    position: absolute;
    inset: -8%;
    background-image: var(--ls-user-gradient-soft-alt);
    background-size: 135% 135%;
    background-position: center, center;
    background-repeat: no-repeat;
    opacity: 0.06;
    filter: blur(34px) saturate(1.02);
    transform: translateZ(0);
    pointer-events: none;
  }
  
  .fullscreen-background__overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    transition: opacity 0.2s ease;
    pointer-events: none;
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
  }
  
  /* Ensure parallax container doesn't interfere */
  .background-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    z-index: -1;
    pointer-events: none;
    overflow: hidden;
  }
  
  .parallax-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
  }
  
  /* Fix for canvas-based animations (particles.js) on iOS */
  .parallax-background canvas,
  .background-container canvas {
    /* Remove hardware acceleration that breaks canvas line rendering on iOS */
    -webkit-transform: none !important;
    transform: none !important;
    will-change: auto !important;
    /* Ensure canvas renders properly */
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
  }
  
  /* iOS Safari specific fixes */
  @supports (-webkit-touch-callout: none) {
    body {
      min-height: 100vh;
      min-height: -webkit-fill-available;
    }
    
    .fullscreen-background {
      /* Cover entire viewport including UI chrome areas */
      top: -100px;
      bottom: -100px;
      height: calc(100vh + 200px);
      height: calc(100dvh + 200px);
      min-height: calc(100vh + 200px);
      min-height: calc(-webkit-fill-available + 200px);
    }

    .fullscreen-background.is-user-gradient {
      background-size: 100% 100%;
      background-position: center center;
    }

.background-container {
      top: -100px;
      bottom: -100px;
      height: calc(100vh + 200px);
      height: calc(100dvh + 200px);
      min-height: calc(100vh + 200px);
    }
  }
  
  /* Prevent white lines on scroll */
  html, body {
    overscroll-behavior-y: none;
    overscroll-behavior-x: none;
  }
</style>
@push('wayvio-body-start')
<div class="fullscreen-background{{ $resolvedType === 'solid' && $hasGradient ? ' is-user-gradient' : '' }}" aria-hidden="true" style="{!! $backgroundLayerStyle !!}">
  @if($overlayActive)
  <div class="fullscreen-background__overlay" style="background-color: {{$overlayColorCss}};"></div>
  @endif
</div>
@endpush
@endif
@endif

@push('wayvio-head-end')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_head') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')@include($GLOBALS['themeName'] . '.extra.custom-head')@endif
@if($info->theme != '' and $info->theme != 'default')

  <!-- Wayvio Theme: "{{$info->theme}}" -->

  <!-- Theme details: -->
  <meta name="designer" href="{{ url('') . "/theme/" . $littlelink_name}}" content="{{ url('') . "/theme/" . $littlelink_name}}">
  <link rel="stylesheet" href="{{ url('/themes/' . $info->theme . '/share.button.css') }}">
  @if(theme('use_default_buttons') == "true")
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/brands.css') }}">
  @else
  <link rel="stylesheet" href="{{ url('/themes/' . $info->theme . '/brands.css') }}">
  @endif
  <link rel="stylesheet" href="{{ url('/themes/' . $info->theme . '/skeleton-auto.css') }}">
@if(file_exists(base_path('themes/' . $info->theme . '/animations.css')))
  <link rel="stylesheet" href="{{ url('/themes/' . $info->theme . '/animations.css') }}">
@else
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/animations.css') }}">
@endif
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/link-interactions.css') }}">

@else
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/share.button.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/brands.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/skeleton-auto.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/link-interactions.css') }}">
	@endif
	@if(is_string($activeThemeVariantCssUrl) && $activeThemeVariantCssUrl !== '')
	  <link rel="stylesheet" href="{{ $activeThemeVariantCssUrl }}">
	@endif
	@if($applyTemplateDesignSettings)
	<style>
	  :root {
	    --textColor: {{ $templateDesignSettings['text_accent_color'] ?? '#FFFFFF' }};
	    --accentColor: {{ $templateDesignSettings['accent_color'] ?? ($templateDesignSettings['text_accent_color'] ?? '#FFFFFF') }};
	    --ls-template-button-color: {{ $templateDesignSettings['button_text_color'] ?? ($templateDesignSettings['text_accent_color'] ?? '#FFFFFF') }};
	    --ls-template-button-bg: {{ $templateDesignSettings['button_background_color'] ?? 'transparent' }};
	    --ls-template-button-border: {{ $templateDesignSettings['button_border_color'] ?? ($templateDesignSettings['button_text_color'] ?? '#FFFFFF') }};
	  }
	</style>
	@endif
	<style>.container{word-break: break-word;}</style>
	@includeIf('wayvio.modules.inter-font')
	@includeIf('wayvio.modules.typography')
	@endpush

@push('wayvio-body-start')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_body') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')@include($GLOBALS['themeName'] . '.extra.custom-body')@endif

@if($info->theme != '' and $info->theme != 'default')
    <!-- Enables parallax background animations -->
    <div class="background-container">
    <section class="parallax-background">
      <div id="object1" class="object1"></div>
      <div id="object2" class="object2"></div>
      <div id="object3" class="object3"></div>
      <div id="object4" class="object4"></div>
      <div id="object5" class="object5"></div>
      <div id="object6" class="object6"></div>
      <div id="object7" class="object7"></div>
      <div id="object8" class="object8"></div>
      <div id="object9" class="object9"></div>
      <div id="object10" class="object10"></div>
      <div id="object11" class="object11"></div>
      <div id="object12" class="object12"></div>
    </section>
    </div>
    <!-- End of parallax background animations -->
@endif
@endpush

@push('wayvio-body-end')
@if(theme('enable_custom_code') == "true" and theme('enable_custom_body_end') == "true" and env('ALLOW_CUSTOM_CODE_IN_THEMES') == 'true')@include($GLOBALS['themeName'] . '.extra.custom-body-end')@endif
@endpush
@include('wayvio.modules.dynamic-contrast')
