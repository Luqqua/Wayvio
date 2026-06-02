@php
    $blockTitle = trim((string) ($link->title ?? ''));
    $blockDescription = trim((string) ($link->form_description ?? ''));
    $style = normalizeContentBlockStyle($link->form_style ?? 'bold', 'bold');
    $blockLocale = trim((string) ($userinfo->locale ?? app()->getLocale()));
    if ($blockLocale === '') {
        $blockLocale = trim((string) config('app.fallback_locale', 'en'));
    }
    if ($blockLocale === '') {
        $blockLocale = 'en';
    }

    $profileUserId = $userinfo->id ?? ($link->user_id ?? null);
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($profileUserId);

    $formTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--textColor, #FFFFFF)';
    $formAccentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
    $formTextIsDark = $applyUserTextColors ? ($textColorSettings['is_dark'] ?? false) : false;

    $formMutedColor = $applyUserTextColors
        ? hexToRgba((string) $formTextColor, $formTextIsDark ? 0.66 : 0.74)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 74%, transparent)';
    $formFieldBg = $applyUserTextColors
        ? hexToRgba((string) $formTextColor, $formTextIsDark ? 0.06 : 0.09)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 8%, transparent)';
    $formFieldBorder = $applyUserTextColors
        ? hexToRgba((string) $formTextColor, $formTextIsDark ? 0.24 : 0.28)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 26%, transparent)';
    $formFocusBorder = $applyUserTextColors
        ? hexToRgba((string) $formAccentColor, $formTextIsDark ? 0.46 : 0.52)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 52%, transparent)';
    $formFocusRing = $applyUserTextColors
        ? hexToRgba((string) $formAccentColor, 0.18)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 18%, transparent)';
    $formSubmitBg = $applyUserTextColors
        ? hexToRgba((string) $formAccentColor, $formTextIsDark ? 0.12 : 0.16)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 16%, transparent)';
    $formSubmitBorder = $applyUserTextColors
        ? hexToRgba((string) $formAccentColor, $formTextIsDark ? 0.34 : 0.42)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 42%, transparent)';
    $boldBackground = 'rgba(255, 255, 255, 0.95)';
@endphp

@include('wayvio.modules.block-style-presets')

<div style="--delay: {{ $initial ?? 1 }}s" class="button-entrance ls-block-entrance">
    <section
        class="wayvio-hub-contact-block ls-hub-contact-form fadein block-preset-{{ $style }} block-preset-surface"
        data-ls-adaptive-bold
        style="
            --ls-form-text: {{ $formTextColor }};
            --ls-form-muted: {{ $formMutedColor }};
            --ls-form-accent: {{ $formAccentColor }};
            --ls-form-field-bg: {{ $formFieldBg }};
            --ls-form-field-border: {{ $formFieldBorder }};
            --ls-form-focus-border: {{ $formFocusBorder }};
            --ls-form-focus-ring: {{ $formFocusRing }};
            --ls-form-submit-bg: {{ $formSubmitBg }};
            --ls-form-submit-border: {{ $formSubmitBorder }};
            --ls-block-text-color: {{ $formTextColor }};
            --ls-block-accent-color: {{ $formAccentColor }};
            --ls-block-bold-bg: {{ $boldBackground }};
        "
        aria-label="{{ $blockTitle !== '' ? $blockTitle : __('messages.block.title.hub_contact_form', [], $blockLocale) }}"
    >
        <x-forms.embed
            form-key="hub_contact_block"
            :hub="$userinfo"
            context="hub_block"
            :title="$blockTitle"
            :description="$blockDescription"
        />
    </section>
</div>

@once
    <style>
        .ls-hub-contact-form {
            width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
            margin: 0 auto;
            box-sizing: border-box;
            overflow: visible;
            color: var(--ls-form-text);
            text-align: left;
            padding: 0;
        }

        .ls-hub-contact-form.block-preset-clean {
            background: transparent;
            border: 0 solid transparent;
            box-shadow: none;
            border-radius: 0;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-hub-contact-form.block-preset-glass {
            background: var(--ls-liquid-glass-background);
            border: 1px solid var(--ls-liquid-glass-border);
            border-radius: 16px;
            -webkit-backdrop-filter: var(--ls-liquid-glass-filter);
            backdrop-filter: var(--ls-liquid-glass-filter);
            box-shadow: var(--ls-liquid-glass-shadow);
        }

        .ls-hub-contact-form.block-preset-bold {
            background: var(--ls-block-bold-bg, rgba(255, 255, 255, 0.95));
            border: 0 solid transparent;
            border-radius: 16px;
            box-shadow: 0 3px 18px rgba(0, 0, 0, 0.11);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-hub-contact-form:not(.block-preset-clean) {
            padding: 18px;
        }

        .ls-hub-contact-form .wayvio-form-embed {
            width: 100%;
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
            color: var(--ls-form-text);
            text-align: left;
        }

        .ls-hub-contact-form .wayvio-form-title {
            margin: 0 0 8px;
            color: var(--ls-form-text);
            font-size: var(--ls-type-section-size, 1.05rem);
            line-height: var(--ls-type-section-line, 1.3);
            font-weight: var(--ls-type-section-weight, 700);
            word-break: break-word;
        }

        .ls-hub-contact-form .wayvio-form-description {
            margin: 0 0 16px;
            color: var(--ls-form-muted);
            font-size: var(--ls-type-small-size, 0.92rem);
            line-height: var(--ls-type-small-line, 1.45);
            word-break: break-word;
        }

        .ls-hub-contact-form .wayvio-form-row {
            gap: 7px;
            margin-bottom: 12px;
        }

        .ls-hub-contact-form .wayvio-form-row label,
        .ls-hub-contact-form .wayvio-form-consent {
            color: var(--ls-form-text);
            font-size: var(--ls-type-small-size, 0.88rem);
            line-height: var(--ls-type-small-line, 1.35);
            font-weight: 650;
        }

        .ls-hub-contact-form .wayvio-form-row input,
        .ls-hub-contact-form .wayvio-form-row textarea {
            border: 1px solid var(--ls-form-field-border);
            border-radius: 12px;
            padding: 11px 12px;
            background: var(--ls-form-field-bg);
            color: var(--ls-form-text);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
            transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
        }

        .ls-hub-contact-form .wayvio-form-row textarea {
            min-height: 128px;
        }

        .ls-hub-contact-form .wayvio-form-row input::placeholder,
        .ls-hub-contact-form .wayvio-form-row textarea::placeholder {
            color: var(--ls-form-muted);
            opacity: 0.75;
        }

        .ls-hub-contact-form .wayvio-form-row input:focus,
        .ls-hub-contact-form .wayvio-form-row textarea:focus {
            outline: none;
            border-color: var(--ls-form-focus-border);
            box-shadow: 0 0 0 3px var(--ls-form-focus-ring);
        }

        .ls-hub-contact-form .wayvio-form-consent {
            color: var(--ls-form-muted);
            margin: 0 0 12px;
            font-weight: 500;
        }

        .ls-hub-contact-form .wayvio-form-consent a {
            color: var(--ls-form-accent);
            text-decoration-color: var(--ls-form-focus-border);
            text-underline-offset: 0.18em;
        }

        .ls-hub-contact-form .wayvio-form-submit {
            width: 100%;
            margin-top: 0;
            min-height: 44px;
            border: 1px solid var(--ls-form-submit-border);
            border-radius: 12px;
            padding: 11px 16px;
            background: var(--ls-form-submit-bg);
            color: var(--ls-form-accent);
            font-weight: 750;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
            transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease, background-color 180ms ease;
        }

        .ls-hub-contact-form .wayvio-form-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.11);
        }

        .ls-hub-contact-form .col-lg-12:has(.cf-turnstile),
        .ls-hub-contact-form .col-lg-12:has(.h-captcha),
        .ls-hub-contact-form .col-lg-12:has(.g-recaptcha) {
            display: flex;
            justify-content: flex-start;
            width: 100%;
            margin: 0 0 12px !important;
            padding: 0;
        }

        .ls-hub-contact-form .cf-turnstile,
        .ls-hub-contact-form .h-captcha,
        .ls-hub-contact-form .g-recaptcha {
            margin: 0;
        }

        .ls-hub-contact-form .wayvio-form-alert {
            border-radius: 12px;
            border: 1px solid var(--ls-form-field-border);
            background: var(--ls-form-field-bg);
        }

        .ls-hub-contact-form.block-preset-clean .wayvio-form-row input,
        .ls-hub-contact-form.block-preset-clean .wayvio-form-row textarea {
            background: transparent;
            border-radius: 10px;
        }

        .ls-hub-contact-form.block-preset-clean .wayvio-form-submit {
            background: transparent;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .ls-hub-contact-form {
                width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            }

            .ls-hub-contact-form:not(.block-preset-clean) {
                padding: 14px;
            }

            .ls-hub-contact-form .wayvio-form-title {
                font-size: var(--ls-type-section-size, 1rem);
            }

            .ls-hub-contact-form .wayvio-form-description,
            .ls-hub-contact-form .wayvio-form-row label,
            .ls-hub-contact-form .wayvio-form-consent {
                font-size: var(--ls-type-small-size, 0.86rem);
            }
        }
    </style>
@endonce
