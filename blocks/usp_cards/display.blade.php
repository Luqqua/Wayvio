@php
    $rawItems = is_array($link->usp_items ?? null) ? $link->usp_items : [];

    $normalizeIcon = function ($value): ?string {
        $icon = strtolower(trim((string) $value));
        if ($icon === '') {
            return null;
        }

        if (preg_match('/^(fa|fas|far|fal|fab)\s+(fa-[a-z0-9-]+)$/', $icon, $matches) === 1) {
            return $matches[1] . ' ' . $matches[2];
        }

        if (preg_match('/^bi\s+(bi-[a-z0-9-]+)$/', $icon, $matches) === 1) {
            return 'bi ' . $matches[1];
        }

        if (preg_match('/^fa-[a-z0-9-]+$/', $icon) === 1) {
            return 'fa ' . $icon;
        }

        if (preg_match('/^bi-[a-z0-9-]+$/', $icon) === 1) {
            return 'bi ' . $icon;
        }

        return null;
    };

    $uspItems = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim(strip_tags((string) ($item['title'] ?? '')));
        $description = trim(strip_tags((string) ($item['description'] ?? '')));
        if ($title === '') {
            continue;
        }

        $uspItems[] = [
            'title' => $title,
            'description' => $description,
            'icon' => $normalizeIcon($item['icon'] ?? null) ?? 'fa fa-star',
        ];

        if (count($uspItems) >= 3) {
            break;
        }
    }

    $itemCount = count($uspItems);
    $style = normalizeContentBlockStyle($link->usp_style ?? 'glass', 'glass');

    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($userinfo->id ?? null);

    $uspTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--textColor, #FFFFFF)';
    $uspAccentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
    $uspTextIsDark = $applyUserTextColors ? ($textColorSettings['is_dark'] ?? false) : false;
    $contextClass = $uspTextIsDark ? 'is-light' : 'is-dark';

    $uspHeadingColor = $uspTextColor;
    $uspBodyColor = $applyUserTextColors
        ? hexToRgba((string) $uspTextColor, $uspTextIsDark ? 0.74 : 0.82)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 82%, transparent)';

    $uspIconBoldBg = $applyUserTextColors
        ? hexToRgba((string) $uspAccentColor, 0.1)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 10%, transparent)';
    $uspIconBoldBorder = $applyUserTextColors
        ? hexToRgba((string) $uspAccentColor, 0.2)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 20%, transparent)';

    $countClass = 'count-' . max(1, min(3, $itemCount));
@endphp

@if($itemCount > 0)
    @include('wayvio.modules.block-style-presets')

    @once
    <style>
        .ls-usp-cards {
            --ls-usp-accent: var(--accentColor, var(--textColor, #f9fafb));
            --ls-usp-heading: var(--ls-usp-heading-default, var(--textColor, #f9fafb));
            --ls-usp-copy: var(--ls-usp-copy-default, color-mix(in srgb, var(--textColor, #f9fafb) 82%, transparent));
            width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
            box-sizing: border-box;
            margin: 0 auto;
            overflow: visible;
        }

        .ls-usp-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(0, 1fr);
            overflow: visible;
        }

        @media (min-width: 640px) {
            .ls-usp-grid.count-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 720px) {
            .ls-usp-grid.count-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .ls-usp-card {
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: visible;
            transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
            border-radius: 16px;
        }

        .ls-usp-card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            text-align: left;
            min-height: 0;
        }

        .ls-usp-icon-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 0.75rem;
            margin-bottom: 12px;
            border: 1px solid transparent;
            background: transparent;
        }

        .block-preset-glass .ls-usp-icon-wrap {
            background:
                linear-gradient(150deg, rgba(244, 246, 249, 0.17) 0%, rgba(237, 240, 244, 0.09) 52%, rgba(233, 237, 241, 0.054) 100%),
                rgba(230, 234, 239, 0.124);
            border-color: rgba(233, 237, 241, 0.19);
            -webkit-backdrop-filter: blur(8px) saturate(1.02);
            backdrop-filter: blur(8px) saturate(1.02);
        }

        .block-preset-bold .ls-usp-icon-wrap {
            background: var(--ls-usp-icon-bold-bg);
            border-color: var(--ls-usp-icon-bold-border);
        }

        .block-preset-clean .ls-usp-icon-wrap {
            background: transparent;
            border-color: var(--ls-usp-icon-bold-border);
        }

        .ls-usp-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            font-size: var(--ls-type-section-size, 1rem);
            line-height: 1;
            color: var(--ls-usp-accent);
        }

        .ls-usp-title {
            margin: 0 0 8px;
            color: var(--ls-usp-heading);
            font-size: var(--ls-type-section-size, 1rem);
            font-weight: var(--ls-type-section-weight, 700);
            line-height: var(--ls-type-section-line, 1.3);
            word-break: normal;
            overflow-wrap: break-word;
            hyphens: none;
        }

        .ls-usp-copy {
            margin: 0;
            color: var(--ls-usp-copy);
            font-size: var(--ls-type-small-size, 0.92rem);
            line-height: var(--ls-type-small-line, 1.45);
            word-break: normal;
            overflow-wrap: break-word;
            hyphens: none;
        }

        @media (max-width: 768px) {
            .ls-usp-cards {
                width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            }

            .ls-usp-grid {
                gap: 10px;
            }

            .ls-usp-card-body {
                padding: 12px;
            }

            .ls-usp-icon-wrap {
                width: 2rem;
                height: 2rem;
                margin-bottom: 10px;
            }

            .ls-usp-title {
                font-size: var(--ls-type-small-size, 0.92rem);
            }

            .ls-usp-copy {
                font-size: var(--ls-type-small-size, 0.82rem);
                line-height: var(--ls-type-small-line, 1.35);
            }
        }
    </style>
    @endonce

    <div style="--delay: {{ $initial }}s" class="button-entrance ls-block-entrance">
        <section
            class="ls-usp-cards fadein {{ $contextClass }} block-preset-{{ $style }}"
            data-ls-adaptive-bold
            style="
                --ls-usp-accent: {{ $uspAccentColor }};
                --ls-usp-heading-default: {{ $uspHeadingColor }};
                --ls-usp-copy-default: {{ $uspBodyColor }};
                --ls-usp-icon-bold-bg: {{ $uspIconBoldBg }};
                --ls-usp-icon-bold-border: {{ $uspIconBoldBorder }};
                --ls-block-text-color: {{ $uspTextColor }};
                --ls-block-accent-color: {{ $uspAccentColor }};
                --ls-block-bold-bg: rgba(255, 255, 255, 0.95);
            "
            aria-label="Unique selling points"
        >
            <div class="ls-usp-grid {{ $countClass }}">
                @foreach($uspItems as $item)
                    <article class="block-preset-surface ls-usp-card">
                        <div class="ls-usp-card-body">
                            <span class="ls-usp-icon-wrap" aria-hidden="true">
                                <i class="ls-usp-icon {{ $item['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <h3 class="ls-usp-title">{{ $item['title'] }}</h3>
                            @if($item['description'] !== '')
                                <p class="ls-usp-copy">{{ $item['description'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endif
