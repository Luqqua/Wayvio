<?php

namespace App\Console\Commands;

use App\Services\Forms\FormsClient;
use App\Services\Observability\JobRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FormsPruneCommand extends Command
{
    protected $signature = 'forms:prune';

    protected $description = 'Prune expired native form submissions from the internal Forms API.';

    public function handle(FormsClient $client, JobRunLogger $runLogger): int
    {
        $startedAt = now();
        Log::info('Forms prune job started', [
            'job' => 'forms:prune',
            'started_at' => $startedAt->toIso8601String(),
        ]);

        $result = $client->pruneExpired();
        if (empty($result['ok'])) {
            $finishedAt = now();
            $error = (string) ($result['error'] ?? 'unknown');
            Log::warning('Forms prune job failed', [
                'job' => 'forms:prune',
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'error' => $error,
                'maintenance_source' => 'forms:prune',
            ]);
            $runLogger->record(
                'forms:prune',
                $startedAt,
                $finishedAt,
                'failed',
                [
                    'error' => $error,
                    'maintenance_source' => 'forms:prune',
                    'maintenance_endpoint' => '/api/forms/maintenance/prune',
                ],
                null,
                null,
                $error,
            );
            $this->error('Forms prune failed: ' . (string) ($result['error'] ?? 'unknown'));
            return self::FAILURE;
        }

        $deleted = (int) ($result['deleted'] ?? 0);
        $refreshedRetention = (int) ($result['refreshed_retention'] ?? 0);
        $finishedAt = now();
        Log::info('Forms prune job finished', [
            'job' => 'forms:prune',
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => $finishedAt->toIso8601String(),
            'duration_seconds' => $finishedAt->diffInSeconds($startedAt),
            'deleted' => $deleted,
            'refreshed_retention' => $refreshedRetention,
            'maintenance_source' => 'forms:prune',
            'maintenance_endpoint' => '/api/forms/maintenance/prune',
        ]);
        $runLogger->record(
            'forms:prune',
            $startedAt,
            $finishedAt,
            'success',
            [
                'deleted' => $deleted,
                'refreshed_retention' => $refreshedRetention,
                'maintenance_source' => 'forms:prune',
                'maintenance_endpoint' => '/api/forms/maintenance/prune',
            ],
            null,
            $deleted,
        );

        $this->info('Deleted expired form submissions: ' . $deleted);
        return self::SUCCESS;
    }
}
