@php
    $allowedBadges = ['vegan', 'organic', 'spicy', 'new'];
    $legacyBadgeMap = [
        'vegan' => 'vegan',
        'bio' => 'organic',
        'organic' => 'organic',
        'scharf' => 'spicy',
        'spicy' => 'spicy',
        'neu' => 'new',
        'new' => 'new',
    ];
    $profileUserId = $userinfo->id ?? ($link->user_id ?? null);
    $style = normalizeContentBlockStyle($link->gastro_style ?? 'clean', 'clean');

    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($profileUserId);

    $textColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--textColor, #FFFFFF)';
    $accentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
    $textIsDark = $applyUserTextColors ? ($textColorSettings['is_dark'] ?? false) : false;

    $mutedTextColor = $applyUserTextColors
        ? hexToRgba((string) $textColor, 0.72)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 72%, transparent)';
    $leaderColor = $applyUserTextColors
        ? hexToRgba((string) $accentColor, $textIsDark ? 0.58 : 0.52)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 54%, transparent)';
    $badgeBorderColor = $applyUserTextColors
        ? hexToRgba((string) $accentColor, $textIsDark ? 0.42 : 0.36)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 36%, transparent)';
    $badgeBgColor = $applyUserTextColors
        ? hexToRgba((string) $accentColor, $textIsDark ? 0.12 : 0.16)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 14%, transparent)';

    $rawItems = is_array($link->gastro_items ?? null) ? $link->gastro_items : [];
    $items = [];

    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim(strip_tags((string) ($item['title'] ?? '')));
        $description = trim(strip_tags((string) ($item['description'] ?? '')));
        $priceRaw = trim((string) ($item['price'] ?? ''));
        $priceValue = null;

        if ($priceRaw !== '') {
            $normalizedPrice = str_replace(',', '.', $priceRaw);
            if (is_numeric($normalizedPrice) && (float) $normalizedPrice >= 0) {
                $priceValue = number_format((float) $normalizedPrice, 2, ',', '.');
            } else {
                $priceValue = trim(strip_tags($priceRaw));
            }
        }

        $badges = [];
        $incomingBadges = is_array($item['badges'] ?? null) ? $item['badges'] : [];
        foreach ($incomingBadges as $badge) {
            $badge = strtolower(trim((string) $badge));
            $normalizedBadge = $legacyBadgeMap[$badge] ?? null;
            if ($normalizedBadge !== null && in_array($normalizedBadge, $allowedBadges, true) && !in_array($normalizedBadge, $badges, true)) {
                $badges[] = $normalizedBadge;
            }
        }

        if ($title === '') {
            continue;
        }

        $items[] = [
            'title' => $title,
            'description' => $description,
            'price' => $priceValue,
            'badges' => $badges,
        ];
    }

    $hasPrices = false;
    foreach ($items as $item) {
        if ($item['price'] !== null) {
            $hasPrices = true;
            break;
        }
    }
@endphp

@if(count($items) > 0)
    @include('wayvio.modules.block-style-presets')

    @once
    <style>
        .ls-gastro-block {
            width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
            box-sizing: border-box;
            margin: 0 auto;
            overflow: visible;
            padding: 0;
        }

        .ls-gastro-block.block-preset-clean {
            background: transparent;
            border: 0 solid transparent;
            box-shadow: none;
            border-radius: 0;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-gastro-block.block-preset-glass {
            background: var(--ls-liquid-glass-background);
            border: 1px solid var(--ls-liquid-glass-border);
            box-shadow: var(--ls-liquid-glass-shadow);
            border-radius: 16px;
            -webkit-backdrop-filter: var(--ls-liquid-glass-filter);
            backdrop-filter: var(--ls-liquid-glass-filter);
        }

        .ls-gastro-block.block-preset-bold {
            background: var(--ls-block-bold-bg, rgba(255, 255, 255, 0.95));
            border: 0 solid transparent;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.11);
            border-radius: 16px;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-gastro-block:not(.block-preset-clean) {
            padding: 10px 12px;
        }

        .ls-gastro-list {
            list-style: none;
            margin: 0;
            padding: 0;
            overflow: visible;
        }

        .ls-gastro-item {
            padding: 14px 0;
            border-bottom: 0;
            text-align: left;
        }

        .ls-gastro-item + .ls-gastro-item {
            margin-top: 6px;
        }

        .ls-gastro-head {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            min-width: 0;
            width: 100%;
        }

        .ls-gastro-name-badges {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 0;
        }

        .ls-gastro-title {
            margin: 0;
            color: var(--ls-gastro-text-color);
            font-size: var(--ls-gastro-main-font-size);
            font-weight: 700;
            line-height: 1.2;
            word-break: break-word;
        }

        .ls-gastro-leader {
            flex: 1;
            min-width: 18px;
            border-bottom: 2px dotted var(--ls-gastro-leader-color);
            transform: translateY(-0.2em);
        }

        .ls-gastro-price {
            margin: 0;
            color: var(--ls-gastro-text-color);
            font-size: var(--ls-gastro-main-font-size);
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }

        .ls-gastro-price--empty {
            opacity: 0.45;
            font-weight: 600;
        }

        .ls-gastro-description {
            margin: 6px 0 0;
            color: var(--ls-gastro-muted-text-color);
            font-size: var(--ls-type-small-size, 0.85rem);
            line-height: var(--ls-type-small-line, 1.45);
            word-break: break-word;
            text-align: left;
        }

        .ls-gastro-badge {
            font-size: var(--ls-type-meta-size, 0.72rem);
            line-height: var(--ls-type-meta-line, 1.25);
            padding: 2px 6px;
            border-radius: 999px;
            color: var(--ls-gastro-accent-color);
            background: var(--ls-gastro-badge-bg);
            border: 1px solid var(--ls-gastro-badge-border);
            letter-spacing: 0;
            white-space: nowrap;
            opacity: 0.95;
        }

        @media (max-width: 768px) {
            .ls-gastro-block {
                width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            }

            .ls-gastro-item {
                padding: 12px 0;
                text-align: left;
            }

            .ls-gastro-title {
                font-size: var(--ls-gastro-main-font-size);
            }
        }
    </style>
    @endonce

    <div style="--delay: {{ $initial }}s" class="button-entrance ls-block-entrance">
        <section
            class="ls-gastro-block fadein block-preset-{{ $style }} block-preset-surface"
            data-ls-adaptive-bold
            style="
                --ls-gastro-text-color: {{ $textColor }};
                --ls-gastro-accent-color: {{ $accentColor }};
                --ls-gastro-muted-text-color: {{ $mutedTextColor }};
                --ls-gastro-leader-color: {{ $leaderColor }};
                --ls-gastro-badge-border: {{ $badgeBorderColor }};
                --ls-gastro-badge-bg: {{ $badgeBgColor }};
                --ls-gastro-main-font-size: var(--ls-type-section-size, clamp(1rem, 0.95rem + 0.2vw, 1.06rem));
                --ls-block-text-color: {{ $textColor }};
                --ls-block-accent-color: {{ $accentColor }};
                --ls-block-bold-bg: rgba(255, 255, 255, 0.95);
            "
            aria-label="{{ __('messages.block.title.gastro_service') }}"
        >
            <ul class="ls-gastro-list">
                @foreach($items as $item)
                    <li class="ls-gastro-item">
                        <div class="ls-gastro-head">
                            <div class="ls-gastro-name-badges">
                                <h3 class="ls-gastro-title">{{ $item['title'] }}</h3>
                                @foreach($item['badges'] as $badge)
                                    <span class="ls-gastro-badge">{{ __('messages.gastro_service.badge.' . $badge) }}</span>
                                @endforeach
                            </div>

                            @if($hasPrices)
                                <span class="ls-gastro-leader" aria-hidden="true"></span>
                                @if($item['price'] !== null)
                                    <span class="ls-gastro-price">{{ $item['price'] }}</span>
                                @else
                                    <span class="ls-gastro-price ls-gastro-price--empty">-</span>
                                @endif
                            @endif
                        </div>

                        @if($item['description'] !== '')
                            <p class="ls-gastro-description">{{ $item['description'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endif
