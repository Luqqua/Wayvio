<?php

function findFile($name)
{
    $directory = base_path("/assets/wayvio/images/");
    $files = scandir($directory);
    $pathinfo = "error.error";
    $pattern = '/^' . preg_quote($name, '/') . '(_\w+)?\.\w+$/i';
    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            $pathinfo = $file;
            break;
        }
    }
    return $pathinfo;
}

function uploadMediaStorageService(): \App\Services\Uploads\MediaStorageService
{
    return app(\App\Services\Uploads\MediaStorageService::class);
}

function userAvatarMediaPath($userId): ?string
{
    $resolvedUserId = (int) $userId;
    if ($resolvedUserId <= 0) {
        return null;
    }

    return uploadMediaStorageService()->avatarPathForUser($resolvedUserId);
}

function userAvatarExists($userId): bool
{
    return userAvatarMediaPath($userId) !== null;
}

function userAvatarUrl($userId): ?string
{
    $path = userAvatarMediaPath($userId);

    return $path ? uploadMediaStorageService()->url($path) : null;
}

function userBackgroundMediaPath($userId): ?string
{
    $resolvedUserId = (int) $userId;
    if ($resolvedUserId <= 0) {
        return null;
    }

    return uploadMediaStorageService()->backgroundPathForUser($resolvedUserId);
}

function userBackgroundExists($userId): bool
{
    return userBackgroundMediaPath($userId) !== null;
}

function userBackgroundUrl($userId): ?string
{
    $path = userBackgroundMediaPath($userId);

    return $path ? uploadMediaStorageService()->url($path) : null;
}

function userFaviconMediaPath($userId): ?string
{
    $resolvedUserId = (int) $userId;
    if ($resolvedUserId <= 0) {
        return null;
    }

    return uploadMediaStorageService()->faviconPathForUser($resolvedUserId);
}

function userFaviconExists($userId): bool
{
    return userFaviconMediaPath($userId) !== null;
}

function userFaviconUrl($userId): ?string
{
    $path = userFaviconMediaPath($userId);

    return $path ? uploadMediaStorageService()->url($path) : null;
}

function mediaPathExists($pathOrKey): bool
{
    return uploadMediaStorageService()->exists(is_string($pathOrKey) ? $pathOrKey : null);
}

function mediaPathUrl($pathOrKey): ?string
{
    if (!is_string($pathOrKey)) {
        return null;
    }

    return uploadMediaStorageService()->url($pathOrKey);
}

function findAvatar($name)
{
    $path = userAvatarMediaPath($name);

    return $path ?? 'error.error';
}

function findBackground($name)
{
    $path = userBackgroundMediaPath($name);

    return $path ?? 'error.error';
}

function analyzeImageBrightness($file) {
    try {
    if (!is_string($file) || trim($file) === '') {
      return 'dark';
    }

    $candidatePath = ltrim(trim($file), '/');
    if (!str_contains($candidatePath, '/')) {
      $candidatePath = 'assets/img/background-img/' . $candidatePath;
    }
    if (preg_match('/^https?:\/\//i', $candidatePath) === 1) {
      return 'dark';
    }
    $file = base_path($candidatePath);
  
    // Get image information using getimagesize
    $imageInfo = getimagesize($file);
    if (!$imageInfo) {
      return 'dark';
    }
  
    // Get the image type
    $type = $imageInfo[2];
  
    // Load the image based on its type
    switch ($type) {
      case IMAGETYPE_JPEG:
      case IMAGETYPE_JPEG2000:
        $img = imagecreatefromjpeg($file);
        break;
      case IMAGETYPE_PNG:
        $img = imagecreatefrompng($file);
        break;
      default:
        return 'dark';
    }
  
    // Get image dimensions
    $width = imagesx($img);
    $height = imagesy($img);
  
    // Calculate the average brightness of the image
    $total_brightness = 0;
    for ($x=0; $x<$width; $x++) {
      for ($y=0; $y<$height; $y++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $brightness = (int)(($r + $g + $b) / 3);
        $total_brightness += $brightness;
      }
    }
    $avg_brightness = $total_brightness / ($width * $height);
  
    // Determine if the image is more dark or light
    if ($avg_brightness < 128) {
      return 'dark';
    } else {
      return 'light';
    }
      } catch (\Throwable $th) {
          return null;
      }
  }
  
  function infoIcon($tip) {
    echo '
      <div class="d-flex justify-content-center align-items-center">
        <a data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $tip . '">
          <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.67 1.99927H16.34C19.73 1.99927 22 4.37927 22 7.91927V16.0903C22 19.6203 19.73 21.9993 16.34 21.9993H7.67C4.28 21.9993 2 19.6203 2 16.0903V7.91927C2 4.37927 4.28 1.99927 7.67 1.99927ZM11.99 9.06027C11.52 9.06027 11.13 8.66927 11.13 8.19027C11.13 7.70027 11.52 7.31027 12.01 7.31027C12.49 7.31027 12.88 7.70027 12.88 8.19027C12.88 8.66927 12.49 9.06027 11.99 9.06027ZM12.87 15.7803C12.87 16.2603 12.48 16.6503 11.99 16.6503C11.51 16.6503 11.12 16.2603 11.12 15.7803V11.3603C11.12 10.8793 11.51 10.4803 11.99 10.4803C12.48 10.4803 12.87 10.8793 12.87 11.3603V15.7803Z" fill="currentColor"></path>
          </svg>
        </a>
      </div>
    ';
  }

if (!function_exists('hexToHsl')) {
    function hexToHsl(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            $h = $s = 0.0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
                case $g: $h = (($b - $r) / $d + 2) / 6; break;
                default:  $h = (($r - $g) / $d + 4) / 6; break;
            }
        }
        return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
    }
}

if (!function_exists('hslToHex')) {
    function hslToHex(float $h, float $s, float $l): string {
        $h /= 360; $s /= 100; $l /= 100;
        if ($s == 0.0) {
            $v = (int) round($l * 255);
            $c = str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
            return '#' . $c . $c . $c;
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hueToRgb = function (float $p, float $q, float $t): float {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1/2) return $q;
            if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
            return $p;
        };
        $r = (int) round($hueToRgb($p, $q, $h + 1/3) * 255);
        $g = (int) round($hueToRgb($p, $q, $h) * 255);
        $b = (int) round($hueToRgb($p, $q, $h - 1/3) * 255);
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('darkenHex')) {
    function darkenHex(string $hex, int $amount): string {
        $hsl = hexToHsl($hex);
        return hslToHex($hsl['h'], $hsl['s'], max(0.0, $hsl['l'] - $amount));
    }
}

if (!function_exists('autoAccentColor')) {
    function autoAccentColor(string $baseHex): string {
        $hsl = hexToHsl($baseHex);
        $newH = fmod($hsl['h'] + 30, 360);
        $newS = min(100.0, $hsl['s'] + 5);
        $newL = min(82.0, $hsl['l'] + 20);
        return hslToHex($newH, $newS, $newL);
    }
}

function hexToRgba(string $hex, float $opacity = 1.0) {
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) !== 6) {
        return 'rgba(0, 0, 0, ' . max(0, min($opacity, 1)) . ')';
    }

    $int = hexdec($hex);
    $r = ($int >> 16) & 255;
    $g = ($int >> 8) & 255;
    $b = $int & 255;

    $alpha = max(0, min($opacity, 1));

    return "rgba($r, $g, $b, $alpha)";
}

if (!function_exists('normalizeHexColor')) {
    function normalizeHexColor($value, string $fallback = '#FFFFFF'): string
    {
        $fallbackColor = '#FFFFFF';
        if (is_string($fallback) && preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($fallback), $fallbackMatch) === 1) {
            $fallbackHex = strtoupper($fallbackMatch[1]);
            if (strlen($fallbackHex) === 3) {
                $fallbackHex = $fallbackHex[0] . $fallbackHex[0] . $fallbackHex[1] . $fallbackHex[1] . $fallbackHex[2] . $fallbackHex[2];
            }
            $fallbackColor = '#' . $fallbackHex;
        }

        if (!is_string($value) || trim($value) === '') {
            return $fallbackColor;
        }

        if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value), $matches) !== 1) {
            return $fallbackColor;
        }

        $hex = strtoupper($matches[1]);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }
}

if (!function_exists('isHexColorDark')) {
    function isHexColorDark(string $hexColor): bool
    {
        $normalized = normalizeHexColor($hexColor, '#FFFFFF');
        $int = hexdec(ltrim($normalized, '#'));
        $r = ($int >> 16) & 255;
        $g = ($int >> 8) & 255;
        $b = $int & 255;

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance < 0.5;
    }
}

if (!function_exists('resolvePageFeatureOwnerUser')) {
    function resolvePageFeatureOwnerUser($userId): ?\App\Models\User
    {
        static $cache = [];

        $cacheKey = is_scalar($userId) ? (string) $userId : 'null';
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $resolvedUserId = (int) $userId;
        if ($resolvedUserId <= 0) {
            return $cache[$cacheKey] = null;
        }

        $pageUser = \App\Models\User::query()->select('id', 'role')->find($resolvedUserId);
        if (!$pageUser) {
            return $cache[$cacheKey] = null;
        }

        $featureOwner = $pageUser;
        if ($pageUser->isAgencyHubAccount() && \Illuminate\Support\Facades\Schema::hasTable('agency_hubs')) {
            $agencyUserId = (int) \App\Models\AgencyHub::query()
                ->where('managed_user_id', $resolvedUserId)
                ->where('status', 'active')
                ->value('agency_user_id');

            if ($agencyUserId > 0 && $agencyUserId !== $resolvedUserId) {
                $agencyUser = \App\Models\User::query()->select('id', 'role')->find($agencyUserId);
                if ($agencyUser) {
                    $featureOwner = $agencyUser;
                }
            }
        }

        return $cache[$cacheKey] = $featureOwner;
    }
}

if (!function_exists('pageOwnerHasTierFeature')) {
    function pageOwnerHasTierFeature($userId, string $featureKey): bool
    {
        static $cache = [];

        $cacheKey = (is_scalar($userId) ? (string) $userId : 'null') . '|' . trim($featureKey);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $featureOwner = resolvePageFeatureOwnerUser($userId);
        if (!$featureOwner) {
            return $cache[$cacheKey] = false;
        }

        if (in_array((string) ($featureOwner->role ?? ''), ['vip', 'admin'], true)) {
            return $cache[$cacheKey] = true;
        }

        if (
            !class_exists(\Modules\Tiers\Services\SubscriptionManager::class)
            || !class_exists(\Modules\Tiers\Services\TierResolver::class)
        ) {
            return $cache[$cacheKey] = false;
        }

        if (
            !\Illuminate\Support\Facades\Schema::hasTable('tiers')
            || !\Illuminate\Support\Facades\Schema::hasTable('user_subscriptions')
        ) {
            return $cache[$cacheKey] = false;
        }

        $subscriptionManager = app(\Modules\Tiers\Services\SubscriptionManager::class);
        $tierResolver = app(\Modules\Tiers\Services\TierResolver::class);
        $tier = $subscriptionManager->getUserTier($featureOwner);

        return $cache[$cacheKey] = $tierResolver->featureEnabled($tier, $featureKey);
    }
}

if (!function_exists('resolveTemplateDesignSettings')) {
    function resolveTemplateDesignSettings($userId, ?string $themeName = null): array
    {
        static $cache = [];

        $resolvedTheme = is_string($themeName) && trim($themeName) !== ''
            ? trim($themeName)
            : null;

        if ($resolvedTheme === null && (int) $userId > 0) {
            $resolvedTheme = (string) (\App\Models\User::query()->where('id', (int) $userId)->value('theme') ?? 'default');
        }

        $resolvedTheme = $resolvedTheme ?: 'default';
        $cacheKey = ((int) $userId) . '|' . strtolower($resolvedTheme);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $template = templateCatalog()->templateByTheme($resolvedTheme, true);
        $defaults = is_array($template) ? (array) ($template['design_defaults'] ?? []) : [];
        $fallbackColor = normalizeHexColor($defaults['text_accent_color'] ?? null, '#FFFFFF');

        $settings = [
            'text_accent_color' => $fallbackColor,
            'accent_color' => normalizeHexColor($defaults['text_accent_color'] ?? null, $fallbackColor),
            'button_style' => in_array(($defaults['button_style'] ?? 'outline'), ['solid', 'outline'], true) ? $defaults['button_style'] : 'outline',
            'button_text_color' => normalizeHexColor($defaults['button_text_color'] ?? null, $fallbackColor),
            'button_background_color' => ($defaults['button_background_color'] ?? 'transparent') === 'transparent'
                ? 'transparent'
                : normalizeHexColor($defaults['button_background_color'] ?? null, '#FFFFFF'),
            'button_border_color' => normalizeHexColor($defaults['button_border_color'] ?? null, $fallbackColor),
        ];

        $canUseTextAccent = pageOwnerHasTierFeature($userId, 'design.custom_colors')
            || pageOwnerHasTierFeature($userId, 'design.link_styling');
        if ($canUseTextAccent) {
            $rawMode = \App\Models\UserData::getData($userId, 'text_color_mode');
            $mode = is_string($rawMode) ? strtolower(trim($rawMode)) : '';
            $rawCustomColor = \App\Models\UserData::getData($userId, 'text_color_custom');
            $customColor = normalizeHexColor($rawCustomColor, $settings['text_accent_color']);

            if ($mode === 'black') {
                $userColor = '#000000';
            } elseif ($mode === 'white') {
                $userColor = '#FFFFFF';
            } elseif ($mode === 'custom') {
                $userColor = $customColor;
            } else {
                $userColor = null;
            }

            if ($userColor !== null) {
                $settings['text_accent_color'] = $userColor;
                $settings['accent_color'] = $userColor;
                $settings['button_text_color'] = $userColor;
                $settings['button_border_color'] = $userColor;
            }
        }

        return $cache[$cacheKey] = $settings;
    }
}

if (!function_exists('resolveUserTextColorSettings')) {
    function resolveUserTextColorSettings($userId, ?string $themeName = null): array
    {
        static $cache = [];

        $cacheKey = (is_scalar($userId) ? (string) $userId : 'null') . '|' . strtolower((string) ($themeName ?? ''));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $designSettings = resolveTemplateDesignSettings($userId, $themeName);
        $templateTextColor = normalizeHexColor($designSettings['text_accent_color'] ?? null, '#FFFFFF');
        $canUseTextAccent = pageOwnerHasTierFeature($userId, 'design.custom_colors')
            || pageOwnerHasTierFeature($userId, 'design.link_styling');
        if (!$canUseTextAccent) {
            return $cache[$cacheKey] = [
                'mode' => 'template',
                'color' => $templateTextColor,
                'custom_color' => $templateTextColor,
                'is_dark' => isHexColorDark($templateTextColor),
            ];
        }

        $rawMode = \App\Models\UserData::getData($userId, 'text_color_mode');
        $mode = is_string($rawMode) ? strtolower(trim($rawMode)) : '';
        if (!in_array($mode, ['white', 'black', 'custom'], true)) {
            $mode = 'template';
        }

        $rawCustomColor = \App\Models\UserData::getData($userId, 'text_color_custom');
        $customColor = normalizeHexColor($rawCustomColor, $templateTextColor);

        if ($mode === 'black') {
            $resolvedColor = '#000000';
        } elseif ($mode === 'custom') {
            $resolvedColor = $customColor;
        } elseif ($mode === 'white') {
            $resolvedColor = '#FFFFFF';
        } else {
            $resolvedColor = $templateTextColor;
        }

        return $cache[$cacheKey] = [
            'mode' => $mode,
            'color' => $resolvedColor,
            'custom_color' => $customColor,
            'is_dark' => isHexColorDark($resolvedColor),
        ];
    }
}

if (!function_exists('wayvioDefaultCustomButtonCss')) {
    function wayvioDefaultCustomButtonCss(): string
    {
        return '--ls-gradient-enabled: 0; color: #000000; background-color: #079AA2; background-image: none; border-style: none; border-width: 0; border-color: #000000; border-radius: 8px; font-family: inherit;';
    }
}

if (!function_exists('normalizeButtonCssForComparison')) {
    function normalizeButtonCssForComparison($css): string
    {
        if (!is_string($css)) {
            return '';
        }

        $css = strtolower(trim($css));
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([:;])\s*/', '$1', $css) ?? $css;

        return rtrim($css, ';') . ';';
    }
}

if (!function_exists('isWayvioDefaultCustomButtonCss')) {
    function isWayvioDefaultCustomButtonCss($css): bool
    {
        return normalizeButtonCssForComparison($css) === normalizeButtonCssForComparison(wayvioDefaultCustomButtonCss());
    }
}

if (!function_exists('applyWayvioDefaultDesignSettings')) {
    function applyWayvioDefaultDesignSettings(?int $userId): void
    {
        $resolvedUserId = (int) $userId;
        if ($resolvedUserId <= 0) {
            return;
        }

        \App\Models\UserData::saveData($resolvedUserId, 'text_color_mode', 'black');
        \App\Models\UserData::saveData($resolvedUserId, 'text_color_custom', '#000000');
        \App\Models\UserData::saveData($resolvedUserId, 'global_custom_button_css', wayvioDefaultCustomButtonCss());
    }
}

if (!function_exists('contentBlockStyleAliases')) {
    function contentBlockStyleAliases(): array
    {
        return [
            'clean' => 'clean',
            'glass' => 'glass',
            'bold' => 'bold',
            // Legacy aliases kept for backward compatibility.
            'soft' => 'glass',
            'accent' => 'glass',
            'ghost' => 'clean',
            'minimal' => 'clean',
        ];
    }
}

if (!function_exists('contentBlockStyleAllowedValues')) {
    function contentBlockStyleAllowedValues(): array
    {
        return ['clean', 'glass', 'bold'];
    }
}

if (!function_exists('contentBlockStyleValidInputs')) {
    function contentBlockStyleValidInputs(): array
    {
        return array_keys(contentBlockStyleAliases());
    }
}

if (!function_exists('contentBlockStyleInRule')) {
    function contentBlockStyleInRule(): string
    {
        return 'in:' . implode(',', contentBlockStyleValidInputs());
    }
}

if (!function_exists('normalizeContentBlockStyle')) {
    function normalizeContentBlockStyle($value, string $default = 'glass'): string
    {
        $allowed = contentBlockStyleAllowedValues();
        if (!in_array($default, $allowed, true)) {
            $default = 'glass';
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return $default;
        }

        $aliases = contentBlockStyleAliases();
        $normalized = $aliases[$raw] ?? null;
        if ($normalized === null) {
            return $default;
        }

        return in_array($normalized, $allowed, true) ? $normalized : $default;
    }
}

if (!function_exists('templateCatalog')) {
    function templateCatalog(): \App\Services\Templates\TemplateCatalogService
    {
        return app(\App\Services\Templates\TemplateCatalogService::class);
    }
}

if (!function_exists('resolveTemplateCapabilities')) {
    function resolveTemplateCapabilities(?string $themeName): array
    {
        return templateCatalog()->capabilitiesForTheme($themeName);
    }
}

if (!function_exists('templateCapability')) {
    function templateCapability(?string $themeName, string $capability, bool $default = false): bool
    {
        return templateCatalog()->capabilityForTheme($themeName, $capability, $default);
    }
}

if (!function_exists('resolveTemplateForTheme')) {
    function resolveTemplateForTheme(?string $themeName): ?array
    {
        return templateCatalog()->templateByTheme($themeName);
    }
}

if (!function_exists('resolveTemplateVariantForUser')) {
    function resolveTemplateVariantForUser($userId, ?string $themeName): ?array
    {
        $template = resolveTemplateForTheme($themeName);
        if (!$template) {
            return null;
        }

        $rawVariantId = \App\Models\UserData::getData($userId, 'theme_variant_id');
        $variantId = is_string($rawVariantId) ? $rawVariantId : null;

        return templateCatalog()->variantForTemplateId((string) ($template['id'] ?? ''), $variantId);
    }
}

function external_file_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:80.0) Gecko/20100101 Firefox/80.0');
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function uri($path) {
    $url = str_replace(['http://', 'https://'], '', url(''));
    return "//" . $url . "/" . $path;
}

function footer($key)
{
    $upperStr = strtoupper($key);
    if (env('TITLE_FOOTER_'.$upperStr) == "") {
        $title = __('messages.footer.'.$key);
    } else {
        $title = env('TITLE_FOOTER_'.$upperStr);
    }
    return $title;
}

function strip_tags_except_allowed_protocols($str) {
    preg_match_all('/<a[^>]+>(.*?)<\/a>/i', $str, $matches, PREG_SET_ORDER);

    foreach ($matches as $val) {
        if (!preg_match('/href=["\'](http:|https:|mailto:|tel:)[^"\']*["\']/', $val[0])) {
            $str = str_replace($val[0], $val[1], $str);
        }
    }

    return $str;
}

if(!function_exists('setBlockAssetContext')) {
  function setBlockAssetContext($type = null) {
      static $currentType = null;
      if ($type !== null) {
          $currentType = $type;
      }
      return $currentType;
  }
}

// Get custom block assets
if(!function_exists('block_asset')) {
  function block_asset($file) {
      $type = setBlockAssetContext(); // Retrieve the current type context
      return url("block-asset/$type?asset=$file");
  }
}

if(!function_exists('get_block_file_contents')) {
  function get_block_file_contents($file) {
      $type = setBlockAssetContext(); // Retrieve the current type context
      return file_get_contents(base_path("blocks/$type/$file"));
  }
}

function block_text_translation_check($text) {
  if (empty($text)) {
    return false;
  }
  $translate = __("messages.$text");
  return $translate === "messages.$text" ? true : false;
}

function block_text($text) {
  $translate = __("messages.$text");
  return $translate === "messages.$text" ? $text : $translate;
}

function bt($text) {
  return block_text($text);
}

if (!function_exists('reservedSlugs')) {
  function reservedSlugs() {
    return [
      'dashboard','admin','account','api','studio','panel','login','register','password','stripe','going',
      'info','pages','theme','vcard','u','report','demo-page','block-asset','social-auth','export','import',
      'checkout','billing','pricing','blocked','forgot-password','reset-password','verify-email','email',
      'two-factor-challenge','two-factor','logout'
    ];
  }
}

if (!function_exists('wayvioAppUrl')) {
  function wayvioAppUrl(string $path = ''): string
  {
    $base = trim((string) config('app.url'));
    if ($base === '') {
      $base = (string) url('/');
    }

    if (!preg_match('#^https?://#i', $base)) {
      $base = 'https://' . ltrim($base, '/');
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
  }
}

if (!function_exists('wayvioReportUrl')) {
  function wayvioReportUrl(?int $userId = null): string
  {
    $url = wayvioAppUrl('/report');

    if ($userId !== null && $userId > 0) {
      $url .= '?' . http_build_query(['id' => $userId]);
    }

    return $url;
  }
}

if (!function_exists('legalDocumentLocale')) {
  function legalDocumentLocale(?string $preferredLocale = null): string
  {
    $candidate = strtolower(trim((string) $preferredLocale));
    if ($candidate === '') {
      $candidate = strtolower((string) app()->getLocale());
    }

    $candidate = str_replace('_', '-', $candidate);
    return str_starts_with($candidate, 'en') ? 'en' : 'de';
  }
}

if (!function_exists('legalDocumentLinks')) {
  /**
   * @return array{locale:string,agb:string,avv:string,privacy:string,imprint:string}
   */
  function legalDocumentLinks(?string $preferredLocale = null): array
  {
    $legalLocale = legalDocumentLocale($preferredLocale);
    $linkFor = static function (string $routeName) use ($legalLocale): string {
      if (!\Illuminate\Support\Facades\Route::has($routeName)) {
        return '';
      }

      return route($routeName, ['legal_lang' => $legalLocale]);
    };

    return [
      'locale' => $legalLocale,
      'agb' => $linkFor('pagesAgb'),
      'avv' => $linkFor('pagesAvv'),
      'privacy' => $linkFor('pagesPrivacy'),
      'imprint' => $linkFor('pagesImprint'),
    ];
  }
}

if (!function_exists('currentTenantContext')) {
  function currentTenantContext(): ?\App\Support\Tenancy\TenantContext
  {
    if (app()->bound(\App\Support\Tenancy\TenantContext::class)) {
      $resolved = app(\App\Support\Tenancy\TenantContext::class);
      if ($resolved instanceof \App\Support\Tenancy\TenantContext) {
        return $resolved;
      }
    }

    $candidate = request()?->attributes?->get('tenant_context');
    return $candidate instanceof \App\Support\Tenancy\TenantContext ? $candidate : null;
  }
}

if (!function_exists('currentTenantOwnerId')) {
  function currentTenantOwnerId(): ?int
  {
    $context = currentTenantContext();
    if (!$context) {
      return null;
    }

    $tenantOwnerId = $context->tenantOwnerUserId();
    return $tenantOwnerId > 0 ? $tenantOwnerId : null;
  }
}
