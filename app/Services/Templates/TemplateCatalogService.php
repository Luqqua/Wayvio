<?php

namespace App\Services\Templates;

use Illuminate\Support\Str;

class TemplateCatalogService
{
    private static ?array $cachedTemplates = null;

    public static function flushCache(): void
    {
        self::$cachedTemplates = null;
    }

    public function isEnabled(): bool
    {
        return (bool) config('template-catalog.enabled', true);
    }

    public function templates(bool $includeDisabled = false): array
    {
        if (self::$cachedTemplates === null) {
            self::$cachedTemplates = $this->buildTemplates();
        }

        if ($includeDisabled) {
            return self::$cachedTemplates;
        }

        return array_values(array_filter(self::$cachedTemplates, static function (array $template): bool {
            return (bool) ($template['enabled'] ?? false);
        }));
    }

    public function template(string $templateId, bool $includeDisabled = false): ?array
    {
        $normalizedId = $this->normalizeId($templateId);
        if ($normalizedId === '') {
            return null;
        }

        foreach ($this->templates($includeDisabled) as $template) {
            if (($template['id'] ?? '') === $normalizedId) {
                return $template;
            }
        }

        return null;
    }

    public function templateByTheme(?string $themeName, bool $includeDisabled = false): ?array
    {
        $normalizedTheme = $this->normalizeTheme($themeName);
        foreach ($this->templates($includeDisabled) as $template) {
            if ($this->normalizeTheme($template['theme'] ?? 'default') === $normalizedTheme) {
                return $template;
            }
        }

        return null;
    }

    public function themeForTemplateId(?string $templateId): ?string
    {
        if (!is_string($templateId) || trim($templateId) === '') {
            return null;
        }

        $template = $this->template($templateId);

        return $template['theme'] ?? null;
    }

    public function allowedTemplateIds(): array
    {
        return array_values(array_map(static function (array $template): string {
            return (string) ($template['id'] ?? '');
        }, $this->templates(false)));
    }

    public function capabilitiesForTheme(?string $themeName): array
    {
        $template = $this->templateByTheme($themeName);
        if (!$template) {
            return [];
        }

        return (array) ($template['capabilities'] ?? []);
    }

    public function capabilityForTheme(?string $themeName, string $capability, bool $default = false): bool
    {
        $capabilities = $this->capabilitiesForTheme($themeName);

        if (!array_key_exists($capability, $capabilities)) {
            return $default;
        }

        return $this->toBool($capabilities[$capability], $default);
    }

    public function variantsForTemplateId(?string $templateId): array
    {
        if (!is_string($templateId) || trim($templateId) === '') {
            return [];
        }

        $template = $this->template($templateId);
        if (!$template) {
            return [];
        }

        return array_values((array) ($template['variants'] ?? []));
    }

    public function normalizeVariantId(?string $templateId, ?string $variantId): ?string
    {
        if (!is_string($templateId) || trim($templateId) === '') {
            return null;
        }

        $template = $this->template($templateId);
        if (!$template) {
            return null;
        }

        $variants = $this->variantsForTemplateId((string) $template['id']);
        if (empty($variants)) {
            return null;
        }

        $requested = $this->normalizeId((string) $variantId);
        foreach ($variants as $variant) {
            if (($variant['id'] ?? '') === $requested) {
                return $requested;
            }
        }

        return (string) ($template['default_variant_id'] ?? $variants[0]['id'] ?? 'default');
    }

    public function variantForTemplateId(?string $templateId, ?string $variantId): ?array
    {
        if (!is_string($templateId) || trim($templateId) === '') {
            return null;
        }

        $template = $this->template($templateId);
        if (!$template) {
            return null;
        }

        $resolvedVariantId = $this->normalizeVariantId((string) $template['id'], $variantId);
        if (!$resolvedVariantId) {
            return null;
        }

        foreach ($this->variantsForTemplateId((string) $template['id']) as $variant) {
            if (($variant['id'] ?? '') === $resolvedVariantId) {
                return $variant;
            }
        }

        return null;
    }

    public function variantForTheme(?string $themeName, ?string $variantId): ?array
    {
        $template = $this->templateByTheme($themeName);
        if (!$template) {
            return null;
        }

        return $this->variantForTemplateId((string) ($template['id'] ?? ''), $variantId);
    }

    private function buildTemplates(): array
    {
        $configuredTemplates = (array) config('template-catalog.templates', []);
        $includeUncatalogued = (bool) config('template-catalog.include_uncatalogued', true);
        $discoveredThemes = $this->discoverThemes();

        $templates = [];
        $mappedThemes = [];

        foreach ($configuredTemplates as $rawId => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $templateId = $this->normalizeId((string) $rawId);
            if ($templateId === '') {
                continue;
            }

            $themeName = $this->normalizeTheme($entry['theme'] ?? null);
            if ($themeName === '') {
                continue;
            }

            $themeMeta = $discoveredThemes[$themeName] ?? $this->createFallbackThemeMeta($themeName);
            $template = $this->normalizeTemplate($templateId, $entry, $themeMeta);
            if (!$template) {
                continue;
            }

            $templates[$templateId] = $template;
            $mappedThemes[$themeName] = true;
        }

        if ($includeUncatalogued) {
            foreach ($discoveredThemes as $themeName => $themeMeta) {
                if (isset($mappedThemes[$themeName])) {
                    continue;
                }

                $templateId = $this->normalizeId($themeName);
                if ($templateId === '' || isset($templates[$templateId])) {
                    continue;
                }

                $template = $this->normalizeTemplate($templateId, ['theme' => $themeMeta['theme']], $themeMeta);
                if (!$template) {
                    continue;
                }

                $templates[$templateId] = $template;
            }
        }

        uasort($templates, static function (array $a, array $b): int {
            $orderA = (int) ($a['order'] ?? 9999);
            $orderB = (int) ($b['order'] ?? 9999);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($templates);
    }

    private function discoverThemes(): array
    {
        $themes = [
            'default' => [
                'theme' => 'default',
                'label' => 'Default',
                'preview_url' => asset('assets/wayvio/images/themes/default.png'),
                'config' => [],
            ],
        ];

        $themeRoot = base_path('themes');
        if (!is_dir($themeRoot)) {
            return $themes;
        }

        $entries = scandir($themeRoot);
        if (!is_array($entries)) {
            return $themes;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $themePath = $themeRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($themePath)) {
                continue;
            }

            $themeName = $this->normalizeTheme($entry);
            if ($themeName === '') {
                continue;
            }

            $readmePath = $themePath . DIRECTORY_SEPARATOR . 'readme.md';
            $label = $entry;
            if (file_exists($readmePath)) {
                $readmeContent = (string) file_get_contents($readmePath);
                if (preg_match('/Theme Name:\s*(.+)/i', $readmeContent, $match) === 1) {
                    $labelCandidate = trim((string) ($match[1] ?? ''));
                    if ($labelCandidate !== '') {
                        $label = $labelCandidate;
                    }
                }
            }

            $previewFile = $themePath . DIRECTORY_SEPARATOR . 'preview.png';
            $previewUrl = file_exists($previewFile)
                ? url('/themes/' . $entry . '/preview.png')
                : asset('assets/wayvio/images/themes/no-preview.png');

            $configPath = $themePath . DIRECTORY_SEPARATOR . 'config.php';
            $config = [];
            if (file_exists($configPath)) {
                $raw = include $configPath;
                if (is_array($raw)) {
                    $config = $raw;
                }
            }

            $themes[$themeName] = [
                'theme' => $entry,
                'label' => $label,
                'preview_url' => $previewUrl,
                'config' => $config,
            ];
        }

        return $themes;
    }

    private function normalizeTemplate(string $templateId, array $entry, array $themeMeta): ?array
    {
        $themeName = $this->normalizeTheme($entry['theme'] ?? ($themeMeta['theme'] ?? ''));
        if ($themeName === '') {
            return null;
        }

        $themeSlug = (string) ($themeMeta['theme'] ?? $themeName);
        $themeConfig = (array) ($themeMeta['config'] ?? []);
        $isDefaultTheme = $themeName === 'default';

        $defaultCapabilities = [
            'custom_buttons' => $isDefaultTheme
                ? true
                : $this->toBool($themeConfig['allow_custom_buttons'] ?? null, false),
            'header' => array_key_exists('supports_header', $themeConfig)
                ? $this->toBool($themeConfig['supports_header'], false)
                : $isDefaultTheme,
            'background_image' => $isDefaultTheme,
            'solid_background' => $isDefaultTheme,
            'gradient' => $isDefaultTheme,
            'overlay' => $isDefaultTheme,
            'custom_icons' => $this->toBool($themeConfig['use_custom_icons'] ?? null, false),
            'dynamic_contrast' => $isDefaultTheme
                ? true
                : $this->toBool($themeConfig['enable_dynamic_contrast'] ?? null, false),
            'custom_code' => $this->toBool($themeConfig['enable_custom_code'] ?? null, false),
        ];

        $overrideCapabilities = is_array($entry['capabilities'] ?? null)
            ? $entry['capabilities']
            : [];

        $capabilities = array_merge($defaultCapabilities, $overrideCapabilities);

        // Coupling rule required by product decision:
        // text/accent customization is always coupled to custom button availability.
        $capabilities['custom_buttons'] = $this->toBool($capabilities['custom_buttons'] ?? false, false);
        $capabilities['text_accent'] = (bool) $capabilities['custom_buttons'];

        $variants = $this->normalizeVariants(
            is_array($entry['variants'] ?? null) ? $entry['variants'] : [],
            $themeSlug
        );
        $defaultVariantId = (string) ($entry['default_variant_id'] ?? '');
        $defaultVariantId = $this->normalizeId($defaultVariantId);
        if ($defaultVariantId === '' || !$this->variantExists($variants, $defaultVariantId)) {
            $defaultVariantId = (string) ($variants[0]['id'] ?? 'default');
        }

        return [
            'id' => $templateId,
            'theme' => $themeSlug,
            'label' => (string) ($entry['label'] ?? $themeMeta['label'] ?? Str::headline($templateId)),
            'description' => (string) ($entry['description'] ?? ''),
            'enabled' => $this->toBool($entry['enabled'] ?? true, true),
            'order' => (int) ($entry['order'] ?? 9999),
            'preview_url' => (string) ($entry['preview_url'] ?? $themeMeta['preview_url'] ?? asset('assets/wayvio/images/themes/no-preview.png')),
            'capabilities' => $capabilities,
            'design_defaults' => $this->normalizeDesignDefaults(
                is_array($entry['design_defaults'] ?? null) ? $entry['design_defaults'] : [],
                $themeConfig
            ),
            'variants' => $variants,
            'default_variant_id' => $defaultVariantId,
        ];
    }

    private function normalizeDesignDefaults(array $rawDefaults, array $themeConfig): array
    {
        $templateAccent = $this->normalizeHexColor(is_string($themeConfig['template_accent'] ?? null) ? $themeConfig['template_accent'] : null);
        $fallbackAccent = $templateAccent ?? '#FFFFFF';
        $textAccentColor = $this->normalizeHexColor(is_string($rawDefaults['text_accent_color'] ?? null) ? $rawDefaults['text_accent_color'] : null) ?? $fallbackAccent;
        $buttonStyle = strtolower(trim((string) ($rawDefaults['button_style'] ?? 'outline')));
        if (!in_array($buttonStyle, ['solid', 'outline'], true)) {
            $buttonStyle = 'outline';
        }

        $buttonTextColor = $this->normalizeHexColor(is_string($rawDefaults['button_text_color'] ?? null) ? $rawDefaults['button_text_color'] : null) ?? $textAccentColor;
        $buttonBackgroundColor = $buttonStyle === 'outline'
            ? 'transparent'
            : ($this->normalizeHexColor(is_string($rawDefaults['button_background_color'] ?? null) ? $rawDefaults['button_background_color'] : null) ?? '#FFFFFF');
        $buttonBorderColor = $this->normalizeHexColor(is_string($rawDefaults['button_border_color'] ?? null) ? $rawDefaults['button_border_color'] : null) ?? $buttonTextColor;

        return [
            'text_accent_color' => $textAccentColor,
            'button_style' => $buttonStyle,
            'button_text_color' => $buttonTextColor,
            'button_background_color' => $buttonBackgroundColor,
            'button_border_color' => $buttonBorderColor,
        ];
    }

    private function normalizeVariants(array $rawVariants, string $themeSlug): array
    {
        $variants = [];

        foreach ($rawVariants as $variantKey => $variantConfig) {
            if (!is_array($variantConfig)) {
                continue;
            }

            $variantId = $this->normalizeId((string) ($variantConfig['id'] ?? $variantKey));
            if ($variantId === '') {
                continue;
            }

            $cssPath = is_string($variantConfig['css'] ?? null)
                ? trim((string) $variantConfig['css'])
                : '';
            if ($cssPath !== '' && !$this->isSafeRelativePath($cssPath)) {
                $cssPath = '';
            }

            $cssUrl = null;
            if ($cssPath !== '') {
                $fullPath = base_path('themes/' . $themeSlug . '/' . $cssPath);
                if (file_exists($fullPath)) {
                    $cssUrl = url('/themes/' . $themeSlug . '/' . ltrim(str_replace('\\', '/', $cssPath), '/'));
                }
            }

            $swatches = [];
            if (is_array($variantConfig['swatches'] ?? null)) {
                foreach ($variantConfig['swatches'] as $color) {
                    $normalized = $this->normalizeHexColor(is_string($color) ? $color : null);
                    if ($normalized !== null) {
                        $swatches[] = $normalized;
                    }
                }
            }

            $variants[] = [
                'id' => $variantId,
                'label' => (string) ($variantConfig['label'] ?? Str::headline($variantId)),
                'css' => $cssPath !== '' ? $cssPath : null,
                'css_url' => $cssUrl,
                'swatches' => $swatches,
            ];
        }

        if (empty($variants)) {
            $variants[] = [
                'id' => 'default',
                'label' => 'Default',
                'css' => null,
                'css_url' => null,
                'swatches' => [],
            ];
        }

        usort($variants, static function (array $a, array $b): int {
            if (($a['id'] ?? '') === 'default') {
                return -1;
            }
            if (($b['id'] ?? '') === 'default') {
                return 1;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $variants;
    }

    private function normalizeId(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value;
    }

    private function normalizeTheme($value): string
    {
        if (!is_string($value)) {
            return 'default';
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        $value = trim((string) $value, '-');

        if ($value === '') {
            return 'default';
        }

        // Disallow parent traversal fragments when theme values come from config.
        if (str_contains($value, '..')) {
            return 'default';
        }

        return $value;
    }

    private function toBool($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default;
            }
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }

        if (str_contains($path, '..')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._\/-]+$/', $path) === 1;
    }

    private function normalizeHexColor(?string $color): ?string
    {
        if (!is_string($color)) {
            return null;
        }

        $color = trim($color);
        if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $matches) !== 1) {
            return null;
        }

        $hex = strtoupper($matches[1]);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }

    private function variantExists(array $variants, string $variantId): bool
    {
        foreach ($variants as $variant) {
            if (($variant['id'] ?? '') === $variantId) {
                return true;
            }
        }

        return false;
    }

    private function createFallbackThemeMeta(string $themeName): array
    {
        return [
            'theme' => $themeName,
            'label' => Str::headline($themeName),
            'preview_url' => asset('assets/wayvio/images/themes/no-preview.png'),
            'config' => [],
        ];
    }
}
