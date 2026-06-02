<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class JobRunLogger
{
    /**
     * @param array<string,mixed> $summary
     */
    public function record(
        string $jobName,
        \DateTimeInterface $startedAt,
        \DateTimeInterface $finishedAt,
        string $status,
        array $summary = [],
        ?int $itemsProcessed = null,
        ?int $itemsAffected = null,
        ?string $errorMessage = null
    ): void {
        if (!Schema::hasTable('job_run_log')) {
            return;
        }

        $startMs = (int) ($startedAt->getTimestamp() * 1000 + (int) $startedAt->format('v'));
        $endMs = (int) ($finishedAt->getTimestamp() * 1000 + (int) $finishedAt->format('v'));
        $durationMs = max(0, $endMs - $startMs);

        $encoded = !empty($summary) ? json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        try {
            DB::table('job_run_log')->insert([
                'job_name' => substr(trim($jobName), 0, 80),
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
                'status' => in_array($status, ['success', 'failed', 'skipped'], true) ? $status : 'failed',
                'items_processed' => $itemsProcessed,
                'items_affected' => $itemsAffected,
                'result_summary' => is_string($encoded) ? $encoded : null,
                'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 512) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Non-fatal: logging must never break the job itself.
        }
    }

    /**
     * Returns the most recent run per job name.
     *
     * @param string[] $jobNames
     * @return array<string, array<string,mixed>>
     */
    public function latestPerJob(array $jobNames): array
    {
        if (empty($jobNames) || !Schema::hasTable('job_run_log')) {
            return [];
        }

        $maxIds = DB::table('job_run_log')
            ->whereIn('job_name', $jobNames)
            ->selectRaw('job_name, MAX(id) as max_id')
            ->groupBy('job_name')
            ->pluck('max_id', 'job_name');

        if ($maxIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('job_run_log')
            ->whereIn('id', $maxIds->values()->all())
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->job_name] = (array) $row;
        }

        return $result;
    }
}
