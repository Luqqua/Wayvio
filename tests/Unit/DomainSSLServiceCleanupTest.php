<?php

namespace Tests\Unit;

use App\Services\Domains\DomainsClient;
use Mockery;
use Modules\CustomDomains\Services\DomainSSLService;
use Tests\TestCase;

class DomainSSLServiceCleanupTest extends TestCase
{
    public function test_cleanup_delegates_hostname_cleanup_to_internal_domains_api(): void
    {
        config()->set('custom-domains.provider', 'cloudflare');

        $client = Mockery::mock(DomainsClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('cleanupHostname')
            ->once()
            ->with('tenant.example.test')
            ->andReturn(['status' => 'deleted']);
        $client->shouldReceive('isErrorResponse')->once()->andReturn(false);
        $this->app->instance(DomainsClient::class, $client);

        app(DomainSSLService::class)->cleanupCertificate('Tenant.Example.Test');
    }

    public function test_cleanup_ignores_invalid_hostnames_before_calling_internal_api(): void
    {
        config()->set('custom-domains.provider', 'cloudflare');

        $client = Mockery::mock(DomainsClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldNotReceive('cleanupHostname');
        $this->app->instance(DomainsClient::class, $client);

        app(DomainSSLService::class)->cleanupCertificate('https://bad.example.test/path');
    }
}
