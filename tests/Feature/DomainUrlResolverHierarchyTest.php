<?php

namespace Tests\Feature;

use App\Services\Domains\DomainUrlResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainUrlResolverHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        config()->set('app.url', 'https://saas.example.test');
        config()->set('advanced-config.custom_url_prefix', '');
    }

    public function test_hub_domain_overrides_agency_and_saas_urls(): void
    {
        [$owner, $hub] = $this->seedOwnerWithHub();

        DB::table('user_custom_domains')->insert([
            'user_id' => $owner->id,
            'page_id' => null,
            'domain' => 'agency.example.test',
            'verification_token' => 'token-agency',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_custom_domains')->insert([
            'user_id' => $owner->id,
            'page_id' => $hub->id,
            'domain' => 'hub-a.example.test',
            'verification_token' => 'token-hub',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(DomainUrlResolver::class);

        $this->assertSame('https://hub-a.example.test', $resolver->profileUrlForEditor($owner, $hub));
    }

    public function test_agency_domain_uses_root_for_owner_and_slug_paths_for_hubs(): void
    {
        [$owner, $hub] = $this->seedOwnerWithHub();

        DB::table('user_custom_domains')->insert([
            'user_id' => $owner->id,
            'page_id' => null,
            'domain' => 'agency.example.test',
            'verification_token' => 'token-agency',
            'status' => 'verified',
            'ssl_status' => 'active',
            'last_checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(DomainUrlResolver::class);

        $this->assertSame('https://agency.example.test', $resolver->profileUrlForEditor($owner, $owner));
        $this->assertSame('https://agency.example.test/hub-main', $resolver->profileUrlForEditor($owner, $hub));
    }

    public function test_saas_domain_is_used_when_no_custom_domain_exists(): void
    {
        [$owner, $hub] = $this->seedOwnerWithHub();

        $resolver = app(DomainUrlResolver::class);

        $this->assertSame('https://saas.example.test/hub-main', $resolver->profileUrlForEditor($owner, $hub));
    }

    public function test_custom_prefix_does_not_override_canonical_profile_urls(): void
    {
        [$owner, $hub] = $this->seedOwnerWithHub();
        config()->set('advanced-config.custom_url_prefix', '+');

        $resolver = app(DomainUrlResolver::class);

        $this->assertSame('https://saas.example.test/hub-main', $resolver->profileUrlForEditor($owner, $hub));
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
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending');
            $table->string('ssl_status')->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{\App\Models\User,\App\Models\User}
     */
    private function seedOwnerWithHub(): array
    {
        $now = now();

        DB::table('users')->insert([
            [
                'id' => 501,
                'name' => 'Agency Owner',
                'email' => 'owner@example.test',
                'password' => 'x',
                'littlelink_name' => 'owner-main',
                'role' => 'user',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 502,
                'name' => 'Hub One',
                'email' => 'hub@example.test',
                'password' => 'x',
                'littlelink_name' => 'hub-main',
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => 501,
            'managed_user_id' => 502,
            'display_name' => 'Hub One',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $owner = \App\Models\User::query()->findOrFail(501);
        $hub = \App\Models\User::query()->findOrFail(502);

        return [$owner, $hub];
    }
}
