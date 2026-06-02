@php
    $currentButtonId = (int) ($button_id ?? 0);
    $showWebsiteIcon = old('GetSiteIcon') !== null
        ? old('GetSiteIcon') == '1'
        : $currentButtonId === 2;

    $rawStoredIcon = old('custom_icon', $custom_icon ?? '');
    $rawStoredIcon = is_string($rawStoredIcon) ? trim($rawStoredIcon) : '';
    $isAdultLink = old('is_adult', $is_adult ?? false) == '1';

    if ($rawStoredIcon === 'ls-hidden-icon') {
        $rawStoredIcon = '';
    }

    // Legacy links used this as default; for website buttons we now treat it as "use favicon".
    if ($rawStoredIcon === 'fa-external-link' && $showWebsiteIcon && old('custom_icon') === null) {
        $rawStoredIcon = '';
    }

    $showIcon = old('show_icon') !== null
        ? old('show_icon') == '1'
        : (!$showWebsiteIcon && (($custom_icon ?? '') !== 'ls-hidden-icon'));

    $iconPresets = [
        ['value' => '', 'label' => __('messages.link.icon.auto'), 'preview' => 'fa-magic'],
        ['value' => 'fa-external-link', 'label' => __('messages.link.icon.external_link')],
        ['value' => 'fa-link', 'label' => __('messages.link.icon.link')],
        ['value' => 'fa-globe', 'label' => __('messages.link.icon.website')],
        ['value' => 'fa-user', 'label' => __('messages.link.icon.profile')],
        ['value' => 'fa-envelope', 'label' => __('messages.link.icon.email')],
        ['value' => 'fa-phone', 'label' => __('messages.link.icon.phone')],
        ['value' => 'fa-comment', 'label' => __('messages.link.icon.chat')],
        ['value' => 'fa-play', 'label' => __('messages.link.icon.play')],
        ['value' => 'fa-music', 'label' => __('messages.link.icon.music')],
        ['value' => 'fa-camera', 'label' => __('messages.link.icon.photo')],
        ['value' => 'fa-video-camera', 'label' => __('messages.link.icon.video')],
        ['value' => 'fa-shopping-cart', 'label' => __('messages.link.icon.shop')],
        ['value' => 'fa-credit-card', 'label' => __('messages.link.icon.payment')],
        ['value' => 'fa-heart', 'label' => __('messages.link.icon.support')],
        ['value' => 'fa-star', 'label' => __('messages.link.icon.featured')],
        ['value' => 'fa-book', 'label' => __('messages.link.icon.book')],
        ['value' => 'fa-newspaper', 'label' => __('messages.link.icon.news')],
        ['value' => 'fa-calendar', 'label' => __('messages.link.icon.event')],
        ['value' => 'fa-map-marker', 'label' => __('messages.link.icon.location')],
        ['value' => 'fa-download', 'label' => __('messages.link.icon.download')],
        ['value' => 'fa-file-lines', 'label' => __('messages.link.icon.document')],
        ['value' => 'fa-rocket', 'label' => __('messages.link.icon.launch')],
        ['value' => 'fa-lightbulb', 'label' => __('messages.link.icon.ideas')],
        ['value' => 'fa-briefcase', 'label' => __('messages.link.icon.business')],
        ['value' => 'fa-graduation-cap', 'label' => __('messages.link.icon.education')],
        ['value' => 'fa-gamepad', 'label' => __('messages.link.icon.gaming')],
        ['value' => 'fa-headphones', 'label' => __('messages.link.icon.audio')],
        ['value' => 'fa-code', 'label' => __('messages.link.icon.code')],
        ['value' => 'fa-share-alt', 'label' => __('messages.link.icon.share')],
        ['value' => 'fa-info-circle', 'label' => __('messages.link.icon.info')],
        ['value' => 'fa-question-circle', 'label' => __('messages.link.icon.help')],
        ['value' => 'fa-lock', 'label' => __('messages.link.icon.secure')],
    ];

    $presetValues = [];
    foreach ($iconPresets as $preset) {
        $presetValues[] = strtolower((string) ($preset['value'] ?? ''));
    }
    $normalizedStoredIcon = strtolower($rawStoredIcon);
    $legacyPresetMap = [
        'fa-newspaper-o' => 'fa-newspaper',
        'fa-file-text-o' => 'fa-file-lines',
        'fa-lightbulb-o' => 'fa-lightbulb',
    ];
    if (isset($legacyPresetMap[$normalizedStoredIcon])) {
        $normalizedStoredIcon = $legacyPresetMap[$normalizedStoredIcon];
    }
    $selectedPresetIcon = '';
    if (!$showWebsiteIcon && $showIcon) {
        $selectedPresetIcon = in_array($normalizedStoredIcon, $presetValues, true)
            ? $normalizedStoredIcon
            : 'fa-external-link';
    }
@endphp

@once
<style>
    #custom-link-icon-editor .icon-picker-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        gap: 8px;
    }

    #custom-link-icon-editor .icon-picker-option {
        border: none;
        border-radius: 6px;
        background: transparent;
        color: #6c757d;
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: color 120ms ease, background-color 120ms ease, opacity 120ms ease;
    }

    #custom-link-icon-editor .icon-picker-option:hover {
        color: #212529;
        background-color: rgba(13, 110, 253, 0.08);
    }

    #custom-link-icon-editor .icon-picker-option i {
        font-size: 17px;
        line-height: 1;
    }

    #custom-link-icon-editor .icon-picker-option:focus-visible {
        outline: 2px solid rgba(59, 130, 246, 0.45);
        outline-offset: 2px;
    }

    #custom-link-icon-editor .icon-picker-option.is-active {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.12);
    }

    #custom-link-icon-editor .icon-picker-option.is-disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    @media (max-width: 992px) {
        #custom-link-icon-editor .icon-picker-grid {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        #custom-link-icon-editor .icon-picker-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
        }
    }

    @media (max-width: 520px) {
        #custom-link-icon-editor .icon-picker-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }
</style>
@endonce

<label for="title" class="form-label">{{ __('messages.Title') }}</label>
<input type="text" name="title" value="{{ old('title', $title) }}" class="form-control" required>

<label for="link" class="form-label">{{ __('messages.URL') }}</label>
<input type="text" name="link" value="{{ old('link', $link) }}" class="form-control" required autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly','readonly')">

<div class="custom-control custom-checkbox m-2">
    <input type="hidden" name="GetSiteIcon" value="0">
    <input
        type="checkbox"
        class="custom-control-input"
        value="1"
        name="GetSiteIcon"
        id="GetSiteIcon"
        {{ $showWebsiteIcon ? 'checked' : '' }}
    >
    <label class="custom-control-label" for="GetSiteIcon">{{ __('messages.Show website icon on button') }}</label>
</div>

<div id="custom-link-icon-editor">
    <div class="custom-control custom-checkbox m-2">
        <input type="hidden" name="show_icon" value="0">
        <input
            type="checkbox"
            class="custom-control-input"
            value="1"
            name="show_icon"
            id="show-icon"
            {{ $showIcon ? 'checked' : '' }}
        >
        <label class="custom-control-label" for="show-icon">{{ __('messages.link.editor.use_custom_icon') }}</label>
    </div>

    <div class="custom-control custom-checkbox m-2">
        <input type="hidden" name="is_adult" value="0">
        <input
            type="checkbox"
            class="custom-control-input"
            value="1"
            name="is_adult"
            id="is_adult"
            {{ $isAdultLink ? 'checked' : '' }}
        >
        <label class="custom-control-label" for="is_adult">{{ __('messages.link.editor.mark_18') }}</label>
    </div>

    <div class="border rounded p-3 mt-2" data-icon-gallery-wrap>
        <label class="form-label fw-semibold mb-2">{{ __('messages.link.editor.choose_icon') }}</label>
        <div class="icon-picker-grid" data-icon-gallery>
            @foreach($iconPresets as $preset)
                @php
                    $iconValue = strtolower((string) ($preset['value'] ?? ''));
                    $iconLabel = (string) ($preset['label'] ?? 'Icon');
                    $previewClass = strtolower((string) ($preset['preview'] ?? ($iconValue !== '' ? $iconValue : 'fa-magic')));
                @endphp
                <button
                    type="button"
                    class="icon-picker-option{{ $selectedPresetIcon === $iconValue ? ' is-active' : '' }}"
                    data-icon-option
                    data-icon-value="{{ $iconValue }}"
                    title="{{ $iconLabel }}"
                    aria-label="{{ $iconLabel }}"
                >
                    <i class="fa {{ $previewClass }}" aria-hidden="true"></i>
                </button>
            @endforeach
        </div>
    </div>

    <input type="hidden" id="custom-icon" name="custom_icon" value="{{ $selectedPresetIcon }}">
</div>

<script>
(() => {
    const initCustomLinkIconEditor = () => {
        const root = document.getElementById('custom-link-icon-editor');
        if (!root || root.dataset.bound === '1') {
            return;
        }
        root.dataset.bound = '1';

        const showIconInput = root.querySelector('#show-icon');
        const iconInput = root.querySelector('#custom-icon');
        const websiteIconToggle = document.getElementById('GetSiteIcon');
        const galleryWrap = root.querySelector('[data-icon-gallery-wrap]');
        const iconOptions = Array.from(root.querySelectorAll('[data-icon-option]'));

        const normalizeIconValue = (value) => (value || '').trim().toLowerCase();
        const legacyIconMap = {
            'fa-newspaper-o': 'fa-newspaper',
            'fa-file-text-o': 'fa-file-lines',
            'fa-lightbulb-o': 'fa-lightbulb',
        };
        const presetValues = new Set(iconOptions.map((option) => normalizeIconValue(option.dataset.iconValue)));

        const setGalleryDisabled = (disabled) => {
            iconOptions.forEach((option) => {
                option.disabled = disabled;
                option.classList.toggle('is-disabled', disabled);
            });
        };

        const setGalleryVisible = (visible) => {
            if (!galleryWrap) {
                return;
            }
            galleryWrap.style.display = visible ? '' : 'none';
        };

        const setActivePreset = (iconValue) => {
            iconOptions.forEach((option) => {
                const optionValue = normalizeIconValue(option.dataset.iconValue);
                option.classList.toggle('is-active', iconValue !== null && optionValue === iconValue);
            });
        };

        const applyState = () => {
            const websiteMode = !!websiteIconToggle?.checked;
            if (websiteMode && showIconInput?.checked) {
                showIconInput.checked = false;
            }
            const showGalleryIcon = !websiteMode && !!showIconInput?.checked;

            let iconValue = normalizeIconValue(iconInput?.value || '');
            if (legacyIconMap[iconValue]) {
                iconValue = legacyIconMap[iconValue];
                if (iconInput) {
                    iconInput.value = iconValue;
                }
            }

            if (websiteMode) {
                iconValue = '';
                if (iconInput) {
                    iconInput.value = '';
                }
            } else if (showGalleryIcon) {
                if (!presetValues.has(iconValue) || iconValue === '') {
                    iconValue = 'fa-external-link';
                    if (iconInput) {
                        iconInput.value = iconValue;
                    }
                }
            } else if (iconInput) {
                iconInput.value = '';
            }

            setGalleryDisabled(!showGalleryIcon);
            setActivePreset(showGalleryIcon ? iconValue : null);
            setGalleryVisible(showGalleryIcon);
        };

        iconOptions.forEach((option) => {
            option.addEventListener('click', () => {
                if (option.disabled || !iconInput) {
                    return;
                }

                if (websiteIconToggle) {
                    websiteIconToggle.checked = false;
                }
                if (showIconInput) {
                    showIconInput.checked = true;
                }
                iconInput.value = normalizeIconValue(option.dataset.iconValue);
                applyState();
            });
        });

        showIconInput?.addEventListener('change', () => {
            if (showIconInput.checked && websiteIconToggle) {
                websiteIconToggle.checked = false;
            }
            applyState();
        });
        websiteIconToggle?.addEventListener('change', () => {
            if (websiteIconToggle.checked && showIconInput) {
                showIconInput.checked = false;
            }
            applyState();
        });
        applyState();
    };

    initCustomLinkIconEditor();
})();
</script>
