<?php

if (!function_exists('uspCardsNormalizeIcon')) {
    function uspCardsNormalizeIcon($value): ?string
    {
        if ($value === null) {
            return null;
        }

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
    }
}

if (!function_exists('uspCardsSanitizeItems')) {
    function uspCardsSanitizeItems($rawItems): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

        $cleanItems = [];
        $truncate = function (string $value, int $length): string {
            if (function_exists('mb_substr')) {
                return mb_substr($value, 0, $length);
            }

            return substr($value, 0, $length);
        };

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim(strip_tags((string) ($item['title'] ?? '')));
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $icon = uspCardsNormalizeIcon($item['icon'] ?? null) ?? 'fa fa-star';

            if ($title === '') {
                continue;
            }

            $cleanItems[] = [
                'title' => $truncate($title, 80),
                'description' => $truncate($description, 220),
                'icon' => $icon,
            ];

            if (count($cleanItems) >= 3) {
                break;
            }
        }

        return $cleanItems;
    }
}

/**
 * Handles the logic for "usp_cards" link type.
 *
 * @param \Illuminate\Http\Request $request
 * @param mixed $linkType
 * @return array
 */
function handleLinkType($request, $linkType)
{
    $sanitizedItems = uspCardsSanitizeItems($request->input('usp_items', []));

    $rules = [
        'title' => [
            'nullable',
            'string',
            'max:120',
        ],
        'usp_style' => [
            'nullable',
            'string',
            contentBlockStyleInRule(),
        ],
        'usp_items' => [
            'required',
            'array',
            'max:3',
            function ($attribute, $value, $fail) use ($sanitizedItems) {
                if (count($sanitizedItems) === 0) {
                    $fail('Please add at least one USP with a title.');
                }
            },
        ],
        'usp_items.*.title' => [
            'nullable',
            'string',
            'max:80',
        ],
        'usp_items.*.description' => [
            'nullable',
            'string',
            'max:220',
        ],
        'usp_items.*.icon' => [
            'nullable',
            'string',
            'max:50',
        ],
    ];

    $title = trim((string) $request->input('title', ''));
    if ($title === '') {
        $title = 'USP Cards';
    }

    $style = normalizeContentBlockStyle($request->input('usp_style', 'glass'), 'glass');

    $linkData = [
        'title' => $title,
        'button_id' => '1',
        'link' => null,
        'usp_style' => $style,
        'usp_items' => $sanitizedItems,
    ];

    return ['rules' => $rules, 'linkData' => $linkData];
}
