<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_CALLBACK_URL'),
    ],
    'twitter' => [
        'client_id'     => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect'      => env('TWITTER_CALLBACK_URL'),
    ],
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_CALLBACK_URL'),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => 'http://example.com/callback-url',
    ],

    'captcha' => [
        'provider' => trim((string) env('CAPTCHA_PROVIDER', '')) !== ''
            ? trim((string) env('CAPTCHA_PROVIDER', ''))
            : (env('TURNSTILE_SECRET') || env('TURNSTILE_SECRET_REGISTER') || env('TURNSTILE_SECRET_LOGIN') || env('TURNSTILE_SECRET_PASSWORD_RESET') || env('TURNSTILE_SECRET_REPORT') || env('TURNSTILE_SECRET_FORMS_IMPRINT_CONTACT') || env('TURNSTILE_SECRET_FORMS_HUB_CONTACT_BLOCK')
                ? 'turnstile'
                : (env('HCAPTCHA_SECRET') ? 'hcaptcha' : (env('RECAPTCHA_SECRET') ? 'recaptcha' : null))),
        'sitekey' => env(
            'CAPTCHA_SITE_KEY',
            env('TURNSTILE_SITE_KEY', env('HCAPTCHA_SITE_KEY', env('HCAPTCHA_SITEKEY', env('RECAPTCHA_SITE_KEY',
                env('TURNSTILE_SITE_KEY_REGISTER', env('TURNSTILE_SITE_KEY_LOGIN', env('TURNSTILE_SITE_KEY_PASSWORD_RESET',
                    env('TURNSTILE_SITE_KEY_REPORT', env('TURNSTILE_SITE_KEY_FORMS_IMPRINT_CONTACT', env('TURNSTILE_SITE_KEY_FORMS_HUB_CONTACT_BLOCK')))
                )))
            ))))
        ),
        'sitekeys' => [
            'register' => env(
                'CAPTCHA_SITE_KEY_REGISTER',
                env('TURNSTILE_SITE_KEY_REGISTER', env('CAPTCHA_SITE_KEY'))
            ),
            'login' => env(
                'CAPTCHA_SITE_KEY_LOGIN',
                env('TURNSTILE_SITE_KEY_LOGIN', env('CAPTCHA_SITE_KEY'))
            ),
            'password_reset' => env(
                'CAPTCHA_SITE_KEY_PASSWORD_RESET',
                env('TURNSTILE_SITE_KEY_PASSWORD_RESET', env('CAPTCHA_SITE_KEY'))
            ),
            'report' => env(
                'CAPTCHA_SITE_KEY_REPORT',
                env('TURNSTILE_SITE_KEY_REPORT', env('CAPTCHA_SITE_KEY'))
            ),
            'forms_imprint_contact' => env(
                'CAPTCHA_SITE_KEY_FORMS_IMPRINT_CONTACT',
                env('TURNSTILE_SITE_KEY_FORMS_IMPRINT_CONTACT', env('CAPTCHA_SITE_KEY'))
            ),
            'forms_hub_contact_block' => env(
                'CAPTCHA_SITE_KEY_FORMS_HUB_CONTACT_BLOCK',
                env('TURNSTILE_SITE_KEY_FORMS_HUB_CONTACT_BLOCK', env('CAPTCHA_SITE_KEY'))
            ),
        ],
        'secrets' => [
            'register' => env(
                'CAPTCHA_SECRET_KEY_REGISTER',
                env('TURNSTILE_SECRET_REGISTER', env('CAPTCHA_SECRET_KEY'))
            ),
            'login' => env(
                'CAPTCHA_SECRET_KEY_LOGIN',
                env('TURNSTILE_SECRET_LOGIN', env('CAPTCHA_SECRET_KEY'))
            ),
            'password_reset' => env(
                'CAPTCHA_SECRET_KEY_PASSWORD_RESET',
                env('TURNSTILE_SECRET_PASSWORD_RESET', env('CAPTCHA_SECRET_KEY'))
            ),
            'report' => env(
                'CAPTCHA_SECRET_KEY_REPORT',
                env('TURNSTILE_SECRET_REPORT', env('CAPTCHA_SECRET_KEY'))
            ),
            'forms_imprint_contact' => env(
                'CAPTCHA_SECRET_KEY_FORMS_IMPRINT_CONTACT',
                env('TURNSTILE_SECRET_FORMS_IMPRINT_CONTACT', env('CAPTCHA_SECRET_KEY'))
            ),
            'forms_hub_contact_block' => env(
                'CAPTCHA_SECRET_KEY_FORMS_HUB_CONTACT_BLOCK',
                env('TURNSTILE_SECRET_FORMS_HUB_CONTACT_BLOCK', env('CAPTCHA_SECRET_KEY'))
            ),
        ],
        'secret' => env(
            'CAPTCHA_SECRET_KEY',
            env('TURNSTILE_SECRET', env('HCAPTCHA_SECRET', env('RECAPTCHA_SECRET',
                env('TURNSTILE_SECRET_REGISTER', env('TURNSTILE_SECRET_LOGIN', env('TURNSTILE_SECRET_PASSWORD_RESET',
                    env('TURNSTILE_SECRET_REPORT', env('TURNSTILE_SECRET_FORMS_IMPRINT_CONTACT', env('TURNSTILE_SECRET_FORMS_HUB_CONTACT_BLOCK')))
                )))
            )))
        ),
    ],

];
