@extends('layouts.sidebar')
@php
    use App\Models\UserData;

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

                if (preg_match('/--background-color:\\s*([^;]+);/i', $content, $match)) {
                    return trim($match[1]);
                }

                if (preg_match('/--bgColor:\\s*([^;]+);/i', $content, $match)) {
                    return trim($match[1]);
                }
            }

            return $fallback;
        }
    }
@endphp

@section('content')
@php
    $backgroundImageLockedGlobal = !($canUseBackgroundImage ?? false);
    $backgroundImageRequiredTierLabelGlobal = (string) ($backgroundImageRequiredTierLabel ?? 'Basic');
    $subscriptionDashboardUrl = url('/dashboard/subscription');
@endphp
@foreach($pages as $page)
@php
    $settingsUserId = app(\App\Services\Agency\AgencyHubContext::class)->editingUserId(auth()->user(), request());
    $slug = (string) ($page->littlelink_name ?? '');
    $reserved = reservedSlugs();
    $previewBaseUrl = in_array($slug, $reserved) ? url('/p/' . $slug) : url('/' . $slug);
    $previewUrl = $previewBaseUrl . (str_contains($previewBaseUrl, '?') ? '&' : '?') . 'studio_preview=1';
    $backgroundImageLocked = $backgroundImageLockedGlobal;
    $backgroundImageRequiredTierLabel = $backgroundImageRequiredTierLabelGlobal;

    $backgroundPath = userBackgroundMediaPath($settingsUserId);
    $hasBackgroundImage = $backgroundPath !== null;
    $backgroundUploadLimit = config('media.upload_limits.background', ['max_kb' => 5120, 'max_width' => 3000, 'max_height' => 2000]);
    $backgroundLimitMb = (int) ceil(max(1, (int) ($backgroundUploadLimit['max_kb'] ?? 5120)) / 1024);
    $backgroundLimitWidth = max(1, (int) ($backgroundUploadLimit['max_width'] ?? 3000));
    $backgroundLimitHeight = max(1, (int) ($backgroundUploadLimit['max_height'] ?? 2000));
    $backgroundImageUrl = $hasBackgroundImage
        ? (mediaPathUrl($backgroundPath) ?? url('/assets/wayvio/images/themes/no-preview.png'))
        : url('/assets/wayvio/images/themes/no-preview.png');
    $valueOrDefault = function ($value, $default) {
        return ($value === null || $value === "null") ? $default : $value;
    };
    $backgroundMode = $valueOrDefault(UserData::getData($settingsUserId, 'background_mode'), ($hasBackgroundImage ? 'image' : 'color'));
    if ($backgroundMode === 'image' && !$hasBackgroundImage) {
        $backgroundMode = 'color';
    }
    $backgroundColor = $valueOrDefault(UserData::getData($settingsUserId, 'background_color'), '#111827');
    $overlaySettings = UserData::getData($settingsUserId, 'background_overlay');
    if ($overlaySettings === "null" || $overlaySettings === null) {
        $overlaySettings = [];
    }
    $overlayColor = is_array($overlaySettings) && !empty($overlaySettings['color']) ? $overlaySettings['color'] : '#000000';
    $overlayOpacity = is_array($overlaySettings) && isset($overlaySettings['opacity']) ? $overlaySettings['opacity'] : 0;
    $gradientSettings = UserData::getData($settingsUserId, 'background_gradient');
    if ($gradientSettings === "null" || $gradientSettings === null) {
        $gradientSettings = [];
    }

    $gradientEnabled = is_array($gradientSettings) ? ($gradientSettings['enabled'] ?? false) : false;
    $rawStops = is_array($gradientSettings) ? ($gradientSettings['stops'] ?? []) : [];

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
    if (is_array($rawStops) && !empty($rawStops)) {
        foreach ($rawStops as $index => $stop) {
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

    if (!empty($gradientStops)) {
        usort($gradientStops, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
    }

    $gradientColorOne = $gradientStops[0]['color'] ?? '#1e293b';
    $gradientColorTwo = $gradientStops[1]['color'] ?? '#0ea5e9';
    $gradientColorThree = $gradientStops[2]['color'] ?? '';
    $gradientStopOne = $gradientStops[0]['position'] ?? 0;
    $gradientStopTwo = $gradientStops[1]['position'] ?? 100;
    $gradientStopThree = $gradientStops[2]['position'] ?? 50;
    if ($gradientEnabled && count(array_filter($gradientStops, fn ($stop) => !empty($stop['color']))) < 2) {
        $gradientEnabled = false;
    }
    $defaultMode = $backgroundMode === 'image' ? 'image' : 'solid';
    $templateDefaultColor = themeDefaultBackgroundColor($page->theme ?? 'default', '#111827');
    $templateCatalogService = templateCatalog();
    $templateCatalogEntries = $templateCatalogService->templates();
    $currentThemeName = (string) ($page->theme ?: 'default');
    $currentTemplate = $templateCatalogService->templateByTheme($currentThemeName) ?? $templateCatalogService->templateByTheme('default');
    $currentTemplateId = (string) ($currentTemplate['id'] ?? 'default');
    $rawCurrentVariantId = UserData::getData($settingsUserId, 'theme_variant_id');
    $currentVariant = $templateCatalogService->variantForTemplateId($currentTemplateId, is_string($rawCurrentVariantId) ? $rawCurrentVariantId : null);
    $currentVariantId = (string) ($currentVariant['id'] ?? $currentTemplate['default_variant_id'] ?? 'default');
    $currentCapabilities = (array) ($currentTemplate['capabilities'] ?? []);
    $subscriptionManager = class_exists(\Modules\Tiers\Services\SubscriptionManager::class) ? app(\Modules\Tiers\Services\SubscriptionManager::class) : null;
    $tierResolver = class_exists(\Modules\Tiers\Services\TierResolver::class) ? app(\Modules\Tiers\Services\TierResolver::class) : null;
    $userTier = $subscriptionManager && auth()->check() ? $subscriptionManager->getUserTier(auth()->user()) : null;
    $roleBypass = auth()->check() && in_array(auth()->user()->role, ['vip', 'admin'], true);
    $canCustomizeTextAccentByTier = ($tierResolver && $userTier && (
        $tierResolver->featureEnabled($userTier, 'design.custom_colors')
        || $tierResolver->featureEnabled($userTier, 'design.link_styling')
    )) || $roleBypass;
    $textAccentRequiredTierLabel = ucfirst((string) config('tiers.default_free_slug', 'free'));
    if ($tierResolver) {
        $requiredSlug = null;
        foreach ((array) config('tiers.order', ['free', 'basic', 'pro', 'agency']) as $candidateSlug) {
            $plan = $tierResolver->configForSlug((string) $candidateSlug);
            $designFeatures = (array) ($plan['features']['design'] ?? []);
            if (!empty($designFeatures['custom_colors']) || !empty($designFeatures['link_styling'])) {
                $requiredSlug = (string) $candidateSlug;
                break;
            }
        }
        if ($requiredSlug === null || $requiredSlug === '') {
            $requiredSlug = (string) config('tiers.default_free_slug', 'free');
        }
        $textAccentRequiredTierLabel = $tierResolver->displayName($requiredSlug);
    }
    $textAccentEnabled = templateCapability($currentThemeName, 'text_accent', $currentThemeName === 'default');
    $textColorSettings = resolveUserTextColorSettings($settingsUserId);
    $textMode = $textColorSettings['mode'] ?? 'white';
    if (!in_array($textMode, ['white', 'black', 'custom'], true)) {
        $textMode = 'custom';
    }
    $customTextColor = $textColorSettings['custom_color'] ?? '#FFFFFF';
    $rawGlobalCustomCss = UserData::getData($settingsUserId, 'global_custom_button_css');
    $buttonEditorTextColor = null;
    if (is_string($rawGlobalCustomCss) && trim($rawGlobalCustomCss) !== '' && strtolower(trim($rawGlobalCustomCss)) !== 'null') {
        if (preg_match('/(?:^|;)\s*color\s*:\s*(#[0-9a-fA-F]{3,6})\s*(?:;|$)/i', $rawGlobalCustomCss, $matches) === 1) {
            $buttonEditorTextColor = normalizeHexColor($matches[1], '#FFFFFF');
        }
    }
    $capabilityGroups = [
        [
            'label' => 'Header',
            'keys' => ['header'],
        ],
        [
            'label' => 'Styling',
            'keys' => ['custom_buttons', 'text_accent'],
        ],
        [
            'label' => 'Background',
            'keys' => ['background_image', 'gradient', 'overlay'],
        ],
    ];
    $templateCatalogJson = [];
    foreach ($templateCatalogEntries as $templateEntry) {
        if (!is_array($templateEntry)) {
            continue;
        }

        $templateCatalogJson[(string) ($templateEntry['id'] ?? '')] = [
            'id' => (string) ($templateEntry['id'] ?? ''),
            'theme' => (string) ($templateEntry['theme'] ?? 'default'),
            'label' => (string) ($templateEntry['label'] ?? ''),
            'description' => (string) ($templateEntry['description'] ?? ''),
            'preview_url' => (string) ($templateEntry['preview_url'] ?? ''),
            'capabilities' => (array) ($templateEntry['capabilities'] ?? []),
            'design_defaults' => (array) ($templateEntry['design_defaults'] ?? []),
            'variants' => array_map(static function ($variant): array {
                return [
                    'id' => (string) ($variant['id'] ?? ''),
                    'label' => (string) ($variant['label'] ?? ''),
                    'swatches' => array_values(array_filter((array) ($variant['swatches'] ?? []), static fn ($color) => is_string($color) && $color !== '')),
                ];
            }, (array) ($templateEntry['variants'] ?? [])),
            'default_variant_id' => (string) ($templateEntry['default_variant_id'] ?? 'default'),
        ];
    }
@endphp

<div class="conatiner-fluid content-inner mt-n5 py-0 editor-page-shell ls-consistent-spacing">
    <div class="card rounded">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="mb-1 d-flex align-items-center gap-2"><i class="bi bi-brush"></i> {{__('messages.Select a theme')}}</h3>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger d-flex align-items-center mt-4" role="alert">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24">
                <use xlink:href="#exclamation-triangle-fill"></use>
            </svg>
            <div>
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        </div>
    @endif

    @if(config('app.allow_custom_backgrounds'))
                    <form action="{{ route('themeBackground') }}" enctype="multipart/form-data" method="post" id="background-form">
                        @csrf
                        <input type="hidden" id="overlay-color-hidden" name="overlay_color" value="{{ $overlayColor }}">
                        <input type="hidden" id="overlay-opacity-hidden" name="overlay_opacity" value="{{ $overlayOpacity }}">
                        <input type="hidden" name="background_mode" id="background-mode-hidden" value="{{ $backgroundMode === 'image' ? 'image' : ($backgroundMode === 'template' ? 'template' : 'color') }}">
                        <input type="hidden" name="template_background_mode" value="default">
                        <input type="hidden" name="selected_mode" id="selected-mode" value="{{ $defaultMode }}">
                        <div class="editor-layout mt-4">
                            <div class="editor-card">
                                <h5 class="mb-3">{{ __('messages.page.colors_section_title') }}</h5>
                                <div class="mb-0">
                                    <h6 class="mb-1">{{ __('messages.page.text_accent_color_title') }}</h6>
                                    @if(!$canCustomizeTextAccentByTier)
                                        <p class="text-muted mb-2">{{ __('Available from :tier.', ['tier' => $textAccentRequiredTierLabel]) }}</p>
                                    @else
                                        <p class="text-muted mb-2">{{ __('messages.page.text_accent_color_description') }}</p>
                                        <div id="text-accent-controls" class="ls-color-controls @if(!$textAccentEnabled) d-none @endif">
                                            <div class="ls-color-choice-options" role="group" aria-label="{{ __('messages.page.text_accent_color_mode_aria') }}">
                                                <label class="form-check ls-color-choice" for="global-text-mode-white">
                                                    <input class="form-check-input" type="radio" name="global_text_color_mode" id="global-text-mode-white" value="white" data-text-accent-control @checked($textMode === 'white')>
                                                    <span class="form-check-label">{{ __('messages.be.white') }}</span>
                                                </label>
                                                <label class="form-check ls-color-choice" for="global-text-mode-black">
                                                    <input class="form-check-input" type="radio" name="global_text_color_mode" id="global-text-mode-black" value="black" data-text-accent-control @checked($textMode === 'black')>
                                                    <span class="form-check-label">{{ __('messages.be.black') }}</span>
                                                </label>
                                                <label class="form-check ls-color-choice" for="global-text-mode-custom">
                                                    <input class="form-check-input" type="radio" name="global_text_color_mode" id="global-text-mode-custom" value="custom" data-text-accent-control @checked($textMode === 'custom')>
                                                    <span class="form-check-label">{{ __('messages.be.custom') }}</span>
                                                </label>
                                            </div>
                                            <div class="ls-color-custom-row" id="global-text-custom-row">
                                                <label class="form-label mb-0" for="global-text-color-custom">{{ __('messages.page.custom_color') }}</label>
                                                <input type="color" id="global-text-color-custom" name="global_text_color_custom" value="{{ $customTextColor }}" class="form-control form-control-color" data-text-accent-control>
                                                @if($buttonEditorTextColor)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ls-color-copy-button" id="copy-button-text-color" data-button-text-color="{{ $buttonEditorTextColor }}" data-text-accent-action>
                                                        {{ __('messages.page.use_button_text_color') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        <div id="text-accent-template-lock" class="alert alert-secondary mt-2 mb-2 @if($textAccentEnabled) d-none @endif">
                                            {{ __('This template locks text/accent customization.') }}
                                        </div>
                                        <div id="accent-save-row" class="d-flex justify-content-end mt-3 pt-2 border-top @if(!$textAccentEnabled) d-none @endif">
                                            <button type="submit" class="btn btn-primary action-btn">{{ __('messages.Apply') }}</button>
                                        </div>
                                    @endif
                                    <p class="text-muted small mb-0">{{ __('messages.page.text_accent_color_scope_hint') }}</p>
                                </div>
                            </div>

                            {{-- Mode selection cards --}}
                            <div class="editor-card">
                                <div class="d-flex flex-wrap gap-3 mb-0" id="background-modes">
                                    <div class="card flex-fill mode-card surface-crisp" data-mode="template">
                                        <div class="card-body d-flex align-items-center justify-content-center text-center p-3">
                                            <div>
                                                <div class="h4 mb-1"><i class="bi bi-layout-text-window"></i></div>
                                                <div class="fw-bold">{{ __('messages.Template') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card flex-fill mode-card surface-crisp" data-mode="image">
                                        <div class="card-body d-flex align-items-center justify-content-center text-center p-3">
                                            <div>
                                                <div class="h4 mb-1"><i class="bi bi-image"></i></div>
                                                <div class="fw-bold">{{ __('messages.Background image') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card flex-fill mode-card surface-crisp" data-mode="solid">
                                        <div class="card-body d-flex align-items-center justify-content-center text-center p-3">
                                            <div>
                                                <div class="h4 mb-1"><i class="bi bi-droplet-half"></i></div>
                                                <div class="fw-bold">{{ __('messages.Solid color') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="editor-card">
                                <p class="section-kicker mb-3">{{ __('messages.Preview') }}</p>
                                <div id="mode-preview" class="rounded border position-relative overflow-hidden live-preview surface-crisp" style="min-height:280px;">
                                    <div id="mode-preview-media" class="live-preview__media"></div>
                                    <div id="mode-preview-overlay" class="live-preview__overlay"></div>
                                </div>
                            </div>

                            {{-- Template Mode --}}
                            <div id="template-mode" class="mode-pane editor-card">
                                <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        {{__('messages.Select theme')}}
                                    </button>
                                </div>
                                <div class="card border bg-light-subtle">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <p class="text-uppercase text-muted small mb-1">{{ __('Selected template') }}</p>
                                                <h6 class="mb-1" id="selected-template-name">{{ $currentTemplate['label'] ?? __('Default') }}</h6>
                                                <p class="small text-muted mb-0" id="selected-template-description">{{ $currentTemplate['description'] ?? '' }}</p>
                                            </div>
                                            <div class="text-end">
                                                <p class="text-uppercase text-muted small mb-1">{{ __('Variant') }}</p>
                                                <div class="fw-semibold" id="selected-template-variant-label">{{ $currentVariant['label'] ?? __('Default') }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <p class="text-uppercase text-muted small mb-2">{{ __('Individually customizable') }}</p>
                                            <div class="d-flex flex-wrap gap-2" id="selected-template-capability-list">
                                                @foreach($capabilityGroups as $capabilityGroup)
                                                    @php
                                                        $groupKeys = (array) ($capabilityGroup['keys'] ?? []);
                                                        $capabilityEnabled = !empty($groupKeys);
                                                        foreach ($groupKeys as $capabilityKey) {
                                                            if (empty($currentCapabilities[$capabilityKey])) {
                                                                $capabilityEnabled = false;
                                                                break;
                                                            }
                                                        }
                                                    @endphp
                                                    <span class="badge {{ $capabilityEnabled ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                        {{ (string) ($capabilityGroup['label'] ?? '') }}: {{ $capabilityEnabled ? __('Yes') : __('No') }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-uppercase text-muted small mb-2">{{ __('Variants (color changes only)') }}</p>
                                            <div class="d-flex flex-wrap gap-2" id="selected-template-variant-list"></div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="submit" form="template-apply-form" class="btn btn-primary">{{ __('Apply theme') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Image Mode --}}
                            <div id="image-mode" class="mode-pane editor-card">
                                @if($backgroundImageLocked)
                                    <div class="alert alert-warning mb-3">
                                        {{ __('Available from :tier.', ['tier' => $backgroundImageRequiredTierLabel]) }}
                                    </div>
                                @endif
                                <div class="mb-4">
                                    <label class="form-label d-flex align-items-center gap-2">
                                        <span>{{ __('messages.Background image') }}</span>
                                        @if($backgroundImageLocked)
                                            <span class="badge bg-secondary">{{ __('Locked') }}</span>
                                        @endif
                                    </label>
                                    <input id="background-image-input" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" class="form-control form-control-lg {{ $backgroundImageLocked ? 'opacity-50' : '' }}" name="image" @if($backgroundImageLocked) data-background-image-upsell @endif>
                                    <div class="form-text">{{ __('messages.upload.limit.notice', ['size' => $backgroundLimitMb, 'width' => $backgroundLimitWidth, 'height' => $backgroundLimitHeight]) }}</div>
                                    @if($hasBackgroundImage)
                                        @if($backgroundImageLocked)
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-background-image-upsell><i class="bi bi-trash-fill"></i> {{__('messages.Remove background')}}</button>
                                        @else
                                            <form method="POST" action="{{ route('removeBackground') }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-trash-fill"></i> {{__('messages.Remove background')}}</button>
                                            </form>
                                        @endif
                                    @endif
                                    <div class="text-muted small mt-2">{{ __('messages.Image helper text') }}</div>
                                </div>

                                <div class="mb-4 border-top pt-3">
                                    <label class="form-label">{{ __('messages.Transparent overlay') }}</label>
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-6">
                                            <label class="form-label" for="overlay-color-image">{{ __('messages.Overlay color') }}</label>
                                            <input data-mode="image" id="overlay-color-image" type="color" class="form-control form-control-color color-swatch overlay-input {{ $backgroundImageLocked ? 'opacity-50' : '' }}" name="overlay_color" value="{{ $overlayColor }}" @if($backgroundImageLocked) data-background-image-upsell @endif>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center" for="overlay-opacity-image">
                                                <span>{{ __('messages.Overlay opacity') }}</span>
                                                <span class="text-muted small overlay-opacity-value">{{$overlayOpacity}}%</span>
                                            </label>
                                            <input data-mode="image" id="overlay-opacity-image" type="range" class="form-range editor-slider overlay-input {{ $backgroundImageLocked ? 'opacity-50' : '' }}" min="0" max="100" step="1" name="overlay_opacity" value="{{$overlayOpacity}}" @if($backgroundImageLocked) data-background-image-upsell @endif>
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ __('messages.Overlay helper text') }}</small>
                                </div>

                                <div class="mb-4 border-top pt-3">
                                    <label class="form-label" for="background-color-image">{{ __('messages.iPhone edge color') }}</label>
                                    <input id="background-color-image" type="color" class="form-control form-control-color color-swatch {{ $backgroundImageLocked ? 'opacity-50' : '' }}" name="background_color" value="{{ $backgroundColor }}" title="{{ __('messages.iPhone edge color') }}" @if($backgroundImageLocked) data-background-image-upsell @endif>
                                    <small class="text-muted">{{ __('messages.iPhone edge color helper') }}</small>
                                </div>

                                @if($backgroundImageLocked)
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary" data-background-image-upsell>{{ __('Upgrade in subscription') }}</button>
                                    </div>
                                @else
                                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                        <button type="submit" class="btn btn-primary action-btn">{{__('messages.Apply')}}</button>
                                    </div>
                                @endif
                            </div>

                            {{-- Shared Background Editor (Solid + Template Individual) --}}
                            <div id="background-editor" class="mode-pane editor-card">
                                <div class="mb-4">
                                    <label class="form-label" for="background-color-editor">{{ __('messages.Solid background color') }}</label>
                                    <input id="background-color-editor" type="color" class="form-control form-control-color color-swatch" name="background_color" value="{{ $backgroundColor }}" title="{{ __('messages.Solid background color') }}">
                                    <small class="text-muted">{{ __('messages.Base color helper') }}</small>
                                </div>

                                <div class="mb-4 border-top pt-3" id="gradient-block">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                        <label class="form-label mb-0">{{ __('messages.be.gradient') }}</label>
                                        <div class="form-check form-switch toggle-inline m-0">
                                            <input type="hidden" name="gradient_enabled" value="0">
                                            <input class="form-check-input" type="checkbox" id="gradient-enabled" name="gradient_enabled" value="1" @if($gradientEnabled) checked @endif>
                                            <label class="form-check-label" for="gradient-enabled">{{ __('messages.Enable gradient') }}</label>
                                        </div>
                                    </div>

                                    <div id="gradient-editor" class="mb-3">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-6 col-sm-12">
                                                <label class="form-label mb-1 d-block">{{ __('messages.be.color_1') }}</label>
                                                <div class="d-flex align-items-center gap-2 gradient-color-row">
                                                    <input type="color" id="gradient-color-one" class="form-control form-control-color color-swatch gradient-input" name="gradient_color_one" value="{{ $gradientColorOne }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12">
                                                <label class="form-label mb-1 d-block">{{ __('messages.be.color_2') }}</label>
                                                <div class="d-flex align-items-center gap-2 gradient-color-row">
                                                    <input type="color" id="gradient-color-two" class="form-control form-control-color color-swatch gradient-input" name="gradient_color_two" value="{{ $gradientColorTwo }}">
                                                </div>
                                                <small class="text-muted">{{ __('messages.gradient_accent_hint') }}</small>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="mb-3 border-top pt-3">
                                    <label class="form-label">{{ __('messages.Transparent overlay') }}</label>
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-6">
                                            <label class="form-label" for="overlay-color-solid">{{ __('messages.Overlay color') }}</label>
                                            <input data-mode="solid" id="overlay-color-solid" type="color" class="form-control form-control-color color-swatch overlay-input" name="overlay_color" value="{{ $overlayColor }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center" for="overlay-opacity-solid">
                                                <span>{{ __('messages.Overlay opacity') }}</span>
                                                <span class="text-muted small overlay-opacity-value">{{$overlayOpacity}}%</span>
                                            </label>
                                            <input data-mode="solid" id="overlay-opacity-solid" type="range" class="form-range editor-slider overlay-input" min="0" max="100" step="1" name="overlay_opacity" value="{{$overlayOpacity}}">
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ __('messages.Overlay helper text') }}</small>
                                </div>

                                <div class="d-flex justify-content-end pt-3 border-top" id="apply-row">
                                    <button type="submit" class="btn btn-primary action-btn">{{__('messages.Apply')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif
                    <form id="template-apply-form" action="{{ route('editTheme') }}" method="post" class="d-none">
                        @csrf
                        <input type="hidden" name="template_id" id="template-id-input" value="{{ $currentTemplateId }}">
                        <input type="hidden" name="variant_id" id="template-variant-input" value="{{ $currentVariantId }}">
                    </form>

    <div class="editor-card mt-4">
        <p class="section-kicker mb-3">{{__('messages.Live preview')}}</p>
        <div>
            <iframe frameborder="0" allowtransparency="true" id="frPreview" style="background: #FFFFFF;height:400px;" class='w-100' src="{{ $previewUrl }}">{{__('messages.No compatible browser')}}</iframe>
        </div>
    </div>
</div>

@endforeach

<script src="{{ asset('assets/external-dependencies/jquery-1.12.4.min.js') }}"></script>
<style>
.surface-crisp {
    overflow: hidden;
    isolation: isolate;
    outline: 1px solid transparent;
}
.editor-page-shell {
    background: #F5F5F7;
    border-radius: 12px;
    padding: 1rem;
}
.editor-layout {
    background: #F5F5F7;
}
.editor-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    margin-bottom: 24px;
}
.section-kicker,
.section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #999999;
    font-weight: 700;
}
.section-kicker {
    margin: 0;
}
.section-label {
    display: block;
    margin-bottom: 0;
}
.editor-card .form-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #999999;
    font-weight: 700;
}
.hint-text {
    font-size: 11px;
    color: #999999;
}
.color-inline {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.form-control-color,
.color-swatch {
    width: 40px;
    height: 40px;
    min-width: 40px;
    padding: 0;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    overflow: hidden;
    -webkit-appearance: none;
    appearance: none;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.form-control-color:hover,
.color-swatch:hover {
    border-color: rgba(var(--bs-primary-rgb), 0.65);
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.14);
}
.form-control-color::-webkit-color-swatch-wrapper {
    padding: 0;
}
.form-control-color::-webkit-color-swatch,
.form-control-color::-moz-color-swatch {
    border: none;
}
.color-hex-input {
    width: 120px;
    min-width: 120px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.ls-color-controls {
    margin-top: 12px;
}
.ls-color-choice-options {
    display: grid;
    gap: 0;
    border-top: 1px solid #eef1f4;
    border-bottom: 1px solid #eef1f4;
}
.ls-color-choice {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 13px 0 13px 12px;
    border-left: 3px solid transparent;
    cursor: pointer;
    transition: border-color 0.16s ease, color 0.16s ease;
}
.ls-color-choice + .ls-color-choice {
    border-top: 1px solid #eef1f4;
}
.ls-color-choice.form-check {
    margin-bottom: 0;
    padding-left: 12px;
}
.ls-color-choice .form-check-input {
    margin-left: 0;
    margin-top: 0;
    flex: 0 0 auto;
}
.ls-color-choice:has(.form-check-input:checked) {
    border-left-color: rgba(var(--bs-primary-rgb), 0.75);
}
.ls-color-choice .form-check-label {
    font-weight: 600;
    margin-bottom: 0;
    cursor: pointer;
}
.ls-color-choice:has(.form-check-input:checked) .form-check-label {
    color: rgba(var(--bs-primary-rgb), 1);
}
.ls-color-choice:has(.form-check-input:disabled) {
    cursor: default;
    opacity: 0.58;
}
.ls-color-custom-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
}
.ls-color-copy-button {
    min-height: 38px;
}
.toggle-inline.form-check {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.toggle-inline .form-check-input {
    width: 2.6rem;
    height: 1.4rem;
    margin: 0;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    background-color: #e5e7eb;
    transition: all 0.2s ease;
}
.toggle-inline .form-check-input:checked {
    background-color: rgba(var(--bs-primary-rgb), 1);
    border-color: rgba(var(--bs-primary-rgb), 1);
}
.toggle-inline .form-check-label {
    font-size: 14px;
    color: #1f2937;
    font-weight: 600;
    margin-bottom: 0;
}
.slider-value {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}
.editor-slider.form-range {
    width: 100%;
    height: 24px;
    padding: 0 2px;
    margin-top: 6px;
    -webkit-appearance: none;
    appearance: none;
    overflow: visible;
    background: transparent;
}
.editor-slider.form-range::-webkit-slider-runnable-track {
    height: 8px;
    border-radius: 999px;
    background: #dbe3ff;
}
.editor-slider.form-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    margin-top: -4px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    background: rgba(var(--bs-primary-rgb), 1);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.26);
}
.editor-slider.form-range::-moz-range-track {
    height: 8px;
    border: none;
    border-radius: 999px;
    background: #dbe3ff;
}
.editor-slider.form-range::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    background: rgba(var(--bs-primary-rgb), 1);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.26);
}
.gradient-preview {
    min-height: 44px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
}
.mode-card {
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    border: 1px solid #d4d4d8;
    border-radius: 10px;
    background: #ffffff;
}
.mode-card .card-body {
    min-height: 96px;
}
.mode-card .fw-bold {
    font-size: 13px;
    color: #4b5563;
    font-weight: 600;
}
.mode-card:hover {
    border-color: rgba(var(--bs-primary-rgb), 0.6);
    transform: translateY(-1px);
}
.mode-card.active,
.mode-card.border-primary {
    border-color: rgba(var(--bs-primary-rgb), 0.85) !important;
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.15);
}
.mode-card.active .fw-bold {
    color: var(--bs-primary);
}
.mode-pane {
    margin-bottom: 0;
}
.action-btn {
    min-width: 9rem;
}
.gradient-color-row .form-control-color,
.gradient-third-controls .form-control-color {
    width: 40px;
    min-width: 40px;
}
.gradient-color-row .form-range,
.gradient-third-controls .form-range {
    flex: 1 1 160px;
    min-width: 140px;
}
.gradient-color-row span,
.gradient-third-controls span {
    min-width: 40px;
    white-space: nowrap;
}
body.dark .editor-page-shell {
    background: #151b24;
}
body.dark .editor-layout {
    background: transparent;
}
body.dark .editor-page-shell > .card,
body.dark .editor-card,
body.dark .editor-page-shell .mode-card,
body.dark .editor-page-shell .card.border.bg-light-subtle {
    background: #1f2733;
    border-color: rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
    color: #e7edf8;
}
body.dark .editor-page-shell .text-muted {
    color: #9fb0c6 !important;
}
body.dark .section-kicker,
body.dark .section-label,
body.dark .editor-card .form-label {
    color: #94a3b8;
}
body.dark .ls-color-choice-options {
    border-top-color: rgba(255, 255, 255, 0.1);
    border-bottom-color: rgba(255, 255, 255, 0.1);
}
body.dark .ls-color-choice + .ls-color-choice {
    border-top-color: rgba(255, 255, 255, 0.1);
}
body.dark .ls-color-choice .form-check-label {
    color: #dee6f4;
}
body.dark .ls-color-choice:has(.form-check-input:checked) .form-check-label {
    color: rgba(var(--bs-primary-rgb), 1);
}
body.dark .toggle-inline .form-check-input {
    border-color: #5b677b;
    background-color: #30384a;
}
body.dark .toggle-inline .form-check-label,
body.dark .slider-value {
    color: #dee6f4;
}
body.dark .mode-card .fw-bold {
    color: #d0d9e8;
}
body.dark .form-control:not(.form-control-color),
body.dark .form-select,
body.dark .form-range {
    color: #e7edf8;
}
body.dark .form-control:not(.form-control-color),
body.dark .form-select {
    background-color: #121924;
    border-color: #3b4558;
}
body.dark .editor-slider.form-range::-webkit-slider-runnable-track,
body.dark .editor-slider.form-range::-moz-range-track {
    background: #3a455b;
}
body.dark .gradient-preview {
    border-color: #3a455b;
}
@media (max-width: 576px) {
    .gradient-color-row,
    .gradient-third-controls {
        flex-wrap: wrap;
    }
    .gradient-color-row .form-range,
    .gradient-third-controls .form-range {
        flex: 1 1 100%;
        min-width: 0;
    }
    .gradient-color-row span,
    .gradient-third-controls span {
        min-width: unset;
    }
}
</style>
<script>
(() => {
    const modeCards = Array.from(document.querySelectorAll('.mode-card'));
    const modePanes = {
        template: document.getElementById('template-mode'),
        image: document.getElementById('image-mode'),
    };
    const backgroundEditor = document.getElementById('background-editor');
    const modePreviewMedia = document.getElementById('mode-preview-media');
    const modePreviewOverlay = document.getElementById('mode-preview-overlay');
    const colorPreviewWrapper = document.getElementById('color-preview-wrapper');
    const colorPreviewMedia = document.getElementById('color-preview-media');
    const colorPreviewOverlay = document.getElementById('color-preview-overlay');
    const selectedModeInput = document.getElementById('selected-mode');
    const hiddenBackgroundMode = document.getElementById('background-mode-hidden');
    const applyRow = document.getElementById('apply-row');
    const backgroundForm = document.getElementById('background-form');
    const baseColorInput = document.getElementById('background-color-editor');
    const baseColorImageInput = document.getElementById('background-color-image');
    const imageInput = document.getElementById('background-image-input');
    const gradientToggle = document.getElementById('gradient-enabled');
    const gradientBar = document.getElementById('gradient-bar');
    const gradientColors = [
        document.getElementById('gradient-color-one'),
        document.getElementById('gradient-color-two'),
        document.getElementById('gradient-color-three'),
    ];
    const gradientStops = [
        document.getElementById('gradient-stop-one'),
        document.getElementById('gradient-stop-two'),
        document.getElementById('gradient-stop-three'),
    ];
    const gradientStopLabels = [
        document.getElementById('gradient-stop-one-label'),
        document.getElementById('gradient-stop-two-label'),
        document.getElementById('gradient-stop-three-label'),
    ];
    const toggleThirdStop = document.getElementById('toggle-third-stop');
    const toggleThirdStopLabel = document.getElementById('toggle-third-stop-label');
    const overlayInputs = {
        image: {
            color: document.getElementById('overlay-color-image'),
            opacity: document.getElementById('overlay-opacity-image'),
        },
        solid: {
            color: document.getElementById('overlay-color-solid'),
            opacity: document.getElementById('overlay-opacity-solid'),
        },
    };
    const overlayOpacityLabels = Array.from(document.querySelectorAll('.overlay-opacity-value'));
    const overlayColorHidden = document.getElementById('overlay-color-hidden');
    const overlayOpacityHidden = document.getElementById('overlay-opacity-hidden');
    const backgroundImageLocked = {{ $backgroundImageLockedGlobal ? 'true' : 'false' }};
    const subscriptionDashboardUrl = @json($subscriptionDashboardUrl);
    const templateDefaultColor = '{{ addslashes($templateDefaultColor) }}';
    let themePreviewUrl = "@if(file_exists(base_path() . '/themes/' . $page->theme . '/preview.png')){{url('/themes/' . $page->theme . '/preview.png')}}@elseif($page->theme === 'default' or empty($page->theme)){{url('/assets/wayvio/images/themes/default.png')}}@else{{url('/assets/wayvio/images/themes/no-preview.png')}}@endif";

    const initialMode = '{{ $page->theme !== "default" ? "template" : $defaultMode }}';
    const state = {
        mode: initialMode,
        baseColor: '{{ $backgroundColor }}',
        gradient: {
            enabled: {{ $gradientEnabled ? 'true' : 'false' }},
            stops: [
                { color: '{{ $gradientColorOne }}', position: Number((gradientStops[0] ? gradientStops[0].value : 0) || 0) },
                { color: '{{ $gradientColorTwo }}', position: Number((gradientStops[1] ? gradientStops[1].value : 100) || 100) },
                { color: '{{ $gradientColorThree ?: '#ffffff' }}', position: Number((gradientStops[2] ? gradientStops[2].value : 50) || 50), enabled: {{ $gradientColorThree ? 'true' : 'false' }} },
            ],
            direction: 'to bottom',
        },
        overlay: { color: '{{ $overlayColor }}', opacity: {{ ($overlayOpacity ?? 0) }}/100 },
        imageUrl: '{{ $backgroundImageUrl }}',
        templatePreviewUrl: themePreviewUrl,
        templateDefaultColor,
    };
    const colorHexBindings = [];

    const normalizeHexInput = (value) => {
        if (typeof value !== 'string') {
            return null;
        }
        const match = value.trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (!match) {
            return null;
        }
        let hex = match[1].toUpperCase();
        if (hex.length === 3) {
            hex = `${hex[0]}${hex[0]}${hex[1]}${hex[1]}${hex[2]}${hex[2]}`;
        }
        return `#${hex}`;
    };

    const syncColorHexInputs = () => {
        colorHexBindings.forEach((binding) => {
            if (binding && typeof binding.sync === 'function') {
                binding.sync();
            }
        });
    };

    const setupHexInputForColor = (colorInput) => {
        if (!colorInput || colorInput.dataset.hexBound === '1') {
            return;
        }

        const row = colorInput.closest('.color-inline') || colorInput.closest('.gradient-color-row') || colorInput.closest('.gradient-third-controls') || colorInput.closest('.ls-color-custom-row') || colorInput.parentElement;
        const hiddenLabel = row ? row.querySelector('.hex-value') : null;
        if (hiddenLabel) {
            hiddenLabel.classList.add('d-none');
        }

        const hexInput = document.createElement('input');
        hexInput.type = 'text';
        hexInput.inputMode = 'text';
        hexInput.autocapitalize = 'off';
        hexInput.autocomplete = 'off';
        hexInput.spellcheck = false;
        hexInput.placeholder = '#RRGGBB';
        hexInput.className = 'form-control form-control-sm color-hex-input';
        hexInput.setAttribute('aria-label', 'Hex color');
        colorInput.insertAdjacentElement('afterend', hexInput);

        const syncFromColor = () => {
            const normalized = normalizeHexInput(colorInput.value) || '#000000';
            hexInput.value = normalized;
            hexInput.disabled = !!colorInput.disabled;
            hexInput.classList.toggle('opacity-50', !!colorInput.disabled);
            hexInput.classList.remove('is-invalid');
        };

        const commitHex = () => {
            if (hexInput.disabled) {
                return;
            }
            const normalized = normalizeHexInput(hexInput.value);
            if (!normalized) {
                hexInput.classList.add('is-invalid');
                return;
            }
            hexInput.classList.remove('is-invalid');
            if (colorInput.value.toUpperCase() !== normalized) {
                colorInput.value = normalized;
                colorInput.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                syncFromColor();
            }
        };

        hexInput.addEventListener('change', commitHex);
        hexInput.addEventListener('blur', commitHex);
        hexInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            commitHex();
        });

        colorInput.addEventListener('input', syncFromColor);
        colorInput.addEventListener('change', syncFromColor);
        const observer = new MutationObserver(syncFromColor);
        observer.observe(colorInput, { attributes: true, attributeFilter: ['disabled'] });
        syncFromColor();
        colorInput.dataset.hexBound = '1';
        colorHexBindings.push({ sync: syncFromColor });
    };

    const initializeHexInputs = () => {
        Array.from(document.querySelectorAll('input[type="color"]')).forEach((input) => {
            setupHexInputForColor(input);
        });
        syncColorHexInputs();
    };

    const redirectToSubscriptionDashboard = (event) => {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        window.location.assign(subscriptionDashboardUrl);
    };

    if (backgroundImageLocked) {
        const upsellTargets = Array.from(document.querySelectorAll('[data-background-image-upsell]'));
        const upsellEvents = ['click', 'input', 'change', 'mousedown', 'touchstart', 'keydown'];
        upsellTargets.forEach((element) => {
            upsellEvents.forEach((eventName) => {
                element.addEventListener(eventName, (event) => {
                    if (eventName === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    redirectToSubscriptionDashboard(event);
                });
            });
        });

        if (backgroundForm) {
            backgroundForm.addEventListener('submit', (event) => {
                const currentMode = hiddenBackgroundMode ? String(hiddenBackgroundMode.value || '') : '';
                if (currentMode === 'image') {
                    redirectToSubscriptionDashboard(event);
                }
            });
        }
    }

    const syncOverlayHidden = () => {
        if (overlayColorHidden) overlayColorHidden.value = state.overlay.color || '#000000';
        if (overlayOpacityHidden) overlayOpacityHidden.value = Math.round((state.overlay.opacity ?? 0) * 100);
    };

    const syncBaseColorInputs = () => {
        const useImageInput = state.mode === 'image';
        if (baseColorInput) {
            baseColorInput.disabled = useImageInput;
            baseColorInput.classList.toggle('opacity-50', useImageInput);
            if (!useImageInput) baseColorInput.value = state.baseColor;
        }
        if (baseColorImageInput) {
            baseColorImageInput.disabled = !useImageInput;
            baseColorImageInput.classList.toggle('opacity-50', !useImageInput);
            if (useImageInput) baseColorImageInput.value = state.baseColor;
        }
        syncColorHexInputs();
    };

    const collectActiveGradientStops = () => {
        if (!state.gradient.enabled) return [];
        const activeStops = [
            { color: state.gradient.stops[0].color, position: state.gradient.stops[0].position },
            { color: state.gradient.stops[1].color, position: state.gradient.stops[1].position },
        ];
        if (state.gradient.stops[2].enabled) {
            activeStops.push({ color: state.gradient.stops[2].color, position: state.gradient.stops[2].position });
        }
        return activeStops
            .filter(stop => stop.color)
            .map(stop => ({
                color: stop.color,
                position: Math.max(0, Math.min(100, Number(stop.position) || 0)),
            }));
    };

    const normalizeGradientStopsForRender = (stops) => {
        if (!Array.isArray(stops) || stops.length < 2) return [];
        const renderStops = stops.map(stop => ({ color: stop.color, position: stop.position }));
        const minTransitionGap = 12;
        const lastStopIndex = renderStops.length - 1;
        let lastPosition = 0;

        renderStops.forEach((stop, index) => {
            let position = Math.max(0, Math.min(100, Number(stop.position) || 0));

            const remainingStops = lastStopIndex - index;
            const maxAllowed = Math.max(0, Math.min(100, 100 - (remainingStops * minTransitionGap)));

            if (index === 0) {
                position = Math.min(position, maxAllowed);
            } else {
                const minAllowed = Math.min(100, lastPosition + minTransitionGap);
                if (minAllowed > maxAllowed) {
                    position = maxAllowed;
                } else {
                    position = Math.max(minAllowed, Math.min(position, maxAllowed));
                }
            }

            stop.position = position;
            lastPosition = position;
        });

        return renderStops;
    };

    const buildGradientLayer = () => {
        const activeStops = collectActiveGradientStops();
        if (activeStops.length < 2) return null;

        const baseColor = (activeStops[0] && activeStops[0].color) ? activeStops[0].color : state.baseColor;
        const accentColor = (activeStops[1] && activeStops[1].color && activeStops[1].color !== baseColor)
            ? activeStops[1].color
            : baseColor;

        const gradientCss = `radial-gradient(ellipse 200% 140% at 50% 20%, ${accentColor} 0%, ${baseColor} 92%)`;
        const gradientSoftCss = `radial-gradient(ellipse 160% 90% at 50% -10%, ${accentColor} 0%, ${baseColor} 85%)`;
        const gradientSoftAltCss = `radial-gradient(ellipse 120% 100% at 50% 30%, ${baseColor} 20%, ${baseColor} 100%)`;

        return {
            type: 'gradient',
            backgroundImage: gradientCss,
            backgroundColor: baseColor,
            backgroundSize: '100% 100%',
            gradientCss,
            gradientSoftCss,
            gradientSoftAltCss,
            c1: baseColor,
            c2: accentColor,
            c3: baseColor,
        };
    };

    const buildGradientCss = () => {
        const layer = buildGradientLayer();
        return layer ? layer.backgroundImage : null;
    };

    const getColorLayer = () => {
        const gradientLayer = buildGradientLayer();
        if (gradientLayer) {
            return gradientLayer;
        }
        return { type: 'solid', backgroundImage: 'none', backgroundColor: state.baseColor, backgroundSize: 'cover' };
    };

    const resolveBackground = () => {
        const hasImage = state.mode === 'image' && state.imageUrl;

        if (hasImage) {
            return {
                type: 'image',
                layer: { backgroundImage: `url('${state.imageUrl}')`, backgroundColor: 'transparent', backgroundSize: 'cover' },
                overlayAllowed: true,
            };
        }

        if (state.mode === 'solid') {
            const layer = getColorLayer();
            return { type: 'solid', layer, overlayAllowed: true };
        }

        if (state.mode === 'template') {
            const previewUrl = state.templatePreviewUrl || '';
            const previewColor = state.templateDefaultColor || templateDefaultColor;
            return {
                type: 'template-default',
                layer: {
                    backgroundImage: previewUrl ? `url('${previewUrl}')` : 'none',
                    backgroundColor: previewUrl ? 'transparent' : previewColor,
                    backgroundSize: previewUrl ? 'contain' : 'cover',
                },
                overlayAllowed: false,
            };
        }

        const fallbackLayer = getColorLayer();
        return { type: 'solid', layer: fallbackLayer, overlayAllowed: true };
    };

    const clearGradientPreviewVars = (mediaEl) => {
        if (!mediaEl) return;
        mediaEl.style.removeProperty('--ls-preview-gradient');
        mediaEl.style.removeProperty('--ls-preview-gradient-soft');
        mediaEl.style.removeProperty('--ls-preview-gradient-soft-alt');
        mediaEl.style.removeProperty('--ls-preview-gradient-c1');
        mediaEl.style.removeProperty('--ls-preview-gradient-c2');
        mediaEl.style.removeProperty('--ls-preview-gradient-c3');
    };

    const applyLayer = (mediaEl, overlayEl, layer, overlayAllowed) => {
        if (!mediaEl || !overlayEl) return;

        const isGradientLayer = !!(layer && layer.type === 'gradient');
        mediaEl.classList.toggle('is-user-gradient', isGradientLayer);

        if (isGradientLayer) {
            mediaEl.style.setProperty('--ls-preview-gradient', layer.gradientCss);
            mediaEl.style.setProperty('--ls-preview-gradient-soft', layer.gradientSoftCss);
            mediaEl.style.setProperty('--ls-preview-gradient-soft-alt', layer.gradientSoftAltCss);
            mediaEl.style.setProperty('--ls-preview-gradient-c1', layer.c1);
            mediaEl.style.setProperty('--ls-preview-gradient-c2', layer.c2);
            mediaEl.style.setProperty('--ls-preview-gradient-c3', layer.c3);
            mediaEl.style.backgroundImage = layer.backgroundImage || 'none';
            mediaEl.style.backgroundColor = layer.backgroundColor || 'transparent';
            mediaEl.style.backgroundSize = layer.backgroundSize || 'cover';
        } else {
            clearGradientPreviewVars(mediaEl);
            mediaEl.style.backgroundImage = (layer && layer.backgroundImage) ? layer.backgroundImage : 'none';
            mediaEl.style.backgroundColor = (layer && layer.backgroundColor) ? layer.backgroundColor : 'transparent';
            mediaEl.style.backgroundSize = (layer && layer.backgroundSize) ? layer.backgroundSize : 'cover';
        }

        mediaEl.style.backgroundRepeat = 'no-repeat';
        mediaEl.style.backgroundPosition = 'center';
        overlayEl.style.backgroundColor = overlayAllowed ? (state.overlay.color || '#000000') : 'transparent';
        overlayEl.style.opacity = overlayAllowed ? state.overlay.opacity : 0;
    };

    const renderGradientBar = () => {
        const gradientCss = buildGradientCss();
        if (gradientBar) {
            gradientBar.style.background = gradientCss || state.baseColor;
        }
        gradientStopLabels.forEach((label, idx) => {
            if (label) {
                const position = Math.max(0, Math.min(100, Number(state.gradient.stops[idx].position) || 0));
                state.gradient.stops[idx].position = position;
                label.textContent = `${position}%`;
            }
        });
    };

        const updateOverlayLabels = () => {
            overlayOpacityLabels.forEach(label => {
                const parentColumn = label.closest('.col-md-6');
                const slider = parentColumn ? parentColumn.querySelector('.form-range') : null;
                if (slider) label.textContent = `${Math.round(Number(slider.value) || 0)}%`;
            });
        };

    const renderLivePreview = () => {
        const resolved = resolveBackground();
        const modeLayer = state.mode === 'template'
            ? {
                backgroundImage: state.templatePreviewUrl ? `url('${state.templatePreviewUrl}')` : 'none',
                backgroundColor: state.templatePreviewUrl ? 'transparent' : (state.templateDefaultColor || templateDefaultColor),
                backgroundSize: state.templatePreviewUrl ? 'contain' : 'cover',
            }
            : resolved.layer;
        const modeOverlayAllowed = resolved.overlayAllowed && state.mode !== 'template';
        applyLayer(modePreviewMedia, modePreviewOverlay, modeLayer, modeOverlayAllowed);

        const colorLayer = state.mode === 'solid' ? resolved.layer : getColorLayer();
        const allowColorOverlay = state.mode === 'solid';
        applyLayer(colorPreviewMedia, colorPreviewOverlay, colorLayer, allowColorOverlay);
        updateOverlayLabels();
        renderGradientBar();
        syncOverlayHidden();
        syncColorHexInputs();
    };

    window.__wayvioPreviewTemplateSelection = function (payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        const nextPreviewUrl = typeof payload.previewUrl === 'string' ? payload.previewUrl : '';
        const nextColor = typeof payload.defaultColor === 'string' && payload.defaultColor !== ''
            ? payload.defaultColor
            : templateDefaultColor;

        state.templatePreviewUrl = nextPreviewUrl;
        state.templateDefaultColor = nextColor;
        themePreviewUrl = nextPreviewUrl;

        if (state.mode === 'template') {
            renderLivePreview();
        }
    };

    const syncHiddenFields = () => {
        if (hiddenBackgroundMode) hiddenBackgroundMode.value = state.mode === 'image' ? 'image' : (state.mode === 'template' ? 'template' : 'color');
        if (selectedModeInput) selectedModeInput.value = state.mode;
        if (applyRow) {
            applyRow.style.display = '';
        }
        syncBaseColorInputs();
    };

    const toggleEditorVisibility = () => {
        const showColorEditor = state.mode === 'solid';
        if (backgroundEditor) backgroundEditor.style.display = showColorEditor ? 'block' : 'none';
        if (colorPreviewWrapper) {
            colorPreviewWrapper.style.display = showColorEditor ? 'block' : 'none';
        }
    };

    const refreshOverlayInputs = () => {
        const resolved = resolveBackground();
        const isImage = resolved.type === 'image';
        const isColorBased = resolved.overlayAllowed && !isImage;

        const setMuted = (el, muted, active) => {
            if (!el) return;
            el.classList.toggle('opacity-50', muted);
            el.disabled = !active;
        };

        setMuted(overlayInputs.image.color, !isImage, isImage);
        setMuted(overlayInputs.image.opacity, !isImage, isImage);
        setMuted(overlayInputs.solid.color, !isColorBased, isColorBased);
        setMuted(overlayInputs.solid.opacity, !isColorBased, isColorBased);
        syncColorHexInputs();
    };

    const refreshGradientDisabled = () => {
        const allowGradient = state.mode === 'solid';
        const disableAll = !allowGradient || !state.gradient.enabled;
        gradientColors.forEach((input, idx) => {
            if (!input) return;
            input.disabled = disableAll || (idx === 2 && !state.gradient.stops[2].enabled);
        });
        gradientStops.forEach((input, idx) => {
            if (!input) return;
            input.disabled = disableAll || (idx === 2 && !state.gradient.stops[2].enabled);
        });
        gradientToggle.disabled = !allowGradient;
        gradientToggle.checked = allowGradient && state.gradient.enabled;
        document.getElementById('gradient-block').style.display = allowGradient ? 'block' : 'none';
        syncColorHexInputs();
    };

    const setActiveMode = (mode) => {
        state.mode = mode;
        modeCards.forEach(card => {
            const isActive = card.dataset.mode === mode;
            card.classList.toggle('border-primary', isActive);
            card.classList.toggle('shadow', isActive);
            card.classList.toggle('active', isActive);
        });
        Object.entries(modePanes).forEach(([key, pane]) => {
            if (pane) pane.style.display = key === mode ? 'block' : 'none';
        });
        syncHiddenFields();
        toggleEditorVisibility();
        refreshOverlayInputs();
        refreshGradientDisabled();
        renderLivePreview();
        if (typeof CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('wayvio:design-mode-changed', {
                detail: { mode: mode },
            }));
        }
    };

    modeCards.forEach(card => card.addEventListener('click', () => setActiveMode(card.dataset.mode)));
    initializeHexInputs();

    if (imageInput) {
        imageInput.addEventListener('change', (event) => {
            const [file] = event.target.files || [];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                state.imageUrl = e.target.result;
                renderLivePreview();
            };
            reader.readAsDataURL(file);
        });
    }

    Object.values(overlayInputs).forEach(group => {
        if (group.color) {
            group.color.addEventListener('input', (e) => {
                if (e.target.disabled) return;
                state.overlay.color = e.target.value;
                renderLivePreview();
            });
        }
        if (group.opacity) {
            group.opacity.addEventListener('input', (e) => {
                if (e.target.disabled) return;
                state.overlay.opacity = Number(e.target.value || 0) / 100;
                renderLivePreview();
            });
        }
    });

    if (baseColorInput) {
        let prevBase = state.baseColor;
        baseColorInput.addEventListener('input', (e) => {
            state.baseColor = e.target.value;
            if (baseColorImageInput && !baseColorImageInput.disabled) {
                baseColorImageInput.value = state.baseColor;
            }
            if (state.gradient.enabled && state.gradient.stops[0].color === prevBase) {
                state.gradient.stops[0].color = state.baseColor;
                if (gradientColors[0]) gradientColors[0].value = state.baseColor;
            }
            prevBase = state.baseColor;
            renderLivePreview();
        });
    }

    if (baseColorImageInput) {
        baseColorImageInput.addEventListener('input', (e) => {
            state.baseColor = e.target.value;
            if (baseColorInput && !baseColorInput.disabled) {
                baseColorInput.value = state.baseColor;
            }
            renderLivePreview();
        });
    }

    if (gradientToggle) {
        gradientToggle.addEventListener('change', (e) => {
            state.gradient.enabled = e.target.checked;
            if (state.gradient.enabled) {
                state.gradient.stops[0].color = state.baseColor;
                if (gradientColors[0]) gradientColors[0].value = state.baseColor;
            }
            refreshGradientDisabled();
            renderLivePreview();
        });
    }

    gradientColors.forEach((input, idx) => {
        if (!input) return;
        input.addEventListener('input', (e) => {
            state.gradient.stops[idx].color = e.target.value;
            if (idx === 0 && !state.gradient.enabled) {
                state.baseColor = e.target.value;
            }
            renderLivePreview();
        });
    });

    gradientStops.forEach((input, idx) => {
        if (!input) return;
        input.addEventListener('input', (e) => {
            state.gradient.stops[idx].position = Number(e.target.value);
            renderLivePreview();
        });
    });

    if (toggleThirdStop) {
        const syncThirdLabel = () => {
            if (toggleThirdStopLabel) {
                toggleThirdStopLabel.textContent = state.gradient.stops[2].enabled ? '{{__("messages.Remove third color")}}' : '{{__("messages.Add third color")}}';
            }
        };
        toggleThirdStop.checked = state.gradient.stops[2].enabled;
        syncThirdLabel();
        toggleThirdStop.addEventListener('change', () => {
            state.gradient.stops[2].enabled = toggleThirdStop.checked;
            syncThirdLabel();
            refreshGradientDisabled();
            renderLivePreview();
        });
    }

    setActiveMode(state.mode);
})();

(() => {
    const tierAllowsTextAccent = {{ $canCustomizeTextAccentByTier ? 'true' : 'false' }};
    const modeInputs = Array.from(document.querySelectorAll('input[name="global_text_color_mode"]'));
    const accentControls = Array.from(document.querySelectorAll('[data-text-accent-control]'));
    const accentControlsWrap = document.getElementById('text-accent-controls');
    const templateLockNotice = document.getElementById('text-accent-template-lock');
    const accentSaveRow = document.getElementById('accent-save-row');
    const selectedDesignModeInput = document.getElementById('selected-mode');
    const templateIdInput = document.getElementById('template-id-input');
    const fallbackTemplateSupportsTextAccent = {{ $textAccentEnabled ? 'true' : 'false' }};
    if (!modeInputs.length && !templateLockNotice) {
        return;
    }

    const customRow = document.getElementById('global-text-custom-row');
    const customColorInput = document.getElementById('global-text-color-custom');
    const copyButtonTextColor = document.getElementById('copy-button-text-color');
    let latestTemplateSupportsTextAccent = fallbackTemplateSupportsTextAccent;

    const activeTextMode = () => {
        const checked = modeInputs.find((input) => input.checked);
        return checked ? checked.value : 'white';
    };

    const activeDesignMode = () => {
        if (!selectedDesignModeInput) {
            return 'solid';
        }
        return String(selectedDesignModeInput.value || 'solid');
    };

    const resolveTemplateSupportsTextAccent = () => {
        const currentTemplateId = templateIdInput ? String(templateIdInput.value || '') : '';
        if (currentTemplateId !== '') {
            const choices = document.querySelectorAll('.template-choice[data-template-id]');
            for (let i = 0; i < choices.length; i += 1) {
                const choice = choices[i];
                if (choice.getAttribute('data-template-id') === currentTemplateId) {
                    return choice.getAttribute('data-text-accent-capable') === '1';
                }
            }
        }

        return latestTemplateSupportsTextAccent;
    };

    const isTextAccentUnlocked = () => {
        if (!tierAllowsTextAccent) {
            return false;
        }
        if (activeDesignMode() !== 'template') {
            return true;
        }
        return resolveTemplateSupportsTextAccent();
    };

    const syncModeUi = () => {
        const unlocked = isTextAccentUnlocked();

        if (accentControlsWrap) {
            accentControlsWrap.classList.toggle('d-none', !unlocked);
        }
        if (templateLockNotice) {
            templateLockNotice.classList.toggle('d-none', unlocked || !tierAllowsTextAccent);
        }
        if (accentSaveRow) {
            accentSaveRow.classList.toggle('d-none', !unlocked);
        }

        accentControls.forEach((input) => {
            input.disabled = !unlocked;
        });
        if (copyButtonTextColor) {
            copyButtonTextColor.disabled = !unlocked;
        }

        if (!unlocked || !customRow || !customColorInput) {
            return;
        }

        const isCustom = activeTextMode() === 'custom';
        customRow.classList.toggle('d-none', !isCustom);
        customColorInput.disabled = !isCustom;
    };

    modeInputs.forEach((input) => {
        input.addEventListener('change', syncModeUi);
    });
    document.addEventListener('wayvio:design-mode-changed', syncModeUi);
    document.addEventListener('wayvio:template-changed', (event) => {
        const supports = event && event.detail && typeof event.detail.supportsTextAccent === 'boolean'
            ? event.detail.supportsTextAccent
            : null;
        if (supports !== null) {
            latestTemplateSupportsTextAccent = supports;
        }
        syncModeUi();
    });

    if (copyButtonTextColor && customColorInput) {
        copyButtonTextColor.addEventListener('click', () => {
            if (copyButtonTextColor.disabled) {
                return;
            }
            const color = copyButtonTextColor.dataset.buttonTextColor || '#ffffff';
            const customModeInput = document.getElementById('global-text-mode-custom');
            if (customModeInput) {
                customModeInput.checked = true;
            }
            customColorInput.value = color;
            customColorInput.dispatchEvent(new Event('input', { bubbles: true }));
            syncModeUi();
        });
    }

    syncModeUi();
})();
</script>
<style>
.live-preview,
.color-preview {
    position: relative;
    overflow: hidden;
}
.live-preview__media,
.live-preview__overlay,
.color-preview__media,
.color-preview__overlay {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}
.live-preview__overlay,
.color-preview__overlay {
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.live-preview__media.is-user-gradient,
.color-preview__media.is-user-gradient {
    background-image: var(--ls-preview-gradient) !important;
    background-color: var(--ls-preview-gradient-c1, transparent);
    background-size: 130% 130%;
}
.live-preview__media.is-user-gradient::before,
.color-preview__media.is-user-gradient::before {
    content: "";
    position: absolute;
    inset: -6%;
    background-image: var(--ls-preview-gradient-soft);
    background-size: 125% 125%;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.1;
    filter: blur(22px) saturate(1.02);
    transform: translateZ(0);
    pointer-events: none;
}
.live-preview__media.is-user-gradient::after,
.color-preview__media.is-user-gradient::after {
    content: "";
    position: absolute;
    inset: -8%;
    background-image: var(--ls-preview-gradient-soft-alt);
    background-size: 135% 135%;
    background-position: center, center;
    background-repeat: no-repeat;
    opacity: 0.06;
    filter: blur(34px) saturate(1.02);
    transform: translateZ(0);
    pointer-events: none;
}
.color-preview {
    height: 160px;
}
.color-preview__label {
    position: relative;
    z-index: 2;
    background: rgba(255, 255, 255, 0.7);
}
.template-choice {
    width: 100%;
    text-align: left;
    padding: 0;
    overflow: hidden;
    transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
}
.template-choice:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1.2rem rgba(0, 0, 0, 0.14);
}
.template-choice--active {
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .45);
}
.template-choice--active .template-choice__image {
    filter: brightness(.78) saturate(1.05);
}
.template-choice--active .template-choice__label {
    background: rgba(var(--bs-primary-rgb), .94);
    color: #fff;
}
.template-choice__image {
    display: block;
    width: 100%;
    aspect-ratio: 3 / 2;
    object-fit: cover;
}
.template-choice__label {
    display: block;
    padding: .65rem .75rem;
    font-weight: 600;
    color: #111827;
}
.template-variant-chip {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
}
.template-variant-swatches {
    display: inline-flex;
    gap: .2rem;
    margin-left: .15rem;
}
.template-variant-swatch {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    border: 1px solid rgba(0, 0, 0, 0.2);
    display: inline-block;
}
</style>
<script type="text/javascript">$("iframe").load(function() { $("iframe").contents().find("a").each(function(index) { $(this).on("click", function(event) { event.preventDefault(); event.stopPropagation(); }); }); });</script>

@push('sidebar-scripts')
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{__('messages.Select a theme')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3" id="template-selection-grid">
                    @foreach($templateCatalogEntries as $templateEntry)
                        @php
                            $templateEntryId = (string) ($templateEntry['id'] ?? '');
                            $templatePreview = (string) ($templateEntry['preview_url'] ?? asset('assets/wayvio/images/themes/no-preview.png'));
                            $isActiveTemplate = $templateEntryId === $currentTemplateId;
                            $templateEntryCapabilities = (array) ($templateEntry['capabilities'] ?? []);
                            $templateTextAccentCapable = !empty($templateEntryCapabilities['text_accent']);
                        @endphp
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <button
                                type="button"
                                class="template-choice card border-0 shadow-sm {{ $isActiveTemplate ? 'template-choice--active' : '' }}"
                                data-template-id="{{ $templateEntryId }}"
                                data-text-accent-capable="{{ $templateTextAccentCapable ? '1' : '0' }}"
                                aria-pressed="{{ $isActiveTemplate ? 'true' : 'false' }}"
                                onclick="if (window.__wayvioSelectTemplateFromModal) { window.__wayvioSelectTemplateFromModal(this.getAttribute('data-template-id')); } return false;"
                            >
                                @if(file_exists(base_path('themes/' . ($templateEntry['theme'] ?? '') . '/preview.png')))
                                <img draggable="false" class="card-img-top img-fluid template-choice__image" src="{{ $templatePreview }}" alt="{{ $templateEntry['label'] ?? $templateEntryId }}">
                                @endif
                                <span class="template-choice__label">{{ $templateEntry['label'] ?? $templateEntryId }}</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="modal-footer">
                <span class="me-auto small text-muted">{{ __('Select a template, then choose variants below on the page.') }}</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__('messages.Close')}}</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function ownKeys(obj) {
        var keys = [];
        if (!obj || typeof obj !== 'object') {
            return keys;
        }
        for (var key in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, key)) {
                keys.push(key);
            }
        }
        return keys;
    }

    function containsVariantId(variants, variantId) {
        if (!Array.isArray(variants)) {
            return false;
        }
        for (var i = 0; i < variants.length; i++) {
            if ((variants[i] && variants[i].id) === variantId) {
                return true;
            }
        }
        return false;
    }

    function findVariantById(variants, variantId) {
        if (!Array.isArray(variants)) {
            return null;
        }
        for (var i = 0; i < variants.length; i++) {
            if ((variants[i] && variants[i].id) === variantId) {
                return variants[i];
            }
        }
        return null;
    }

    function hideModal(modalElement) {
        if (!modalElement) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            var modalApi = window.bootstrap.Modal;
            var modalInstance = null;

            if (typeof modalApi.getOrCreateInstance === 'function') {
                modalInstance = modalApi.getOrCreateInstance(modalElement);
            } else if (typeof modalApi.getInstance === 'function') {
                modalInstance = modalApi.getInstance(modalElement);
                if (!modalInstance && typeof modalApi === 'function') {
                    modalInstance = new modalApi(modalElement);
                }
            } else if (typeof modalApi === 'function') {
                modalInstance = new modalApi(modalElement);
            }

            if (modalInstance && typeof modalInstance.hide === 'function') {
                modalInstance.hide();
                return;
            }
        }

        if (window.jQuery && typeof window.jQuery(modalElement).modal === 'function') {
            window.jQuery(modalElement).modal('hide');
        }
    }

    function bootTemplatePicker() {
        var catalog = @json($templateCatalogJson);
        var capabilityGroups = @json($capabilityGroups);
        var choices = document.querySelectorAll('.template-choice[data-template-id]');
        var modalElement = document.getElementById('exampleModal');
        var templateIdInput = document.getElementById('template-id-input');
        var variantIdInput = document.getElementById('template-variant-input');
        var templateNameEl = document.getElementById('selected-template-name');
        var templateDescriptionEl = document.getElementById('selected-template-description');
        var selectedVariantLabelEl = document.getElementById('selected-template-variant-label');
        var capabilityListEl = document.getElementById('selected-template-capability-list');
        var variantListEl = document.getElementById('selected-template-variant-list');
        var catalogKeys = ownKeys(catalog);

        if (!modalElement) {
            return;
        }
        if (modalElement.getAttribute('data-template-picker-bound') === '1') {
            return;
        }
        modalElement.setAttribute('data-template-picker-bound', '1');

        if (!templateIdInput || !variantIdInput || catalogKeys.length === 0) {
            return;
        }

        function capabilityBadge(label, enabled) {
            var span = document.createElement('span');
            span.className = 'badge ' + (enabled ? 'text-bg-success' : 'text-bg-secondary');
            span.textContent = label + ': ' + (enabled ? 'Yes' : 'No');
            return span;
        }

        function updateChoiceState(activeTemplateId) {
            for (var i = 0; i < choices.length; i++) {
                var choice = choices[i];
                var isActive = choice.getAttribute('data-template-id') === activeTemplateId;
                if (isActive) {
                    choice.classList.add('template-choice--active');
                } else {
                    choice.classList.remove('template-choice--active');
                }
                choice.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        }

        function renderCapabilities(template) {
            if (!capabilityListEl) {
                return;
            }

            capabilityListEl.innerHTML = '';
            var caps = (template && template.capabilities) ? template.capabilities : {};
            for (var i = 0; i < capabilityGroups.length; i++) {
                var group = capabilityGroups[i] || {};
                var keys = Array.isArray(group.keys) ? group.keys : [];
                if (!group.label || keys.length === 0) {
                    continue;
                }
                var enabled = true;
                for (var j = 0; j < keys.length; j++) {
                    if (!caps[keys[j]]) {
                        enabled = false;
                        break;
                    }
                }
                capabilityListEl.appendChild(capabilityBadge(group.label, enabled));
            }
        }

        function resolveVariantId(template, requestedVariantId) {
            var variants = (template && Array.isArray(template.variants)) ? template.variants : [];
            if (variants.length === 0) {
                return 'default';
            }

            if (containsVariantId(variants, requestedVariantId)) {
                return requestedVariantId;
            }

            var currentInputValue = variantIdInput && variantIdInput.value ? variantIdInput.value : null;
            if (containsVariantId(variants, currentInputValue)) {
                return currentInputValue;
            }

            if (template && containsVariantId(variants, template.default_variant_id)) {
                return template.default_variant_id;
            }

            return variants[0].id;
        }

        function resolveTemplatePreviewColor(template, variantId) {
            var variants = (template && Array.isArray(template.variants)) ? template.variants : [];
            var variantMeta = findVariantById(variants, variantId);
            if (variantMeta && Array.isArray(variantMeta.swatches) && variantMeta.swatches.length > 0 && variantMeta.swatches[0]) {
                return variantMeta.swatches[0];
            }
            if (variants.length > 0 && Array.isArray(variants[0].swatches) && variants[0].swatches.length > 0 && variants[0].swatches[0]) {
                return variants[0].swatches[0];
            }
            return '#111827';
        }

        function syncLivePreview(template, variantId, userTriggered) {
            if (!userTriggered) {
                return;
            }

            var previewUrl = (template && template.preview_url) ? template.preview_url : '';
            var previewColor = resolveTemplatePreviewColor(template, variantId);

            if (typeof window.__wayvioPreviewTemplateSelection === 'function') {
                window.__wayvioPreviewTemplateSelection({
                    previewUrl: previewUrl,
                    defaultColor: previewColor,
                });
            }
        }

        function activateTemplate(templateId, requestedVariantId, userTriggered) {
            var fallbackTemplateId = catalogKeys[0];
            var template = catalog[templateId] || catalog[fallbackTemplateId];
            if (!template) {
                return;
            }

            templateIdInput.value = template.id;

            var resolvedVariantId = resolveVariantId(template, requestedVariantId || null);
            variantIdInput.value = resolvedVariantId;

            if (templateNameEl) {
                templateNameEl.textContent = template.label || template.id;
            }
            if (templateDescriptionEl) {
                templateDescriptionEl.textContent = template.description || '';
            }

            renderCapabilities(template);
            renderVariants(template, resolvedVariantId);
            updateChoiceState(template.id);
            syncLivePreview(template, resolvedVariantId, !!userTriggered);
            if (typeof CustomEvent === 'function') {
                document.dispatchEvent(new CustomEvent('wayvio:template-changed', {
                    detail: {
                        templateId: template.id,
                        supportsTextAccent: !!((template.capabilities || {}).text_accent),
                    },
                }));
            }
        }

        function variantChip(template, variant, selectedVariantId) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm ' + (variant.id === selectedVariantId ? 'btn-primary' : 'btn-outline-secondary') + ' template-variant-chip';
            button.setAttribute('data-template-id', template.id);
            button.setAttribute('data-variant-id', variant.id);
            button.textContent = variant.label || variant.id;

            if (Array.isArray(variant.swatches) && variant.swatches.length > 0) {
                var swatchWrap = document.createElement('span');
                swatchWrap.className = 'template-variant-swatches';
                for (var i = 0; i < variant.swatches.length && i < 4; i++) {
                    var dot = document.createElement('span');
                    dot.className = 'template-variant-swatch';
                    dot.style.backgroundColor = variant.swatches[i];
                    swatchWrap.appendChild(dot);
                }
                button.appendChild(swatchWrap);
            }

            button.addEventListener('click', function () {
                activateTemplate(template.id, variant.id, true);
            });

            return button;
        }

        function renderVariants(template, selectedVariantId) {
            if (!variantListEl) {
                return;
            }
            variantListEl.innerHTML = '';

            var variants = (template && Array.isArray(template.variants)) ? template.variants : [];
            if (variants.length === 0) {
                var muted = document.createElement('span');
                muted.className = 'small text-muted';
                muted.textContent = 'No variants available.';
                variantListEl.appendChild(muted);
                return;
            }

            var chosenVariantId = resolveVariantId(template, selectedVariantId || null);
            variantIdInput.value = chosenVariantId;

            for (var i = 0; i < variants.length; i++) {
                variantListEl.appendChild(variantChip(template, variants[i], chosenVariantId));
            }

            if (selectedVariantLabelEl) {
                var variantMeta = findVariantById(variants, chosenVariantId);
                selectedVariantLabelEl.textContent = (variantMeta && variantMeta.label) ? variantMeta.label : (chosenVariantId || 'Default');
            }
        }

        function onTemplateChoiceSelected(choiceTarget) {
            if (choiceTarget && choiceTarget.hasAttribute('data-template-id')) {
                var templateId = choiceTarget.getAttribute('data-template-id') || '';
                activateTemplate(templateId, null, true);
                hideModal(modalElement);
            }
        }

        window.__wayvioSelectTemplateFromModal = function (templateId) {
            activateTemplate(templateId || '', null, true);
            hideModal(modalElement);
        };

        for (var choiceIndex = 0; choiceIndex < choices.length; choiceIndex++) {
            (function (choiceEl) {
                choiceEl.addEventListener('click', function () {
                    onTemplateChoiceSelected(choiceEl);
                });
                choiceEl.addEventListener('touchend', function () {
                    onTemplateChoiceSelected(choiceEl);
                });
            })(choices[choiceIndex]);
        }

        var initialTemplateId = (templateIdInput.value && catalog[templateIdInput.value]) ? templateIdInput.value : catalogKeys[0];
        if (initialTemplateId) {
            activateTemplate(initialTemplateId, variantIdInput.value || null, false);
        }

        window.__wayvioActivateTemplate = activateTemplate;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTemplatePicker);
    } else {
        bootTemplatePicker();
    }
})();
</script>
@endpush

@endsection
