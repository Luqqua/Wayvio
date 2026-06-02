<?php

namespace App\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CustomDomains\Services\DomainSSLService;
use Throwable;

class ProvisionDomainSslCertificateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(public int $domainId)
    {
        $this->timeout = max(30, (int) config('custom-domains.queue.job_timeout_seconds', 300));
        $this->onQueue((string) config('custom-domains.queue.name', 'domains'));
    }

    public function tries(): int
    {
        return max(1, (int) config('custom-domains.queue.job_max_attempts', 8));
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        $configured = config('custom-domains.queue.job_backoff_seconds', [60, 300, 900, 1800, 3600]);
        if (!is_array($configured)) {
            return [60, 300, 900, 1800, 3600];
        }

        $sanitized = array_values(array_filter(array_map(
            static fn ($value) => (int) $value,
            $configured,
        ), static fn (int $value) => $value > 0));

        return $sanitized !== [] ? $sanitized : [60, 300, 900, 1800, 3600];
    }

    public function retryUntil(): DateTimeInterface
    {
        $hours = max(1, (int) config('custom-domains.queue.job_retry_window_hours', 24));
        return now()->addHours($hours);
    }

    /**
     * @throws \RuntimeException
     */
    public function handle(DomainSSLService $sslService): void
    {
        $sslService->provisionCertificateByDomainId($this->domainId);
    }

    public function failed(Throwable $exception): void
    {
        app(DomainSSLService::class)->markDomainFailedById($this->domainId, $exception->getMessage());
    }
}
