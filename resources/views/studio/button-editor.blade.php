@if((bool) config('app.enable_button_editor', true))
@extends('layouts.sidebar')

@include('components.favicon')
@include('components.favicon-extension')

@push('sidebar-stylesheets')
<link rel="stylesheet" href="{{ asset('assets/wayvio/css/brands.css') }}">
<link rel="stylesheet" href="{{ asset('assets/wayvio/css/animations.css') }}">
<link rel="stylesheet" href="{{ asset('assets/wayvio/css/link-interactions.css') }}">
@endpush

@section('content')
@php
    $initialCss = (!is_string($custom_css) || strtolower($custom_css) === 'null') ? '' : $custom_css;
    $defaultBackground = '#079aa2';
    $previewTitle = __('messages.be.preview_title');
    $textColorHint = __('messages.be.text_color_hint');
    $previewIconValue = is_string($custom_icon ?? null) ? trim((string) $custom_icon) : '';
    $legacyFaMap = [
        'fa-newspaper-o' => 'fa-newspaper',
        'fa-file-text-o' => 'fa-file-lines',
        'fa-lightbulb-o' => 'fa-lightbulb',
    ];
    $normalizedPreviewIcon = strtolower($previewIconValue);
    if (isset($legacyFaMap[$normalizedPreviewIcon])) {
        $previewIconValue = $legacyFaMap[$normalizedPreviewIcon];
    }
    $showPreviewIcon = $previewIconValue !== 'ls-hidden-icon';
    $previewUsesFaIcon = preg_match('/^fa-[a-z0-9-]+$/i', $previewIconValue) === 1;
    $previewUsesBiIcon = preg_match('/^bi-[a-z0-9-]+$/i', $previewIconValue) === 1;
    if ((int) $buttonId === 2 && $previewIconValue === 'fa-external-link') {
        $previewIconValue = '';
        $previewUsesFaIcon = false;
        $previewUsesBiIcon = false;
    }
@endphp
<div class="conatiner-fluid content-inner mt-n5 py-0 editor-page-shell ls-consistent-spacing">
        <div class="card rounded">
            <div class="card-body">
                <h3 class="mb-1 d-flex align-items-center gap-2"><i class="bi bi-pen"></i> {{ __('messages.Button Editor') }}</h3>
                <p class="text-muted mb-0">{{ __('messages.Custom Button') }} - {{ __('messages.be.global_style_note') }}</p>
                <div class="alert {{ !empty($templateAllowsCustomButtons) ? 'alert-info' : 'alert-warning' }} mt-3 mb-0">
                    @if(!empty($templateAllowsCustomButtons))
                        {{ __('messages.be.template_button_defaults_hint', ['template' => $activeTemplateLabel ?? __('messages.Template')]) }}
                    @else
                        {{ __('messages.be.template_button_locked_hint', ['template' => $activeTemplateLabel ?? __('messages.Template')]) }}
                    @endif
                </div>
            </div>
        </div>

    <form action="{{ route('editCSS', (is_numeric($id ?? null) && (int) $id > 0) ? ['id' => (int) $id] : []) }}" method="post" id="button-style-form">
        @csrf
        <input type="hidden" id="custom-css-input" name="custom_css" value="{{ $initialCss }}">

        <div class="card rounded mt-4">
            <div class="card-body">
                <p class="text-uppercase text-muted small mb-2">{{ __('messages.Preview') }}</p>
                <div class="button-preview-stage rounded border bg-light p-4 d-flex align-items-center justify-content-center">
                    <div class="preview-phone-frame">
                        @if($buttonId == 1)
                            <a id="button-preview" class="button button-custom button-click button-hover icon-hover ls-link-interactive preview-button surface-crisp" style="{{ $initialCss }}">
                                @if($showPreviewIcon)
                                    @if($previewUsesBiIcon)
                                        <i id="preview-icon" class="icon-btn hvr-icon bi {{ $previewIconValue }}"></i>
                                    @else
                                        <i id="preview-icon" class="icon-btn hvr-icon fa {{ $previewUsesFaIcon ? $previewIconValue : 'fa-external-link' }}"></i>
                                    @endif
                                @endif
                                <span id="preview-text">{{ $previewTitle }}</span>
                            </a>
                        @else
                            <a id="button-preview" class="button button-custom_website button-click button-hover icon-hover ls-link-interactive preview-button surface-crisp" style="{{ $initialCss }}">
                                @if($showPreviewIcon)
                                    @if($previewUsesBiIcon)
                                        <i id="preview-icon-fa" class="icon-btn hvr-icon bi {{ $previewIconValue }}"></i>
                                    @elseif($previewUsesFaIcon)
                                        <i id="preview-icon-fa" class="icon-btn hvr-icon fa {{ $previewIconValue }}"></i>
                                    @else
                                        <i id="preview-icon-fa" class="icon-btn hvr-icon fa fa-link"></i>
                                    @endif
                                @endif
                                <span id="preview-text">{{ $previewTitle }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="editor-layout mt-4">
            <div class="editor-card">
                <p class="section-kicker mb-3">{{ __('messages.be.text_color') }}</p>
                <small class="hint-text d-block mb-2">{{ $textColorHint }}</small>
                <label class="section-label">{{ __('messages.be.button_text_color_mode') }}</label>
                <div class="mode-chip-group mt-2" role="group" aria-label="{{ __('messages.be.button_text_color_mode') }}">
                    <input class="mode-chip-input" type="radio" name="text-color-mode" id="text-color-mode-white" value="white">
                    <label class="mode-chip" for="text-color-mode-white">{{ __('messages.be.white') }}</label>

                    <input class="mode-chip-input" type="radio" name="text-color-mode" id="text-color-mode-black" value="black">
                    <label class="mode-chip" for="text-color-mode-black">{{ __('messages.be.black') }}</label>

                    <input class="mode-chip-input" type="radio" name="text-color-mode" id="text-color-mode-custom" value="custom">
                    <label class="mode-chip" for="text-color-mode-custom">{{ __('messages.be.custom') }}</label>
                </div>

                <label class="section-label mt-4" for="text-color-input">{{ __('messages.page.custom_color') }}</label>
                <div class="color-inline text-color-custom-row mt-2" id="text-color-custom-row">
                    <input type="color" id="text-color-input" class="form-control form-control-color color-swatch color-input-compact" value="#000000">
                    <span class="hex-value" id="text-color-value-label">#000000</span>
                </div>

            </div>

            <div class="editor-card">
                <p class="section-kicker mb-3">{{ __('messages.background') }} + {{ __('messages.Transparent') }}</p>

                <label class="section-label" for="background-color-input">{{ ucfirst(__('messages.background')) }}</label>
                <div class="color-inline mt-2">
                    <input type="color" id="background-color-input" class="form-control form-control-color color-swatch" value="{{ $defaultBackground }}" title="{{ ucfirst(__('messages.background')) }}">
                    <span class="hex-value" id="background-color-value">#079AA2</span>
                </div>

                <div class="form-check form-switch toggle-inline mt-3">
                    <input class="form-check-input" type="checkbox" id="background-transparent">
                    <label class="form-check-label" for="background-transparent">{{ __('messages.Transparent') }}</label>
                </div>

                <label class="section-label mt-4" for="overlay-color-input">{{ __('messages.Overlay color') }}</label>
                <div class="color-inline mt-2">
                    <input type="color" id="overlay-color-input" class="form-control form-control-color color-swatch" value="#000000">
                    <span class="hex-value" id="overlay-color-value">#000000</span>
                </div>

                <label class="section-label mt-4 d-flex justify-content-between align-items-center" for="overlay-opacity-input">
                    <span>{{ __('messages.Overlay opacity') }}</span>
                    <span class="slider-value" id="overlay-opacity-value">0%</span>
                </label>
                <input type="range" id="overlay-opacity-input" class="form-range editor-slider" min="0" max="100" step="1" value="0">
            </div>

            <div class="editor-card">
                <div class="d-flex justify-content-between align-items-start mb-2 gap-3">
                    <p class="section-kicker mb-0">{{ __('messages.be.gradient') }}</p>
                    <div class="form-check form-switch toggle-inline m-0">
                        <input class="form-check-input" type="checkbox" id="gradient-toggle">
                        <label class="form-check-label" for="gradient-toggle">{{ __('messages.Enable') ?? 'Enable' }}</label>
                    </div>
                </div>

                <div id="gradient-bar" class="gradient-preview mb-3"></div>

                <div class="gradient-stop-block">
                    <label class="section-label" for="gradient-color-one">{{ __('messages.be.color_1') }}</label>
                    <div class="color-inline mt-2">
                        <input type="color" id="gradient-color-one" class="form-control form-control-color gradient-color-input color-swatch" value="{{ $defaultBackground }}">
                        <span class="hex-value" id="gradient-color-one-value">#2F3338</span>
                    </div>
                    <label class="section-label mt-3 d-flex justify-content-between align-items-center" for="gradient-stop-one">
                        <span>Position</span>
                        <span class="slider-value" id="gradient-stop-one-label">0%</span>
                    </label>
                    <input type="range" class="form-range gradient-stop gradient-range editor-slider" id="gradient-stop-one" min="0" max="100" step="1" value="0">
                </div>

                <div class="gradient-stop-block mt-4">
                    <label class="section-label" for="gradient-color-two">{{ __('messages.be.color_2') }}</label>
                    <div class="color-inline mt-2">
                        <input type="color" id="gradient-color-two" class="form-control form-control-color gradient-color-input color-swatch" value="#302b63">
                        <span class="hex-value" id="gradient-color-two-value">#302B63</span>
                    </div>
                    <label class="section-label mt-3 d-flex justify-content-between align-items-center" for="gradient-stop-two">
                        <span>Position</span>
                        <span class="slider-value" id="gradient-stop-two-label">100%</span>
                    </label>
                    <input type="range" class="form-range gradient-stop gradient-range editor-slider" id="gradient-stop-two" min="0" max="100" step="1" value="100">
                </div>

                <div class="form-check form-switch toggle-inline mt-4">
                    <input class="form-check-input" type="checkbox" id="toggle-third-stop" data-enabled="false">
                    <label class="form-check-label" for="toggle-third-stop">{{ __('messages.be.add_color_3') }}</label>
                </div>

                <div class="gradient-stop-block mt-3">
                    <label class="section-label" for="gradient-color-three">{{ __('messages.be.color_3') }}</label>
                    <div class="color-inline mt-2">
                        <input type="color" id="gradient-color-three" class="form-control form-control-color gradient-color-input color-swatch" value="#ffffff" disabled>
                        <span class="hex-value" id="gradient-color-three-value">#FFFFFF</span>
                    </div>
                    <label class="section-label mt-3 d-flex justify-content-between align-items-center" for="gradient-stop-three">
                        <span>Position</span>
                        <span class="slider-value" id="gradient-stop-three-label">50%</span>
                    </label>
                    <input type="range" class="form-range gradient-stop gradient-range editor-slider" id="gradient-stop-three" min="0" max="100" step="1" value="50" disabled>
                </div>

                <label class="section-label mt-4">{{ __('messages.Direction') }}</label>
                <div class="gradient-direction-row mt-2">
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to right" aria-label="to right"><i class="bi bi-arrow-right"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to left" aria-label="to left"><i class="bi bi-arrow-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to bottom" aria-label="to bottom"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to top" aria-label="to top"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to bottom right" aria-label="to bottom right"><i class="bi bi-arrow-down-right"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to bottom left" aria-label="to bottom left"><i class="bi bi-arrow-down-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to top right" aria-label="to top right"><i class="bi bi-arrow-up-right"></i></button>
                    <button type="button" class="btn btn-outline-secondary gradient-direction" data-direction="to top left" aria-label="to top left"><i class="bi bi-arrow-up-left"></i></button>
                </div>
            </div>
        </div>

        <div class="editor-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="section-kicker mb-0">{{ __('messages.be.border') }}</p>
                <div class="form-check form-switch toggle-inline m-0">
                    <input class="form-check-input" type="checkbox" id="border-toggle">
                    <label class="form-check-label" for="border-toggle">{{ __('messages.be.enable_border') }}</label>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <label class="section-label d-flex justify-content-between align-items-center" for="border-width">
                        <span>{{ __('messages.be.thickness') }}</span>
                        <span id="border-width-value" class="slider-value">0px</span>
                    </label>
                    <input type="range" class="form-range editor-slider" id="border-width" min="0" max="10" step="1" value="0">
                </div>
                <div class="col-lg-6">
                    <label class="section-label d-flex justify-content-between align-items-center" for="border-radius">
                        <span>{{ __('messages.be.radius') }}</span>
                        <span id="border-radius-value" class="slider-value">8px</span>
                    </label>
                    <input type="range" class="form-range editor-slider" id="border-radius" min="0" max="50" step="1" value="8">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-4">
                <button type="button" class="btn btn-soft-danger action-btn" id="reset-style">{{ __('messages.Reset to default') }}</button>
                <button type="submit" class="btn btn-primary action-btn" id="save-button">{{ __('messages.Save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('sidebar-scripts')
<script src="{{ asset('assets/external-dependencies/fontawesome.js') }}" crossorigin="anonymous"></script>
<script>
(function() {
    const preview = document.getElementById('button-preview');
    const previewIcon = document.getElementById('preview-icon');
    const previewIconFa = document.getElementById('preview-icon-fa');
    const textColorModeInputs = Array.from(document.querySelectorAll('input[name="text-color-mode"]'));
    const textColorCustomRow = document.getElementById('text-color-custom-row');
    const textColorInput = document.getElementById('text-color-input');
    const backgroundInput = document.getElementById('background-color-input');
    const transparentToggle = document.getElementById('background-transparent');
    const overlayColorInput = document.getElementById('overlay-color-input');
    const overlayOpacityInput = document.getElementById('overlay-opacity-input');
    const overlayOpacityValue = document.getElementById('overlay-opacity-value');
    const gradientToggle = document.getElementById('gradient-toggle');
    const gradientColorOne = document.getElementById('gradient-color-one');
    const gradientColorTwo = document.getElementById('gradient-color-two');
    const gradientColorThree = document.getElementById('gradient-color-three');
    const gradientStopOne = document.getElementById('gradient-stop-one');
    const gradientStopTwo = document.getElementById('gradient-stop-two');
    const gradientStopThree = document.getElementById('gradient-stop-three');
    const gradientStopLabels = [
        document.getElementById('gradient-stop-one-label'),
        document.getElementById('gradient-stop-two-label'),
        document.getElementById('gradient-stop-three-label'),
    ];
    const gradientBar = document.getElementById('gradient-bar');
    const gradientDirectionButtons = Array.from(document.querySelectorAll('.gradient-direction'));
    const toggleThirdStop = document.getElementById('toggle-third-stop');
    const borderToggle = document.getElementById('border-toggle');
    const borderWidthInput = document.getElementById('border-width');
    const borderWidthValue = document.getElementById('border-width-value');
    const borderRadiusInput = document.getElementById('border-radius');
    const borderRadiusValue = document.getElementById('border-radius-value');
    const resetButton = document.getElementById('reset-style');
    const customCssInput = document.getElementById('custom-css-input');
    const form = document.getElementById('button-style-form');
    const textColorValueLabel = document.getElementById('text-color-value-label');
    const backgroundColorValueLabel = document.getElementById('background-color-value');
    const overlayColorValueLabel = document.getElementById('overlay-color-value');
    const gradientColorOneValueLabel = document.getElementById('gradient-color-one-value');
    const gradientColorTwoValueLabel = document.getElementById('gradient-color-two-value');
    const gradientColorThreeValueLabel = document.getElementById('gradient-color-three-value');
    const initialCss = (customCssInput?.value && customCssInput.value !== 'NULL') ? customCssInput.value : '';
    const hasInitialCss = initialCss.trim() !== '';
    const defaultBackground = '{{ $defaultBackground }}';
    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
    const colorHexBindings = [];

    if (initialCss && preview) {
        preview.setAttribute('style', initialCss);
    }

    const parseCssValue = (prop, fallback = '') => {
        if (!initialCss) return fallback;
        const match = new RegExp(prop + '\\s*:\\s*([^;]+)', 'i').exec(initialCss);
        return match ? match[1].trim() : fallback;
    };

    const parseNumeric = (prop, fallback = 0) => {
        const value = parseCssValue(prop, '');
        const parsed = parseInt(value, 10);
        return Number.isNaN(parsed) ? fallback : parsed;
    };

    const normalizeHexColor = (value, fallback = '#ffffff') => {
        const parse = (input) => {
            if (typeof input !== 'string') return null;
            const trimmed = input.trim();
            const match = trimmed.match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (match) {
                let hex = match[1].toUpperCase();
                if (hex.length === 3) {
                    hex = `${hex[0]}${hex[0]}${hex[1]}${hex[1]}${hex[2]}${hex[2]}`;
                }
                return `#${hex}`;
            }

            const rgbMatch = trimmed.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*[\d.]+\s*)?\)$/i);
            if (!rgbMatch) return null;
            const [r, g, b] = rgbMatch.slice(1, 4).map((part) => Number(part));
            if ([r, g, b].some((channel) => Number.isNaN(channel) || channel < 0 || channel > 255)) {
                return null;
            }
            const toHex = (channel) => channel.toString(16).padStart(2, '0').toUpperCase();
            return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
        };

        return parse(value) || parse(fallback) || '#FFFFFF';
    };

    const normalizeManualHexInput = (value) => {
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

    const inferTextColorMode = (hexColor) => {
        const normalized = normalizeHexColor(hexColor, '#FFFFFF').toUpperCase();
        if (normalized === '#000000') return 'black';
        if (normalized === '#FFFFFF') return 'white';
        return 'custom';
    };

    const toDisplayHex = (value, fallback = '#FFFFFF') => normalizeHexColor(value, fallback).toUpperCase();

    const syncHexInputs = () => {
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

        const row = colorInput.closest('.color-inline') || colorInput.closest('.gradient-color-row') || colorInput.closest('.gradient-third-controls') || colorInput.parentElement;
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
            const normalized = normalizeHexColor(colorInput.value, '#000000').toUpperCase();
            hexInput.value = normalized;
            hexInput.disabled = !!colorInput.disabled;
            hexInput.classList.toggle('opacity-50', !!colorInput.disabled);
            hexInput.classList.remove('is-invalid');
        };

        const commitHex = () => {
            if (hexInput.disabled) {
                return;
            }
            const normalized = normalizeManualHexInput(hexInput.value);
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
        syncFromColor();
        colorInput.dataset.hexBound = '1';
        colorHexBindings.push({ sync: syncFromColor });
    };

    const initializeHexInputs = () => {
        const colorInputs = form ? Array.from(form.querySelectorAll('input[type="color"]')) : [];
        colorInputs.forEach((input) => setupHexInputForColor(input));
    };

    const initialBackground = parseCssValue('background-color', defaultBackground);
    const initialTextColor = normalizeHexColor(parseCssValue('color', '#000000'), '#000000');
    const initialTextColorMode = inferTextColorMode(initialTextColor);
    const initialBorderWidth = clamp(parseNumeric('border-width', 0), 0, 10);
    const initialRadius = clamp(parseNumeric('border-radius', 8), 0, 50);
    const initialFont = parseCssValue('font-family', 'inherit');
    const initialGradientFlag = parseCssValue('--ls-gradient-enabled', '');
    const initialGradientMatch = parseCssValue('background-image', '');
    const inferGradientEnabled = (backgroundImage) => {
        const bg = (backgroundImage || '').trim().toLowerCase();
        if (!bg || bg === 'none') return false;

        const gradientCalls = bg.match(/linear-gradient\(/g) || [];
        if (gradientCalls.length === 0) return false;

        if (gradientCalls.length === 1) {
            const overlayLike = /linear-gradient\(\s*rgba\([^)]*\)\s*,\s*rgba\([^)]*\)\s*\)/i.test(bg);
            if (overlayLike) return false;

            const hex = bg.match(/#(?:[0-9a-f]{3}|[0-9a-f]{6})/gi);
            if (hex && hex.length === 2 && hex[0].toLowerCase() === hex[1].toLowerCase()) {
                return false;
            }
        }

        return true;
    };
    const initialGradientEnabled = initialGradientFlag === '1'
        ? true
        : initialGradientFlag === '0'
            ? false
            : inferGradientEnabled(initialGradientMatch);
    const initialBaseColor = initialBackground === 'transparent' ? defaultBackground : initialBackground;

    const state = {
        textColorMode: initialTextColorMode,
        customTextColor: initialTextColor,
        background: initialBaseColor,
        transparent: initialBackground === 'transparent',
        overlayColor: '#000000',
        overlayOpacity: 0,
        gradient: {
            enabled: initialGradientEnabled,
            stops: [
                { color: initialBaseColor, position: Number(gradientStopOne?.value || 0) },
                { color: gradientColorTwo?.value || '#302b63', position: Number(gradientStopTwo?.value || 100) },
                { color: gradientColorThree?.value || '#ffffff', position: Number(gradientStopThree?.value || 50), enabled: false },
            ],
            direction: 'to right',
        },
        borderEnabled: initialBorderWidth > 0,
        borderWidth: initialBorderWidth,
        borderRadius: initialRadius,
        fontFamily: initialFont || 'inherit',
    };

    const defaults = {
        background: defaultBackground,
        transparent: false,
        overlayColor: '#000000',
        overlayOpacity: 0,
        gradient: {
            enabled: false,
            stops: [
                { color: defaultBackground, position: 0 },
                { color: '#302b63', position: 100 },
                { color: '#ffffff', position: 50, enabled: false },
            ],
            direction: 'to right',
        },
        borderEnabled: false,
        borderWidth: 0,
        borderRadius: 8,
        fontFamily: 'inherit',
        textColorMode: 'black',
        customTextColor: '#000000',
    };

    const touched = { value: hasInitialCss };
    const resolveTextColorFromState = () => {
        if (state.textColorMode === 'black') return '#000000';
        if (state.textColorMode === 'white') return '#FFFFFF';
        return normalizeHexColor(state.customTextColor, '#FFFFFF');
    };

    const syncColorValueLabels = () => {
        if (textColorValueLabel) textColorValueLabel.textContent = toDisplayHex(resolveTextColorFromState(), '#FFFFFF');
        if (backgroundColorValueLabel) backgroundColorValueLabel.textContent = toDisplayHex(state.background || defaultBackground, defaultBackground);
        if (overlayColorValueLabel) overlayColorValueLabel.textContent = toDisplayHex(state.overlayColor || '#000000', '#000000');
        if (gradientColorOneValueLabel) gradientColorOneValueLabel.textContent = toDisplayHex(state.gradient.stops[0]?.color || defaultBackground, defaultBackground);
        if (gradientColorTwoValueLabel) gradientColorTwoValueLabel.textContent = toDisplayHex(state.gradient.stops[1]?.color || '#302b63', '#302b63');
        if (gradientColorThreeValueLabel) {
            const thirdColor = state.gradient.stops[2]?.color || '#ffffff';
            gradientColorThreeValueLabel.textContent = toDisplayHex(thirdColor, '#ffffff');
            gradientColorThreeValueLabel.classList.toggle('opacity-50', !state.gradient.stops[2]?.enabled);
        }
    };

    const syncControlValues = () => {
        if (backgroundInput) {
            backgroundInput.value = state.background || defaultBackground;
            backgroundInput.disabled = !!state.transparent;
            backgroundInput.classList.toggle('opacity-50', !!state.transparent);
        }
        if (transparentToggle) transparentToggle.checked = !!state.transparent;
        if (overlayColorInput) overlayColorInput.value = state.overlayColor || '#000000';
        if (overlayOpacityInput) overlayOpacityInput.value = Math.round((state.overlayOpacity || 0) * 100);
        if (overlayOpacityValue) overlayOpacityValue.textContent = `${Math.round((state.overlayOpacity || 0) * 100)}%`;
        if (gradientToggle) gradientToggle.checked = !!state.gradient.enabled;
        if (gradientColorOne) gradientColorOne.value = state.background || defaultBackground;
        if (gradientColorTwo) gradientColorTwo.value = state.gradient.stops[1]?.color || '#302b63';
        if (gradientColorThree) gradientColorThree.value = state.gradient.stops[2]?.color || '#ffffff';
        if (gradientStopOne) gradientStopOne.value = state.gradient.stops[0]?.position || 0;
        if (gradientStopTwo) gradientStopTwo.value = state.gradient.stops[1]?.position || 100;
        if (gradientStopThree) {
            gradientStopThree.value = state.gradient.stops[2]?.position || 50;
            gradientStopThree.disabled = !state.gradient.stops[2].enabled || !state.gradient.enabled;
        }
        if (gradientColorThree) gradientColorThree.disabled = !state.gradient.stops[2].enabled || !state.gradient.enabled;
        if (gradientColorOne) gradientColorOne.disabled = false;
        if (gradientColorTwo) gradientColorTwo.disabled = !state.gradient.enabled;
        if (gradientStopOne) gradientStopOne.disabled = !state.gradient.enabled;
        if (gradientStopTwo) gradientStopTwo.disabled = !state.gradient.enabled;
        gradientStopLabels.forEach((label, idx) => {
            if (label) label.textContent = `${state.gradient.stops[idx].position}%`;
        });
        gradientDirectionButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.direction === state.gradient.direction);
            btn.disabled = !state.gradient.enabled;
        });
        if (toggleThirdStop) {
            toggleThirdStop.checked = !!state.gradient.stops[2].enabled;
            toggleThirdStop.disabled = !state.gradient.enabled;
        }
        if (borderToggle) borderToggle.checked = !!state.borderEnabled;
        if (borderWidthInput) borderWidthInput.value = state.borderWidth || 0;
        if (borderWidthValue) borderWidthValue.textContent = `${state.borderWidth || 0}px`;
        if (borderRadiusInput) borderRadiusInput.value = state.borderRadius || 0;
        if (borderRadiusValue) borderRadiusValue.textContent = `${state.borderRadius || 0}px`;
        if (textColorInput) textColorInput.value = normalizeHexColor(state.customTextColor, '#FFFFFF');
        if (textColorModeInputs.length > 0) {
            textColorModeInputs.forEach((input) => {
                input.checked = input.value === state.textColorMode;
            });
        }
        if (textColorCustomRow) {
            const isCustomMode = state.textColorMode === 'custom';
            textColorCustomRow.classList.toggle('opacity-50', !isCustomMode);
            if (textColorInput) {
                textColorInput.disabled = !isCustomMode;
            }
        }
        syncHexInputs();
        syncColorValueLabels();
    };

    const hexToRgba = (hex, opacity) => {
        const sanitized = hex.replace('#', '').trim();
        if (![3, 6].includes(sanitized.length)) return `rgba(0,0,0,${opacity})`;
        const chunk = sanitized.length === 3 ? sanitized.split('').map(c => c + c).join('') : sanitized;
        const intVals = chunk.match(/.{2}/g).map(val => parseInt(val, 16));
        return `rgba(${intVals[0]}, ${intVals[1]}, ${intVals[2]}, ${opacity})`;
    };

    const renderGradientBar = () => {
        if (!gradientBar) return;
        const gradientCss = buildGradientCss();
        gradientBar.style.height = '32px';
        gradientBar.style.background = gradientCss || (state.background || defaultBackground);
    };

    const buildGradientCss = () => {
        if (!state.gradient.enabled) return null;
        const activeStops = [
            { color: state.gradient.stops[0].color, position: state.gradient.stops[0].position },
            { color: state.gradient.stops[1].color, position: state.gradient.stops[1].position },
        ];
        if (state.gradient.stops[2].enabled) {
            activeStops.push({ color: state.gradient.stops[2].color, position: state.gradient.stops[2].position });
        }
        const usableStops = activeStops.filter(stop => stop.color);
        if (usableStops.length < 2) return null;
        usableStops.sort((a, b) => a.position - b.position);
        const gradientPieces = usableStops.map(stop => `${stop.color} ${stop.position}%`);
        return `linear-gradient(${state.gradient.direction}, ${gradientPieces.join(', ')})`;
    };

    const buildCssText = () => {
        const resolvedTextColor = resolveTextColorFromState();
        const layers = [];
        if (state.overlayOpacity > 0) {
            const overlay = hexToRgba(state.overlayColor || '#000000', state.overlayOpacity);
            layers.push(`linear-gradient(${overlay}, ${overlay})`);
        }
        const baseColor = state.transparent ? 'transparent' : (state.background || defaultBackground);
        const gradientLayer = buildGradientCss();
        if (gradientLayer) {
            layers.push(gradientLayer);
        }
        const backgroundImage = layers.length ? layers.join(', ') : 'none';

        return [
            `--ls-gradient-enabled: ${state.gradient.enabled ? '1' : '0'};`,
            `color: ${resolvedTextColor};`,
            `background-color: ${baseColor};`,
            `background-image: ${backgroundImage};`,
            `border-style: ${state.borderEnabled ? 'solid' : 'none'};`,
            `border-width: ${state.borderEnabled ? `${state.borderWidth}px` : '0'};`,
            `border-color: ${resolvedTextColor};`,
            `border-radius: ${state.borderRadius}px;`,
            state.fontFamily ? `font-family: ${state.fontFamily};` : '',
        ].filter(Boolean).join(' ');
    };

    const applyPreviewIconColor = (color) => {
        if (!preview) return;
        const resolved = color || preview.style.color || '#ffffff';
        const liveIconNodes = preview.querySelectorAll('.icon-btn, .svg-inline--fa, .svg-inline--fa path, .svg-inline--fa g');
        liveIconNodes.forEach((node) => {
            node.style.color = resolved;
            node.style.fill = resolved;
            node.style.stroke = resolved;
        });
    };

    const applyPreview = (commitCss = false) => {
        if (preview) {
            const cssText = buildCssText();
            const baseColor = state.transparent ? 'transparent' : (state.background || defaultBackground);
            const resolvedTextColor = resolveTextColorFromState();
            preview.style.color = resolvedTextColor;
            preview.style.backgroundColor = baseColor;
            const layers = cssText.match(/background-image:\s*([^;]+);/i);
            preview.style.backgroundImage = layers && layers[1] ? layers[1] : 'none';
            preview.style.borderStyle = state.borderEnabled ? 'solid' : 'none';
            preview.style.borderWidth = state.borderEnabled ? `${state.borderWidth}px` : '0';
            preview.style.borderColor = state.borderEnabled ? resolvedTextColor : 'transparent';
            preview.style.borderRadius = `${state.borderRadius}px`;
            preview.style.fontFamily = state.fontFamily || 'inherit';

            if (previewIcon) {
                previewIcon.style.color = resolvedTextColor || preview.style.color || '';
            }
            if (previewIconFa) {
                previewIconFa.style.color = resolvedTextColor || preview.style.color || '';
            }
            applyPreviewIconColor(resolvedTextColor || preview.style.color || '#ffffff');

            renderGradientBar();
            if (commitCss && customCssInput) {
                touched.value = true;
                customCssInput.value = cssText;
            }
        }
        syncControlValues();
    };

    initializeHexInputs();
    syncControlValues();
    if (hasInitialCss && preview) {
        // Preserve and show the exact persisted user style on first render.
        preview.setAttribute('style', initialCss);
        if (previewIcon) {
            previewIcon.style.color = preview.style.color || '';
        }
        if (previewIconFa) {
            previewIconFa.style.color = preview.style.color || '';
        }
        applyPreviewIconColor(preview.style.color || '#ffffff');
        renderGradientBar();
    } else {
        applyPreview();
    }

    if (backgroundInput) {
        backgroundInput.addEventListener('input', (event) => {
            state.background = event.target.value;
            state.gradient.stops[0].color = state.background;
            state.transparent = false;
            applyPreview(true);
        });
    }

    if (transparentToggle) {
        transparentToggle.addEventListener('change', (event) => {
            state.transparent = event.target.checked;
            applyPreview(true);
        });
    }

    if (overlayColorInput) {
        overlayColorInput.addEventListener('input', (event) => {
            state.overlayColor = event.target.value;
            applyPreview(true);
        });
    }

    if (overlayOpacityInput) {
        overlayOpacityInput.addEventListener('input', (event) => {
            state.overlayOpacity = clamp(Number(event.target.value || 0) / 100, 0, 1);
            applyPreview(true);
        });
    }

    if (gradientToggle) {
        gradientToggle.addEventListener('change', (event) => {
            state.gradient.enabled = event.target.checked;
            applyPreview(true);
        });
    }

    const updateGradientStop = (idx, value) => {
        state.gradient.stops[idx].position = clamp(Number(value), 0, 100);
        if (gradientStopLabels[idx]) gradientStopLabels[idx].textContent = `${state.gradient.stops[idx].position}%`;
        applyPreview(true);
    };

    [gradientStopOne, gradientStopTwo, gradientStopThree].forEach((input, idx) => {
        if (!input) return;
        input.addEventListener('input', (event) => updateGradientStop(idx, event.target.value));
    });

    const updateGradientColor = (idx, value) => {
        state.gradient.stops[idx].color = value;
        if (idx === 0) {
            state.background = value;
            state.transparent = false;
        }
        applyPreview(true);
    };

    [gradientColorOne, gradientColorTwo, gradientColorThree].forEach((input, idx) => {
        if (!input) return;
        input.addEventListener('input', (event) => updateGradientColor(idx, event.target.value));
    });

    gradientDirectionButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            state.gradient.direction = btn.dataset.direction;
            applyPreview(true);
        });
    });

    if (toggleThirdStop) {
        toggleThirdStop.addEventListener('change', (event) => {
            const enabled = event.target.checked;
            state.gradient.stops[2].enabled = enabled;
            if (gradientColorThree) gradientColorThree.disabled = !enabled || !state.gradient.enabled;
            if (gradientStopThree) gradientStopThree.disabled = !enabled || !state.gradient.enabled;
            applyPreview(true);
        });
    }

    if (borderToggle) {
        borderToggle.addEventListener('change', (event) => {
            state.borderEnabled = event.target.checked;
            applyPreview(true);
        });
    }

    if (borderWidthInput) {
        borderWidthInput.addEventListener('input', (event) => {
            state.borderWidth = clamp(parseInt(event.target.value, 10) || 0, 0, 10);
            applyPreview(true);
        });
    }

    if (borderRadiusInput) {
        borderRadiusInput.addEventListener('input', (event) => {
            state.borderRadius = clamp(parseInt(event.target.value, 10) || 0, 0, 50);
            applyPreview(true);
        });
    }

    if (textColorModeInputs.length > 0) {
        textColorModeInputs.forEach((input) => {
            input.addEventListener('change', (event) => {
                const selectedMode = event.target.value;
                if (!['white', 'black', 'custom'].includes(selectedMode)) {
                    return;
                }
                state.textColorMode = selectedMode;
                applyPreview(true);
            });
        });
    }

    if (textColorInput) {
        textColorInput.addEventListener('input', (event) => {
            state.customTextColor = normalizeHexColor(event.target.value, '#FFFFFF');
            if (state.textColorMode !== 'custom') {
                state.textColorMode = 'custom';
            }
            applyPreview(true);
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            Object.assign(state, defaults);
            touched.value = true;
            if (customCssInput) customCssInput.value = '';
            applyPreview(true);
        });
    }

    if (form) {
        form.addEventListener('submit', () => {
            if (!touched.value && customCssInput) {
                customCssInput.value = initialCss || '';
            }
        });
    }
})();
</script>
<style>
.surface-crisp {
    overflow: hidden;
    isolation: isolate;
    outline: 1px solid transparent;
}
.button-preview-stage {
    min-height: 120px;
    padding: 12px !important;
}
.preview-phone-frame {
    width: min(100%, 390px);
    margin: 0 auto;
    padding: 14px 16px;
    border-radius: 20px;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
    display: flex;
    align-items: center;
    justify-content: center;
}
.preview-button {
    position: relative;
    display: grid !important;
    align-items: center;
    width: 100%;
    max-width: 100%;
    min-width: 220px;
    min-height: 52px;
    padding: 14px 18px;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    line-height: 1.25;
}
#button-preview #preview-text {
    display: block;
    width: 100%;
    min-width: 0;
    text-align: center;
    white-space: normal;
    word-break: break-word;
    overflow-wrap: break-word;
    padding-left: 48px;
    padding-right: 48px;
}
#button-preview .icon-btn,
#button-preview .icon.hvr-icon,
#button-preview #preview-icon,
#button-preview #preview-icon-fa,
#button-preview #preview-icon-img {
    position: absolute !important;
    left: 14px;
    top: 50%;
    transform: translateY(-50%) !important;
    margin: 0 !important;
    padding: 0 !important;
    right: auto !important;
    bottom: auto !important;
    width: 26px;
    height: 26px;
}
#button-preview .icon-btn,
#button-preview #preview-icon,
#button-preview #preview-icon-fa {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1 !important;
    font-size: 24px;
}
#button-preview #preview-icon-img {
    object-fit: contain;
}
#button-preview .svg-inline--fa {
    color: inherit !important;
    fill: currentColor !important;
    stroke: currentColor !important;
}
#button-preview .svg-inline--fa path,
#button-preview .svg-inline--fa g {
    fill: currentColor !important;
    stroke: currentColor !important;
}
#button-preview.button.button-hover,
#button-preview.button.button-hover:hover,
#button-preview.button.button-hover:focus-visible,
#button-preview.button.button-hover:active {
    filter: none !important;
}
#button-preview.button.button-hover:hover,
#button-preview.button.button-hover:focus-visible,
#button-preview.button.button-hover:active {
    box-shadow: inset 0 0 0 999px var(--ls-link-hover-overlay, rgba(0, 0, 0, 0.1)) !important;
}
.view-share-actions .btn {
    width: auto;
    flex: 0 0 auto;
}
.view-share-actions .view-page-btn,
.view-share-actions .view-page-share {
    white-space: nowrap;
}
.iq-banner button.btn,
.iq-banner .hero-mobile-actions .btn {
    width: auto;
    height: auto;
    min-height: 0;
    font-size: var(--bs-btn-font-size);
    font-weight: var(--bs-btn-font-weight);
    line-height: var(--bs-btn-line-height);
    padding: var(--bs-btn-padding-y) var(--bs-btn-padding-x);
    letter-spacing: normal;
}
.iq-banner .dashboard-topbar .navbar-toggler {
    width: auto;
    height: auto;
    min-height: 0;
    padding: var(--bs-navbar-toggler-padding-y) var(--bs-navbar-toggler-padding-x);
    font-size: var(--bs-navbar-toggler-font-size);
    line-height: 1;
    font-weight: 400;
    letter-spacing: normal;
    border-radius: var(--bs-navbar-toggler-border-radius);
}
.favicon-preview .favicon-thumb {
    width: 28px;
    height: 28px;
    object-fit: contain;
    font-size: 24px;
}
.sidebar .icon {
    padding: 0;
    width: auto;
    height: auto;
}
.sidebar .icon svg,
.sidebar .sidebar-toggle .icon svg {
    width: 20px;
    height: 20px;
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

.hint-text {
    font-size: 11px;
    color: #999999;
}

.mode-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.mode-chip-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.mode-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 1px solid #d4d4d8;
    border-radius: 999px;
    background: #ffffff;
    color: #4b5563;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mode-chip-input:checked + .mode-chip {
    border-color: rgba(var(--bs-primary-rgb), 0.85);
    color: var(--bs-primary);
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.15);
}

.mode-chip:hover {
    border-color: rgba(var(--bs-primary-rgb), 0.6);
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

.hex-value {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.color-hex-input {
    width: 120px;
    min-width: 120px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    text-transform: uppercase;
    letter-spacing: 0.02em;
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

.gradient-direction-row {
    display: grid;
    grid-template-columns: repeat(8, minmax(48px, 1fr));
    gap: 8px;
}

.gradient-direction-row .gradient-direction {
    border-radius: 8px;
    border-color: #d1d5db;
    background: #ffffff;
    color: #64748b;
}

.gradient-direction-row .gradient-direction.active {
    border-color: rgba(var(--bs-primary-rgb), 0.9);
    color: var(--bs-primary);
}

.text-color-custom-row {
    transition: opacity 0.2s ease;
}

.action-btn {
    min-width: 9rem;
}

body.dark .editor-page-shell {
    background: #151b24;
}

body.dark .editor-layout {
    background: transparent;
}

body.dark .editor-page-shell > .card,
body.dark .editor-card {
    background: #1f2733;
    border-color: rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
    color: #e7edf8;
}

body.dark .button-preview-stage {
    background: #1a212d !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

body.dark .preview-phone-frame {
    background: #121924;
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.38);
}

body.dark .section-kicker,
body.dark .section-label,
body.dark .hint-text,
body.dark .text-muted {
    color: #9fb0c6 !important;
}

body.dark .hex-value,
body.dark .slider-value,
body.dark .toggle-inline .form-check-label {
    color: #dee6f4;
}

body.dark .mode-chip {
    background: #121924;
    border-color: #3a455b;
    color: #d0d9e8;
}

body.dark .toggle-inline .form-check-input {
    border-color: #5b677b;
    background-color: #30384a;
}

body.dark .gradient-direction-row .gradient-direction {
    background: #121924;
    border-color: #3a455b;
    color: #d0d9e8;
}

body.dark .form-control:not(.form-control-color),
body.dark .form-select {
    background-color: #121924;
    border-color: #3b4558;
    color: #e7edf8;
}

body.dark .editor-slider.form-range::-webkit-slider-runnable-track,
body.dark .editor-slider.form-range::-moz-range-track {
    background: #3a455b;
}

body.dark .gradient-preview {
    border-color: #3a455b;
}

@media (max-width: 992px) {
    .gradient-direction-row {
        grid-template-columns: repeat(4, minmax(52px, 1fr));
    }
}

</style>
@endpush
@endif
