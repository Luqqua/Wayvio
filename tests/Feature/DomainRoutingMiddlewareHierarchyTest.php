<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\CustomDomains\Http\Middleware\DomainRoutingMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DomainRoutingMiddlewareHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_hub_domain_slug_variants_redirect_to_root(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2001,
            'name' => 'Hub User',
            'email' => 'hub-routing@example.test',
            'password' => 'x',
            'littlelink_name' => 'hub-main',
            'role' => 'agency_hub',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2001,
            'page_id' => 2001,
            'domain' => 'hub.example.test',
            'verification_token' => 'token-hub-routing',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $middleware = app(DomainRoutingMiddleware::class);

        $slugResponse = $middleware->handle(
            Request::create('http://hub.example.test/hub-main', 'GET'),
            fn () => response('next')
        );
        $prefixedResponse = $middleware->handle(
            Request::create('http://hub.example.test/p/other-hub', 'GET'),
            fn () => response('next')
        );

        $this->assertInstanceOf(RedirectResponse::class, $slugResponse);
        $this->assertRedirectPath('/', $slugResponse);

        $this->assertInstanceOf(RedirectResponse::class, $prefixedResponse);
        $this->assertRedirectPath('/', $prefixedResponse);
    }

    public function test_agency_domain_root_renders_owner_and_prefixed_paths_redirect_to_slug_paths(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2101,
            'name' => 'Agency Owner',
            'email' => 'agency-routing@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-main',
            'role' => 'user',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2101,
            'page_id' => null,
            'domain' => 'Agency.Example.Test',
            'verification_token' => 'token-agency-routing',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $middleware = app(DomainRoutingMiddleware::class);
        $controller = Mockery::mock(UserController::class);
        $controller
            ->shouldReceive('littlelink')
            ->once()
            ->with(Mockery::on(fn (Request $request): bool => (string) $request->input('littlelink') === 'owner-main'))
            ->andReturn(response('owner-root'));
        $this->app->instance(UserController::class, $controller);

        $rootResponse = $middleware->handle(
            Request::create('http://agency.example.test/', 'GET'),
            fn () => response('next')
        );
        $prefixedResponse = $middleware->handle(
            Request::create('http://agency.example.test/p/hub-main', 'GET'),
            fn () => response('next')
        );

        $this->assertSame(200, $rootResponse->getStatusCode());
        $this->assertSame('owner-root', (string) $rootResponse->getContent());

        $this->assertInstanceOf(RedirectResponse::class, $prefixedResponse);
        $this->assertRedirectPath('/hub-main', $prefixedResponse);
    }

    public function test_agency_domain_owner_slug_redirects_to_root(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2151,
            'name' => 'Agency Owner',
            'email' => 'agency-routing-owner-slug@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-main',
            'role' => 'user',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2151,
            'page_id' => null,
            'domain' => 'agency-owner.example.test',
            'verification_token' => 'token-agency-routing-owner',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = app(DomainRoutingMiddleware::class)->handle(
            Request::create('http://agency-owner.example.test/owner-main', 'GET'),
            fn () => response('next')
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertRedirectPath('/', $response);
    }

    public function test_agency_domain_root_legal_paths_render_owner_pages(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2161,
            'name' => 'Agency Owner',
            'email' => 'agency-routing-legal@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-main',
            'role' => 'user',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2161,
            'page_id' => null,
            'domain' => 'agency-legal.example.test',
            'verification_token' => 'token-agency-routing-legal',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $controller = Mockery::mock(UserController::class);
        $controller
            ->shouldReceive('imprint')
            ->once()
            ->with(Mockery::on(fn (Request $request): bool => (string) $request->input('littlelink') === 'owner-main'))
            ->andReturn(response('owner-imprint'));
        $controller
            ->shouldReceive('privacy')
            ->once()
            ->with(Mockery::on(fn (Request $request): bool => (string) $request->input('littlelink') === 'owner-main'))
            ->andReturn(response('owner-privacy'));
        $this->app->instance(UserController::class, $controller);

        $middleware = app(DomainRoutingMiddleware::class);

        $imprintResponse = $middleware->handle(
            Request::create('http://agency-legal.example.test/imprint', 'GET'),
            fn () => response('next')
        );
        $privacyResponse = $middleware->handle(
            Request::create('http://agency-legal.example.test/privacy', 'GET'),
            fn () => response('next')
        );
        $privacyAliasResponse = $middleware->handle(
            Request::create('http://agency-legal.example.test/datenschutz', 'GET'),
            fn () => response('next')
        );
        $ownerSlugPrivacyResponse = $middleware->handle(
            Request::create('http://agency-legal.example.test/owner-main/privacy', 'GET'),
            fn () => response('next')
        );

        $this->assertSame('owner-imprint', (string) $imprintResponse->getContent());
        $this->assertSame('owner-privacy', (string) $privacyResponse->getContent());

        $this->assertInstanceOf(RedirectResponse::class, $privacyAliasResponse);
        $this->assertRedirectPath('/privacy', $privacyAliasResponse);

        $this->assertInstanceOf(RedirectResponse::class, $ownerSlugPrivacyResponse);
        $this->assertRedirectPath('/privacy', $ownerSlugPrivacyResponse);
    }

    public function test_page_scoped_domain_privacy_paths_render_page_privacy(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2171,
            'name' => 'Hub User',
            'email' => 'hub-routing-privacy@example.test',
            'password' => 'x',
            'littlelink_name' => 'hub-main',
            'role' => 'agency_hub',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2171,
            'page_id' => 2171,
            'domain' => 'hub-privacy.example.test',
            'verification_token' => 'token-hub-routing-privacy',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $controller = Mockery::mock(UserController::class);
        $controller
            ->shouldReceive('privacy')
            ->once()
            ->with(Mockery::on(fn (Request $request): bool => (string) $request->input('littlelink') === 'hub-main'))
            ->andReturn(response('hub-privacy'));
        $this->app->instance(UserController::class, $controller);

        $response = app(DomainRoutingMiddleware::class)->handle(
            Request::create('http://hub-privacy.example.test/privacy', 'GET'),
            fn () => response('next')
        );

        $this->assertSame('hub-privacy', (string) $response->getContent());
    }

    public function test_agency_domain_blocks_unknown_non_asset_paths(): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => 2201,
            'name' => 'Agency Owner',
            'email' => 'agency-routing-2@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-main',
            'role' => 'user',
            'block' => 'no',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => 2201,
            'page_id' => null,
            'domain' => 'agency-two.example.test',
            'verification_token' => 'token-agency-routing-2',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $middleware = app(DomainRoutingMiddleware::class);

        try {
            $middleware->handle(
                Request::create('http://agency-two.example.test/dashboard', 'GET'),
                fn () => response('next')
            );

            $this->fail('Expected 404 HttpException for unsupported custom-domain path.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_duplicate_verified_domain_mappings_fail_closed(): void
    {
        $now = now();
        DB::table('users')->insert([
            [
                'id' => 2301,
                'name' => 'Tenant One',
                'email' => 'tenant-one@example.test',
                'password' => 'x',
                'littlelink_name' => 'tenant-one',
                'role' => 'user',
                'block' => 'no',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2302,
                'name' => 'Tenant Two',
                'email' => 'tenant-two@example.test',
                'password' => 'x',
                'littlelink_name' => 'tenant-two',
                'role' => 'user',
                'block' => 'no',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_custom_domains')->insert([
            [
                'user_id' => 2301,
                'page_id' => 2301,
                'domain' => 'collision.example.test',
                'verification_token' => 'token-collision-1',
                'status' => 'verified',
                'ssl_status' => 'active',
                'last_checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => 2302,
                'page_id' => 2302,
                'domain' => 'collision.example.test',
                'verification_token' => 'token-collision-2',
                'status' => 'verified',
                'ssl_status' => 'active',
                'last_checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $middleware = app(DomainRoutingMiddleware::class);

        $response = $middleware->handle(
            Request::create('http://collision.example.test/', 'GET'),
            fn () => response('next')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('next', trim((string) $response->getContent()));
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

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('littlelink_name')->nullable()->unique();
            $table->text('littlelink_description')->nullable();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('theme')->nullable();
            $table->string('locale')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id')->unique();
            $table->string('display_name', 160);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain');
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    private function assertRedirectPath(string $expectedPath, RedirectResponse $response): void
    {
        $path = parse_url($response->getTargetUrl(), PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : '/';

        $this->assertSame($expectedPath, $normalized);
    }
}
