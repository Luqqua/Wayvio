<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Meta\CustomMetaPolicyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomMetaPolicyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('tiers.default_free_slug', 'free');
        config()->set('tiers.admin_tier_slug', 'pro');
        config()->set('tiers.legacy_slug_map', [
            'free' => 'free',
            'pro' => 'pro',
        ]);
        config()->set('tiers.plans', [
            [
                'name' => 'Free',
                'slug' => 'free',
                'limits' => [
                    'max_pages' => 1,
                    'max_links_per_page' => 10,
                    'agency_hub_slots' => 1,
                ],
                'features' => [
                    'seo' => ['custom_meta' => false],
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'limits' => [
                    'max_pages' => 1,
                    'max_links_per_page' => 50,
                    'agency_hub_slots' => 1,
                ],
                'features' => [
                    'seo' => ['custom_meta' => true],
                ],
            ],
        ]);

        $now = now();
        DB::table('tiers')->insert([
            ['id' => 1, 'name' => 'Free', 'slug' => 'free', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Pro', 'slug' => 'pro', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_free_tier_never_delivers_custom_meta(): void
    {
        $user = $this->seedUserWithSubscription(userId: 1001, tierId: 1, metaStatus: 'active');

        $this->assertFalse(app(CustomMetaPolicyService::class)->canDeliverCustomMeta($user));
    }

    public function test_pro_tier_with_active_lifecycle_delivers_custom_meta(): void
    {
        $user = $this->seedUserWithSubscription(userId: 1002, tierId: 2, metaStatus: 'active');

        $this->assertTrue(app(CustomMetaPolicyService::class)->canDeliverCustomMeta($user));
    }

    public function test_pro_tier_with_non_active_lifecycle_does_not_deliver_custom_meta(): void
    {
        $suspendedUser = $this->seedUserWithSubscription(userId: 1003, tierId: 2, metaStatus: 'suspended');
        $pendingUser = $this->seedUserWithSubscription(userId: 1004, tierId: 2, metaStatus: 'pending_deletion');

        $policy = app(CustomMetaPolicyService::class);
        $this->assertFalse($policy->canDeliverCustomMeta($suspendedUser));
        $this->assertFalse($policy->canDeliverCustomMeta($pendingUser));
    }

    public function test_agency_hub_uses_agency_owner_tier_for_custom_meta_delivery(): void
    {
        $owner = $this->seedUserWithSubscription(userId: 1005, tierId: 2, metaStatus: 'active');
        $hub = $this->seedUserWithoutSubscription(userId: 1006, role: 'agency_hub', metaStatus: 'active');

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $owner->id,
            'managed_user_id' => $hub->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(CustomMetaPolicyService::class)->canDeliverCustomMeta($hub));
    }

    public function test_agency_hub_lifecycle_still_blocks_custom_meta_delivery(): void
    {
        $owner = $this->seedUserWithSubscription(userId: 1007, tierId: 2, metaStatus: 'active');
        $hub = $this->seedUserWithoutSubscription(userId: 1008, role: 'agency_hub', metaStatus: 'suspended');

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $owner->id,
            'managed_user_id' => $hub->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(CustomMetaPolicyService::class)->canDeliverCustomMeta($hub));
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
            $table->json('meta_overrides')->nullable();
            $table->string('role')->default('user');
            $table->string('meta_tags_status', 32)->default('active');
            $table->string('meta_tags_status_reason', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    private function seedUserWithSubscription(int $userId, int $tierId, string $metaStatus): User
    {
        $now = now();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Meta User ' . $userId,
            'email' => 'meta-' . $userId . '@example.test',
            'password' => 'x',
            'littlelink_name' => 'meta-user-' . $userId,
            'meta_overrides' => json_encode([
                'title' => 'Custom Title ' . $userId,
            ], JSON_UNESCAPED_SLASHES),
            'role' => 'user',
            'meta_tags_status' => $metaStatus,
            'meta_tags_status_reason' => $metaStatus === 'active' ? null : 'downgrade',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->insert([
            'id' => $userId,
            'user_id' => $userId,
            'tier_id' => $tierId,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::query()->findOrFail($userId);
    }

    private function seedUserWithoutSubscription(int $userId, string $role, string $metaStatus): User
    {
        $now = now();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Meta User ' . $userId,
            'email' => 'meta-' . $userId . '@example.test',
            'password' => 'x',
            'littlelink_name' => 'meta-user-' . $userId,
            'meta_overrides' => json_encode([
                'title' => 'Custom Title ' . $userId,
            ], JSON_UNESCAPED_SLASHES),
            'role' => $role,
            'meta_tags_status' => $metaStatus,
            'meta_tags_status_reason' => $metaStatus === 'active' ? null : 'downgrade',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::query()->findOrFail($userId);
    }
}
