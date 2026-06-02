<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default meta values
    |--------------------------------------------------------------------------
    |
    | These defaults apply to all user pages unless overridden by Business
    | customers via the meta settings UI. Patterns support the placeholders
    | ":username", ":bio" and ":app".
    |
    */

    'defaults' => [
        'title_pattern' => ':username | ' . env('APP_NAME', 'Wayvio'),
        'description_pattern' => ':username auf ' . env('APP_NAME', 'Wayvio') . ': alle wichtigen Links und Infos auf einen Blick.',
        'keywords_pattern' => ':username, :username business profil, :username website, leistungen, kontakt, ' . env('APP_NAME', 'Wayvio'),
        'robots' => 'index,follow',
        'twitter_card' => 'summary_large_image',
        'og_locale' => env('APP_OG_LOCALE', env('APP_LOCALE', 'de_DE')),
        'og_locale_map' => [
            'en' => 'en_US',
            'de' => 'de_DE',
        ],
        'locales' => [
            'en' => [
                'title_pattern' => ':username | ' . env('APP_NAME', 'Wayvio'),
                'description_pattern' => ':username on ' . env('APP_NAME', 'Wayvio') . ' - all key links and info in one place.',
                'keywords_pattern' => ':username, :username business profile, :username website, services, contact, ' . env('APP_NAME', 'Wayvio'),
                'og_locale' => 'en_US',
            ],
            'de' => [
                'title_pattern' => ':username | ' . env('APP_NAME', 'Wayvio'),
                'description_pattern' => ':username auf ' . env('APP_NAME', 'Wayvio') . ': alle wichtigen Links und Infos auf einen Blick.',
                'keywords_pattern' => ':username, :username business profil, :username website, leistungen, kontakt, ' . env('APP_NAME', 'Wayvio'),
                'og_locale' => 'de_DE',
            ],
        ],
        'canonical_base' => null, // e.g. https://example.com
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured data
    |--------------------------------------------------------------------------
    |
    | Structured data can be enabled for Business users if desired.
    |
    */
    'structured_data' => [
        'enabled' => false,
        'type' => 'Person', // Person | Organization | Brand
    ],
];
