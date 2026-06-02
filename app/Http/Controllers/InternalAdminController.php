<?php

namespace App\Http\Controllers;

use App\Console\Commands\Mod\BackupStatusCommand;
use App\Services\InternalAdmin\InternalAdminActionRegistry;
use App\Services\Observability\JobRunLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class InternalAdminController extends Controller
{
    public function __construct(
        private readonly InternalAdminActionRegistry $registry,
        private readonly JobRunLogger $runLogger,
    ) {
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'internal-admin-bridge',
            'action_count' => count($this->registry->all()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function status(): JsonResponse
    {
        $generatedAt = now('UTC');

        $trackedJobs = [
            'check_payment_status' => ['interval_hours' => 26],
            'check_grace_periods' => ['interval_hours' => 26],
            'check_suspended_resources' => ['interval_hours' => 26],
            'check_pending_deletions' => ['interval_hours' => 26],
            'billing:reconcile-check' => ['interval_hours' => 26],
            'billing:send-notifications' => ['interval_hours' => null],
        ];

        $latestRuns = $this->runLogger->latestPerJob(array_keys($trackedJobs));

        $jobsSnapshot = [];
        foreach ($trackedJobs as $jobName => $meta) {
            $run = $latestRuns[$jobName] ?? null;
            $entry = [
                'last_run_at' => $run ? $run['started_at'] : null,
                'last_status' => $run ? $run['status'] : null,
                'last_duration_ms' => $run ? $run['duration_ms'] : null,
                'last_items_processed' => $run ? $run['items_processed'] : null,
                'last_result_summary' => $run && $run['result_summary']
                    ? json_decode((string) $run['result_summary'], true)
                    : null,
                'last_error_message' => $run ? ($run['error_message'] ?? null) : null,
            ];

            if ($meta['interval_hours'] !== null && $run) {
                $lastRunAt = \Carbon\Carbon::parse($run['started_at']);
                $ageHours = $lastRunAt->diffInHours($generatedAt);
                $entry['staleness'] = $ageHours > $meta['interval_hours'] ? 'warn' : 'ok';
                $entry['age_hours'] = $ageHours;
            } elseif ($meta['interval_hours'] !== null && !$run) {
                $entry['staleness'] = 'warn';
                $entry['age_hours'] = null;
            }

            $jobsSnapshot[$jobName] = $entry;
        }

        $subscriptionStats = $this->subscriptionStats();
        $schedulerHeartbeat = $this->schedulerHeartbeat();
        $backupSnapshot = $this->backupSnapshot();

        $overallOk = !in_array('warn', array_column($jobsSnapshot, 'staleness'), true)
            && $schedulerHeartbeat['recent'];

        return response()->json([
            'generated_at_utc' => $generatedAt->toIso8601String(),
            'overall_ok' => $overallOk,
            'scheduler' => $schedulerHeartbeat,
            'jobs' => $jobsSnapshot,
            'subscriptions' => $subscriptionStats,
            'backup' => $backupSnapshot,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function subscriptionStats(): array
    {
        try {
            $rows = DB::table('user_subscriptions')
                ->selectRaw('stripe_status, COUNT(*) as cnt')
                ->groupBy('stripe_status')
                ->get();

            $byStatus = [];
            $total = 0;
            foreach ($rows as $row) {
                $key = $row->stripe_status ?? 'none';
                $byStatus[$key] = (int) $row->cnt;
                $total += (int) $row->cnt;
            }

            $stripeBacked = DB::table('user_subscriptions')
                ->whereNotNull('stripe_subscription_id')
                ->where('stripe_subscription_id', '<>', '')
                ->count();
            $internalManual = max(0, $total - $stripeBacked);
            $accountPendingDeletion = DB::table('users')->whereNotNull('account_delete_after_at')->whereNull('account_deleted_at')->count();
            $paymentFailed = DB::table('user_subscriptions')->whereNotNull('payment_failed_at')->count();

            return [
                'total' => $total,
                'stripe_backed' => $stripeBacked,
                'internal_manual' => $internalManual,
                'by_local_status' => $byStatus,
                'by_stripe_status' => $byStatus,
                'payment_failed' => $paymentFailed,
                'account_pending_deletion' => $accountPendingDeletion,
            ];
        } catch (Throwable) {
            return ['error' => 'unavailable'];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function schedulerHeartbeat(): array
    {
        $configuredPath = trim((string) config('backup.health.scheduler_heartbeat_path', ''));
        $heartbeatPath = $configuredPath !== '' ? $configuredPath : storage_path('app/backup-scheduler-heartbeat.json');
        $staleAfterMinutes = max(1, (int) config('backup.health.scheduler_heartbeat_stale_minutes', 5));

        if (!is_file($heartbeatPath)) {
            return ['present' => false, 'recent' => false, 'last_heartbeat_utc' => null];
        }

        try {
            $raw = @file_get_contents($heartbeatPath);
            $payload = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($payload)) {
                return ['present' => true, 'recent' => false, 'last_heartbeat_utc' => null];
            }

            $ts = is_numeric($payload['updated_at_unix'] ?? null) ? (int) $payload['updated_at_unix'] : 0;
            if ($ts <= 0 && is_string($payload['updated_at_utc'] ?? null)) {
                $parsed = strtotime((string) $payload['updated_at_utc']);
                $ts = $parsed !== false ? (int) $parsed : 0;
            }

            if ($ts <= 0) {
                return ['present' => true, 'recent' => false, 'last_heartbeat_utc' => null];
            }

            $lastBeat = \Carbon\CarbonImmutable::createFromTimestamp($ts, 'UTC');
            $ageMinutes = $lastBeat->diffInMinutes(\Carbon\CarbonImmutable::now('UTC'));

            return [
                'present' => true,
                'recent' => $ageMinutes <= $staleAfterMinutes,
                'last_heartbeat_utc' => $lastBeat->toIso8601String(),
                'age_minutes' => $ageMinutes,
            ];
        } catch (Throwable) {
            return ['present' => true, 'recent' => false, 'last_heartbeat_utc' => null];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function backupSnapshot(): array
    {
        try {
            /** @var array<string,mixed> $status */
            $status = app(BackupStatusCommand::class)->buildStatus();
            return [
                'overall' => data_get($status, 'health.overall'),
                'db_automation_enabled' => data_get($status, 'db.automation.enabled'),
                'db_latest_modified_at' => data_get($status, 'db.latest_backup.modified_at_utc'),
                'db_latest_age_hours' => data_get($status, 'db.latest_backup.age_hours'),
                'scheduler_heartbeat_recent' => data_get($status, 'health.scheduler.recent'),
            ];
        } catch (Throwable) {
            return ['error' => 'unavailable'];
        }
    }

    public function actions(): JsonResponse
    {
        return response()->json([
            'actions' => array_keys($this->registry->all()),
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_key' => ['required', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
        ]);

        $actionKey = (string) $validated['action_key'];
        $params = (array) ($validated['params'] ?? []);

        $definition = $this->registry->find($actionKey);
        if (!$definition) {
            return response()->json([
                'ok' => false,
                'error' => 'unknown_action',
            ], 404);
        }

        $command = (string) ($definition['command'] ?? '');
        if ($command === '') {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_action_definition',
            ], 500);
        }

        try {
            $artisanInput = $this->registry->buildArtisanInput($definition, $params);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_params',
                'message' => $e->getMessage(),
            ], 422);
        }

        $previousAllowedCommands = config('mod.allowed_commands', ['*']);
        $previousAuditContext = [
            'request_id' => config('audit.request_id'),
            'actor_admin_user_id' => config('audit.actor_admin_user_id'),
            'actor_admin_username' => config('audit.actor_admin_username'),
            'source' => config('audit.source'),
        ];

        $auditRequestId = trim((string) (
            $request->headers->get('X-Request-Id')
            ?: $request->headers->get('X-Correlation-Id')
            ?: ''
        ));
        $rawAdminUserId = $request->headers->get('X-Admin-User-Id');
        $auditActorUserId = is_numeric($rawAdminUserId) && (int) $rawAdminUserId > 0
            ? (int) $rawAdminUserId
            : null;
        $auditActorUsername = trim((string) $request->headers->get('X-Admin-Username', ''));

        try {
            config(['mod.allowed_commands' => [$command]]);
            config([
                'audit.request_id' => $auditRequestId !== '' ? $auditRequestId : null,
                'audit.actor_admin_user_id' => $auditActorUserId,
                'audit.actor_admin_username' => $auditActorUsername !== '' ? $auditActorUsername : null,
                'audit.source' => 'internal_admin',
            ]);

            $exitCode = Artisan::call($command, $artisanInput);
            $rawOutput = Artisan::output();
            $output = mb_substr((string) $rawOutput, 0, 64_000);

            return response()->json([
                'ok' => $exitCode === 0,
                'action_key' => $actionKey,
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
            ], $exitCode === 0 ? 200 : 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'action_key' => $actionKey,
                'command' => $command,
                'error' => 'execution_failed',
                'message' => $e->getMessage(),
            ], 500);
        } finally {
            config(['mod.allowed_commands' => $previousAllowedCommands]);
            config([
                'audit.request_id' => $previousAuditContext['request_id'],
                'audit.actor_admin_user_id' => $previousAuditContext['actor_admin_user_id'],
                'audit.actor_admin_username' => $previousAuditContext['actor_admin_username'],
                'audit.source' => $previousAuditContext['source'],
            ]);
        }
    }
}
