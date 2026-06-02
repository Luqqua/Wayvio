<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_tenant_owner_user_id_migration_backfills_critical_tables(): void
    {
        $ownerId = 8601;
        $managedId = 8602;

        DB::table('users')->insert([
            [
                'id' => $ownerId,
                'name' => 'Owner',
                'email' => 'owner-migration@example.test',
                'password' => 'x',
                'role' => 'user',
                'block' => 'no',
                'littlelink_name' => 'owner-migration',
                'theme' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $managedId,
                'name' => 'Managed',
                'email' => 'managed-migration@example.test',
                'password' => 'x',
                'role' => 'agency_hub',
                'block' => 'no',
                'littlelink_name' => 'managed-migration',
                'theme' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('links')->insert([
            'id' => 99001,
            'user_id' => $managedId,
            'button_id' => 1,
            'link' => 'https://example.test/link',
            'title' => 'Link',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_custom_domains')->insert([
            'id' => 99002,
            'user_id' => $managedId,
            'page_id' => $managedId,
            'domain' => 'managed-migration.example.test',
            'verification_token' => 'token-managed-migration',
            'status' => 'pending',
            'ssl_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('analytics_events_extended')->insert([
            'id' => 99003,
            'user_id' => $managedId,
            'page_id' => $managedId,
            'link_id' => 99001,
            'created_at' => now(),
        ]);

        DB::table('partner_attributions')->insert([
            'id' => 99004,
            'referred_user_id' => $managedId,
            'partner_user_id' => $ownerId,
            'commission_rate_bps' => 3000,
            'source' => 'code',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'attributed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('analytics_resource_states')->insert([
            'id' => 99005,
            'user_id' => $managedId,
            'status' => 'active',
            'reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('compliance_audit_log')->insert([
            'id' => 99006,
            'user_id' => $managedId,
            'actor_user_id' => $ownerId,
            'event_type' => 'test_event',
            'status' => 'success',
            'source' => 'test',
            'created_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_04_08_000500_add_tenant_owner_user_id_to_critical_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('links', 'tenant_owner_user_id'));
        $this->assertTrue(Schema::hasColumn('user_custom_domains', 'tenant_owner_user_id'));
        $this->assertTrue(Schema::hasColumn('analytics_events_extended', 'tenant_owner_user_id'));
        $this->assertTrue(Schema::hasColumn('partner_attributions', 'tenant_owner_user_id'));
        $this->assertTrue(Schema::hasColumn('analytics_resource_states', 'tenant_owner_user_id'));
        $this->assertTrue(Schema::hasColumn('compliance_audit_log', 'tenant_owner_user_id'));

        $this->assertSame($ownerId, (int) DB::table('links')->where('id', 99001)->value('tenant_owner_user_id'));
        $this->assertSame($ownerId, (int) DB::table('user_custom_domains')->where('id', 99002)->value('tenant_owner_user_id'));
        $this->assertSame($ownerId, (int) DB::table('analytics_events_extended')->where('id', 99003)->value('tenant_owner_user_id'));
        $this->assertSame($ownerId, (int) DB::table('partner_attributions')->where('id', 99004)->value('tenant_owner_user_id'));
        $this->assertSame($ownerId, (int) DB::table('analytics_resource_states')->where('id', 99005)->value('tenant_owner_user_id'));
        $this->assertSame($ownerId, (int) DB::table('compliance_audit_log')->where('id', 99006)->value('tenant_owner_user_id'));
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
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index(['agency_user_id', 'managed_user_id']);
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('button_id')->nullable();
            $table->string('link')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('pending');
            $table->timestamps();
        });

        Schema::create('analytics_events_extended', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('link_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('partner_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('referred_user_id')->unique();
            $table->unsignedBigInteger('partner_user_id');
            $table->string('source', 32)->default('code');
            $table->unsignedInteger('commission_rate_bps');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('attributed_at');
            $table->timestamps();
        });

        Schema::create('analytics_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status', 32)->default('active');
            $table->string('reason', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type', 80);
            $table->string('status', 32)->default('success');
            $table->string('source', 32)->default('web');
            $table->timestamp('created_at')->nullable();
        });
    }
}
