<?php

use Illuminate\Support\Str;

$isProduction = strtolower((string) env('APP_ENV', 'production')) === 'production';
$archiveEncryptionRaw = strtolower(trim((string) env('BACKUP_ARCHIVE_ENCRYPTION', 'default')));
$archiveEncryption = in_array($archiveEncryptionRaw, ['', 'none', 'null', 'false'], true)
    ? null
    : $archiveEncryptionRaw;

return [

    'backup' => [

        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('BACKUP_NAME', 'db-backups'),

        'source' => [

            'files' => [

                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    base_path(),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => [
                    base_path('backups'),
                ],

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative
                 * Set to `null` to include complete absolute path
                 * Example: base_path()
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'excludeTables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ],
             * ],
             *
             * If you are using only InnoDB tables on a MySQL server, you can
             * also supply the useSingleTransaction option to avoid table locking.
             *
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'useSingleTransaction' => true,
             *       ],
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            'databases' => [
                env('BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
            ],
        ],

        /*
         * The database dump can be compressed to decrease diskspace usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => Str::random(16).'-',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => array_values(array_filter(array_unique([
                env('BACKUP_DB_DISK', 'backups'),
            ]))),
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => env('BACKUP_TEMPORARY_DIRECTORY')
            ? (string) env('BACKUP_TEMPORARY_DIRECTORY')
            : storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can set it to `null` or `false` to disable encryption.
         *
         * When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
         * available on your system.
         */
        'encryption' => $archiveEncryption,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Events` classes.
     */
    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => [env('BACKUP_DB_DISK', 'backups')],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],

        /*
        [
            'name' => 'name of the second app',
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        */
    ],

    'automation' => [
        'db' => [
            'enabled' => filter_var(env('BACKUP_DB_AUTOMATED_ENABLED', false), FILTER_VALIDATE_BOOL),
            'interval' => env('BACKUP_DB_AUTOMATED_INTERVAL', 'daily'),
            'time' => env('BACKUP_DB_AUTOMATED_TIME', '02:30'),
            'disk' => env('BACKUP_DB_DISK', 'backups'),
        ],
        'full' => [
            'enabled' => filter_var(env('BACKUP_FULL_AUTOMATED_ENABLED', false), FILTER_VALIDATE_BOOL),
            'interval' => env('BACKUP_FULL_AUTOMATED_INTERVAL', 'daily'),
            'time' => env('BACKUP_FULL_AUTOMATED_TIME', '03:30'),
            'db_disk' => env('BACKUP_FULL_DB_DISK', env('BACKUP_DB_DISK', 'backups')),
            'media_target_disk' => env('BACKUP_FULL_MEDIA_TARGET_DISK', env('MEDIA_BACKUP_TARGET_DISK', 'backups')),
            'media_prefix' => env('BACKUP_FULL_MEDIA_PREFIX', env('MEDIA_BACKUP_PREFIX', 'media-backups')),
            'manifest_disk' => env('BACKUP_FULL_MANIFEST_DISK', env('BACKUP_FULL_MEDIA_TARGET_DISK', env('MEDIA_BACKUP_TARGET_DISK', 'backups'))),
            'manifest_prefix' => env('BACKUP_FULL_PREFIX', 'full-backups'),
        ],
        'target_profile' => env('BACKUP_TARGET_PROFILE', 'local'),
        'alert_webhook_url' => env('BACKUP_ALERT_WEBHOOK_URL', env('MEDIA_ALERT_WEBHOOK_URL')),
    ],

    'health' => [
        'scheduler_heartbeat_path' => env('BACKUP_SCHEDULER_HEARTBEAT_PATH', storage_path('app/backup-scheduler-heartbeat.json')),
        'scheduler_heartbeat_stale_minutes' => max(1, (int) env('BACKUP_SCHEDULER_HEARTBEAT_STALE_MINUTES', 5)),
    ],

    'security' => [
        'require_archive_password' => filter_var(env('BACKUP_REQUIRE_ARCHIVE_PASSWORD', $isProduction), FILTER_VALIDATE_BOOL),
        'require_archive_encryption' => filter_var(env('BACKUP_REQUIRE_ARCHIVE_ENCRYPTION', $isProduction), FILTER_VALIDATE_BOOL),
        'require_dump_binary_path' => filter_var(env('BACKUP_REQUIRE_DB_DUMP_BINARY_PATH', $isProduction), FILTER_VALIDATE_BOOL),
        'require_outside_project_root' => filter_var(env('BACKUP_REQUIRE_OUTSIDE_PROJECT_ROOT', $isProduction), FILTER_VALIDATE_BOOL),
        'db_require_tls' => filter_var(env('DB_REQUIRE_TLS', $isProduction), FILTER_VALIDATE_BOOL),
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [

            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => max(0, (int) env('BACKUP_RETENTION_KEEP_ALL_DAYS', 2)),

            /*
             * The number of days for which daily backups must be kept.
             */
            'keep_daily_backups_for_days' => max(0, (int) env('BACKUP_RETENTION_DAILY_DAYS', 14)),

            /*
             * The number of weeks for which one weekly backup must be kept.
             */
            'keep_weekly_backups_for_weeks' => max(0, (int) env('BACKUP_RETENTION_WEEKLY_WEEKS', 8)),

            /*
             * The number of months for which one monthly backup must be kept.
             */
            'keep_monthly_backups_for_months' => max(0, (int) env('BACKUP_RETENTION_MONTHLY_MONTHS', 12)),

            /*
             * The number of years for which one yearly backup must be kept.
             */
            'keep_yearly_backups_for_years' => max(0, (int) env('BACKUP_RETENTION_YEARLY_YEARS', 2)),

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => max(0, (int) env('BACKUP_RETENTION_MAX_MB', 0)),
        ],
    ],

];
