<?php

if (!function_exists('socialProofNormalizeAvatarUrl')) {
    function socialProofNormalizeAvatarUrl($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $avatarUrl = trim((string) $value);
        if ($avatarUrl === '') {
            return null;
        }

        if (str_starts_with($avatarUrl, '/')) {
            return $avatarUrl;
        }

        if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($avatarUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            return null;
        }

        return $avatarUrl;
    }
}

if (!function_exists('socialProofSanitizeTestimonials')) {
    function socialProofSanitizeTestimonials($rawTestimonials): array
    {
        if (!is_array($rawTestimonials)) {
            return [];
        }

        $cleanTestimonials = [];

        foreach ($rawTestimonials as $testimonial) {
            if (!is_array($testimonial)) {
                continue;
            }

            $customerName = trim(strip_tags((string) ($testimonial['customer_name'] ?? '')));
            $reviewText = trim(strip_tags((string) ($testimonial['review_text'] ?? '')));
            $rating = filter_var($testimonial['rating'] ?? null, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 5,
                ],
            ]);
            $avatarUrl = socialProofNormalizeAvatarUrl($testimonial['avatar_url'] ?? null);

            if ($customerName === '' || $reviewText === '' || $rating === false) {
                continue;
            }

            $cleanTestimonials[] = [
                'customer_name' => $customerName,
                'rating' => (int) $rating,
                'review_text' => $reviewText,
                'avatar_url' => $avatarUrl,
            ];

            if (count($cleanTestimonials) >= 5) {
                break;
            }
        }

        return $cleanTestimonials;
    }
}

/**
 * Handles the logic for "social_proof" link type.
 *
 * @param \Illuminate\Http\Request $request
 * @param mixed $linkType
 * @return array
 */
function handleLinkType($request, $linkType)
{
    $sanitizedTestimonials = socialProofSanitizeTestimonials($request->input('testimonials', []));

    $rules = [
        'title' => [
            'nullable',
            'string',
            'max:120',
        ],
        'social_style' => [
            'nullable',
            'string',
            contentBlockStyleInRule(),
        ],
        'testimonials' => [
            'required',
            'array',
            'max:5',
            function ($attribute, $value, $fail) use ($sanitizedTestimonials) {
                if (count($sanitizedTestimonials) === 0) {
                    $fail('Please add at least one complete testimonial.');
                }
            },
        ],
        'testimonials.*.customer_name' => [
            'nullable',
            'string',
            'max:80',
        ],
        'testimonials.*.rating' => [
            'nullable',
            'integer',
            'between:1,5',
        ],
        'testimonials.*.review_text' => [
            'nullable',
            'string',
            'max:600',
        ],
        'testimonials.*.avatar_url' => [
            'nullable',
            'string',
            'max:2048',
        ],
    ];

    $title = trim((string) $request->input('title', ''));
    if ($title === '') {
        $title = 'Social Proof';
    }

    $style = normalizeContentBlockStyle($request->input('social_style', 'glass'), 'glass');

    $linkData = [
        'title' => $title,
        'button_id' => '1',
        'link' => null,
        'social_style' => $style,
        'testimonials' => $sanitizedTestimonials,
    ];

    return ['rules' => $rules, 'linkData' => $linkData];
}
