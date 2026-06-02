<?php

namespace Tests\Feature;

use App\Services\Domains\DomainsClient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Tiers\Services\SubscriptionManager;
use Tests\TestCase;

class CustomDomainsClientPathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createDomainTables();

        config()->set('tiers.admin_tier_slug', 'business');
    }

    public function test_custom_domain_routes_use_internal_api_when_enabled(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains/user/*' => Http::response([], 200),
            'http://127.0.0.1:8001/api/domains' => Http::response([
                'id' => 71,
                'user_id' => 201,
                'domain' => 'example.com',
                'status' => 'pending',
            ], 201),
            'http://127.0.0.1:8001/api/domains/5/verify' => Http::response([
                'status' => 'queued',
            ], 202),
            'http://127.0.0.1:8001/api/domains/5' => Http::response([
                'status' => 'deleted',
            ], 200),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $createResponse = $this->postJson('/account/domains', [
            'domain' => 'example.com',
        ]);

        $createResponse->assertOk()->assertJsonPath('domain', 'example.com');

        /** @var DomainsClient $client */
        $client = app(DomainsClient::class);
        $verifyResult = $client->triggerVerify($user->id, 5);
        $this->assertIsArray($verifyResult);
        $this->assertSame('queued', $verifyResult['status'] ?? null);

        $deleteResult = $client->deleteDomain($user->id, 5);
        $this->assertIsArray($deleteResult);
        $this->assertSame('deleted', $deleteResult['status'] ?? null);

        Http::assertSentCount(3);
    }

    public function test_custom_domain_index_filters_foreign_domains_from_internal_api_response(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains/user/*' => Http::response([
                [
                    'id' => 501,
                    'user_id' => 201,
                    'page_id' => 201,
                    'domain' => 'owner-only.example.test',
                    'status' => 'verified',
                ],
                [
                    'id' => 999,
                    'user_id' => 777,
                    'page_id' => null,
                    'domain' => 'foreign.example.test',
                    'status' => 'verified',
                ],
            ], 200),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->getJson('/account/domains');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', 501)
            ->assertJsonPath('0.user_id', 201);
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_custom_domain_verify_is_noop_for_already_verified_domain(): void
    {
        $this->enableRouteModelBindingForDomainActionTests();
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');
        $this->mockSubscriptionFeatureAccess(true);
        Gate::before(fn () => true);

        Http::fake();

        $user = $this->adminUser();
        $this->actingAs($user);

        DB::table('user_custom_domains')->insert([
            'id' => 902,
            'user_id' => $user->id,
            'page_id' => $user->id,
            'domain' => 'verified-domain.example.test',
            'verification_token' => 'verified-domain-token',
            'status' => 'verified',
            'ssl_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/account/domains/902/verify');

        $response
            ->assertOk()
            ->assertJsonPath('id', 902)
            ->assertJsonPath('status', 'verified');

        Http::assertNothingSent();
    }

    public function test_custom_domain_verify_missing_model_returns_json_not_exception_text(): void
    {
        $this->enableRouteModelBindingForDomainActionTests();
        $this->actingAs($this->adminUser());

        $response = $this->postJson('/account/domains/999999/verify');

        $response
            ->assertNotFound()
            ->assertJsonPath('error', 'domain_not_found')
            ->assertJsonMissing(['message' => 'No query results for model [Modules\\CustomDomains\\Models\\UserCustomDomain] 999999']);
    }

    public function test_custom_domain_destroy_missing_model_returns_json_not_exception_text(): void
    {
        $this->enableRouteModelBindingForDomainActionTests();
        $this->actingAs($this->adminUser());

        $response = $this->deleteJson('/account/domains/999999');

        $response
            ->assertNotFound()
            ->assertJsonPath('error', 'domain_not_found')
            ->assertJsonMissing(['message' => 'No query results for model [Modules\\CustomDomains\\Models\\UserCustomDomain] 999999']);
    }

    public function test_custom_domain_create_returns_503_when_internal_api_unavailable(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains' => Http::response([
                'error' => [
                    'code' => 'internal_error',
                ],
            ], 503),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->postJson('/account/domains', [
            'domain' => 'fallback.example.com',
        ]);

        $response->assertStatus(503)->assertJsonPath('error', 'domains_api_unavailable');

        $this->assertFalse(
            DB::table('user_custom_domains')
                ->where('user_id', $user->id)
                ->where('domain', 'fallback.example.com')
                ->exists()
        );

        Http::assertSentCount(1);
    }

    public function test_custom_domain_create_is_blocked_when_domain_already_exists_locally(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake();

        DB::table('user_custom_domains')->insert([
            'id' => 900,
            'user_id' => 999,
            'page_id' => 999,
            'domain' => 'taken-domain.example.test',
            'verification_token' => 'taken-domain-token',
            'status' => 'verified',
            'ssl_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->postJson('/account/domains', [
            'domain' => 'taken-domain.example.test',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error', 'domain_exists');

        Http::assertNothingSent();
    }

    public function test_custom_domain_create_is_blocked_when_scope_already_has_domain_locally(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake();

        $user = $this->adminUser();
        $this->actingAs($user);

        DB::table('user_custom_domains')->insert([
            'id' => 901,
            'user_id' => $user->id,
            'page_id' => $user->id,
            'domain' => 'existing-scope.example.test',
            'verification_token' => 'existing-scope-token',
            'status' => 'pending',
            'ssl_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/account/domains', [
            'domain' => 'second-scope.example.test',
            'scope' => 'hub',
            'page_id' => $user->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error', 'domain_scope_limit_reached')
            ->assertJsonPath('message', 'Only one custom domain is allowed here. Remove the existing domain before adding another one.');

        Http::assertNothingSent();
    }

    public function test_custom_domain_create_sanitizes_internal_api_validation_errors_for_frontend(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains' => Http::response([
                'error' => [
                    'code' => 'invalid_page_scope',
                    'message' => 'Invalid domain scope.',
                ],
            ], 422),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->postJson('/account/domains', [
            'domain' => 'invalid-scope.example.com',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error', 'domains_request_rejected')
            ->assertJsonPath('message', 'The domain request could not be completed. Please check your input and try again.')
            ->assertJsonMissing(['code' => 'invalid_page_scope'])
            ->assertJsonMissing(['message' => 'Invalid domain scope.']);

        Http::assertSentCount(1);
    }

    public function test_custom_domain_create_sanitizes_cloudflare_provider_messages_for_frontend(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        $upstreamMessage = "No custom metadata access has been allocated for this zone or account. "
            . "If you're already a paid SSL for SaaS customer, please contact your Customer Success Manager.";

        Http::fake([
            'http://127.0.0.1:8001/api/domains' => Http::response([
                'error' => [
                    'code' => 'cloudflare_worker_error',
                    'message' => $upstreamMessage,
                ],
            ], 422),
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->postJson('/account/domains', [
            'domain' => 'cloudflare-error.example.com',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error', 'domains_request_rejected')
            ->assertJsonPath('message', 'The domain request could not be completed. Please check your input and try again.')
            ->assertJsonMissing(['code' => 'cloudflare_worker_error']);

        $this->assertStringNotContainsString($upstreamMessage, $response->getContent());

        Http::assertSentCount(1);
    }

    public function test_custom_domain_create_returns_503_when_internal_api_disabled(): void
    {
        config()->set('internal-api.domains.enabled', false);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake();

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->postJson('/account/domains', [
            'domain' => 'disabled-flag.example.com',
        ]);

        $response->assertStatus(503)->assertJsonPath('error', 'domains_api_disabled');

        $this->assertFalse(
            DB::table('user_custom_domains')
                ->where('user_id', $user->id)
                ->where('domain', 'disabled-flag.example.com')
                ->exists()
        );

        Http::assertNothingSent();
    }

    public function test_domains_client_can_request_hostname_cleanup(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains/cleanup-hostname' => Http::response([
                'status' => 'deleted',
                'domain' => 'example.com',
            ], 200),
        ]);

        /** @var DomainsClient $client */
        $client = app(DomainsClient::class);
        $response = $client->cleanupHostname('example.com', false, 201);

        $this->assertIsArray($response);
        $this->assertSame('deleted', $response['status'] ?? null);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8001/api/domains/cleanup-hostname'
                && (int) ($request['tenant_owner_user_id'] ?? 0) === 201;
        });

        Http::assertSentCount(1);
    }

    public function test_domains_client_can_request_tenant_reconcile(): void
    {
        config()->set('internal-api.domains.enabled', true);
        config()->set('internal-api.domains.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.domains.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/domains/reconcile-tenant' => Http::response([
                'tenant_owner_user_id' => 201,
                'upsert_count' => 2,
                'delete_count' => 1,
            ], 200),
        ]);

        /** @var DomainsClient $client */
        $client = app(DomainsClient::class);
        $response = $client->reconcileTenantDomains(201, ['gone.example.test'], 201);

        $this->assertIsArray($response);
        $this->assertSame(201, (int) ($response['tenant_owner_user_id'] ?? 0));
        $this->assertSame(2, (int) ($response['upsert_count'] ?? 0));
        $this->assertSame(1, (int) ($response['delete_count'] ?? 0));

        Http::assertSentCount(1);
    }

    public function test_agency_branding_page_alias_supports_get_method(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/account/agency-branding', 'GET'));

        $this->assertSame('agency.branding.page', $route->getName());
    }

    public function test_save_agency_branding_redirects_to_domains_page(): void
    {
        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn(true);
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->post('/account/agency-branding', [
            'branding_link' => 'https://agency.example.test',
        ]);

        $response->assertRedirect('/account/custom-domains');
    }

    public function test_save_agency_branding_stores_uploaded_logo(): void
    {
        config()->set('media.disk', 'media_local');
        config()->set('media.convert_uploads_to_webp', false);
        Storage::fake('media_local');

        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn(true);
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->post('/account/agency-branding', [
            'branding_link' => 'https://agency.example.test',
            'branding_asset' => UploadedFile::fake()->image('agency-logo.png', 160, 80),
        ]);

        $response->assertRedirect('/account/custom-domains');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertSame('https://agency.example.test', $settings['agency_branding_link'] ?? null);
        $this->assertIsString($settings['agency_branding_asset'] ?? null);
        $this->assertStringContainsString('/agency-branding/', $settings['agency_branding_asset']);
        Storage::disk('media_local')->assertExists($settings['agency_branding_asset']);
    }

    public function test_save_agency_branding_treats_null_link_string_as_empty(): void
    {
        config()->set('media.disk', 'media_local');
        config()->set('media.convert_uploads_to_webp', false);
        Storage::fake('media_local');
        $this->mockSubscriptionFeatureAccess(true);

        $user = $this->adminUser();
        $this->actingAs($user);

        $response = $this->post('/account/agency-branding', [
            'branding_link' => 'null',
            'branding_asset' => UploadedFile::fake()->image('agency-logo.png', 160, 80),
        ]);

        $response->assertRedirect('/account/custom-domains');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertIsString($settings['agency_branding_asset'] ?? null);
        $this->assertArrayNotHasKey('agency_branding_link', $settings);
        Storage::disk('media_local')->assertExists($settings['agency_branding_asset']);
    }

    public function test_save_agency_branding_replaces_existing_logo(): void
    {
        config()->set('media.disk', 'media_local');
        config()->set('media.convert_uploads_to_webp', false);
        Storage::fake('media_local');
        $this->mockSubscriptionFeatureAccess(true);

        $user = $this->adminUser();
        $this->actingAs($user);
        $oldPath = 'tenants/201/users/201/agency-branding/old-logo.png';
        Storage::disk('media_local')->put($oldPath, 'old-logo');
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode([
                'agency_branding_asset' => $oldPath,
                'agency_branding_link' => 'https://old-agency.example.test',
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/account/agency-branding', [
            'branding_link' => 'https://new-agency.example.test',
            'branding_asset' => UploadedFile::fake()->image('new-agency-logo.png', 180, 90),
        ]);

        $response->assertRedirect('/account/custom-domains');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertSame('https://new-agency.example.test', $settings['agency_branding_link'] ?? null);
        $this->assertIsString($settings['agency_branding_asset'] ?? null);
        $this->assertNotSame($oldPath, $settings['agency_branding_asset']);
        Storage::disk('media_local')->assertMissing($oldPath);
        Storage::disk('media_local')->assertExists($settings['agency_branding_asset']);
    }

    public function test_save_agency_branding_removes_existing_logo(): void
    {
        config()->set('media.disk', 'media_local');
        Storage::fake('media_local');
        $this->mockSubscriptionFeatureAccess(true);

        $user = $this->adminUser();
        $this->actingAs($user);
        $oldPath = 'tenants/201/users/201/agency-branding/old-logo.png';
        Storage::disk('media_local')->put($oldPath, 'old-logo');
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode([
                'agency_branding_asset' => $oldPath,
                'agency_branding_link' => 'https://agency.example.test',
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/account/agency-branding', [
            'branding_link' => 'https://agency.example.test',
            'remove_branding_asset' => '1',
        ]);

        $response->assertRedirect('/account/custom-domains');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertArrayNotHasKey('agency_branding_asset', $settings);
        $this->assertSame('https://agency.example.test', $settings['agency_branding_link'] ?? null);
        Storage::disk('media_local')->assertMissing($oldPath);
    }

    private function useSqliteInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function createDomainTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_pages')->default(1);
            $table->unsignedInteger('max_links_per_page')->default(10);
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('custom_domain_enabled')->default(false);
            $table->boolean('design_customization_enabled')->default(false);
            $table->unsignedInteger('price_1m')->default(0);
            $table->unsignedInteger('price_3m')->default(0);
            $table->unsignedInteger('price_6m')->default(0);
            $table->timestamps();
        });

        DB::table('tiers')->insert([
            'id' => 30,
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Business',
            'max_pages' => 10,
            'max_links_per_page' => 200,
            'analytics_enabled' => true,
            'custom_domain_enabled' => true,
            'design_customization_enabled' => true,
            'price_1m' => 3500,
            'price_3m' => 9500,
            'price_6m' => 18000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    private function adminUser(): User
    {
        if (!DB::table('users')->where('id', 201)->exists()) {
            DB::table('users')->insert([
                'id' => 201,
                'name' => 'Domains Admin',
                'email' => 'domains-admin@example.com',
                'password' => null,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::query()->findOrFail(201);
    }

    private function mockSubscriptionFeatureAccess(bool $enabled): void
    {
        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn($enabled);

        $this->app->instance(SubscriptionManager::class, $subscriptionManager);
    }

    private function enableRouteModelBindingForDomainActionTests(): void
    {
        $this->withMiddleware();
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\CheckBlockedUser::class,
            \App\Http\Middleware\EnsurePendingTosAccepted::class,
            \App\Http\Middleware\EnsureTwoFactorVerified::class,
            \Modules\Tiers\Http\Middleware\SubscriptionMiddleware::class,
            \Modules\Tiers\Http\Middleware\EnforceTierLimits::class,
            \Modules\CustomDomains\Http\Middleware\EnsureTierAllowsCustomDomain::class,
        ]);
    }
}
