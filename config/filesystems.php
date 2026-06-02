<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'backups' => [
            'driver' => 'local',
            'root' => env('BACKUPS_DISK_ROOT') ?: base_path('backups'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        'media_local' => [
            'driver' => 'local',
            'root' => base_path('assets/media'),
            'url' => env('MEDIA_LOCAL_URL', env('APP_URL').'/assets/media'),
            'visibility' => 'public',
        ],

        'media_s3' => [
            'driver' => 's3',
            'key' => env('MEDIA_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MEDIA_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MEDIA_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('MEDIA_AWS_BUCKET', env('AWS_BUCKET')),
            'url' => env('MEDIA_AWS_URL', env('AWS_URL')),
            'endpoint' => env('MEDIA_AWS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('MEDIA_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'root' => env('MEDIA_AWS_ROOT', ''),
        ],

        'backups_worker' => [
            'driver' => 'worker-r2',
            'url' => env('WORKER_R2_URL'),
            'token' => env('WORKER_R2_AUTH_TOKEN'),
            'secret' => env('WORKER_R2_SHARED_SECRET'),
            'timeout' => env('WORKER_R2_TIMEOUT', 120),
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        base_path('storage') => storage_path('app/public'),
    ],

];
