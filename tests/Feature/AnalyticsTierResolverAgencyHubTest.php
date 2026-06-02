<?php

namespace Tests\Feature;

use App\Services\Analytics\AnalyticsTierResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsTierResolverAgencyHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_agency_hub_analytics_tier_is_resolved_from_agency_owner(): void
    {
        $now = now();

        DB::table('users')->insert([
            [
                'id' => 1001,
                'name' => 'Agency Owner',
                'email' => 'owner@example.test',
                'password' => 'x',
                'littlelink_name' => 'owner-main',
                'role' => 'user',
                'block' => 'no',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 1002,
                'name' => 'Agency Hub',
                'email' => 'hub@example.test',
                'password' => 'x',
                'littlelink_name' => 'hub-main',
                'role' => 'agency_hub',
                'block' => 'no',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => 1001,
            'tier_id' => 2,
            'hub_slots_included' => 2,
            'hub_slots_addon' => 0,
            'expires_at' => $now->copy()->addMonth(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => 1001,
            'managed_user_id' => 1002,
            'display_name' => 'Hub One',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $managedUser = \App\Models\User::query()->findOrFail(1002);

        $resolver = app(AnalyticsTierResolver::class);

        $this->assertSame('agency', $resolver->tierLevel($managedUser));
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

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tier_id');
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->timestamp('expires_at')->nullable();
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

        $now = now();
        DB::table('tiers')->insert([
            [
                'id' => 1,
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Free',
                'max_pages' => 1,
                'max_links_per_page' => 10,
                'analytics_enabled' => true,
                'custom_domain_enabled' => false,
                'design_customization_enabled' => false,
                'price_1m' => 0,
                'price_3m' => 0,
                'price_6m' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Agency',
                'slug' => 'agency',
                'description' => 'Agency',
                'max_pages' => 2,
                'max_links_per_page' => 200,
                'analytics_enabled' => true,
                'custom_domain_enabled' => true,
                'design_customization_enabled' => true,
                'price_1m' => 5900,
                'price_3m' => 15900,
                'price_6m' => 29900,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
