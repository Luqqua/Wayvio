@php
    $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    $defaults = [
        'group_days' => true,
        'preset' => 'clean',
        'days' => [
            'monday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'tuesday' => ['open' => true, 'slots' => [['09:00', '12:00'], ['14:00', '18:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'friday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'saturday' => ['open' => true, 'slots' => [['10:00', '14:00']]],
            'sunday' => ['open' => false, 'slots' => []],
        ],
    ];

    $toBool = static function ($value, bool $default = false): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no', ''], true)) {
                return false;
            }
        }

        return $default;
    };

    $resolveLocale = static function ($value, ?string $default = null): string {
        $supported = array_values(array_filter((array) config('app.supported_locales', []), 'strlen'));
        $fallback = (string) config('app.fallback_locale', 'en');
        $defaultLocale = $default ?: app()->getLocale();
        $defaultLocale = is_string($defaultLocale) && trim($defaultLocale) !== '' ? trim($defaultLocale) : $fallback;

        $supportedMap = [];
        foreach ($supported as $locale) {
            $supportedMap[strtolower((string) $locale)] = (string) $locale;
        }

        $normalize = static function ($candidate) use ($supportedMap): ?string {
            if (!is_string($candidate) && !is_numeric($candidate)) {
                return null;
            }

            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                return null;
            }

            if (isset($supportedMap[$normalized])) {
                return $supportedMap[$normalized];
            }

            $base = strtolower((string) strtok($normalized, '-'));
            if ($base !== '' && isset($supportedMap[$base])) {
                return $supportedMap[$base];
            }

            return null;
        };

        return $normalize($value)
            ?? $normalize($defaultLocale)
            ?? $fallback;
    };

    $normalizeTime = static function ($value): ?string {
        if ($value === null) {
            return null;
        }

        $time = trim((string) $value);
        if ($time === '') {
            return null;
        }

        return preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time) === 1 ? $time : null;
    };

    $minutesFromTime = static function (string $time): int {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    };

    $normalizeSlots = static function ($rawSlots) use ($normalizeTime, $minutesFromTime): array {
        if (!is_array($rawSlots)) {
            return [];
        }

        $slots = [];
        foreach ($rawSlots as $slot) {
            if (count($slots) >= 2 || !is_array($slot)) {
                continue;
            }

            $from = $normalizeTime($slot['from'] ?? ($slot[0] ?? null));
            $to = $normalizeTime($slot['to'] ?? ($slot[1] ?? null));
            if ($from === null || $to === null) {
                continue;
            }

            if ($minutesFromTime($to) <= $minutesFromTime($from)) {
                continue;
            }

            $slots[] = [$from, $to];
        }

        return $slots;
    };

    $rawOpeningHours = is_array($link->opening_hours ?? null) ? $link->opening_hours : [];
    // Always prefer the active page locale so labels like "Closed" follow current language.
    $openingHoursLocale = $resolveLocale(app()->getLocale(), $rawOpeningHours['locale'] ?? null);

    $dayLabels = [
        'monday' => trans('messages.opening_hours.day.monday', [], $openingHoursLocale),
        'tuesday' => trans('messages.opening_hours.day.tuesday', [], $openingHoursLocale),
        'wednesday' => trans('messages.opening_hours.day.wednesday', [], $openingHoursLocale),
        'thursday' => trans('messages.opening_hours.day.thursday', [], $openingHoursLocale),
        'friday' => trans('messages.opening_hours.day.friday', [], $openingHoursLocale),
        'saturday' => trans('messages.opening_hours.day.saturday', [], $openingHoursLocale),
        'sunday' => trans('messages.opening_hours.day.sunday', [], $openingHoursLocale),
    ];

    $dayShortLabels = [
        'monday' => trans('messages.opening_hours.day_short.monday', [], $openingHoursLocale),
        'tuesday' => trans('messages.opening_hours.day_short.tuesday', [], $openingHoursLocale),
        'wednesday' => trans('messages.opening_hours.day_short.wednesday', [], $openingHoursLocale),
        'thursday' => trans('messages.opening_hours.day_short.thursday', [], $openingHoursLocale),
        'friday' => trans('messages.opening_hours.day_short.friday', [], $openingHoursLocale),
        'saturday' => trans('messages.opening_hours.day_short.saturday', [], $openingHoursLocale),
        'sunday' => trans('messages.opening_hours.day_short.sunday', [], $openingHoursLocale),
    ];

    $openingHours = [
        'group_days' => $toBool($rawOpeningHours['group_days'] ?? $defaults['group_days'], true),
        'preset' => normalizeContentBlockStyle($rawOpeningHours['preset'] ?? $defaults['preset'], 'clean'),
        'days' => [],
    ];

    $rawDays = is_array($rawOpeningHours['days'] ?? null) ? $rawOpeningHours['days'] : [];
    foreach ($dayOrder as $dayKey) {
        $defaultDay = $defaults['days'][$dayKey];
        $dayInput = is_array($rawDays[$dayKey] ?? null) ? $rawDays[$dayKey] : $defaultDay;

        $open = $toBool($dayInput['open'] ?? $defaultDay['open'], $defaultDay['open']);
        $slotsSource = array_key_exists('slots', $dayInput) ? $dayInput['slots'] : $defaultDay['slots'];
        $slots = $normalizeSlots($slotsSource);

        if (!$open) {
            $openingHours['days'][$dayKey] = ['open' => false, 'slots' => []];
            continue;
        }

        if (count($slots) === 0) {
            $openingHours['days'][$dayKey] = ['open' => false, 'slots' => []];
            continue;
        }

        $openingHours['days'][$dayKey] = ['open' => true, 'slots' => $slots];
    }

    $formatSlots = static function (array $slots): string {
        $parts = [];
        foreach ($slots as $slot) {
            if (!is_array($slot) || count($slot) < 2) {
                continue;
            }

            $parts[] = $slot[0] . ' – ' . $slot[1];
        }

        return implode(', ', $parts);
    };

    $dayDisplayText = static function (array $dayData) use ($formatSlots, $openingHoursLocale): string {
        if (!($dayData['open'] ?? false)) {
            return trans('messages.opening_hours.closed', [], $openingHoursLocale);
        }

        return $formatSlots(is_array($dayData['slots'] ?? null) ? $dayData['slots'] : []);
    };

    $daySignature = static function (array $dayData): string {
        if (!($dayData['open'] ?? false)) {
            return 'closed';
        }

        return 'slots:' . json_encode(array_values($dayData['slots'] ?? []));
    };

    $rows = [];
    if ($openingHours['group_days']) {
        $currentRow = null;

        foreach ($dayOrder as $index => $dayKey) {
            $dayData = $openingHours['days'][$dayKey];
            $signature = $daySignature($dayData);

            if ($currentRow === null || $currentRow['signature'] !== $signature) {
                if ($currentRow !== null) {
                    $rows[] = $currentRow;
                }

                $currentRow = [
                    'signature' => $signature,
                    'start_index' => $index,
                    'end_index' => $index,
                    'start_day' => $dayKey,
                    'end_day' => $dayKey,
                    'display' => $dayDisplayText($dayData),
                ];
            } else {
                $currentRow['end_index'] = $index;
                $currentRow['end_day'] = $dayKey;
            }
        }

        if ($currentRow !== null) {
            $rows[] = $currentRow;
        }
    } else {
        foreach ($dayOrder as $index => $dayKey) {
            $dayData = $openingHours['days'][$dayKey];
            $rows[] = [
                'signature' => $daySignature($dayData),
                'start_index' => $index,
                'end_index' => $index,
                'start_day' => $dayKey,
                'end_day' => $dayKey,
                'display' => $dayDisplayText($dayData),
            ];
        }
    }

    foreach ($rows as $rowIndex => $row) {
        if ($row['start_index'] === $row['end_index']) {
            $rows[$rowIndex]['label'] = $dayLabels[$row['start_day']];
            continue;
        }

        $rows[$rowIndex]['label'] = $dayShortLabels[$row['start_day']] . ' – ' . $dayShortLabels[$row['end_day']];
    }

    $profileUserId = $userinfo->id ?? ($link->user_id ?? null);
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($profileUserId);

    $textColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--textColor, #FFFFFF)';
    $accentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
    $textIsDark = $applyUserTextColors ? ($textColorSettings['is_dark'] ?? false) : false;

    $mutedTextColor = $applyUserTextColors
        ? hexToRgba((string) $textColor, $textIsDark ? 0.66 : 0.74)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 74%, transparent)';
    $dividerColor = $applyUserTextColors
        ? hexToRgba((string) $textColor, $textIsDark ? 0.24 : 0.28)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 26%, transparent)';
    $glassBackground = $applyUserTextColors
        ? hexToRgba((string) $textColor, 0.12)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 12%, transparent)';
    $glassBorder = $applyUserTextColors
        ? hexToRgba((string) $textColor, 0.20)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 20%, transparent)';
    $boldBackground = 'rgba(255, 255, 255, 0.95)';
@endphp

@if(count($rows) > 0)
    @include('wayvio.modules.block-style-presets')

    @once
    <style>
        .ls-opening-hours {
            width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
            box-sizing: border-box;
            margin: 0 auto;
            overflow: visible;
            padding: 0;
        }

        .ls-opening-hours.block-preset-clean {
            background: transparent;
            border: 0 solid transparent;
            box-shadow: none;
            border-radius: 0;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-opening-hours.block-preset-glass {
            background: var(--ls-liquid-glass-background, var(--ls-hours-glass-bg));
            border: 1px solid var(--ls-liquid-glass-border, var(--ls-hours-glass-border));
            border-radius: 16px;
            -webkit-backdrop-filter: var(--ls-liquid-glass-filter);
            backdrop-filter: var(--ls-liquid-glass-filter);
            box-shadow: var(--ls-liquid-glass-shadow);
        }

        .ls-opening-hours.block-preset-bold {
            background: var(--ls-block-bold-bg, var(--ls-hours-bold-bg));
            border: 0 solid transparent;
            border-radius: 16px;
            box-shadow: 0 3px 18px rgba(0, 0, 0, 0.11);
            overflow: visible;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .ls-opening-hours:not(.block-preset-clean) {
            padding: 12px 14px;
        }

        .ls-hours-table {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ls-hours-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--ls-hours-divider);
        }

        .ls-hours-row:last-child {
            border-bottom: 0;
        }

        .ls-hours-day {
            min-width: 98px;
            color: var(--ls-hours-text);
            font-size: var(--ls-type-body-size, 0.94rem);
            line-height: var(--ls-type-body-line, 1.3);
            font-weight: 600;
            flex-shrink: 0;
            text-align: left;
        }

        .ls-hours-time {
            color: var(--ls-hours-text);
            font-size: var(--ls-type-body-size, 0.92rem);
            line-height: var(--ls-type-body-line, 1.35);
            text-align: right;
            flex: 1;
            min-width: 0;
            word-break: break-word;
        }

        .ls-hours-row.is-closed {
            background: transparent !important;
            border-radius: 0;
            padding-left: 0;
            padding-right: 0;
        }

        .ls-hours-row.is-closed .ls-hours-time {
            color: var(--ls-hours-text);
        }

        @media (max-width: 768px) {
            .ls-opening-hours {
                width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            }

            .ls-opening-hours:not(.block-preset-clean) {
                padding: 10px 12px;
            }

            .ls-hours-day {
                min-width: 92px;
                font-size: var(--ls-type-small-size, 0.9rem);
            }

            .ls-hours-time {
                font-size: var(--ls-type-small-size, 0.88rem);
            }
        }
    </style>
    @endonce

    <div style="--delay: {{ $initial }}s" class="button-entrance ls-block-entrance">
        <section
            class="ls-opening-hours fadein block-preset-{{ $openingHours['preset'] }} block-preset-surface"
            data-ls-adaptive-bold
            style="
                --ls-hours-text: {{ $textColor }};
                --ls-hours-accent: {{ $accentColor }};
                --ls-hours-muted: {{ $mutedTextColor }};
                --ls-hours-divider: {{ $dividerColor }};
                --ls-hours-glass-bg: {{ $glassBackground }};
                --ls-hours-glass-border: {{ $glassBorder }};
                --ls-hours-bold-bg: {{ $boldBackground }};
                --ls-block-text-color: {{ $textColor }};
                --ls-block-accent-color: {{ $accentColor }};
                --ls-block-bold-bg: {{ $boldBackground }};
            "
            data-opening-hours-block
            aria-label="{{ trans('messages.opening_hours.aria_label', [], $openingHoursLocale) }}"
        >
            <div class="ls-hours-table">
                @foreach($rows as $row)
                    @php
                        $rowClass = 'ls-hours-row';
                        if ($row['signature'] === 'closed') {
                            $rowClass .= ' is-closed';
                        }
                    @endphp

                    <div class="{{ $rowClass }}">
                        <span class="ls-hours-day">{{ $row['label'] }}</span>
                        <span class="ls-hours-time">{{ $row['display'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endif
