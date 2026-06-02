<?php

namespace App\Console\Commands\Mod;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use Throwable;

class BackupStatusCommand extends BaseModCommand
{
    protected $signature = 'backup:status {--json : Ausgabe als JSON}';
    protected $description = 'Zeigt den aktuellen Backup-Status (DB, Media, Full) inkl. letzter Artefakte.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $status = $this->buildStatus();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $this->line('Backup status generated at: ' . ($status['generated_at_utc'] ?? 'n/a'));
        $this->line('DB auto: ' . $this->boolLabel((bool) ($status['db']['automation']['enabled'] ?? false)));
        $this->line('DB latest: ' . (($status['db']['latest_backup']['path'] ?? null) ?: 'none'));
        $this->line('Media auto: ' . $this->boolLabel((bool) ($status['media']['automation']['enabled'] ?? false)));
        $this->line('Media latest manifest: ' . (($status['media']['latest_manifest']['path'] ?? null) ?: 'none'));
        $this->line('Full auto: ' . $this->boolLabel((bool) ($status['full']['automation']['enabled'] ?? false)));
        $this->line('Full latest manifest: ' . (($status['full']['latest_manifest']['path'] ?? null) ?: 'none'));
        $this->line('Automation health: ' . strtoupper((string) ($status['health']['overall'] ?? 'unknown')));
        $this->line('Automation possible: ' . $this->boolLabel((bool) ($status['health']['automation_possible'] ?? false)));
        $this->line('Scheduler heartbeat: ' . (($status['health']['scheduler']['last_heartbeat_utc'] ?? null) ?: 'none'));

        return Command::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    public function buildStatus(): array
    {
        $dbDisk = (string) config('backup.automation.db.disk', 'backups');
        $mediaTargetDisk = (string) config('media.backup_target_disk', 'backups');
        $mediaPrefix = trim((string) config('media.backup_prefix', 'media-backups'), '/');
        $backupConnection = (string) config('backup.backup.source.databases.0', 'unknown');

        $fullDisk = (string) config('backup.automation.full.manifest_disk', $mediaTargetDisk);
        $fullPrefix = trim((string) config('backup.automation.full.manifest_prefix', 'full-backups'), '/');

        $dbLatest = $this->latestFileMeta($dbDisk, static function (string $path): bool {
            return str_ends_with(strtolower($path), '.zip');
        });

        $mediaLatestManifest = $this->latestManifestMeta($mediaTargetDisk, $mediaPrefix);
        $fullLatestManifest = $this->latestManifestMeta($fullDisk, $fullPrefix);
        $dbSecurity = $this->databaseSecuritySummary($backupConnection);
        $archiveEncryptionValue = config('backup.backup.encryption');
        $archiveEncryptionEnabled = $this->archiveEncryptionEnabled($archiveEncryptionValue);

        $status = [
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'target_profile' => (string) config('backup.automation.target_profile', 'local'),
            'alert_webhook_configured' => trim((string) config('backup.automation.alert_webhook_url', '')) !== ''
                || trim((string) config('media.alert_webhook_url', '')) !== '',
            'db' => [
                'runtime_connection' => (string) config('database.default', 'unknown'),
                'backup_connection' => $backupConnection,
                'archive_password_configured' => trim((string) config('backup.backup.password', '')) !== '',
                'archive_encryption' => [
                    'configured' => $archiveEncryptionEnabled,
                    'value' => is_scalar($archiveEncryptionValue) ? (string) $archiveEncryptionValue : null,
                ],
                'security' => $dbSecurity,
                'automation' => [
                    'enabled' => (bool) config('backup.automation.db.enabled', false),
                    'interval' => (string) config('backup.automation.db.interval', 'daily'),
                    'time' => (string) config('backup.automation.db.time', '02:30'),
                    'disk' => $dbDisk,
                ],
                'latest_backup' => $dbLatest,
            ],
            'media' => [
                'source_disk' => (string) config('media.disk', 'media_local'),
                'automation' => [
                    'enabled' => (bool) config('media.automated_backup_enabled', false),
                    'interval' => (string) config('media.automated_backup_interval', 'daily'),
                    'time' => (string) config('media.automated_backup_time', '03:15'),
                    'target_disk' => $mediaTargetDisk,
                    'prefix' => $mediaPrefix,
                    'health_max_age_hours' => (int) config('media.backup_health_max_age_hours', 30),
                    'restore_drill_enabled' => (bool) config('media.restore_drill_enabled', false),
                    'restore_drill_day_of_month' => (int) config('media.restore_drill_day_of_month', 1),
                    'restore_drill_time' => (string) config('media.restore_drill_time', '04:15'),
                    'restore_drill_sample_files' => (int) config('media.restore_drill_sample_files', 20),
                ],
                'latest_manifest' => $mediaLatestManifest,
            ],
            'full' => [
                'automation' => [
                    'enabled' => (bool) config('backup.automation.full.enabled', false),
                    'interval' => (string) config('backup.automation.full.interval', 'daily'),
                    'time' => (string) config('backup.automation.full.time', '03:30'),
                    'db_disk' => (string) config('backup.automation.full.db_disk', $dbDisk),
                    'media_target_disk' => (string) config('backup.automation.full.media_target_disk', $mediaTargetDisk),
                    'media_prefix' => (string) config('backup.automation.full.media_prefix', $mediaPrefix),
                    'manifest_disk' => $fullDisk,
                    'manifest_prefix' => $fullPrefix,
                ],
                'latest_manifest' => $fullLatestManifest,
            ],
            'storage_disks' => $this->storageDiskSummary(),
        ];

        $status['health'] = $this->buildHealthStatus($status);

        return $status;
    }

    /**
     * @param array<string,mixed> $status
     * @return array<string,mixed>
     */
    protected function buildHealthStatus(array $status): array
    {
        $checks = [];

        $dbAuto = (array) ($status['db']['automation'] ?? []);
        $mediaAuto = (array) ($status['media']['automation'] ?? []);
        $fullAuto = (array) ($status['full']['automation'] ?? []);

        $enabledMap = [
            'db' => (bool) ($dbAuto['enabled'] ?? false),
            'media' => (bool) ($mediaAuto['enabled'] ?? false),
            'full' => (bool) ($fullAuto['enabled'] ?? false),
        ];
        $enabledCount = count(array_filter($enabledMap, static fn (bool $enabled): bool => $enabled));

        if ($enabledCount === 0) {
            $this->addHealthCheck(
                $checks,
                'automation_enabled',
                'warn',
                'No automated backup job is enabled. Manual backups work, but scheduling is inactive.'
            );
        }

        $scheduler = $this->schedulerHeartbeatStatus();
        if ($enabledCount > 0) {
            if ((bool) ($scheduler['recent'] ?? false)) {
                $this->addHealthCheck(
                    $checks,
                    'scheduler_heartbeat',
                    'ok',
                    'Scheduler heartbeat is recent.'
                );
            } else {
                $this->addHealthCheck(
                    $checks,
                    'scheduler_heartbeat',
                    'error',
                    'Scheduler heartbeat is missing or stale. Automated backups are likely not running right now.'
                );
            }
        } else {
            $this->addHealthCheck(
                $checks,
                'scheduler_heartbeat',
                (bool) ($scheduler['recent'] ?? false) ? 'ok' : 'warn',
                (bool) ($scheduler['recent'] ?? false)
                    ? 'Scheduler heartbeat is recent.'
                    : 'Scheduler heartbeat is missing or stale.'
            );
        }

        $this->appendArchiveProtectionChecks($checks, $status);

        $this->appendDiskRootLocationCheck(
            $checks,
            'db_target_disk_root_policy',
            'DB target disk',
            (string) ($dbAuto['disk'] ?? '')
        );
        $this->appendDiskRootLocationCheck(
            $checks,
            'media_target_disk_root_policy',
            'Media target disk',
            (string) ($mediaAuto['target_disk'] ?? '')
        );
        $this->appendDiskRootLocationCheck(
            $checks,
            'full_manifest_disk_root_policy',
            'Full manifest disk',
            (string) ($fullAuto['manifest_disk'] ?? '')
        );

        if ($enabledMap['db'] || $enabledMap['full']) {
            $this->appendDumpBinaryPathCheck($checks, (string) ($status['db']['backup_connection'] ?? ''));
            $this->appendDbTransportSecurityCheck($checks, (string) ($status['db']['backup_connection'] ?? ''));
        }

        if ($enabledMap['db']) {
            $this->appendScheduleConfigurationCheck(
                $checks,
                'db_schedule',
                'DB automation',
                (string) ($dbAuto['interval'] ?? ''),
                (string) ($dbAuto['time'] ?? '')
            );
            $this->appendDiskAccessCheck(
                $checks,
                'db_target_disk',
                'DB target disk',
                (string) ($dbAuto['disk'] ?? ''),
                true
            );
            $this->appendDbConnectionCheck($checks, (string) ($status['db']['backup_connection'] ?? ''));
            $this->appendFreshnessCheck(
                $checks,
                'db_latest',
                'DB backup',
                (string) ($dbAuto['interval'] ?? 'daily'),
                is_array($status['db']['latest_backup'] ?? null) ? $status['db']['latest_backup'] : null
            );
        }

        if ($enabledMap['media']) {
            $this->appendScheduleConfigurationCheck(
                $checks,
                'media_schedule',
                'Media automation',
                (string) ($mediaAuto['interval'] ?? ''),
                (string) ($mediaAuto['time'] ?? '')
            );
            $this->appendDiskAccessCheck(
                $checks,
                'media_source_disk',
                'Media source disk',
                (string) ($status['media']['source_disk'] ?? ''),
                false
            );
            $this->appendDiskAccessCheck(
                $checks,
                'media_target_disk',
                'Media target disk',
                (string) ($mediaAuto['target_disk'] ?? ''),
                true
            );
            $this->appendFreshnessCheck(
                $checks,
                'media_latest',
                'Media backup',
                (string) ($mediaAuto['interval'] ?? 'daily'),
                is_array($status['media']['latest_manifest'] ?? null) ? $status['media']['latest_manifest'] : null
            );
        }

        if ($enabledMap['full']) {
            $this->appendScheduleConfigurationCheck(
                $checks,
                'full_schedule',
                'Full automation',
                (string) ($fullAuto['interval'] ?? ''),
                (string) ($fullAuto['time'] ?? '')
            );
            $this->appendDiskAccessCheck(
                $checks,
                'full_db_disk',
                'Full DB disk',
                (string) ($fullAuto['db_disk'] ?? ''),
                true
            );
            $this->appendDiskAccessCheck(
                $checks,
                'full_media_target_disk',
                'Full media target disk',
                (string) ($fullAuto['media_target_disk'] ?? ''),
                true
            );
            $this->appendDiskAccessCheck(
                $checks,
                'full_manifest_disk',
                'Full manifest disk',
                (string) ($fullAuto['manifest_disk'] ?? ''),
                true
            );

            $mediaPrefix = trim((string) ($fullAuto['media_prefix'] ?? ''), '/');
            $this->addHealthCheck(
                $checks,
                'full_media_prefix',
                $mediaPrefix !== '' ? 'ok' : 'error',
                $mediaPrefix !== ''
                    ? 'Full media prefix is configured.'
                    : 'Full media prefix is empty.'
            );

            $manifestPrefix = trim((string) ($fullAuto['manifest_prefix'] ?? ''), '/');
            $this->addHealthCheck(
                $checks,
                'full_manifest_prefix',
                $manifestPrefix !== '' ? 'ok' : 'error',
                $manifestPrefix !== ''
                    ? 'Full manifest prefix is configured.'
                    : 'Full manifest prefix is empty.'
            );

            $this->appendFreshnessCheck(
                $checks,
                'full_latest',
                'Full backup',
                (string) ($fullAuto['interval'] ?? 'daily'),
                is_array($status['full']['latest_manifest'] ?? null) ? $status['full']['latest_manifest'] : null
            );
            $this->appendFullManifestIntegrityCheck(
                $checks,
                is_array($status['full']['latest_manifest'] ?? null) ? $status['full']['latest_manifest'] : null
            );
        }

        $hasError = false;
        $hasWarn = false;
        foreach ($checks as $check) {
            $checkStatus = (string) ($check['status'] ?? '');
            if ($checkStatus === 'error') {
                $hasError = true;
                continue;
            }
            if ($checkStatus === 'warn') {
                $hasWarn = true;
            }
        }

        $overall = $hasError ? 'error' : ($hasWarn ? 'warn' : 'ok');

        return [
            'overall' => $overall,
            'automation_possible' => $enabledCount > 0 && !$hasError,
            'enabled_automations' => $enabledCount,
            'scheduler' => $scheduler,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function addHealthCheck(array &$checks, string $key, string $status, string $message): void
    {
        $normalized = in_array($status, ['ok', 'warn', 'error'], true) ? $status : 'warn';
        $checks[] = [
            'key' => $key,
            'status' => $normalized,
            'message' => $message,
        ];
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendScheduleConfigurationCheck(
        array &$checks,
        string $keyPrefix,
        string $label,
        string $interval,
        string $time
    ): void {
        $normalizedInterval = strtolower(trim($interval));
        $intervalValid = in_array($normalizedInterval, ['daily', 'weekly', 'monthly'], true);
        $this->addHealthCheck(
            $checks,
            $keyPrefix . '_interval',
            $intervalValid ? 'ok' : 'error',
            $intervalValid
                ? $label . " interval is set to '" . $normalizedInterval . "'."
                : $label . " interval is invalid: '" . trim($interval) . "'."
        );

        $timeTrimmed = trim($time);
        $timeValid = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeTrimmed) === 1;
        $this->addHealthCheck(
            $checks,
            $keyPrefix . '_time',
            $timeValid ? 'ok' : 'error',
            $timeValid
                ? $label . " time is set to '" . $timeTrimmed . "'."
                : $label . " time is invalid: '" . $timeTrimmed . "'."
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendDiskAccessCheck(
        array &$checks,
        string $key,
        string $label,
        string $diskName,
        bool $requireWritable
    ): void {
        $diskName = trim($diskName);
        if ($diskName === '') {
            $this->addHealthCheck($checks, $key, 'error', $label . ' is empty.');
            return;
        }

        $configuredDisks = (array) config('filesystems.disks', []);
        $diskConfig = $configuredDisks[$diskName] ?? null;
        if (!is_array($diskConfig)) {
            $this->addHealthCheck(
                $checks,
                $key,
                'error',
                $label . " references unknown disk '" . $diskName . "'."
            );
            return;
        }

        $driver = strtolower(trim((string) ($diskConfig['driver'] ?? '')));
        if ($driver === 'local') {
            $root = trim((string) ($diskConfig['root'] ?? ''));
            if ($root === '') {
                $this->addHealthCheck(
                    $checks,
                    $key,
                    'error',
                    $label . " disk '" . $diskName . "' has no root path configured."
                );
                return;
            }

            if (!is_dir($root)) {
                $this->addHealthCheck(
                    $checks,
                    $key,
                    'error',
                    $label . " root path does not exist: " . $root
                );
                return;
            }

            if (!is_readable($root)) {
                $this->addHealthCheck(
                    $checks,
                    $key,
                    'error',
                    $label . " root path is not readable: " . $root
                );
                return;
            }

            if ($requireWritable && !is_writable($root)) {
                $this->addHealthCheck(
                    $checks,
                    $key,
                    'error',
                    $label . " root path is not writable: " . $root
                );
                return;
            }

            $this->addHealthCheck(
                $checks,
                $key,
                'ok',
                $label . " disk '" . $diskName . "' is accessible."
            );
            return;
        }

        if ($driver === 's3') {
            $missing = [];
            foreach (['bucket', 'region', 'key', 'secret'] as $requiredKey) {
                if (trim((string) ($diskConfig[$requiredKey] ?? '')) === '') {
                    $missing[] = $requiredKey;
                }
            }

            if (!empty($missing)) {
                $this->addHealthCheck(
                    $checks,
                    $key,
                    'error',
                    $label . " disk '" . $diskName . "' is missing S3 config: " . implode(', ', $missing) . '.'
                );
                return;
            }

            $this->addHealthCheck(
                $checks,
                $key,
                'ok',
                $label . " disk '" . $diskName . "' has S3 credentials configured."
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            $key,
            'warn',
            $label . " disk '" . $diskName . "' uses driver '" . ($driver !== '' ? $driver : 'unknown') . "'."
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendDbConnectionCheck(array &$checks, string $connection): void
    {
        $connection = trim($connection);
        if ($connection === '') {
            $this->addHealthCheck($checks, 'db_connection', 'error', 'DB backup connection is empty.');
            return;
        }

        $connections = (array) config('database.connections', []);
        $connectionConfig = $connections[$connection] ?? null;
        if (!is_array($connectionConfig)) {
            $this->addHealthCheck(
                $checks,
                'db_connection',
                'error',
                "DB backup connection '" . $connection . "' does not exist in database.connections."
            );
            return;
        }

        $driver = trim((string) ($connectionConfig['driver'] ?? ''));
        if ($driver === '') {
            $this->addHealthCheck(
                $checks,
                'db_connection',
                'warn',
                "DB backup connection '" . $connection . "' has no driver configured."
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            'db_connection',
            'ok',
            "DB backup connection '" . $connection . "' is configured (driver: " . $driver . ').'
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     * @param array<string,mixed>|null $latest
     */
    protected function appendFreshnessCheck(
        array &$checks,
        string $key,
        string $label,
        string $interval,
        ?array $latest
    ): void {
        if (!is_array($latest) || trim((string) ($latest['path'] ?? '')) === '') {
            $this->addHealthCheck($checks, $key, 'warn', $label . ' has no recent artifact yet.');
            return;
        }

        $ageHours = $latest['age_hours'] ?? null;
        if (!is_numeric($ageHours)) {
            $this->addHealthCheck($checks, $key, 'warn', $label . ' age is currently unknown.');
            return;
        }

        $ageValue = (int) $ageHours;
        $maxAge = $this->maxExpectedAgeHours($interval);

        if ($ageValue > $maxAge) {
            $this->addHealthCheck(
                $checks,
                $key,
                'warn',
                $label . ' looks stale (' . $ageValue . 'h old, expected <= ' . $maxAge . 'h).'
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            $key,
            'ok',
            $label . ' freshness is within expected range (' . $ageValue . 'h old).'
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     * @param array<string,mixed> $status
     */
    protected function appendArchiveProtectionChecks(array &$checks, array $status): void
    {
        $passwordConfigured = (bool) ($status['db']['archive_password_configured'] ?? false);
        $archiveEncryption = is_array($status['db']['archive_encryption'] ?? null)
            ? (array) $status['db']['archive_encryption']
            : [];
        $encryptionConfigured = (bool) ($archiveEncryption['configured'] ?? false);
        $encryptionValue = trim((string) ($archiveEncryption['value'] ?? ''));

        $requirePassword = (bool) config('backup.security.require_archive_password', false);
        $requireEncryption = (bool) config('backup.security.require_archive_encryption', false);

        if ($passwordConfigured) {
            $this->addHealthCheck(
                $checks,
                'backup_archive_password',
                'ok',
                'Backup archive password is configured.'
            );
        } else {
            $this->addHealthCheck(
                $checks,
                'backup_archive_password',
                $requirePassword ? 'error' : 'warn',
                $requirePassword
                    ? 'Backup archive password is missing but required by policy.'
                    : 'Backup archive password is not configured.'
            );
        }

        if ($encryptionConfigured) {
            $this->addHealthCheck(
                $checks,
                'backup_archive_encryption',
                'ok',
                "Backup archive encryption is enabled ('" . ($encryptionValue !== '' ? $encryptionValue : 'default') . "')."
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            'backup_archive_encryption',
            $requireEncryption ? 'error' : 'warn',
            $requireEncryption
                ? 'Backup archive encryption is disabled but required by policy.'
                : 'Backup archive encryption is disabled.'
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendDiskRootLocationCheck(
        array &$checks,
        string $key,
        string $label,
        string $diskName
    ): void {
        $diskName = trim($diskName);
        if ($diskName === '') {
            return;
        }

        $configuredDisks = (array) config('filesystems.disks', []);
        $diskConfig = $configuredDisks[$diskName] ?? null;
        if (!is_array($diskConfig)) {
            return;
        }

        $driver = strtolower(trim((string) ($diskConfig['driver'] ?? '')));
        if ($driver !== 'local') {
            $this->addHealthCheck(
                $checks,
                $key,
                'ok',
                $label . " disk '" . $diskName . "' uses '" . ($driver !== '' ? $driver : 'unknown') . "' driver."
            );
            return;
        }

        $root = trim((string) ($diskConfig['root'] ?? ''));
        if ($root === '') {
            return;
        }

        $projectRoot = realpath(base_path());
        $diskRoot = realpath($root);
        if (!is_string($projectRoot) || $projectRoot === '') {
            return;
        }

        $effectiveDiskRoot = (is_string($diskRoot) && $diskRoot !== '') ? $diskRoot : $root;
        $projectPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $insideProject = $effectiveDiskRoot === $projectRoot
            || str_starts_with($effectiveDiskRoot, $projectPrefix);

        $requireOutside = (bool) config('backup.security.require_outside_project_root', false);

        if ($insideProject && $requireOutside) {
            $this->addHealthCheck(
                $checks,
                $key,
                'error',
                $label . " root path is inside project root but policy requires external storage: " . $effectiveDiskRoot
            );
            return;
        }

        if ($insideProject) {
            $this->addHealthCheck(
                $checks,
                $key,
                'warn',
                $label . " root path is inside project root: " . $effectiveDiskRoot
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            $key,
            'ok',
            $label . " root path is outside project root: " . $effectiveDiskRoot
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendDumpBinaryPathCheck(array &$checks, string $connection): void
    {
        $requireDumpBinaryPath = (bool) config('backup.security.require_dump_binary_path', false);
        $connection = trim($connection);
        if ($connection === '') {
            return;
        }

        $connectionConfig = config('database.connections.' . $connection);
        if (!is_array($connectionConfig)) {
            return;
        }

        $driver = strtolower(trim((string) ($connectionConfig['driver'] ?? '')));
        if ($driver !== 'mysql') {
            $this->addHealthCheck(
                $checks,
                'db_dump_binary_path',
                'ok',
                "DB dump binary path check skipped for driver '" . ($driver !== '' ? $driver : 'unknown') . "'."
            );
            return;
        }

        $dump = is_array($connectionConfig['dump'] ?? null) ? (array) $connectionConfig['dump'] : [];
        $dumpPath = trim((string) ($dump['dump_binary_path'] ?? ''));

        if ($dumpPath === '') {
            $this->addHealthCheck(
                $checks,
                'db_dump_binary_path',
                $requireDumpBinaryPath ? 'error' : 'warn',
                $requireDumpBinaryPath
                    ? 'DB dump binary path is missing but required by policy (DB_DUMP_BINARY_PATH).'
                    : 'DB dump binary path is not configured (DB_DUMP_BINARY_PATH).'
            );
            return;
        }

        $candidate = rtrim($dumpPath, '/\\') . DIRECTORY_SEPARATOR . 'mysqldump';
        if (is_file($candidate) && is_executable($candidate)) {
            $this->addHealthCheck(
                $checks,
                'db_dump_binary_path',
                'ok',
                'DB dump binary resolved: ' . $candidate
            );
            return;
        }

        if (is_file($dumpPath) && is_executable($dumpPath)) {
            $this->addHealthCheck(
                $checks,
                'db_dump_binary_path',
                'ok',
                'DB dump binary resolved: ' . $dumpPath
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            'db_dump_binary_path',
            $requireDumpBinaryPath ? 'error' : 'warn',
            'Configured DB dump binary path is not executable: ' . $dumpPath
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    protected function appendDbTransportSecurityCheck(array &$checks, string $connection): void
    {
        $requireTls = (bool) config('backup.security.db_require_tls', false);
        $connection = trim($connection);
        if ($connection === '') {
            return;
        }

        $connectionConfig = config('database.connections.' . $connection);
        if (!is_array($connectionConfig)) {
            return;
        }

        $driver = strtolower(trim((string) ($connectionConfig['driver'] ?? '')));
        if ($driver === 'mysql') {
            $socket = trim((string) ($connectionConfig['unix_socket'] ?? ''));
            if ($socket !== '') {
                $this->addHealthCheck(
                    $checks,
                    'db_transport_tls',
                    'ok',
                    "DB connection uses unix socket '" . $socket . "' (local transport)."
                );
                return;
            }

            $security = is_array($connectionConfig['security'] ?? null) ? (array) $connectionConfig['security'] : [];
            $sslCa = trim((string) ($security['ssl_ca'] ?? ''));
            $sslCert = trim((string) ($security['ssl_cert'] ?? ''));
            $sslKey = trim((string) ($security['ssl_key'] ?? ''));
            $hasTlsMaterial = $sslCa !== '' || $sslCert !== '' || $sslKey !== '';

            if ($hasTlsMaterial) {
                $this->addHealthCheck(
                    $checks,
                    'db_transport_tls',
                    'ok',
                    'DB TLS material is configured (DB_SSL_CA/DB_SSL_CERT/DB_SSL_KEY).'
                );
                return;
            }

            $this->addHealthCheck(
                $checks,
                'db_transport_tls',
                $requireTls ? 'error' : 'warn',
                $requireTls
                    ? 'DB TLS is required by policy but no TLS material is configured.'
                    : 'DB TLS material is not configured.'
            );
            return;
        }

        if ($driver === 'pgsql') {
            $sslmode = strtolower(trim((string) ($connectionConfig['sslmode'] ?? 'prefer')));
            $strictModes = ['require', 'verify-ca', 'verify-full'];
            $isStrict = in_array($sslmode, $strictModes, true);

            $this->addHealthCheck(
                $checks,
                'db_transport_tls',
                ($requireTls && !$isStrict) ? 'error' : 'ok',
                ($requireTls && !$isStrict)
                    ? "DB TLS is required by policy, but pgsql sslmode is '" . $sslmode . "'."
                    : "DB pgsql sslmode is '" . $sslmode . "'."
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            'db_transport_tls',
            'warn',
            "DB TLS policy check is not implemented for driver '" . ($driver !== '' ? $driver : 'unknown') . "'."
        );
    }

    /**
     * @param array<int,array<string,string>> $checks
     * @param array<string,mixed>|null $latestManifestMeta
     */
    protected function appendFullManifestIntegrityCheck(array &$checks, ?array $latestManifestMeta): void
    {
        if (!is_array($latestManifestMeta)) {
            return;
        }

        $manifest = is_array($latestManifestMeta['manifest'] ?? null)
            ? (array) $latestManifestMeta['manifest']
            : null;
        if (!is_array($manifest)) {
            $this->addHealthCheck(
                $checks,
                'full_manifest_integrity',
                'warn',
                'Full backup manifest payload is missing or unreadable.'
            );
            return;
        }

        $overallOk = $manifest['overall_ok'] ?? null;
        if ($overallOk === true) {
            $this->addHealthCheck(
                $checks,
                'full_manifest_integrity',
                'ok',
                'Full backup manifest reports overall_ok=true.'
            );
            return;
        }

        if ($overallOk === false) {
            $this->addHealthCheck(
                $checks,
                'full_manifest_integrity',
                'error',
                'Full backup manifest reports overall_ok=false.'
            );
            return;
        }

        $this->addHealthCheck(
            $checks,
            'full_manifest_integrity',
            'warn',
            'Full backup manifest does not contain overall_ok.'
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected function databaseSecuritySummary(string $connection): array
    {
        $summary = [
            'require_tls' => (bool) config('backup.security.db_require_tls', false),
            'dump_binary_path' => null,
            'dump_binary_resolved' => false,
            'using_unix_socket' => false,
            'unix_socket' => null,
            'tls_material_configured' => false,
        ];

        $connection = trim($connection);
        if ($connection === '') {
            return $summary;
        }

        $connectionConfig = config('database.connections.' . $connection);
        if (!is_array($connectionConfig)) {
            return $summary;
        }

        $dump = is_array($connectionConfig['dump'] ?? null) ? (array) $connectionConfig['dump'] : [];
        $dumpPath = trim((string) ($dump['dump_binary_path'] ?? ''));
        if ($dumpPath !== '') {
            $summary['dump_binary_path'] = $dumpPath;
            $candidate = rtrim($dumpPath, '/\\') . DIRECTORY_SEPARATOR . 'mysqldump';
            $summary['dump_binary_resolved'] = (is_file($candidate) && is_executable($candidate))
                || (is_file($dumpPath) && is_executable($dumpPath));
        }

        $socket = trim((string) ($connectionConfig['unix_socket'] ?? ''));
        if ($socket !== '') {
            $summary['using_unix_socket'] = true;
            $summary['unix_socket'] = $socket;
        }

        $security = is_array($connectionConfig['security'] ?? null) ? (array) $connectionConfig['security'] : [];
        $sslCa = trim((string) ($security['ssl_ca'] ?? ''));
        $sslCert = trim((string) ($security['ssl_cert'] ?? ''));
        $sslKey = trim((string) ($security['ssl_key'] ?? ''));
        $summary['tls_material_configured'] = $sslCa !== '' || $sslCert !== '' || $sslKey !== '';

        return $summary;
    }

    protected function archiveEncryptionEnabled($value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));
        return !in_array($normalized, ['', 'none', 'null', 'false'], true);
    }

    protected function maxExpectedAgeHours(string $interval): int
    {
        $normalized = strtolower(trim($interval));
        if ($normalized === 'weekly') {
            return (8 * 24) + 12;
        }
        if ($normalized === 'monthly') {
            return (35 * 24) + 12;
        }

        return 36;
    }

    /**
     * @return array<string,mixed>
     */
    protected function schedulerHeartbeatStatus(): array
    {
        $configuredPath = trim((string) config('backup.health.scheduler_heartbeat_path', ''));
        $heartbeatPath = $configuredPath !== '' ? $configuredPath : storage_path('app/backup-scheduler-heartbeat.json');
        $staleAfterMinutes = max(1, (int) config('backup.health.scheduler_heartbeat_stale_minutes', 5));

        $result = [
            'path' => $heartbeatPath,
            'stale_after_minutes' => $staleAfterMinutes,
            'present' => false,
            'last_heartbeat_utc' => null,
            'age_minutes' => null,
            'recent' => false,
        ];

        if (!is_file($heartbeatPath)) {
            return $result;
        }

        $result['present'] = true;

        try {
            $raw = @file_get_contents($heartbeatPath);
            if (!is_string($raw) || trim($raw) === '') {
                return $result;
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                return $result;
            }

            $heartbeatTs = 0;
            if (is_numeric($payload['updated_at_unix'] ?? null)) {
                $heartbeatTs = (int) $payload['updated_at_unix'];
            }

            if ($heartbeatTs <= 0 && is_string($payload['updated_at_utc'] ?? null)) {
                $parsed = strtotime((string) $payload['updated_at_utc']);
                if ($parsed !== false) {
                    $heartbeatTs = (int) $parsed;
                }
            }

            if ($heartbeatTs <= 0) {
                return $result;
            }

            $lastBeat = CarbonImmutable::createFromTimestamp($heartbeatTs, 'UTC');
            $ageMinutes = $lastBeat->diffInMinutes(CarbonImmutable::now('UTC'));

            $result['last_heartbeat_utc'] = $lastBeat->toIso8601String();
            $result['age_minutes'] = $ageMinutes;
            $result['recent'] = $ageMinutes <= $staleAfterMinutes;
        } catch (Throwable) {
            // Keep best-effort heartbeat status.
        }

        return $result;
    }

    /**
     * @param callable(string):bool|null $filter
     * @return array<string,mixed>|null
     */
    protected function latestFileMeta(string $disk, ?callable $filter = null): ?array
    {
        $files = $this->listDiskFileAttributes($disk);
        if ($files === null) {
            return [
                'disk' => $disk,
                'path' => null,
                'error' => 'Unable to list disk contents.',
            ];
        }

        $candidates = [];
        foreach ($files as $file) {
            $path = $file->path();
            if ($filter && !$filter($path)) {
                continue;
            }

            $modified = $file->lastModified();
            if ($modified === null) {
                continue;
            }

            $candidates[] = [
                'path' => $path,
                'modified' => (int) $modified,
                'size_bytes' => (int) ($file->fileSize() ?? 0),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);
        $latest = $candidates[0];

        return [
            'disk' => $disk,
            'path' => $latest['path'],
            'size_bytes' => $latest['size_bytes'],
            'modified_at_utc' => CarbonImmutable::createFromTimestamp($latest['modified'], 'UTC')->toIso8601String(),
            'age_hours' => CarbonImmutable::createFromTimestamp($latest['modified'], 'UTC')
                ->diffInHours(CarbonImmutable::now('UTC')),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function latestManifestMeta(string $disk, string $prefix): ?array
    {
        $files = $this->listDiskFileAttributes($disk, $prefix);
        if ($files === null) {
            return [
                'disk' => $disk,
                'path' => null,
                'error' => 'Unable to list disk contents.',
            ];
        }

        $manifests = [];
        foreach ($files as $file) {
            $path = $file->path();
            $modified = $file->lastModified();
            if (str_ends_with($path, '/manifest.json') && $modified !== null) {
                $manifests[] = [
                    'path' => $path,
                    'modified' => (int) $modified,
                ];
            }
        }

        if (empty($manifests)) {
            return null;
        }

        usort($manifests, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        $latestPath = $manifests[0]['path'];
        $latestTs = (int) $manifests[0]['modified'];
        $result = [
            'disk' => $disk,
            'path' => $latestPath,
            'modified_at_utc' => CarbonImmutable::createFromTimestamp($latestTs, 'UTC')->toIso8601String(),
            'age_hours' => CarbonImmutable::createFromTimestamp($latestTs, 'UTC')
                ->diffInHours(CarbonImmutable::now('UTC')),
        ];

        try {
            $manifestRaw = Storage::disk($disk)->get($latestPath);
            $manifest = json_decode((string) $manifestRaw, true);
            if (is_array($manifest)) {
                $result['manifest'] = $manifest;
            }
        } catch (Throwable) {
            // Manifest parse issues are non-fatal for status output.
        }

        return $result;
    }

    /**
     * @return array<int,FileAttributes>|null
     */
    protected function listDiskFileAttributes(string $disk, string $prefix = ''): ?array
    {
        try {
            $items = [];
            foreach (Storage::disk($disk)->getDriver()->listContents($prefix, true) as $item) {
                if ($item instanceof FileAttributes) {
                    $items[] = $item;
                }
            }

            return $items;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function storageDiskSummary(): array
    {
        $configured = (array) config('filesystems.disks', []);
        $summary = [];

        foreach ($configured as $name => $diskConfig) {
            if (!is_array($diskConfig)) {
                continue;
            }

            $summary[] = [
                'name' => (string) $name,
                'driver' => (string) ($diskConfig['driver'] ?? 'unknown'),
                'root' => (string) ($diskConfig['root'] ?? ''),
                'bucket' => (string) ($diskConfig['bucket'] ?? ''),
            ];
        }

        return $summary;
    }
}
