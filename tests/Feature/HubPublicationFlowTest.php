<?php

namespace Tests\Feature;

use App\Http\Controllers\AgencyHubController;
use App\Http\Controllers\UserController;
use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Agency\AgencyHubQuotaManager;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Hubs\HubPublicationService;
use App\Services\Lifecycle\AccountLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class HubPublicationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        $this->seedButtons();
        $this->mockAnalyticsDispatcher();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unpublished_hub_returns_neutral_404_for_guest(): void
    {
        $hubUser = $this->createUser([
            'id' => 8101,
            'littlelink_name' => 'guest-unpublished',
            'is_published' => false,
        ]);
        $this->createLink($hubUser->id);

        $request = $this->makePublicRequest('/guest-unpublished', 'guest-unpublished');

        $response = app(UserController::class)->littlelink($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('This page is not available', (string) $response->getContent());
    }

    public function test_owner_can_view_unpublished_hub(): void
    {
        $owner = $this->createUser([
            'id' => 8201,
            'littlelink_name' => 'owner-unpublished',
            'is_published' => false,
        ]);
        $this->createLink($owner->id);

        $request = $this->makePublicRequest('/owner-unpublished', 'owner-unpublished', $owner);

        $response = app(UserController::class)->littlelink($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('wayvio.wayvio', $response->name());
    }

    public function test_agency_owner_can_view_unpublished_managed_hub(): void
    {
        $agencyOwner = $this->createUser([
            'id' => 8301,
            'littlelink_name' => 'agency-owner',
            'is_published' => true,
        ]);
        $managedHub = $this->createUser([
            'id' => 8302,
            'littlelink_name' => 'managed-unpublished',
            'role' => 'agency_hub',
            'is_published' => false,
        ]);
        $this->createLink($managedHub->id);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $agencyOwner->id,
            'managed_user_id' => $managedHub->id,
            'display_name' => 'Managed Hub',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = $this->makePublicRequest('/managed-unpublished', 'managed-unpublished', $agencyOwner);

        $response = app(UserController::class)->littlelink($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('wayvio.wayvio', $response->name());
    }

    public function test_studio_publication_endpoint_publishes_and_audits(): void
    {
        $owner = $this->createUser([
            'id' => 8401,
            'littlelink_name' => 'studio-owner',
            'is_published' => false,
        ]);
        $this->be($owner);

        $request = $this->makePostRequest('/studio/page/publication', ['publish' => 1], $owner);

        $response = app(UserController::class)->setPublication($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(1, (int) DB::table('users')->where('id', $owner->id)->value('is_published'));
        $this->assertNotNull(DB::table('users')->where('id', $owner->id)->value('published_at'));

        $audit = DB::table('compliance_audit_log')->where('event_type', 'hub_published')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($owner->id, (int) $audit->user_id);
        $this->assertStringContainsString('"hub_id":'.$owner->id, (string) $audit->metadata);
    }

    public function test_agency_publication_endpoint_rejects_suspended_hubs(): void
    {
        $agencyOwner = $this->createUser([
            'id' => 8501,
            'littlelink_name' => 'agency-owner-suspended',
            'is_published' => true,
        ]);
        $managedHub = $this->createUser([
            'id' => 8502,
            'littlelink_name' => 'managed-suspended',
            'role' => 'agency_hub',
            'is_published' => false,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $agencyOwner->id,
            'managed_user_id' => $managedHub->id,
            'display_name' => 'Managed Suspended',
            'status' => 'suspended',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(true);

        $controller = new AgencyHubController(
            $agencyContext,
            Mockery::mock(AgencyHubQuotaManager::class),
            Mockery::mock(ComplianceAuditService::class),
            Mockery::mock(AccountLifecycleService::class),
            app(HubPublicationService::class),
        );

        $request = $this->makePostRequest('/agency/hubs/8502/publication', ['publish' => 1], $agencyOwner);

        $response = $controller->setPublication($request, 8502);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(0, (int) DB::table('users')->where('id', $managedHub->id)->value('is_published'));
        $this->assertSame(0, DB::table('compliance_audit_log')->where('event_type', 'hub_published')->count());
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
            $table->text('meta_overrides')->nullable();
            $table->string('locale')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('display_name', 160);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('buttons', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('alt')->nullable();
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('button_id')->nullable();
            $table->string('title')->nullable();
            $table->string('link')->nullable();
            $table->text('type_params')->nullable();
            $table->string('up_link')->default('no');
            $table->unsignedBigInteger('order')->default(0);
            $table->unsignedBigInteger('click_number')->default(0);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('compliance_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type', 80);
            $table->string('status', 32)->default('success');
            $table->string('source', 32)->default('web');
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedButtons(): void
    {
        DB::table('buttons')->insert([
            'id' => 1,
            'name' => 'custom_website',
            'alt' => 'Website',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(array $overrides = []): User
    {
        $id = (int) ($overrides['id'] ?? random_int(9000, 9999));
        $now = now();

        DB::table('users')->insert(array_merge([
            'id' => $id,
            'name' => 'User '.$id,
            'email' => "user-{$id}@example.test",
            'password' => bcrypt('secret'),
            'littlelink_name' => "user-{$id}",
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'meta_overrides' => null,
            'locale' => 'en',
            'is_published' => false,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        return User::query()->findOrFail($id);
    }

    private function createLink(int $userId): void
    {
        DB::table('links')->insert([
            'id' => random_int(100000, 900000),
            'user_id' => $userId,
            'button_id' => 1,
            'title' => 'Link',
            'link' => 'https://example.test',
            'type_params' => null,
            'up_link' => 'no',
            'order' => 0,
            'click_number' => 0,
            'is_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePublicRequest(string $uri, string $slug, ?User $user = null): Request
    {
        $request = Request::create($uri, 'GET');
        $request->merge(['littlelink' => $slug]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makePostRequest(string $uri, array $payload, ?User $user = null): Request
    {
        $request = Request::create($uri, 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        $request->headers->set('referer', $uri);

        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function mockAnalyticsDispatcher(): void
    {
        $mock = Mockery::mock(AnalyticsDispatcher::class)->shouldIgnoreMissing();
        $mock->shouldReceive('recordPageView')->andReturnTrue();
        $this->app->instance(AnalyticsDispatcher::class, $mock);
    }
}
