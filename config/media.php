<?php

return [
    'disk' => env('MEDIA_DISK', 'media_local'),

    // tenant-aware object prefix (kept consistent across local and S3 disks)
    'tenant_prefix' => env('MEDIA_TENANT_PREFIX', 'tenants'),

    // Enforce upload conversion to WebP for storage efficiency (fallback to original if conversion fails).
    'convert_uploads_to_webp' => filter_var(env('MEDIA_CONVERT_TO_WEBP', true), FILTER_VALIDATE_BOOL),
    'webp_quality' => max(30, min(100, (int) env('MEDIA_WEBP_QUALITY', 82))),

    // Upload throttling target (used by named limiter `uploads`).
    'upload_rate_limit_per_minute' => max(1, (int) env('MEDIA_UPLOAD_RATE_LIMIT_PER_MINUTE', 10)),

    // Optional responsive derivatives (stored on the same media disk).
    'responsive_variants_enabled' => filter_var(env('MEDIA_RESPONSIVE_VARIANTS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'responsive_variant_widths' => array_values(array_filter(array_map(static function ($width) {
        return (int) trim((string) $width);
    }, explode(',', (string) env('MEDIA_RESPONSIVE_VARIANT_WIDTHS', '256,512,1024'))), static function (int $width) {
        return $width > 0;
    })),
    'responsive_variant_quality' => max(30, min(100, (int) env('MEDIA_RESPONSIVE_VARIANT_QUALITY', 80))),
    'responsive_variants_queue' => trim((string) env('MEDIA_RESPONSIVE_VARIANTS_QUEUE', 'media')),

    // Optional malware scanning (e.g. ClamAV) before persisting uploads.
    'malware_scan_enabled' => filter_var(env('MEDIA_MALWARE_SCAN_ENABLED', false), FILTER_VALIDATE_BOOL),
    'malware_scan_fail_closed' => filter_var(env('MEDIA_MALWARE_SCAN_FAIL_CLOSED', false), FILTER_VALIDATE_BOOL),
    'malware_scan_binary' => env('MEDIA_MALWARE_SCAN_BINARY', 'clamscan'),
    'malware_scan_args' => env('MEDIA_MALWARE_SCAN_ARGS', '--no-summary --infected'),
    'malware_scan_timeout_seconds' => max(5, (int) env('MEDIA_MALWARE_SCAN_TIMEOUT_SECONDS', 20)),

    // Media backup snapshot prefix on backup disk.
    'backup_prefix' => env('MEDIA_BACKUP_PREFIX', 'media-backups'),
    'backup_target_disk' => env('MEDIA_BACKUP_TARGET_DISK', 'backups'),
    'automated_backup_enabled' => filter_var(env('MEDIA_AUTOMATED_BACKUP_ENABLED', false), FILTER_VALIDATE_BOOL),
    'automated_backup_interval' => env('MEDIA_AUTOMATED_BACKUP_INTERVAL', 'daily'),
    'automated_backup_time' => env('MEDIA_AUTOMATED_BACKUP_TIME', '03:15'),
    'alert_webhook_url' => env('MEDIA_ALERT_WEBHOOK_URL'),
    'backup_health_max_age_hours' => max(1, (int) env('MEDIA_BACKUP_HEALTH_MAX_AGE_HOURS', 30)),
    'restore_drill_enabled' => filter_var(env('MEDIA_RESTORE_DRILL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'restore_drill_day_of_month' => max(1, min(28, (int) env('MEDIA_RESTORE_DRILL_DAY_OF_MONTH', 1))),
    'restore_drill_time' => env('MEDIA_RESTORE_DRILL_TIME', '04:15'),
    'restore_drill_sample_files' => max(1, (int) env('MEDIA_RESTORE_DRILL_SAMPLE_FILES', 20)),

    'user_data_keys' => [
        'avatar' => env('MEDIA_USERDATA_AVATAR_KEY', 'avatar_media_key'),
        'background' => env('MEDIA_USERDATA_BACKGROUND_KEY', 'background_media_key'),
        'favicon' => env('MEDIA_USERDATA_FAVICON_KEY', 'favicon_media_key'),
    ],

    // Canonical upload limits used by validation + UI helper texts.
    'upload_limits' => [
        'agency_logo' => [
            'max_kb' => 3072, // 3 MB
            'max_width' => 2500,
            'max_height' => 2500,
        ],
        'avatar' => [
            'max_kb' => 2048, // 2 MB
            'max_width' => 1200,
            'max_height' => 1200,
        ],
        'header_hero' => [
            'max_kb' => 4096, // 4 MB
            'max_width' => 3000,
            'max_height' => 1200,
        ],
        'background' => [
            'max_kb' => 5120, // 5 MB
            'max_width' => 3000,
            'max_height' => 2000,
        ],
        'favicon' => [
            'max_kb' => 512, // 512 KB
            'max_width' => 256,
            'max_height' => 256,
        ],
    ],
];
