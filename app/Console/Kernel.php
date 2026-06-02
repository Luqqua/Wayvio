<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\Mod\UserListCommand::class,
        \App\Console\Commands\Mod\UserViewCommand::class,
        \App\Console\Commands\Mod\UserUpdateStateCommand::class,
        \App\Console\Commands\Mod\UserDeleteCommand::class,
        \App\Console\Commands\Mod\UserLinksCommand::class,
        \App\Console\Commands\Mod\RegistrationSetCommand::class,
        \App\Console\Commands\Mod\HubsListCommand::class,
        \App\Console\Commands\Mod\LinkToggleCommand::class,
        \App\Console\Commands\Mod\SubscriptionListCommand::class,
        \App\Console\Commands\Mod\SubscriptionUpdateCommand::class,
        \App\Console\Commands\Mod\BackupCreateCommand::class,
        \App\Console\Commands\Mod\BackupListCommand::class,
        \App\Console\Commands\Mod\BackupRestoreCommand::class,
        \App\Console\Commands\Mod\TemplateListCommand::class,
        \App\Console\Commands\Mod\TemplateAssignCommand::class,
        \App\Console\Commands\Mod\ThemeListCommand::class,
        \App\Console\Commands\Mod\ThemeAssignCommand::class,
        \App\Console\Commands\Mod\TierSetCommand::class,
        \App\Console\Commands\Mod\RoleSetCommand::class,
        \App\Console\Commands\Mod\AnalyticsViewCommand::class,
        \App\Console\Commands\Mod\ComplianceReportCommand::class,
        \App\Console\Commands\Mod\SystemStatusCommand::class,
        \App\Console\Commands\Mod\ReportListCommand::class,
        \App\Console\Commands\Mod\ReportNewestCommand::class,
        \App\Console\Commands\Mod\ReportUserCommand::class,
        \App\Console\Commands\Mod\ReportDeleteCommand::class,
        \App\Console\Commands\Mod\ReportProcessedCommand::class,
        \App\Console\Commands\Mod\ReportCommentCommand::class,
        \App\Console\Commands\Mod\BrandingSetSiteAssetCommand::class,
        \App\Console\Commands\ReportsPruneCommand::class,
        \App\Console\Commands\LegalRequireCurrentCommand::class,
        \App\Console\Commands\TiersSyncCommand::class,
        \App\Console\Commands\SettingsSingleSourceCommand::class,
        \App\Console\Commands\FormsPruneCommand::class,
        \App\Console\Commands\PrivacyPruneOperationalDataCommand::class,
        \App\Console\Commands\BillingReconcileCheckCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $this->writeBackupSchedulerHeartbeat();

        $schedule->command('billing:send-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('check_payment_status')->daily()->withoutOverlapping()
            ->onFailure(function (): void {
                $this->notifyJobFailure('check_payment_status', 'Scheduled check_payment_status failed.');
            });
        $schedule->command('check_grace_periods')->daily()->withoutOverlapping()
            ->onFailure(function (): void {
                $this->notifyJobFailure('check_grace_periods', 'Scheduled check_grace_periods failed.');
            });
        $schedule->command('check_suspended_resources')->daily()->withoutOverlapping()
            ->onFailure(function (): void {
                $this->notifyJobFailure('check_suspended_resources', 'Scheduled check_suspended_resources failed.');
            });
        $schedule->command('check_pending_deletions')->daily()->withoutOverlapping()
            ->onFailure(function (): void {
                $this->notifyJobFailure('check_pending_deletions', 'Scheduled check_pending_deletions failed.');
            });
        if ((bool) config('billing.lifecycle.deleted_account_purge_enabled', false)) {
            $schedule->command('lifecycle:purge-deleted-accounts')->daily()->withoutOverlapping()
                ->onFailure(function (): void {
                    $this->notifyJobFailure('lifecycle:purge-deleted-accounts', 'Scheduled lifecycle:purge-deleted-accounts failed.');
                });
        }

        $schedule->command('billing:reconcile-check')->dailyAt('00:30')->withoutOverlapping()
            ->onFailure(function (): void {
                $this->notifyJobFailure('billing:reconcile-check', 'Scheduled billing:reconcile-check failed.');
            });

        if ((bool) config('partners.auto_approve_commissions', false)) {
            $schedule->command('partners:approve-commissions')->hourly()->withoutOverlapping();
        }

        $schedule->call(function (): void {
            if (Schema::hasTable('job_run_log')) {
                DB::table('job_run_log')->where('created_at', '<', now()->subDays(90))->delete();
            }
        })->daily()->name('job_run_log:prune');

        $auditLogRetentionDays = (int) config('compliance.audit_log_retention_days', 365);
        if ($auditLogRetentionDays > 0) {
            $schedule
                ->command('compliance:prune-audit-log')
                ->daily()
                ->withoutOverlapping();
        }

        $schedule
            ->command('privacy:prune-operational-data')
            ->daily()
            ->withoutOverlapping();

        $retentionDays = (int) config('reports.retention_days', 180);
        if ($retentionDays > 0) {
            $schedule
                ->command('reports:prune --days=' . $retentionDays)
                ->daily()
                ->withoutOverlapping();
        }

        if ((bool) config('forms.enabled', true) && (string) config('forms.api_key') !== '') {
            $schedule
                ->command('forms:prune')
                ->daily()
                ->withoutOverlapping();
        }

        if ((bool) config('backup.automation.db.enabled', false)) {
            $dbInterval = $this->normalizedBackupInterval((string) config('backup.automation.db.interval', 'daily'));
            $dbTime = (string) config('backup.automation.db.time', '02:30');
            $dbDisk = (string) config('backup.automation.db.disk', 'backups');

            $dbEvent = $schedule
                ->command('backup:run', [
                    '--only-db' => true,
                    '--only-to-disk' => $dbDisk,
                    '--disable-notifications' => true,
                ])
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled DB backup:run failed.');
                });
            $this->applyBackupInterval($dbEvent, $dbInterval, $dbTime);

            $cleanupTime = $this->timeWithAddedMinutes($dbTime, 20, '02:50');
            $cleanupEvent = $schedule
                ->command('backup:clean', ['--disable-notifications' => true])
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled backup:clean failed.');
                });
            $this->applyBackupInterval($cleanupEvent, $dbInterval, $cleanupTime);
        }

        if ((bool) config('media.automated_backup_enabled', false)) {
            $mediaInterval = $this->normalizedBackupInterval((string) config('media.automated_backup_interval', 'daily'));
            $backupTime = (string) config('media.automated_backup_time', '03:15');
            $targetDisk = (string) config('media.backup_target_disk', 'backups');
            $mediaBackupEvent = $schedule
                ->command('media:backup', [
                    '--target-disk' => $targetDisk,
                ])
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled media:backup failed.');
                });
            $this->applyBackupInterval($mediaBackupEvent, $mediaInterval, $backupTime);

            $healthTime = $this->timeWithAddedMinutes($backupTime, 30, '03:45');
            $maxAgeHours = max(1, (int) config('media.backup_health_max_age_hours', 30));
            $mediaHealthEvent = $schedule
                ->command('media:backup-health', [
                    '--disk' => $targetDisk,
                    '--max-age-hours' => $maxAgeHours,
                ])
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled media:backup-health failed.');
                });
            $this->applyBackupInterval($mediaHealthEvent, $mediaInterval, $healthTime);
        }

        if ((bool) config('media.restore_drill_enabled', false)) {
            $dayOfMonth = max(1, min(28, (int) config('media.restore_drill_day_of_month', 1)));
            $drillTime = (string) config('media.restore_drill_time', '04:15');
            $sampleFiles = max(1, (int) config('media.restore_drill_sample_files', 20));
            $targetDisk = (string) config('media.backup_target_disk', 'backups');

            $schedule
                ->command('media:restore-drill', [
                    '--source-disk' => $targetDisk,
                    '--sample' => $sampleFiles,
                ])
                ->monthlyOn($dayOfMonth, $drillTime)
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled media:restore-drill failed.');
                });
        }

        if ((bool) config('backup.automation.full.enabled', false)) {
            $fullInterval = $this->normalizedBackupInterval((string) config('backup.automation.full.interval', 'daily'));
            $fullTime = (string) config('backup.automation.full.time', '03:30');

            $fullEvent = $schedule
                ->command('backup:run-full')
                ->withoutOverlapping()
                ->onFailure(function () {
                    $this->notifyBackupFailure('Scheduled backup:run-full failed.');
                });
            $this->applyBackupInterval($fullEvent, $fullInterval, $fullTime);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected function timeWithAddedMinutes(string $time, int $minutes, string $fallback): string
    {
        $parts = explode(':', trim($time));
        if (count($parts) < 2) {
            return $fallback;
        }

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return $fallback;
        }

        $totalMinutes = ($hour * 60) + $minute + max(0, $minutes);
        $wrapped = $totalMinutes % (24 * 60);
        $newHour = intdiv($wrapped, 60);
        $newMinute = $wrapped % 60;

        return sprintf('%02d:%02d', $newHour, $newMinute);
    }

    protected function normalizedBackupInterval(string $interval): string
    {
        $normalized = strtolower(trim($interval));
        if (in_array($normalized, ['daily', 'weekly', 'monthly'], true)) {
            return $normalized;
        }

        return 'daily';
    }

    protected function applyBackupInterval($event, string $interval, string $time): void
    {
        if ($interval === 'weekly') {
            $event->weeklyOn(0, $time);
            return;
        }

        if ($interval === 'monthly') {
            $event->monthlyOn(1, $time);
            return;
        }

        $event->dailyAt($time);
    }

    protected function writeBackupSchedulerHeartbeat(): void
    {
        $configuredPath = trim((string) config('backup.health.scheduler_heartbeat_path', ''));
        $heartbeatPath = $configuredPath !== '' ? $configuredPath : storage_path('app/backup-scheduler-heartbeat.json');

        try {
            $directory = dirname($heartbeatPath);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                return;
            }

            $payload = [
                'updated_at_utc' => now('UTC')->toIso8601String(),
                'updated_at_unix' => time(),
                'hostname' => gethostname() ?: null,
                'pid' => getmypid() ?: null,
            ];

            @file_put_contents(
                $heartbeatPath,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        } catch (\Throwable) {
            // Best-effort heartbeat only.
        }
    }

    protected function notifyBackupFailure(string $message): void
    {
        $this->notifyJobFailure('backup', $message, 'backup_scheduler_failure');
    }

    protected function notifyJobFailure(string $jobName, string $message, string $event = 'lifecycle_job_failure'): void
    {
        Log::error($message, ['job' => $jobName]);

        $webhookUrl = trim((string) config('backup.automation.alert_webhook_url', ''));
        if ($webhookUrl === '') {
            $webhookUrl = trim((string) config('media.alert_webhook_url', ''));
        }

        if ($webhookUrl === '') {
            return;
        }

        try {
            Http::timeout(5)->asJson()->post($webhookUrl, [
                'event' => $event,
                'job' => $jobName,
                'message' => $message,
                'app' => (string) config('app.name', 'wayvio'),
                'timestamp_utc' => now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send job failure webhook: ' . $e->getMessage());
        }
    }
}
