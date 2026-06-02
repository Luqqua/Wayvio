<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Lifecycle\AccountLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgencyHubReactivationLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_reactivates_suspended_hub_when_slot_is_available(): void
    {
        $ownerId = 9001;
        $this->seedAgencyUser($ownerId, hubSlotsIncluded: 3, hubSlotsAddon: 0);

        $this->seedHub($ownerId, 9101, 'Hub Active', 'active', null);
        $this->seedHub($ownerId, 9102, 'Hub Suspended', 'suspended', 'downgrade');

        $service = app(AccountLifecycleService::class);
        $owner = User::query()->findOrFail($ownerId);

        $this->assertSame(1, $service->availableManagedHubSlots($owner));
        $this->assertTrue($service->reactivateHubByUser($owner, 9102));

        $reactivated = DB::table('agency_hubs')->where('managed_user_id', 9102)->first();
        $this->assertNotNull($reactivated);
        $this->assertSame('active', (string) $reactivated->status);
        $this->assertNull($reactivated->lifecycle_reason);
        $this->assertNull($reactivated->suspended_at);
        $this->assertNull($reactivated->pending_deletion_at);
        $this->assertNull($reactivated->delete_after_at);

        $this->assertSame(0, $service->availableManagedHubSlots($owner->fresh()));
        $this->assertTrue(
            DB::table('audit_log')
                ->where('user_id', $ownerId)
                ->where('event_type', 'hub_reactivated_by_user')
                ->exists()
        );
    }

    public function test_does_not_reactivate_when_no_slot_is_available(): void
    {
        $ownerId = 9002;
        $this->seedAgencyUser($ownerId, hubSlotsIncluded: 3, hubSlotsAddon: 0);

        $this->seedHub($ownerId, 9201, 'Hub Active 1', 'active', null);
        $this->seedHub($ownerId, 9202, 'Hub Active 2', 'active', null);
        $this->seedHub($ownerId, 9203, 'Hub Suspended', 'suspended', 'downgrade');

        $service = app(AccountLifecycleService::class);
        $owner = User::query()->findOrFail($ownerId);

        $this->assertSame(0, $service->availableManagedHubSlots($owner));
        $this->assertFalse($service->reactivateHubByUser($owner, 9203));

        $suspended = DB::table('agency_hubs')->where('managed_user_id', 9203)->first();
        $this->assertNotNull($suspended);
        $this->assertSame('suspended', (string) $suspended->status);
    }

    public function test_legacy_single_slot_value_is_clamped_to_agency_minimum(): void
    {
        $ownerId = 9003;
        $this->seedAgencyUser($ownerId, hubSlotsIncluded: 1, hubSlotsAddon: 0);
        $this->seedHub($ownerId, 9301, 'Hub Suspended', 'suspended', 'downgrade');

        $service = app(AccountLifecycleService::class);
        $owner = User::query()->findOrFail($ownerId);

        // Agency minimum is 2 total slots, which means 1 managed slot is available.
        $this->assertSame(1, $service->availableManagedHubSlots($owner));
        $this->assertTrue($service->reactivateHubByUser($owner, 9301));
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
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('locale')->nullable();
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id')->unique();
            $table->string('display_name', 160);
            $table->string('status', 32)->default('active');
            $table->string('lifecycle_reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at_lifecycle')->nullable();
            $table->timestamps();
            $table->index(['agency_user_id', 'status']);
        });

        Schema::create('audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type', 80);
            $table->string('old_status', 64)->nullable();
            $table->string('new_status', 64)->nullable();
            $table->string('reason', 32);
            $table->text('metadata')->nullable();
            $table->timestamp('created_at');
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
            $table->timestamp('created_at');
        });
    }

    private function seedAgencyUser(int $ownerId, int $hubSlotsIncluded, int $hubSlotsAddon): void
    {
        $now = now();

        DB::table('users')->insert([
            'id' => $ownerId,
            'name' => 'Agency Owner '.$ownerId,
            'email' => 'owner-'.$ownerId.'@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-'.$ownerId,
            'role' => 'user',
            'block' => 'no',
            'locale' => 'de',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $ownerId,
            'hub_slots_included' => $hubSlotsIncluded,
            'hub_slots_addon' => $hubSlotsAddon,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedHub(int $ownerId, int $managedUserId, string $name, string $status, ?string $reason): void
    {
        $now = now();

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedUserId,
            'display_name' => $name,
            'status' => $status,
            'lifecycle_reason' => $reason,
            'suspended_at' => $status === 'suspended' ? $now : null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
