<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\AnalyticsTierResolver;
use App\Services\Domains\DomainsClient;
use App\Services\Meta\CustomMetaPolicyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\CustomDomains\Http\Middleware\EnsureTierAllowsCustomDomain;
use Modules\Tiers\Services\SubscriptionManager;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        $this->disableNonTenantMiddlewares();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_links_crud_delete_denies_cross_tenant_access(): void
    {
        $actor = $this->seedUser(7101, 'actor@tenant.test');
        $foreign = $this->seedUser(7102, 'foreign@tenant.test');

        DB::table('links')->insert([
            'id' => 8101,
            'user_id' => $foreign->id,
            'tenant_owner_user_id' => $foreign->id,
            'button_id' => 1,
            'link' => 'https://foreign.example.test',
            'title' => 'Foreign Link',
            'up_link' => 'no',
            'order' => 0,
            'is_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($actor)
            ->get('/studio/linkparamform_part/predefined/8101');

        $response->assertStatus(403);
    }

    public function test_custom_domains_scope_list_denies_foreign_page_selection(): void
    {
        $actor = $this->seedUser(7201, 'actor-domains@test.local');
        $foreign = $this->seedUser(7202, 'foreign-domains@test.local');

        $this->mockSubscriptionFeatureAccess(true);
        $this->mockDomainsClient(enabled: true, listResponse: []);

        $response = $this
            ->actingAs($actor)
            ->getJson('/account/domains?scope=hub&page_id=' . $foreign->id);

        $response->assertStatus(422);
    }

    public function test_custom_domains_destroy_denies_foreign_domain_resource(): void
    {
        $actor = $this->seedUser(7301, 'actor-destroy@test.local');
        $foreign = $this->seedUser(7302, 'foreign-destroy@test.local');

        DB::table('user_custom_domains')->insert([
            'id' => 9301,
            'user_id' => $foreign->id,
            'tenant_owner_user_id' => $foreign->id,
            'page_id' => $foreign->id,
            'domain' => 'foreign-delete.example.test',
            'verification_token' => 'token-delete-foreign',
            'status' => 'verified',
            'ssl_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockSubscriptionFeatureAccess(true);
        $this->mockDomainsClient(enabled: true, listResponse: []);

        $response = $this
            ->actingAs($actor)
            ->deleteJson('/account/domains/9301');

        $response->assertStatus(403);
    }

    public function test_custom_domains_store_denies_foreign_page_selection(): void
    {
        $actor = $this->seedUser(7351, 'actor-store-domains@test.local');
        $foreign = $this->seedUser(7352, 'foreign-store-domains@test.local');

        $this->mockSubscriptionFeatureAccess(true);
        $this->mockDomainsClient(enabled: true, listResponse: []);

        $response = $this
            ->actingAs($actor)
            ->postJson('/account/domains', [
                'domain' => 'tenant-scope-check.example.test',
                'scope' => 'hub',
                'page_id' => $foreign->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_meta_update_denies_cross_tenant_target(): void
    {
        $actor = $this->seedUser(7401, 'actor-meta@test.local');
        $foreign = $this->seedUser(7402, 'foreign-meta@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $foreign->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $customMetaPolicy = Mockery::mock(CustomMetaPolicyService::class);
        $customMetaPolicy->shouldReceive('isFeatureIncludedByTier')->andReturn(true);
        $this->app->instance(CustomMetaPolicyService::class, $customMetaPolicy);

        $response = $this
            ->actingAs($actor)
            ->post('/studio/meta', [
                'title' => 'cross-tenant-attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_analytics_dashboard_denies_cross_tenant_target(): void
    {
        $actor = $this->seedUser(7501, 'actor-analytics@test.local');
        $foreign = $this->seedUser(7502, 'foreign-analytics@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $foreign->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $analyticsClient = Mockery::mock(AnalyticsClient::class)->shouldIgnoreMissing();
        $tierResolver = Mockery::mock(AnalyticsTierResolver::class)->shouldIgnoreMissing();
        $this->app->instance(AnalyticsClient::class, $analyticsClient);
        $this->app->instance(AnalyticsTierResolver::class, $tierResolver);

        $response = $this
            ->actingAs($actor)
            ->get('/dashboard/analytics');

        $response->assertStatus(403);
    }

    public function test_analytics_dashboard_denies_mismatched_site_query_context(): void
    {
        $actor = $this->seedUser(7511, 'actor-analytics-site-query@test.local');
        $foreign = $this->seedUser(7512, 'foreign-analytics-site-query@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $actor->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $analyticsClient = Mockery::mock(AnalyticsClient::class)->shouldIgnoreMissing();
        $tierResolver = Mockery::mock(AnalyticsTierResolver::class)->shouldIgnoreMissing();
        $this->app->instance(AnalyticsClient::class, $analyticsClient);
        $this->app->instance(AnalyticsTierResolver::class, $tierResolver);

        $response = $this
            ->actingAs($actor)
            ->get('/dashboard/analytics?site_id=' . $foreign->id);

        $response->assertStatus(403);
    }

    public function test_analytics_dashboard_denies_mismatched_page_query_context(): void
    {
        $actor = $this->seedUser(7521, 'actor-analytics-page-query@test.local');
        $foreign = $this->seedUser(7522, 'foreign-analytics-page-query@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $actor->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $analyticsClient = Mockery::mock(AnalyticsClient::class)->shouldIgnoreMissing();
        $tierResolver = Mockery::mock(AnalyticsTierResolver::class)->shouldIgnoreMissing();
        $this->app->instance(AnalyticsClient::class, $analyticsClient);
        $this->app->instance(AnalyticsTierResolver::class, $tierResolver);

        $response = $this
            ->actingAs($actor)
            ->get('/dashboard/analytics?page_id=' . $foreign->id);

        $response->assertStatus(403);
    }

    public function test_add_link_page_query_denies_mismatched_page_context(): void
    {
        $actor = $this->seedUser(7601, 'actor-add-link@test.local');
        $foreign = $this->seedUser(7602, 'foreign-add-link@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $actor->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $response = $this
            ->actingAs($actor)
            ->get('/studio/add-link?page_id=' . $foreign->id);

        $response->assertStatus(403);
    }

    public function test_add_link_param_partial_does_not_render_foreign_link_values(): void
    {
        $actor = $this->seedUser(7651, 'actor-add-link-render@test.local');
        $foreign = $this->seedUser(7652, 'foreign-add-link-render@test.local');
        $foreignLinkUrl = 'https://foreign-render-check.example.test/secret';

        DB::table('links')->insert([
            'id' => 8651,
            'user_id' => $foreign->id,
            'tenant_owner_user_id' => $foreign->id,
            'button_id' => 1,
            'link' => $foreignLinkUrl,
            'title' => 'Foreign Render Link',
            'up_link' => 'no',
            'order' => 0,
            'is_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($actor)
            ->get('/studio/linkparamform_part/link/0');

        $response->assertOk();
        $response->assertDontSee($foreignLinkUrl);
    }

    public function test_show_legal_page_query_denies_mismatched_page_context(): void
    {
        $actor = $this->seedUser(7701, 'actor-legal@test.local');
        $foreign = $this->seedUser(7702, 'foreign-legal@test.local');

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->andReturn((int) $actor->id);
        $agencyContext->shouldReceive('isAgencyAccount')->andReturn(false);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $response = $this
            ->actingAs($actor)
            ->get('/studio/legal?page_id=' . $foreign->id);

        $response->assertStatus(403);
    }

    private function disableNonTenantMiddlewares(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\CheckBlockedUser::class,
            \App\Http\Middleware\EnsurePendingTosAccepted::class,
            \App\Http\Middleware\EnsureTwoFactorVerified::class,
            \Modules\Tiers\Http\Middleware\SubscriptionMiddleware::class,
            \Modules\Tiers\Http\Middleware\EnforceTierLimits::class,
            EnsureTierAllowsCustomDomain::class,
        ]);
    }

    private function mockSubscriptionFeatureAccess(bool $enabled): void
    {
        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn($enabled);

        $this->app->instance(SubscriptionManager::class, $subscriptionManager);
    }

    /**
     * @param array<int,array<string,mixed>> $listResponse
     */
    private function mockDomainsClient(bool $enabled, array $listResponse): void
    {
        $domainsClient = Mockery::mock(DomainsClient::class);
        $domainsClient->shouldReceive('enabled')->andReturn($enabled);
        $domainsClient->shouldReceive('listByUser')->andReturn($listResponse);
        $domainsClient->shouldReceive('isErrorResponse')->andReturn(false);

        $this->app->instance(DomainsClient::class, $domainsClient);
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
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('littlelink_name')->nullable();
            $table->string('theme')->nullable();
            $table->json('meta_overrides')->nullable();
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->unsignedBigInteger('button_id')->nullable();
            $table->string('link')->nullable();
            $table->string('title')->nullable();
            $table->string('up_link')->default('no');
            $table->unsignedBigInteger('order')->default(0);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('pending');
            $table->timestamps();
            $table->index(['user_id', 'page_id']);
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });
    }

    private function seedUser(int $id, string $email): User
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Tenant User ' . $id,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
            'role' => 'user',
            'block' => 'no',
            'littlelink_name' => 'tenant-user-' . $id,
            'theme' => 'default',
            'meta_overrides' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
