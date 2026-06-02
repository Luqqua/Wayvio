<?php

if (!function_exists('gastroServiceNormalizePrice')) {
    function gastroServiceNormalizePrice($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $price = trim(strip_tags((string) $value));
        if ($price === '') {
            return null;
        }

        $price = preg_replace('/\s+/u', ' ', $price);
        if (!is_string($price)) {
            return null;
        }

        if (function_exists('mb_substr')) {
            $price = mb_substr($price, 0, 32);
        } else {
            $price = substr($price, 0, 32);
        }

        $numericCandidate = str_replace(',', '.', $price);
        if (is_numeric($numericCandidate)) {
            $numericPrice = (float) $numericCandidate;
            if ($numericPrice < 0 || $numericPrice > 999999.99) {
                return null;
            }

            return number_format($numericPrice, 2, '.', '');
        }

        return $price;
    }
}

if (!function_exists('gastroServiceSanitizeItems')) {
    function gastroServiceSanitizeItems($rawItems): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

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
        $cleanItems = [];

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim(strip_tags((string) ($item['title'] ?? '')));
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $rawPrice = $item['price'] ?? null;
            $price = gastroServiceNormalizePrice($rawPrice);

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

            $cleanItems[] = [
                'title' => $title,
                'description' => $description === '' ? null : $description,
                'price' => $price,
                'badges' => $badges,
            ];
        }

        return $cleanItems;
    }
}

/**
 * Handles the logic for "gastro_service" link type.
 *
 * @param \Illuminate\Http\Request $request
 * @param mixed $linkType
 * @return array
 */
function handleLinkType($request, $linkType)
{
    $sanitizedItems = gastroServiceSanitizeItems($request->input('gastro_items', []));

    $rules = [
        'title' => [
            'nullable',
            'string',
            'max:120',
        ],
        'gastro_style' => [
            'nullable',
            'string',
            contentBlockStyleInRule(),
        ],
        'gastro_items' => [
            'required',
            'array',
            function ($attribute, $value, $fail) use ($sanitizedItems) {
                if (count($sanitizedItems) === 0) {
                    $fail('Please add at least one valid item with a title.');
                }
            },
        ],
        'gastro_items.*.title' => [
            'nullable',
            'string',
            'max:120',
        ],
        'gastro_items.*.description' => [
            'nullable',
            'string',
            'max:500',
        ],
        'gastro_items.*.price' => [
            'nullable',
            'string',
            'max:32',
        ],
        'gastro_items.*.badges' => [
            'nullable',
            'array',
        ],
        'gastro_items.*.badges.*' => [
            'string',
            'in:vegan,organic,spicy,new',
        ],
    ];

    $title = trim((string) $request->input('title', ''));
    if ($title === '') {
        $title = (string) __('messages.block.title.gastro_service');
    }

    $styleInput = $request->input('gastro_style', $request->input('gastro_style_ui', 'clean'));
    $style = normalizeContentBlockStyle($styleInput, 'clean');

    $linkData = [
        'title' => $title,
        'button_id' => '1',
        'link' => null,
        'gastro_style' => $style,
        'gastro_items' => $sanitizedItems,
    ];

    return ['rules' => $rules, 'linkData' => $linkData];
}
