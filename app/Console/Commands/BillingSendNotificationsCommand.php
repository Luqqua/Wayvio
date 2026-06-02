<?php

namespace App\Console\Commands;

use App\Notifications\BillingSubscriptionEventNotification;
use App\Services\Lifecycle\LifecycleAuditService;
use App\Services\Observability\JobRunLogger;
use App\Support\EmailLocaleResolver;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Models\BillingNotificationOutbox;

class BillingSendNotificationsCommand extends Command
{
    protected $signature = 'billing:send-notifications {--limit=}';
    protected $description = 'Send queued billing notification emails from the outbox table.';
    /** @var array<int,string> */
    private array $localeCache = [];
    private ?bool $hasLastLoginLocaleColumn = null;

    public function handle(LifecycleAuditService $lifecycleAudit, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        if (!config('billing.notifications.enabled', true)) {
            $this->info('Billing notification emails are disabled by config.');
            return self::SUCCESS;
        }

        $maxAttempts = max(1, (int) config('billing.notifications.max_attempts', 5));
        $defaultLimit = max(1, (int) config('billing.notifications.batch_size', 100));
        $sendDelayMs = max(0, (int) config('billing.notifications.send_delay_ms', 0));
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit !== null && $requestedLimit !== ''
            ? max(1, (int) $requestedLimit)
            : $defaultLimit;

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $discarded = 0;

        try {
            $rows = BillingNotificationOutbox::query()
                ->whereIn('status', ['pending', 'failed'])
                ->whereNull('sent_at')
                ->where('attempt_count', '<', $maxAttempts)
                ->where(function ($query): void {
                    $query->whereNull('scheduled_for')
                        ->orWhere('scheduled_for', '<=', Carbon::now());
                })
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('scheduled_for')
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (QueryException $e) {
            if ($this->looksLikeMissingOutboxTable($e)) {
                $this->warn('billing_notification_outbox table not found. Run migrations to enable billing emails.');
                return self::SUCCESS;
            }

            throw $e;
        }

        foreach ($rows as $row) {
            $claimed = BillingNotificationOutbox::query()
                ->where('id', $row->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'processing',
                    'attempt_count' => DB::raw('attempt_count + 1'),
                    'updated_at' => Carbon::now(),
                ]);

            if ($claimed === 0) {
                continue;
            }

            $processed++;
            $payload = is_array($row->payload) ? $row->payload : [];
            if (!isset($payload['locale']) || trim((string) $payload['locale']) === '') {
                $payload['locale'] = $this->resolveLocale((int) $row->user_id);
            }

            try {
                Notification::route('mail', $row->email)->notify(
                    new BillingSubscriptionEventNotification((string) $row->template_key, $payload)
                );

                $sentAt = Carbon::now();
                BillingNotificationOutbox::query()
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => $sentAt,
                        'failed_at' => null,
                        'last_error' => null,
                        'updated_at' => Carbon::now(),
                    ]);

                $lifecycleAudit->event((int) $row->user_id, 'email_sent', 'system', [
                    'email_type' => (string) $row->template_key,
                    'outbox_id' => (int) $row->id,
                    'sent_at' => $sentAt->toIso8601String(),
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $latest = BillingNotificationOutbox::query()
                    ->select(['id', 'attempt_count'])
                    ->find($row->id);
                $attemptCount = (int) ($latest->attempt_count ?? 1);
                $nextStatus = $attemptCount >= $maxAttempts ? 'discarded' : 'failed';

                BillingNotificationOutbox::query()
                    ->where('id', $row->id)
                    ->update([
                        'status' => $nextStatus,
                        'failed_at' => Carbon::now(),
                        'last_error' => substr((string) $e->getMessage(), 0, 255),
                        'updated_at' => Carbon::now(),
                    ]);

                if ($nextStatus === 'discarded') {
                    $discarded++;
                } else {
                    $failed++;
                }
            }

            if ($sendDelayMs > 0) {
                usleep($sendDelayMs * 1000);
            }
        }

        $this->info(sprintf(
            'Billing notifications processed=%d sent=%d failed=%d discarded=%d limit=%d delay_ms=%d',
            $processed,
            $sent,
            $failed,
            $discarded,
            $limit,
            $sendDelayMs,
        ));

        // Only record when something happened to avoid 1440 rows/day for idle runs.
        if ($processed > 0 || $failed > 0) {
            $runLogger->record(
                'billing:send-notifications',
                $startedAt,
                now(),
                $failed > 0 && $sent === 0 ? 'failed' : 'success',
                ['sent' => $sent, 'failed' => $failed, 'discarded' => $discarded],
                $processed,
            );
        }

        return self::SUCCESS;
    }

    private function resolveLocale(int $userId): string
    {
        if ($userId <= 0) {
            return EmailLocaleResolver::resolve();
        }

        if (array_key_exists($userId, $this->localeCache)) {
            return $this->localeCache[$userId];
        }

        $locale = EmailLocaleResolver::resolve();
        if (Schema::hasTable('users')) {
            $query = DB::table('users')
                ->where('id', $userId)
                ->select(['locale']);

            if ($this->usersTableHasLastLoginLocaleColumn()) {
                $query->addSelect('last_login_locale');
            }

            $user = $query->first();

            $locale = EmailLocaleResolver::resolve(
                is_object($user) && isset($user->locale) ? (string) $user->locale : null,
                is_object($user) && isset($user->last_login_locale) ? (string) $user->last_login_locale : null
            );
        }

        $this->localeCache[$userId] = $locale;

        return $locale;
    }

    private function looksLikeMissingOutboxTable(QueryException $e): bool
    {
        $message = strtolower((string) $e->getMessage());
        return str_contains($message, 'billing_notification_outbox')
            && (
                str_contains($message, 'no such table')
                || str_contains($message, 'doesn\'t exist')
                || str_contains($message, 'no existe')
            );
    }

    private function usersTableHasLastLoginLocaleColumn(): bool
    {
        if ($this->hasLastLoginLocaleColumn !== null) {
            return $this->hasLastLoginLocaleColumn;
        }

        if (!Schema::hasTable('users')) {
            $this->hasLastLoginLocaleColumn = false;
            return false;
        }

        $this->hasLastLoginLocaleColumn = Schema::hasColumn('users', 'last_login_locale');

        return $this->hasLastLoginLocaleColumn;
    }
}
