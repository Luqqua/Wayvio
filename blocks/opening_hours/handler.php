<?php

if (!function_exists('openingHoursDayOrder')) {
    function openingHoursDayOrder(): array
    {
        return [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];
    }
}

if (!function_exists('openingHoursDefaultDays')) {
    function openingHoursDefaultDays(): array
    {
        return [
            'monday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'tuesday' => ['open' => true, 'slots' => [['09:00', '12:00'], ['14:00', '18:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'friday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'saturday' => ['open' => true, 'slots' => [['10:00', '14:00']]],
            'sunday' => ['open' => false, 'slots' => []],
        ];
    }
}

if (!function_exists('openingHoursDefaultData')) {
    function openingHoursDefaultData(): array
    {
        return [
            'locale' => app()->getLocale(),
            'group_days' => true,
            'preset' => 'clean',
            'days' => openingHoursDefaultDays(),
        ];
    }
}

if (!function_exists('openingHoursBooleanValue')) {
    function openingHoursBooleanValue($value, bool $default = false): bool
    {
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
    }
}

if (!function_exists('openingHoursResolveLocale')) {
    function openingHoursResolveLocale($value, ?string $defaultLocale = null): string
    {
        $supported = array_values(array_filter((array) config('app.supported_locales', []), 'strlen'));
        $fallback = (string) config('app.fallback_locale', 'en');
        $default = $defaultLocale ?: app()->getLocale();
        $default = is_string($default) && trim($default) !== '' ? trim($default) : $fallback;

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
            ?? $normalize($default)
            ?? $fallback;
    }
}

if (!function_exists('openingHoursExtractLocaleFromTypeParams')) {
    function openingHoursExtractLocaleFromTypeParams($typeParams): ?string
    {
        if (!is_string($typeParams) || trim($typeParams) === '') {
            return null;
        }

        $decoded = json_decode($typeParams, true);
        if (!is_array($decoded)) {
            return null;
        }

        $openingHours = $decoded['opening_hours'] ?? null;
        if (!is_array($openingHours)) {
            return null;
        }

        $locale = $openingHours['locale'] ?? null;
        if (!is_string($locale)) {
            return null;
        }

        $locale = trim($locale);
        return $locale !== '' ? $locale : null;
    }
}

if (!function_exists('openingHoursFindPersistedLocale')) {
    function openingHoursFindPersistedLocale(int $linkId, ?int $userId = null): ?string
    {
        if ($linkId <= 0) {
            return null;
        }

        try {
            $query = \App\Models\Link::query()->select(['id', 'type_params'])->where('id', $linkId);
            if ($userId !== null && $userId > 0) {
                $query->where('user_id', $userId);
            }

            $link = $query->first();
            if (!$link) {
                return null;
            }

            return openingHoursExtractLocaleFromTypeParams($link->type_params ?? null);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('openingHoursNormalizeTime')) {
    function openingHoursNormalizeTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $time = trim((string) $value);
        if ($time === '') {
            return null;
        }

        if (preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time) !== 1) {
            return null;
        }

        return $time;
    }
}

if (!function_exists('openingHoursTimeToMinutes')) {
    function openingHoursTimeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }
}

if (!function_exists('openingHoursNormalizeSlots')) {
    function openingHoursNormalizeSlots($rawSlots): array
    {
        if (!is_array($rawSlots)) {
            return [];
        }

        $cleanSlots = [];

        foreach ($rawSlots as $slot) {
            if (count($cleanSlots) >= 2 || !is_array($slot)) {
                continue;
            }

            $from = openingHoursNormalizeTime($slot['from'] ?? ($slot[0] ?? null));
            $until = openingHoursNormalizeTime($slot['to'] ?? ($slot[1] ?? null));

            if ($from === null || $until === null) {
                continue;
            }

            if (openingHoursTimeToMinutes($until) <= openingHoursTimeToMinutes($from)) {
                continue;
            }

            $cleanSlots[] = [$from, $until];
        }

        return $cleanSlots;
    }
}

if (!function_exists('openingHoursNormalizeDay')) {
    function openingHoursNormalizeDay($rawDay, array $defaultDay): array
    {
        $day = is_array($rawDay) ? $rawDay : [];

        $open = openingHoursBooleanValue($day['open'] ?? $defaultDay['open'], (bool) $defaultDay['open']);
        $slotsSource = array_key_exists('slots', $day) ? $day['slots'] : $defaultDay['slots'];
        $slots = openingHoursNormalizeSlots($slotsSource);

        if (!$open) {
            return [
                'open' => false,
                'slots' => [],
            ];
        }

        return [
            'open' => true,
            'slots' => $slots,
        ];
    }
}

if (!function_exists('openingHoursRawHasMoreThanTwoSlots')) {
    function openingHoursRawHasMoreThanTwoSlots($rawData): bool
    {
        if (!is_array($rawData)) {
            return false;
        }

        $rawDays = is_array($rawData['days'] ?? null) ? $rawData['days'] : [];
        foreach (openingHoursDayOrder() as $dayKey) {
            $rawDay = is_array($rawDays[$dayKey] ?? null) ? $rawDays[$dayKey] : null;
            if ($rawDay === null) {
                continue;
            }

            $rawSlots = $rawDay['slots'] ?? null;
            if (is_array($rawSlots) && count($rawSlots) > 2) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('openingHoursSanitizeData')) {
    function openingHoursSanitizeData($rawData): array
    {
        $defaults = openingHoursDefaultData();
        $raw = is_array($rawData) ? $rawData : [];

        $sanitized = [
            'locale' => openingHoursResolveLocale($raw['locale'] ?? null, (string) ($defaults['locale'] ?? app()->getLocale())),
            'group_days' => openingHoursBooleanValue($raw['group_days'] ?? $defaults['group_days'], true),
            'preset' => normalizeContentBlockStyle($raw['preset'] ?? $defaults['preset'], 'clean'),
            'days' => [],
        ];

        $rawDays = is_array($raw['days'] ?? null) ? $raw['days'] : [];
        foreach (openingHoursDayOrder() as $dayKey) {
            $defaultDay = $defaults['days'][$dayKey];
            $sanitized['days'][$dayKey] = openingHoursNormalizeDay($rawDays[$dayKey] ?? null, $defaultDay);
        }

        return $sanitized;
    }
}

if (!function_exists('handleLinkType_opening_hours')) {
    /**
     * Handles the logic for "opening_hours" link type.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $linkType
     * @return array
     */
    function handleLinkType_opening_hours($request, $linkType)
    {
        $rawOpeningHours = $request->input('opening_hours', []);
        $sanitizedOpeningHours = openingHoursSanitizeData($rawOpeningHours);
        $existingLinkId = (int) $request->input('linkid', 0);
        $editorUserId = (int) $request->input('page_id', 0);
        $persistedLocale = openingHoursFindPersistedLocale($existingLinkId, $editorUserId > 0 ? $editorUserId : null);
        if (is_string($persistedLocale) && $persistedLocale !== '') {
            $sanitizedOpeningHours['locale'] = openingHoursResolveLocale($persistedLocale, (string) $sanitizedOpeningHours['locale']);
        }

        $rules = [
            'title' => [
                'nullable',
                'string',
                'max:120',
            ],
            'opening_hours' => [
                'required',
                'array',
                function ($attribute, $value, $fail) use ($rawOpeningHours, $sanitizedOpeningHours) {
                    if (openingHoursRawHasMoreThanTwoSlots($rawOpeningHours)) {
                        $fail('Each weekday may contain a maximum of two time slots.');
                        return;
                    }

                    foreach (openingHoursDayOrder() as $dayKey) {
                        $dayData = $sanitizedOpeningHours['days'][$dayKey] ?? ['open' => false, 'slots' => []];
                        if ($dayData['open'] && count($dayData['slots']) === 0) {
                            $fail('Open days require at least one valid HH:MM time slot with an end time after the start time.');
                            return;
                        }
                    }
                },
            ],
            'opening_hours.preset' => [
                'nullable',
                'string',
                contentBlockStyleInRule(),
            ],
            'opening_hours.days' => [
                'nullable',
                'array',
            ],
        ];

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = 'Opening Hours';
        }

        $linkData = [
            'title' => $title,
            'button_id' => '1',
            'link' => null,
            'opening_hours' => $sanitizedOpeningHours,
        ];

        return ['rules' => $rules, 'linkData' => $linkData];
    }
}

if (!function_exists('handleLinkType')) {
    function handleLinkType($request, $linkType)
    {
        return handleLinkType_opening_hours($request, $linkType);
    }
}
