<?php

namespace App\Console\Commands\Mod;

use GeoSot\EnvEditor\Facades\EnvEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class BackupConfigureCommand extends BaseModCommand
{
    protected $signature = 'backup:configure
        {--target-profile= : local|s3}
        {--apply-profile-defaults : Set sensible disk defaults for selected profile}
        {--db-auto= : true|false}
        {--db-interval= : daily|weekly|monthly}
        {--db-time= : HH:MM}
        {--db-disk= : Storage disk}
        {--db-connection= : mysql|sqlite|pgsql...}
        {--media-auto= : true|false}
        {--media-interval= : daily|weekly|monthly}
        {--media-time= : HH:MM}
        {--media-target-disk= : Storage disk}
        {--media-prefix= : Prefix path}
        {--media-health-max-age-hours= : Positive integer}
        {--media-restore-drill= : true|false}
        {--media-restore-drill-day= : 1..28}
        {--media-restore-drill-time= : HH:MM}
        {--media-restore-drill-sample= : >=1}
        {--full-auto= : true|false}
        {--full-interval= : daily|weekly|monthly}
        {--full-time= : HH:MM}
        {--full-db-disk= : Storage disk}
        {--full-media-target-disk= : Storage disk}
        {--full-media-prefix= : Prefix path}
        {--full-manifest-disk= : Storage disk}
        {--full-prefix= : Prefix path}
        {--alert-webhook-url= : Optional webhook URL (empty to clear)}
        {--json : Ausgabe als JSON}';

    protected $description = 'Setzt Backup-Automation und Zielkonfiguration in der ENV-Datei.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        try {
            $changes = $this->collectChanges();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $changedKeys = [];
        foreach ($changes as $key => $value) {
            $this->upsertEnv($key, $value);
            $changedKeys[] = $key;
        }

        $configCleared = false;
        try {
            Artisan::call('config:clear');
            $configCleared = true;
        } catch (\Throwable) {
            $configCleared = false;
        }

        $payload = [
            'ok' => true,
            'changed_keys' => $changedKeys,
            'changed_count' => count($changedKeys),
            'config_cleared' => $configCleared,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($changedKeys)) {
            $this->info('Keine Änderungen angefordert.');
        } else {
            $this->info('Backup-Konfiguration aktualisiert.');
            $this->line('Geänderte Keys: ' . implode(', ', $changedKeys));
        }

        $this->line('Config cache cleared: ' . $this->boolLabel($configCleared));
        return Command::SUCCESS;
    }

    /**
     * @return array<string,string>
     */
    protected function collectChanges(): array
    {
        $changes = [];

        $targetProfile = $this->optionalTrim('target-profile');
        if ($targetProfile !== null) {
            $normalizedProfile = strtolower($targetProfile);
            if (!in_array($normalizedProfile, ['local', 's3'], true)) {
                throw new InvalidArgumentException('target-profile muss local oder s3 sein.');
            }

            $changes['BACKUP_TARGET_PROFILE'] = $normalizedProfile;

            if ((bool) $this->option('apply-profile-defaults')) {
                $profileDisk = $normalizedProfile === 's3' ? 's3' : 'backups';
                $changes['BACKUP_DB_DISK'] = $profileDisk;
                $changes['MEDIA_BACKUP_TARGET_DISK'] = $profileDisk;
                $changes['BACKUP_FULL_DB_DISK'] = $profileDisk;
                $changes['BACKUP_FULL_MEDIA_TARGET_DISK'] = $profileDisk;
                $changes['BACKUP_FULL_MANIFEST_DISK'] = $profileDisk;
            }
        }

        $this->mapBoolOption($changes, 'db-auto', 'BACKUP_DB_AUTOMATED_ENABLED');
        $this->mapBoolOption($changes, 'media-auto', 'MEDIA_AUTOMATED_BACKUP_ENABLED');
        $this->mapBoolOption($changes, 'media-restore-drill', 'MEDIA_RESTORE_DRILL_ENABLED');
        $this->mapBoolOption($changes, 'full-auto', 'BACKUP_FULL_AUTOMATED_ENABLED');
        $this->mapEnumOption($changes, 'db-interval', 'BACKUP_DB_AUTOMATED_INTERVAL', ['daily', 'weekly', 'monthly']);
        $this->mapEnumOption($changes, 'media-interval', 'MEDIA_AUTOMATED_BACKUP_INTERVAL', ['daily', 'weekly', 'monthly']);
        $this->mapEnumOption($changes, 'full-interval', 'BACKUP_FULL_AUTOMATED_INTERVAL', ['daily', 'weekly', 'monthly']);

        $this->mapTimeOption($changes, 'db-time', 'BACKUP_DB_AUTOMATED_TIME');
        $this->mapTimeOption($changes, 'media-time', 'MEDIA_AUTOMATED_BACKUP_TIME');
        $this->mapTimeOption($changes, 'media-restore-drill-time', 'MEDIA_RESTORE_DRILL_TIME');
        $this->mapTimeOption($changes, 'full-time', 'BACKUP_FULL_AUTOMATED_TIME');

        $this->mapDiskOption($changes, 'db-disk', 'BACKUP_DB_DISK');
        $this->mapDiskOption($changes, 'media-target-disk', 'MEDIA_BACKUP_TARGET_DISK');
        $this->mapDiskOption($changes, 'full-db-disk', 'BACKUP_FULL_DB_DISK');
        $this->mapDiskOption($changes, 'full-media-target-disk', 'BACKUP_FULL_MEDIA_TARGET_DISK');
        $this->mapDiskOption($changes, 'full-manifest-disk', 'BACKUP_FULL_MANIFEST_DISK');

        $this->mapGenericOption($changes, 'db-connection', 'BACKUP_DB_CONNECTION', 32);
        $this->mapPrefixOption($changes, 'media-prefix', 'MEDIA_BACKUP_PREFIX');
        $this->mapPrefixOption($changes, 'full-media-prefix', 'BACKUP_FULL_MEDIA_PREFIX');
        $this->mapPrefixOption($changes, 'full-prefix', 'BACKUP_FULL_PREFIX');

        $this->mapIntOption($changes, 'media-health-max-age-hours', 'MEDIA_BACKUP_HEALTH_MAX_AGE_HOURS', 1, 24 * 30);
        $this->mapIntOption($changes, 'media-restore-drill-day', 'MEDIA_RESTORE_DRILL_DAY_OF_MONTH', 1, 28);
        $this->mapIntOption($changes, 'media-restore-drill-sample', 'MEDIA_RESTORE_DRILL_SAMPLE_FILES', 1, 5000);

        if (array_key_exists('alert-webhook-url', $this->options())) {
            $url = $this->option('alert-webhook-url');
            if ($url !== null) {
                $value = trim((string) $url);
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('alert-webhook-url ist keine gültige URL.');
                }

                $changes['MEDIA_ALERT_WEBHOOK_URL'] = $value;
                $changes['BACKUP_ALERT_WEBHOOK_URL'] = $value;
            }
        }

        return $changes;
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapBoolOption(array &$changes, string $option, string $envKey): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        $normalized = strtolower($raw);
        $truthy = ['1', 'true', 'yes', 'on', 'enabled', 'enable'];
        $falsy = ['0', 'false', 'no', 'off', 'disabled', 'disable'];

        if (in_array($normalized, $truthy, true)) {
            $changes[$envKey] = 'true';
            return;
        }

        if (in_array($normalized, $falsy, true)) {
            $changes[$envKey] = 'false';
            return;
        }

        throw new InvalidArgumentException("Option --{$option} muss true/false sein.");
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapTimeOption(array &$changes, string $option, string $envKey): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) !== 1) {
            throw new InvalidArgumentException("Option --{$option} muss im Format HH:MM sein.");
        }

        $changes[$envKey] = $raw;
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapDiskOption(array &$changes, string $option, string $envKey): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $raw) !== 1) {
            throw new InvalidArgumentException("Option --{$option} enthält ein ungültiges Disk-Format.");
        }

        $changes[$envKey] = $raw;
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapPrefixOption(array &$changes, string $option, string $envKey): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        $normalized = trim($raw, '/');
        if ($normalized === '') {
            throw new InvalidArgumentException("Option --{$option} darf nicht leer sein.");
        }

        if (preg_match('/^[A-Za-z0-9._\-\/]{1,255}$/', $normalized) !== 1) {
            throw new InvalidArgumentException("Option --{$option} enthält ungültige Zeichen.");
        }

        $changes[$envKey] = $normalized;
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapIntOption(
        array &$changes,
        string $option,
        string $envKey,
        int $min,
        int $max
    ): void {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        if (preg_match('/^\d+$/', $raw) !== 1) {
            throw new InvalidArgumentException("Option --{$option} muss eine positive Ganzzahl sein.");
        }

        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Option --{$option} muss zwischen {$min} und {$max} liegen.");
        }

        $changes[$envKey] = (string) $value;
    }

    /**
     * @param array<string,string> $changes
     */
    protected function mapGenericOption(array &$changes, string $option, string $envKey, int $maxLength): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        if (mb_strlen($raw) > $maxLength) {
            throw new InvalidArgumentException("Option --{$option} überschreitet die maximale Länge.");
        }

        $changes[$envKey] = $raw;
    }

    /**
     * @param array<string,string> $changes
     * @param array<int,string> $allowed
     */
    protected function mapEnumOption(array &$changes, string $option, string $envKey, array $allowed): void
    {
        $raw = $this->optionalTrim($option);
        if ($raw === null) {
            return;
        }

        $normalized = strtolower($raw);
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException(
                "Option --{$option} muss einer der folgenden Werte sein: " . implode(', ', $allowed) . '.'
            );
        }

        $changes[$envKey] = $normalized;
    }

    protected function optionalTrim(string $option): ?string
    {
        $raw = $this->option($option);
        if ($raw === null) {
            return null;
        }

        return trim((string) $raw);
    }

    protected function upsertEnv(string $key, string $value): void
    {
        if (EnvEditor::keyExists($key)) {
            EnvEditor::editKey($key, $value);
            return;
        }

        EnvEditor::addKey($key, $value);
    }
}
