<?php

return [
    'provider' => [
        'name' => trim((string) env('LEGAL_PROVIDER_NAME', '')),
        'address' => trim((string) env('LEGAL_PROVIDER_ADDRESS', '')),
        'email' => trim((string) env('LEGAL_PROVIDER_EMAIL', '')),
    ],
    'agreements' => [
        'agb' => [
            'label' => 'AGB',
            'version' => env('LEGAL_AGB_VERSION', 'v2.1'),
            'url' => '/pages/agb',
            'text_file' => env('LEGAL_AGB_TEXT_FILE', '../legal_docs/wayvio_agb.txt'),
            'sha256' => env('LEGAL_AGB_SHA256', '2fe217f2ebac04e6382b928d1aeb0c48b98c274fec30b63cfa0c494343d22abe'),
            'text_file_en' => env('LEGAL_AGB_TEXT_FILE_EN', '../legal_docs/wayvio_agb_en.txt'),
            'sha256_en' => env('LEGAL_AGB_SHA256_EN', '27c8109bef0ce40949ffedbbd2c4f271724f1046044d73fa0eaa1c9c94b1aeb1'),
        ],
        'avv' => [
            'label' => 'AVV',
            'version' => env('LEGAL_AVV_VERSION', 'v1.9'),
            'url' => '/pages/avv',
            'text_file' => env('LEGAL_AVV_TEXT_FILE', '../legal_docs/wayvio_avv.txt'),
            'sha256' => env('LEGAL_AVV_SHA256', '7e2b7b4cb8ad395b37ec1003845f0d77c260f1bde6ee42ee6bfc1a3eaca00250'),
            'text_file_en' => env('LEGAL_AVV_TEXT_FILE_EN', '../legal_docs/wayvio_avv_en.txt'),
            'sha256_en' => env('LEGAL_AVV_SHA256_EN', 'db892e26b4be54a62bfebaa3593fa0d0aa5cdbd2f03db06a471687eb38dc016e'),
        ],
    ],
    'documents' => [
        'privacy' => [
            'label' => 'Datenschutzerklaerung',
            'version' => env('LEGAL_PRIVACY_VERSION', 'v1.8'),
            'text_file' => env('LEGAL_PRIVACY_TEXT_FILE', '../legal_docs/wayvio_datenschutz.txt'),
            'sha256' => env('LEGAL_PRIVACY_SHA256', '71f507d16cceb88b50e9f5154c1c25093968e032e4fa2682c5431abcad4f32d8'),
            'text_file_en' => env('LEGAL_PRIVACY_TEXT_FILE_EN', '../legal_docs/wayvio_datenschutz_en.txt'),
            'sha256_en' => env('LEGAL_PRIVACY_SHA256_EN', '336ddb94168a5eafdcc15565503b3c369913a17abf6efd2b532dc292ad5fea0f'),
        ],
        'imprint' => [
            'label' => 'Impressum',
            'version' => env('LEGAL_IMPRINT_VERSION', '2026-05'),
            'text_file' => env('LEGAL_IMPRINT_TEXT_FILE', '../legal_docs/wayvio_impressum.txt'),
            'sha256' => env('LEGAL_IMPRINT_SHA256', '98bd6bd5314efa7ff32b77e9a80b5ff29da09f663f621f0ec966f5664e8b6e68'),
            'text_file_en' => env('LEGAL_IMPRINT_TEXT_FILE_EN', '../legal_docs/wayvio_impressum_en.txt'),
            'sha256_en' => env('LEGAL_IMPRINT_SHA256_EN', '6a4de23e82d7d3cf3f7dcd5883c94084fb3fded86e7e5c379a9c0d2edc16238e'),
        ],
    ],
];
