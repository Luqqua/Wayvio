<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Tenancy\TenantResolver;
use App\Support\Tenancy\TenantResolutionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TenantResolverTest extends TestCase
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

    public function test_resolves_normal_user_context(): void
    {
        $userId = 6101;
        $this->seedUser($userId, 'user');
        $actor = User::query()->findOrFail($userId);

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->once()->andReturn($userId);
        $agencyContext->shouldReceive('isAgencyAccount')->once()->andReturn(false);

        $resolver = new TenantResolver($agencyContext);
        $context = $resolver->resolveForActor($actor, Request::create('/studio/links', 'GET'));

        $this->assertSame($userId, $context->tenantOwnerUserId());
        $this->assertSame($userId, $context->actorUserId());
        $this->assertSame($userId, $context->activeResourceUserId());
        $this->assertFalse($context->isAgencyAccount());
        $this->assertTrue($context->isOwnerActor());
    }

    public function test_resolves_agency_owner_context_for_managed_hub(): void
    {
        $ownerId = 6201;
        $managedId = 6202;
        $this->seedUser($ownerId, 'user');
        $this->seedUser($managedId, User::ROLE_AGENCY_HUB);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Managed Hub',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = User::query()->findOrFail($ownerId);

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->once()->andReturn($managedId);
        $agencyContext->shouldReceive('isAgencyAccount')->once()->andReturn(true);

        $resolver = new TenantResolver($agencyContext);
        $context = $resolver->resolveForActor($owner, Request::create('/dashboard/analytics', 'GET'));

        $this->assertSame($ownerId, $context->tenantOwnerUserId());
        $this->assertSame($ownerId, $context->actorUserId());
        $this->assertSame($managedId, $context->activeResourceUserId());
        $this->assertTrue($context->isAgencyAccount());
        $this->assertTrue($context->isOwnerActor());
    }

    public function test_resolves_managed_hub_actor_to_owner_tenant(): void
    {
        $ownerId = 6301;
        $managedId = 6302;
        $this->seedUser($ownerId, 'user');
        $this->seedUser($managedId, User::ROLE_AGENCY_HUB);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Managed Hub',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $managedActor = User::query()->findOrFail($managedId);

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->once()->andReturn($managedId);

        $resolver = new TenantResolver($agencyContext);
        $context = $resolver->resolveForActor($managedActor, Request::create('/studio/links', 'GET'));

        $this->assertSame($ownerId, $context->tenantOwnerUserId());
        $this->assertSame($managedId, $context->actorUserId());
        $this->assertSame($managedId, $context->activeResourceUserId());
        $this->assertTrue($context->isAgencyAccount());
        $this->assertFalse($context->isOwnerActor());
    }

    public function test_denies_when_agency_hubs_mapping_is_inconsistent(): void
    {
        $managedId = 6402;
        $this->seedUser(6401, 'user');
        $this->seedUser(6403, 'user');
        $this->seedUser($managedId, User::ROLE_AGENCY_HUB);

        DB::table('agency_hubs')->insert([
            [
                'agency_user_id' => 6401,
                'managed_user_id' => $managedId,
                'display_name' => 'Managed A',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'agency_user_id' => 6403,
                'managed_user_id' => $managedId,
                'display_name' => 'Managed B',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $managedActor = User::query()->findOrFail($managedId);

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('editingUserId')->once()->andReturn($managedId);

        $resolver = new TenantResolver($agencyContext);

        $this->expectException(TenantResolutionException::class);
        $this->expectExceptionMessage('Tenant context is inconsistent.');

        $resolver->resolveForActor($managedActor, Request::create('/studio/links', 'GET'));
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
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('littlelink_name')->nullable();
            $table->string('theme')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('display_name', 160)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index(['agency_user_id', 'managed_user_id']);
        });
    }

    private function seedUser(int $id, string $role): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'User ' . $id,
            'email' => sprintf('u%d@example.test', $id),
            'password' => 'x',
            'role' => $role,
            'block' => 'no',
            'littlelink_name' => 'u-' . $id,
            'theme' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
