<?php

namespace App\Console\Commands;

use App\Models\UserData;
use App\Models\User;
use App\Notifications\LegalAgreementChangeNoticeNotification;
use App\Services\Compliance\ComplianceAuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LegalRequireCurrentCommand extends Command
{
    private const USERDATA_ARCHIVE_KEY = 'legal_agreement_archive';

    private bool $warnedMissingUserSettingsTable = false;

    protected $signature = 'legal:require-current
                            {--type=all : agb|avv|all}
                            {--dry-run : Zeigt nur an, welche Nutzer betroffen sind}
                            {--grace-days=30 : Tage bis Login-Gating greift (0 = sofort)}
                            {--mail-delay-ms=250 : Pause zwischen Legal-Update-E-Mails}
                            {--chunk=500 : Chunk size fuer Updates}';

    protected $description = 'Require current AGB/AVV acceptance (with optional grace period and AGB change notifications).';

    public function handle(ComplianceAuditService $complianceAudit): int
    {
        $requestedType = strtolower(trim((string) $this->option('type')));
        $chunkSize = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $graceDays = max(0, (int) $this->option('grace-days'));
        $mailDelayMs = max(0, (int) $this->option('mail-delay-ms'));
        $enforceAfter = now()->addDays($graceDays);

        if (!Schema::hasTable('users')) {
            $this->error('users table not found. Run migrations first.');
            return Command::FAILURE;
        }

        $availableTypes = $complianceAudit->agreementTypes();
        $targetTypes = $requestedType === 'all' || $requestedType === ''
            ? $availableTypes
            : [$requestedType];

        $invalidTypes = array_diff($targetTypes, $availableTypes);
        if ($invalidTypes !== []) {
            $this->error('Unsupported type: ' . implode(', ', $invalidTypes));
            $this->line('Available types: ' . implode(', ', $availableTypes));
            return Command::FAILURE;
        }

        $this->line('Mode: ' . ($dryRun ? 'DRY RUN' : 'EXECUTE'));
        $this->line('Chunk size: ' . $chunkSize);
        $this->line('Grace days: ' . $graceDays);
        $this->line('Mail delay ms: ' . $mailDelayMs);

        $totalAffected = 0;
        $totalInvalidated = 0;
        $totalDeferred = 0;
        $totalMailSent = 0;
        $totalMailSkipped = 0;
        $totalMailFailed = 0;
        $totalMailRateLimited = 0;

        foreach ($targetTypes as $type) {
            $columnMap = $this->columnMap($type);
            if ($columnMap === null) {
                continue;
            }

            if (!Schema::hasColumn('users', $columnMap['accepted_at']) || !Schema::hasColumn('users', $columnMap['version'])) {
                $this->error("[{$type}] Required columns missing. Run migrations first.");
                return Command::FAILURE;
            }

            $snapshot = $complianceAudit->currentAgreementSnapshot($type);
            $currentVersion = (string) ($snapshot['version'] ?? 'unknown');

            $query = User::query()->where(function (Builder $inner) use ($columnMap, $currentVersion): void {
                $inner->where(function (Builder $acceptedOutdated) use ($columnMap, $currentVersion): void {
                    $acceptedOutdated->whereNotNull($columnMap['accepted_at'])
                        ->where(function (Builder $versionMismatch) use ($columnMap, $currentVersion): void {
                            $versionMismatch->whereNull($columnMap['version'])
                                ->orWhere($columnMap['version'], '!=', $currentVersion);
                        });
                })->orWhere(function (Builder $invalidLegacyState) use ($columnMap): void {
                    $invalidLegacyState->whereNull($columnMap['accepted_at'])
                        ->whereNotNull($columnMap['version']);
                });
            });

            $affected = (clone $query)->count();
            $totalAffected += $affected;

            $this->line("[{$type}] target version={$currentVersion} | affected users={$affected}");

            if ($dryRun || $affected === 0) {
                continue;
            }

            $processedForType = 0;

            $query
                ->select('id', $columnMap['accepted_at'], $columnMap['version'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($users) use (
                    $type,
                    $columnMap,
                    $currentVersion,
                    $graceDays,
                    $enforceAfter,
                    $complianceAudit,
                    &$processedForType
                ): void {
                    $ids = collect();

                    foreach ($users as $user) {
                        $userId = (int) ($user->id ?? 0);
                        if ($userId <= 0) {
                            continue;
                        }

                        $ids->push($userId);

                        $savedVersion = trim((string) ($user->{$columnMap['version']} ?? ''));
                        $acceptedAt = $user->{$columnMap['accepted_at']} ?? null;

                        $this->archiveUserDataAgreementSnapshot(
                            userId: $userId,
                            agreementType: $type,
                            previousVersion: $savedVersion !== '' ? $savedVersion : null,
                            previousAcceptedAt: $acceptedAt !== null ? (string) $acceptedAt : null,
                            targetVersion: $currentVersion,
                        );

                        if ($graceDays > 0) {
                            $existingDeferred = $complianceAudit->deferredAgreementEnforcement($userId, $type);
                            $emailSentAt = null;
                            if (is_array($existingDeferred)) {
                                $existingTargetVersion = trim((string) ($existingDeferred['target_version'] ?? ''));
                                $existingEmailSentAt = trim((string) ($existingDeferred['email_sent_at'] ?? ''));
                                if ($existingTargetVersion === $currentVersion && $existingEmailSentAt !== '') {
                                    $emailSentAt = $existingEmailSentAt;
                                }
                            }

                            $complianceAudit->scheduleDeferredAgreementEnforcement(
                                userId: $userId,
                                agreementType: $type,
                                targetVersion: $currentVersion,
                                enforceAfter: $enforceAfter,
                                notifiedAt: now(),
                                emailSentAt: $emailSentAt
                            );
                        }
                    }

                    $ids = $ids->filter()->values();
                    if ($ids->isEmpty()) {
                        return;
                    }

                    if ($graceDays > 0) {
                        $processedForType += $ids->count();
                        return;
                    }

                    $processedForType += User::query()
                        ->whereIn('id', $ids->all())
                        ->update([
                            $columnMap['accepted_at'] => null,
                            $columnMap['version'] => null,
                        ]);
                });

            if ($graceDays > 0) {
                $totalDeferred += $processedForType;
                $this->info("[{$type}] deferred_enforcement={$processedForType} | enforce_after={$enforceAfter->toDateString()}");
            } else {
                $totalInvalidated += $processedForType;
                $this->info("[{$type}] invalidated={$processedForType}");
            }

            if ($type === 'agb') {
                $mailStats = $this->notifyActiveUsersAboutAgreementChange(
                    agreementType: $type,
                    agreementVersion: $currentVersion,
                    enforceAfter: $enforceAfter,
                    chunkSize: $chunkSize,
                    mailDelayMs: $mailDelayMs,
                    complianceAudit: $complianceAudit,
                    dryRun: $dryRun,
                );

                $totalMailSent += $mailStats['sent'];
                $totalMailSkipped += $mailStats['skipped'];
                $totalMailFailed += $mailStats['failed'];
                $totalMailRateLimited += $mailStats['rate_limited'];
                $this->line("[{$type}] mail sent={$mailStats['sent']} skipped={$mailStats['skipped']} failed={$mailStats['failed']} rate_limited={$mailStats['rate_limited']}");
            }
        }

        if ($dryRun) {
            $this->info("Dry run finished. affected_users={$totalAffected}");
            return Command::SUCCESS;
        }

        $this->info(sprintf(
            'Done. affected_users=%d invalidated=%d deferred=%d mail_sent=%d mail_skipped=%d mail_failed=%d mail_failed_rate_limited=%d',
            $totalAffected,
            $totalInvalidated,
            $totalDeferred,
            $totalMailSent,
            $totalMailSkipped,
            $totalMailFailed,
            $totalMailRateLimited
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{sent:int,skipped:int,failed:int,rate_limited:int}
     */
    private function notifyActiveUsersAboutAgreementChange(
        string $agreementType,
        string $agreementVersion,
        Carbon $enforceAfter,
        int $chunkSize,
        int $mailDelayMs,
        ComplianceAuditService $complianceAudit,
        bool $dryRun,
    ): array {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $rateLimited = 0;

        $snapshot = $complianceAudit->currentAgreementSnapshot($agreementType);
        $documentUrl = (string) ($snapshot['url'] ?? url('/pages/agb'));
        $selectColumns = ['id', 'email'];
        if (Schema::hasColumn('users', 'locale')) {
            $selectColumns[] = 'locale';
        }
        if (Schema::hasColumn('users', 'last_login_locale')) {
            $selectColumns[] = 'last_login_locale';
        }

        $activeUsersQuery = User::query()
            ->withoutAgencyHubAccounts()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if (Schema::hasColumn('users', 'block')) {
            $activeUsersQuery->where('block', 'no');
        }

        $activeUsersQuery
            ->select($selectColumns)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (
                &$sent,
                &$skipped,
                &$failed,
                $agreementType,
                $agreementVersion,
                $enforceAfter,
                $documentUrl,
                $mailDelayMs,
                $complianceAudit,
                $dryRun
            ): void {
                foreach ($users as $user) {
                    $userId = (int) ($user->id ?? 0);
                    if ($userId <= 0) {
                        $skipped++;
                        continue;
                    }

                    $existingDeferred = $complianceAudit->deferredAgreementEnforcement($userId, $agreementType);
                    $existingTargetVersion = trim((string) ($existingDeferred['target_version'] ?? ''));
                    $existingEmailSentAt = trim((string) ($existingDeferred['email_sent_at'] ?? ''));

                    if ($existingTargetVersion === $agreementVersion && $existingEmailSentAt !== '') {
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $sent++;
                        if ($mailDelayMs > 0) {
                            usleep($mailDelayMs * 1000);
                        }
                        continue;
                    }

                    try {
                        $user->notify(new LegalAgreementChangeNoticeNotification(
                            agreementType: $agreementType,
                            targetVersion: $agreementVersion,
                            effectiveAt: $enforceAfter,
                            documentUrl: $documentUrl,
                        ));

                        $complianceAudit->scheduleDeferredAgreementEnforcement(
                            userId: $userId,
                            agreementType: $agreementType,
                            targetVersion: $agreementVersion,
                            enforceAfter: $enforceAfter,
                            notifiedAt: now(),
                            emailSentAt: now()
                        );
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errorMessage = trim((string) $e->getMessage());
                        $errorType = $this->isRateLimitedMailFailure($errorMessage)
                            ? 'rate_limited'
                            : 'delivery_failed';
                        if ($errorType === 'rate_limited') {
                            $rateLimited++;
                        }

                        $this->warn('Failed to send legal update email for user #' . $userId . ' [mail_error=' . $errorType . ']: ' . $errorMessage);
                    }

                    if ($mailDelayMs > 0) {
                        usleep($mailDelayMs * 1000);
                    }
                }
            });

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'rate_limited' => $rateLimited,
        ];
    }

    private function isRateLimitedMailFailure(string $message): bool
    {
        $haystack = strtolower(trim($message));
        if ($haystack === '') {
            return false;
        }

        $needles = [
            'limit reached',
            'rate limit',
            'quota',
            'too many email',
            'too many requests',
            'too many messages',
            'per second',
            'throttl',
            'mailbox unavailable',
            'over quota',
        ];

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function archiveUserDataAgreementSnapshot(
        int $userId,
        string $agreementType,
        ?string $previousVersion,
        ?string $previousAcceptedAt,
        string $targetVersion
    ): void {
        if (!Schema::hasTable('user_settings')) {
            if (!$this->warnedMissingUserSettingsTable) {
                $this->warn('user_settings table missing. Legacy agreement snapshots cannot be archived in UserData.');
                $this->warnedMissingUserSettingsTable = true;
            }

            return;
        }

        $existing = UserData::getData($userId, self::USERDATA_ARCHIVE_KEY);
        $archive = is_array($existing) ? $existing : [];

        // Keep a bounded archive to prevent unlimited growth in user_settings.
        if (count($archive) > 200) {
            $archive = array_slice($archive, -200);
        }

        $archive[] = [
            'agreement_type' => $agreementType,
            'agreement_version' => $previousVersion,
            'accepted_at' => $previousAcceptedAt,
            'archived_at' => now()->toIso8601String(),
            'archived_reason' => 'version_outdated',
            'target_version' => $targetVersion,
            'archived_by' => 'legal:require-current',
        ];

        UserData::saveData($userId, self::USERDATA_ARCHIVE_KEY, $archive);
    }

    /**
     * @return array{accepted_at:string,version:string}|null
     */
    private function columnMap(string $type): ?array
    {
        return match (strtolower(trim($type))) {
            'agb' => [
                'accepted_at' => 'agb_accepted_at',
                'version' => 'agb_version',
            ],
            'avv' => [
                'accepted_at' => 'avv_accepted_at',
                'version' => 'avv_version',
            ],
            default => null,
        };
    }
}
