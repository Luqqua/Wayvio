<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgencyModCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        config()->set('mod.allowed_commands', ['*']);
    }

    public function test_tier_set_dry_run_reports_hub_pruning_without_changes(): void
    {
        [$ownerId] = $this->seedAgencyScenario(4);

        $this->artisan('tier:set', [
            'user_id' => $ownerId,
            'tier' => 'agency',
            '--months' => 1,
            '--hubs' => 2,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(4, DB::table('agency_hubs')->where('agency_user_id', $ownerId)->count());
        $this->assertSame(4, (int) DB::table('user_subscriptions')->where('user_id', $ownerId)->value('hub_slots_included'));
    }

    public function test_tier_set_downgrade_suspends_managed_hubs_without_data_loss(): void
    {
        [$ownerId, $managedIds] = $this->seedAgencyScenario(3);

        $this->artisan('tier:set', [
            'user_id' => $ownerId,
            'tier' => 'pro',
            '--months' => 1,
        ])->assertSuccessful();

        $this->assertSame(0, DB::table('agency_hubs')->where('agency_user_id', $ownerId)->where('status', 'active')->count());
        $this->assertSame(3, DB::table('agency_hubs')->where('agency_user_id', $ownerId)->where('status', 'suspended')->count());
        $this->assertSame(3, DB::table('users')->whereIn('id', $managedIds)->count());
        $this->assertGreaterThan(0, DB::table('links')->whereIn('user_id', $managedIds)->count());
        $this->assertSame(
            3,
            DB::table('user_custom_domains')
                ->where('user_id', $ownerId)
                ->whereIn('page_id', $managedIds)
                ->count()
        );
        $this->assertTrue(
            DB::table('user_custom_domains')
                ->where('user_id', $ownerId)
                ->whereNull('page_id')
                ->exists()
        );
        $this->assertSame('pro', (string) DB::table('tiers')->where('id', DB::table('user_subscriptions')->where('user_id', $ownerId)->value('tier_id'))->value('slug'));
    }

    public function test_tier_set_agency_slots_include_owner_page_when_pruning(): void
    {
        [$ownerId, $managedIds] = $this->seedAgencyScenario(2);

        $this->artisan('tier:set', [
            'user_id' => $ownerId,
            'tier' => 'agency',
            '--months' => 1,
            '--hubs' => 2,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('agency_hubs')->where('agency_user_id', $ownerId)->where('status', 'active')->count());
        $this->assertSame(1, DB::table('agency_hubs')->where('agency_user_id', $ownerId)->where('status', 'suspended')->count());
        $this->assertSame(2, DB::table('users')->whereIn('id', $managedIds)->count());
    }

    public function test_user_list_command_excludes_agency_hub_accounts(): void
    {
        [$ownerId, $managedIds] = $this->seedAgencyScenario(2);

        Artisan::call('user:list');
        $output = Artisan::output();

        $this->assertStringContainsString('#'.$ownerId.' | Agency Owner', $output);
        foreach ($managedIds as $managedId) {
            $this->assertStringNotContainsString('#'.$managedId.' |', $output);
        }
    }

    public function test_user_list_and_user_view_include_partner_state(): void
    {
        [$ownerId] = $this->seedAgencyScenario(0);
        $now = now();

        DB::table('partner_accounts')->insert([
            'id' => 1,
            'user_id' => $ownerId,
            'status' => 'active',
            'default_commission_rate_bps' => 3000,
            'stripe_connect_account_id' => null,
            'payout_minimum_cents' => 5000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Artisan::call('user:list');
        $listOutput = Artisan::output();
        $this->assertStringContainsString('#'.$ownerId.' | Agency Owner', $listOutput);
        $this->assertStringContainsString('partner=yes', $listOutput);
        $this->assertStringContainsString('partner_status=active', $listOutput);

        Artisan::call('user:view', ['id' => $ownerId]);
        $viewOutput = Artisan::output();
        $this->assertStringContainsString('Partner: yes', $viewOutput);
        $this->assertStringContainsString('Partner-Status: active', $viewOutput);
    }

    public function test_partners_activate_fails_for_already_active_partner(): void
    {
        [$ownerId] = $this->seedAgencyScenario(0);
        $now = now();

        DB::table('partner_accounts')->insert([
            'id' => 1,
            'user_id' => $ownerId,
            'status' => 'active',
            'default_commission_rate_bps' => 3000,
            'stripe_connect_account_id' => null,
            'payout_minimum_cents' => 5000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $exitCode = Artisan::call('partners:activate', ['user_id' => $ownerId, '--rate-bps' => 3000]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already a partner', Artisan::output());
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
            $table->index(['agency_user_id', 'status']);
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('button_id')->nullable();
            $table->string('link')->nullable();
            $table->string('title')->nullable();
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
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('default_commission_rate_bps')->default(3000);
            $table->string('stripe_connect_account_id')->nullable();
            $table->unsignedInteger('payout_minimum_cents')->default(5000);
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
                'analytics_enabled' => false,
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
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Pro',
                'max_pages' => 1,
                'max_links_per_page' => 80,
                'analytics_enabled' => true,
                'custom_domain_enabled' => false,
                'design_customization_enabled' => true,
                'price_1m' => 1500,
                'price_3m' => 4000,
                'price_6m' => 7500,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Agency',
                'slug' => 'agency',
                'description' => 'Agency',
                'max_pages' => 50,
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

    /**
     * @return array{int,array<int,int>}
     */
    private function seedAgencyScenario(int $managedCount): array
    {
        $ownerId = 9001;
        $now = now();

        DB::table('users')->insert([
            'id' => $ownerId,
            'name' => 'Agency Owner',
            'email' => 'owner@example.test',
            'password' => 'x',
            'littlelink_name' => 'owner-main',
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $ownerId,
            'tier_id' => 3,
            'hub_slots_included' => max(2, $managedCount),
            'hub_slots_addon' => 0,
            'expires_at' => $now->copy()->addMonth(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_custom_domains')->insert([
            'id' => 3000,
            'user_id' => $ownerId,
            'page_id' => null,
            'domain' => 'agency-default.example.test',
            'verification_token' => 'token-agency-default',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $managedIds = [];
        for ($i = 1; $i <= $managedCount; $i++) {
            $managedId = 9100 + $i;
            $managedIds[] = $managedId;

            DB::table('users')->insert([
                'id' => $managedId,
                'name' => 'Managed '.$i,
                'email' => "managed{$i}@example.test",
                'password' => 'x',
                'littlelink_name' => "managed-{$i}",
                'littlelink_description' => null,
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'en',
                'created_at' => $now->copy()->addSeconds($i),
                'updated_at' => $now->copy()->addSeconds($i),
            ]);

            DB::table('agency_hubs')->insert([
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedId,
                'display_name' => 'Hub '.$i,
                'status' => 'active',
                'created_at' => $now->copy()->addSeconds($i),
                'updated_at' => $now->copy()->addSeconds($i),
            ]);

            DB::table('user_custom_domains')->insert([
                'id' => 3000 + $i,
                'user_id' => $ownerId,
                'page_id' => $managedId,
                'domain' => "hub-{$managedId}.example.test",
                'verification_token' => "token-hub-{$managedId}",
                'status' => 'verified',
                'ssl_status' => 'active',
                'last_checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($link = 1; $link <= $i; $link++) {
                DB::table('links')->insert([
                    'id' => ($managedId * 100) + $link,
                    'user_id' => $managedId,
                    'button_id' => 1,
                    'link' => "https://example.test/{$managedId}/{$link}",
                    'title' => "L{$link}",
                    'is_disabled' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return [$ownerId, $managedIds];
    }
}
