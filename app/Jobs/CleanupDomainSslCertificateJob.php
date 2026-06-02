<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CustomDomains\Services\DomainSSLService;

class CleanupDomainSslCertificateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(public string $domain)
    {
        $this->timeout = max(30, (int) config('custom-domains.queue.job_timeout_seconds', 300));
        $this->onQueue((string) config('custom-domains.queue.name', 'domains'));
    }

    public function tries(): int
    {
        return max(1, min(5, (int) config('custom-domains.queue.job_max_attempts', 8)));
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @throws \RuntimeException
     */
    public function handle(DomainSSLService $sslService): void
    {
        $sslService->cleanupCertificate($this->domain);
    }
}
