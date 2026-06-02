<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FormsPruneCommandTest extends TestCase
{
    public function test_forms_prune_sends_maintenance_secret_and_source_header(): void
    {
        config()->set('forms.api_base', 'http://127.0.0.1:8001');
        config()->set('forms.api_key', 'api-key');
        config()->set('forms.maintenance_secret', 'maintenance-key');

        Http::fake([
            'http://127.0.0.1:8001/api/forms/maintenance/prune' => Http::response([
                'deleted' => 5,
                'refreshed_retention' => 2,
            ], 200),
        ]);

        $this->artisan('forms:prune')
            ->expectsOutput('Deleted expired form submissions: 5')
            ->assertExitCode(0);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8001/api/forms/maintenance/prune'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Maintenance-Source', 'forms:prune')
                && $request->hasHeader('X-Maintenance-Secret', 'maintenance-key');
        });
    }

    public function test_forms_prune_falls_back_to_internal_api_key_when_maintenance_secret_missing(): void
    {
        config()->set('forms.api_base', 'http://127.0.0.1:8001');
        config()->set('forms.api_key', 'shared-internal-key');
        config()->set('forms.maintenance_secret', '');

        Http::fake([
            'http://127.0.0.1:8001/api/forms/maintenance/prune' => Http::response([
                'deleted' => 0,
                'refreshed_retention' => 0,
            ], 200),
        ]);

        $this->artisan('forms:prune')
            ->expectsOutput('Deleted expired form submissions: 0')
            ->assertExitCode(0);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8001/api/forms/maintenance/prune'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Maintenance-Secret', 'shared-internal-key');
        });
    }
}

