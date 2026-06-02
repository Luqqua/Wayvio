<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Lifecycle\AccountLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgencyHubLifecycleCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('billing.lifecycle.agency_hub_auto_cleanup_enabled', true);
        config()->set('billing.lifecycle.downgrade_retention_days', 60);
        config()->set('billing.lifecycle.pending_deletion_grace_days', 7);
    }

    public function test_suspended_downgrade_hub_moves_to_pending_deletion_after_retention_window(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 2);
        $activeManagedId = 1003;
        $now = now();

        DB::table('users')->insert([
            'id' => $activeManagedId,
            'name' => 'Managed Active',
            'email' => 'managed-active@example.test',
            'password' => 'x',
            'littlelink_name' => 'managed-active',
            'role' => 'agency_hub',
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_delete_after_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $activeManagedId,
            'display_name' => 'Still Active',
            'status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Cleanup Candidate',
            'status' => 'suspended',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now->copy()->subDays(61),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')
            ->where('user_id', $ownerId)
            ->update([
                'hub_inventory_over_quota_since' => $now->copy()->subDays(61),
                'hub_inventory_over_quota_count' => 1,
                'updated_at' => $now,
            ]);

        app(AccountLifecycleService::class)->handleSuspendedResources();

        $hub = DB::table('agency_hubs')
            ->where('agency_user_id', $ownerId)
            ->where('managed_user_id', $managedId)
            ->first();

        $this->assertNotNull($hub);
        $this->assertSame('pending_deletion', (string) $hub->status);
        $this->assertNotNull($hub->pending_deletion_at);
        $this->assertNotNull($hub->delete_after_at);
        $this->assertTrue(
            DB::table('audit_log')
                ->where('user_id', $ownerId)
                ->where('event_type', 'resources_pending_deletion')
                ->exists()
        );
    }

    public function test_suspended_hub_is_not_auto_deleted_when_current_slot_limit_still_allows_total_inventory(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 3);
        $activeManagedId = 1003;
        $now = now();

        DB::table('users')->insert([
            'id' => $activeManagedId,
            'name' => 'Managed Active',
            'email' => 'managed-active@example.test',
            'password' => 'x',
            'littlelink_name' => 'managed-active',
            'role' => 'agency_hub',
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_delete_after_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $activeManagedId,
            'display_name' => 'Still Active',
            'status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Old Suspended',
            'status' => 'suspended',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now->copy()->subDays(61),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->handleSuspendedResources();

        $hub = DB::table('agency_hubs')
            ->where('agency_user_id', $ownerId)
            ->where('managed_user_id', $managedId)
            ->first();
        $this->assertNotNull($hub);
        $this->assertSame('suspended', (string) $hub->status);
        $this->assertNull($hub->pending_deletion_at);
        $this->assertNull($hub->delete_after_at);
    }

    public function test_over_quota_cleanup_starts_timer_before_marking_pending_deletion(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 2);
        $activeManagedId = 1003;
        $now = now();

        DB::table('users')->insert([
            'id' => $activeManagedId,
            'name' => 'Managed Active',
            'email' => 'managed-active@example.test',
            'password' => 'x',
            'littlelink_name' => 'managed-active',
            'role' => 'agency_hub',
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_delete_after_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $activeManagedId,
            'display_name' => 'Still Active',
            'status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Recently Suspended',
            'status' => 'suspended',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now->copy()->subDays(90),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->handleSuspendedResources();

        $hub = DB::table('agency_hubs')
            ->where('agency_user_id', $ownerId)
            ->where('managed_user_id', $managedId)
            ->first();
        $this->assertNotNull($hub);
        $this->assertSame('suspended', (string) $hub->status);
        $this->assertNull($hub->pending_deletion_at);

        $subscription = DB::table('user_subscriptions')->where('user_id', $ownerId)->first();
        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->hub_inventory_over_quota_since);
        $this->assertSame(1, (int) ($subscription->hub_inventory_over_quota_count ?? 0));
    }

    public function test_non_agency_slot_profile_treats_all_managed_hubs_as_over_quota(): void
    {
        [$ownerId, $managedA] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 1);
        $managedB = 1003;
        $now = now();

        DB::table('users')->insert([
            'id' => $managedB,
            'name' => 'Managed Hub B',
            'email' => 'managed-b@example.test',
            'password' => 'x',
            'littlelink_name' => 'managed-b',
            'role' => 'agency_hub',
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_delete_after_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedA,
                'display_name' => 'Managed A',
                'status' => 'active',
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedB,
                'display_name' => 'Managed B',
                'status' => 'active',
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')
            ->where('user_id', $ownerId)
            ->update([
                'hub_inventory_over_quota_since' => $now->copy()->subDays(61),
                'hub_inventory_over_quota_count' => 2,
                'updated_at' => $now,
            ]);

        app(AccountLifecycleService::class)->handleSuspendedResources();

        $hubA = DB::table('agency_hubs')->where('managed_user_id', $managedA)->first();
        $hubB = DB::table('agency_hubs')->where('managed_user_id', $managedB)->first();

        $this->assertNotNull($hubA);
        $this->assertNotNull($hubB);
        $this->assertSame('pending_deletion', (string) $hubA->status);
        $this->assertSame('pending_deletion', (string) $hubB->status);
    }

    public function test_pending_deletion_job_hard_deletes_expired_hubs_and_managed_data(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 1);
        $now = now();

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Expired Pending Hub',
            'status' => 'pending_deletion',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now->copy()->subDays(90),
            'pending_deletion_at' => $now->copy()->subDays(8),
            'delete_after_at' => $now->copy()->subDay(),
            'deleted_at_lifecycle' => null,
            'created_at' => $now->copy()->subDays(120),
            'updated_at' => $now,
        ]);

        DB::table('links')->insert([
            'user_id' => $managedId,
            'title' => 'Example',
            'link' => 'https://example.test',
            'is_disabled' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => $ownerId,
            'page_id' => $managedId,
            'domain' => 'managed-' . $managedId . '.example.test',
            'verification_token' => 'token-' . $managedId,
            'status' => 'verified',
            'ssl_status' => 'active',
            'lifecycle_status' => 'pending_deletion',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now->copy()->subDays(61),
            'pending_deletion_at' => $now->copy()->subDays(8),
            'delete_after_at' => $now->copy()->subDay(),
            'deleted_at_lifecycle' => null,
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('analytics_events_extended')->insert([
            'user_id' => $managedId,
            'event_name' => 'click',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('analytics_resource_states')->insert([
            'user_id' => $managedId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_settings')->insert([
            'user_id' => $managedId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('social_accounts')->insert([
            'user_id' => $managedId,
            'provider' => 'github',
            'provider_id' => 'gh-' . $managedId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->handlePendingDeletions();

        $this->assertSame(0, DB::table('agency_hubs')->where('managed_user_id', $managedId)->count());
        $this->assertSame(0, DB::table('users')->where('id', $managedId)->count());
        $this->assertSame(0, DB::table('links')->where('user_id', $managedId)->count());
        $this->assertSame(0, DB::table('user_custom_domains')->where('page_id', $managedId)->count());
        $this->assertSame(0, DB::table('analytics_events_extended')->where('user_id', $managedId)->count());
        $this->assertSame(0, DB::table('analytics_resource_states')->where('user_id', $managedId)->count());
        $this->assertSame(0, DB::table('user_settings')->where('user_id', $managedId)->count());
        $this->assertSame(0, DB::table('social_accounts')->where('user_id', $managedId)->count());

        $audit = DB::table('audit_log')
            ->where('user_id', $ownerId)
            ->where('event_type', 'resources_deleted')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($audit);

        $metadata = json_decode((string) ($audit->metadata ?? ''), true);
        $this->assertIsArray($metadata);
        $this->assertSame(1, (int) ($metadata['hubs_deleted'] ?? 0));
    }

    public function test_pending_deletion_job_hard_deletes_only_target_hub_forms_data(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 1);
        $otherManagedId = 1003;
        $now = now();

        DB::table('users')->insert([
            'id' => $otherManagedId,
            'name' => 'Managed Hub Other',
            'email' => 'managed-other@example.test',
            'password' => 'x',
            'littlelink_name' => 'managed-other',
            'role' => 'agency_hub',
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_delete_after_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedId,
                'display_name' => 'Expired Pending Hub',
                'status' => 'pending_deletion',
                'lifecycle_reason' => 'downgrade',
                'suspended_at' => $now->copy()->subDays(90),
                'pending_deletion_at' => $now->copy()->subDays(8),
                'delete_after_at' => $now->copy()->subDay(),
                'deleted_at_lifecycle' => null,
                'created_at' => $now->copy()->subDays(120),
                'updated_at' => $now,
            ],
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $otherManagedId,
                'display_name' => 'Active Hub',
                'status' => 'active',
                'lifecycle_reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at_lifecycle' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('forms_resource_states')->insert([
            [
                'user_id' => $managedId,
                'tenant_owner_user_id' => $ownerId,
                'status' => 'active',
                'reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $otherManagedId,
                'tenant_owner_user_id' => $ownerId,
                'status' => 'active',
                'reason' => null,
                'suspended_at' => null,
                'pending_deletion_at' => null,
                'delete_after_at' => null,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('forms_submissions')->insert([
            [
                'id' => 9401,
                'tenant_owner_user_id' => $ownerId,
                'hub_user_id' => $managedId,
                'retention_until' => $now->copy()->addDays(30),
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9402,
                'tenant_owner_user_id' => $ownerId,
                'hub_user_id' => $otherManagedId,
                'retention_until' => $now->copy()->addDays(30),
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9403,
                'tenant_owner_user_id' => 2999,
                'hub_user_id' => 2999,
                'retention_until' => $now->copy()->addDays(30),
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        app(AccountLifecycleService::class)->handlePendingDeletions();

        $this->assertSame(0, DB::table('agency_hubs')->where('managed_user_id', $managedId)->count());
        $this->assertSame(1, DB::table('agency_hubs')->where('managed_user_id', $otherManagedId)->count());

        $this->assertSame(0, DB::table('users')->where('id', $managedId)->count());
        $this->assertSame(1, DB::table('users')->where('id', $otherManagedId)->count());

        $this->assertSame(0, DB::table('forms_resource_states')->where('user_id', $managedId)->count());
        $this->assertSame(1, DB::table('forms_resource_states')->where('user_id', $otherManagedId)->count());

        $this->assertSame(0, DB::table('forms_submissions')->where('hub_user_id', $managedId)->count());
        $this->assertSame(1, DB::table('forms_submissions')->where('hub_user_id', $otherManagedId)->count());
        $this->assertSame(1, DB::table('forms_submissions')->where('hub_user_id', 2999)->count());
    }

    public function test_user_can_permanently_delete_suspended_hub(): void
    {
        [$ownerId, $managedId] = $this->seedOwnerAndManagedUser(hubSlotsIncluded: 2);
        $now = now();

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'display_name' => 'Manual Delete Hub',
            'status' => 'suspended',
            'lifecycle_reason' => 'downgrade',
            'suspended_at' => $now,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('links')->insert([
            'user_id' => $managedId,
            'title' => 'Cleanup',
            'link' => 'https://cleanup.test',
            'is_disabled' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = app(AccountLifecycleService::class);
        $owner = User::query()->findOrFail($ownerId);

        $this->assertTrue($service->permanentlyDeleteHubByUser($owner, $managedId));
        $this->assertSame(0, DB::table('agency_hubs')->where('managed_user_id', $managedId)->count());
        $this->assertSame(0, DB::table('users')->where('id', $managedId)->count());
        $this->assertSame(0, DB::table('links')->where('user_id', $managedId)->count());
        $this->assertTrue(
            DB::table('audit_log')
                ->where('user_id', $ownerId)
                ->where('event_type', 'hub_deleted_by_user')
                ->exists()
        );
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
            $table->string('account_status', 32)->default('active');
            $table->timestamp('account_delete_after_at')->nullable();
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

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->timestamp('hub_inventory_over_quota_since')->nullable();
            $table->unsignedInteger('hub_inventory_over_quota_count')->nullable();
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('unknown');
            $table->string('lifecycle_status', 32)->default('active');
            $table->string('lifecycle_reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at_lifecycle')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_events_extended', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('event_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status', 32)->default('active');
            $table->string('reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forms_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forms_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->unsignedBigInteger('hub_user_id')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider');
            $table->string('provider_id');
            $table->timestamps();
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

    /**
     * @return array{int,int}
     */
    private function seedOwnerAndManagedUser(int $hubSlotsIncluded = 1): array
    {
        $ownerId = 1001;
        $managedId = 1002;
        $now = now();

        DB::table('users')->insert([
            [
                'id' => $ownerId,
                'name' => 'Agency Owner',
                'email' => 'owner@example.test',
                'password' => 'x',
                'littlelink_name' => 'owner-main',
                'role' => 'user',
                'block' => 'no',
                'locale' => 'de',
                'account_status' => 'active',
                'account_delete_after_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $managedId,
                'name' => 'Managed Hub User',
                'email' => 'managed@example.test',
                'password' => 'x',
                'littlelink_name' => 'managed-hub',
                'role' => 'agency_hub',
                'block' => 'no',
                'locale' => 'de',
                'account_status' => 'active',
                'account_delete_after_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $ownerId,
            'hub_slots_included' => $hubSlotsIncluded,
            'hub_slots_addon' => 0,
            'tier_id' => null,
            'hub_inventory_over_quota_since' => null,
            'hub_inventory_over_quota_count' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$ownerId, $managedId];
    }
}
