<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AgencyHubContextCapacityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_slot_summary_reports_create_block_when_total_inventory_is_full(): void
    {
        $ownerId = $this->seedAgencyOwnerWithInventory();
        $owner = User::query()->findOrFail($ownerId);

        $summary = app(AgencyHubContext::class)->slotSummary($owner);

        $this->assertSame(2, (int) ($summary['used'] ?? 0)); // owner + one active hub
        $this->assertSame(3, (int) ($summary['total'] ?? 0)); // owner + two managed slots
        $this->assertSame(1, (int) ($summary['available'] ?? 0)); // active-slot perspective
        $this->assertSame(2, (int) ($summary['managed_total'] ?? 0));
        $this->assertSame(2, (int) ($summary['managed_limit'] ?? 0));
        $this->assertTrue((bool) ($summary['create_blocked'] ?? false)); // total inventory already full
    }

    public function test_create_hub_is_blocked_when_total_inventory_is_full_even_with_active_slot_available(): void
    {
        $ownerId = $this->seedAgencyOwnerWithInventory();
        $owner = User::query()->findOrFail($ownerId);

        $request = Request::create('/agency/hubs', 'POST');
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();

        $context = app(AgencyHubContext::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Hub inventory limit reached');

        $context->createHub($owner, [
            'display_name' => 'Blocked Hub',
            'littlelink_name' => 'blocked-hub',
            'littlelink_description' => 'should fail due total-cap',
        ], $request);
    }

    public function test_duplicate_slug_validation_does_not_create_hub_record(): void
    {
        $ownerId = $this->seedAgencyOwnerWithFreeCapacity();
        $owner = User::query()->findOrFail($ownerId);

        $request = Request::create('/agency/hubs', 'POST');
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();

        $before = DB::table('agency_hubs')->where('agency_user_id', $ownerId)->count();

        try {
            app(AgencyHubContext::class)->createHub($owner, [
                'display_name' => 'Duplicate Slug',
                'littlelink_name' => 'managed-existing',
                'littlelink_description' => 'must fail',
            ], $request);
            $this->fail('Expected a validation exception for duplicate hub slug.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('littlelink_name', $e->errors());
        }

        $after = DB::table('agency_hubs')->where('agency_user_id', $ownerId)->count();
        $this->assertSame($before, $after);
        $this->assertSame(0, DB::table('agency_hubs')->where('display_name', 'Duplicate Slug')->count());
    }

    public function test_page_id_query_selects_authorized_active_hub_context(): void
    {
        $ownerId = $this->seedAgencyOwnerWithInventory();
        $owner = User::query()->findOrFail($ownerId);
        $managedActiveId = 4202;

        $request = Request::create('/studio/add-link?page_id=' . $managedActiveId, 'GET');
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();
        $request->session()->put(AgencyHubContext::SESSION_KEY, $ownerId);

        $activeUserId = app(AgencyHubContext::class)->editingUserId($owner, $request);

        $this->assertSame($managedActiveId, $activeUserId);
        $this->assertSame($managedActiveId, (int) $request->session()->get(AgencyHubContext::SESSION_KEY));
    }

    public function test_hubs_includes_owner_option_with_real_owner_user_id(): void
    {
        $ownerId = $this->seedAgencyOwnerWithInventory();
        $owner = User::query()->findOrFail($ownerId);

        $hubs = app(AgencyHubContext::class)->hubs($owner);
        $ownerHub = $hubs->first();

        $this->assertNotNull($ownerHub);
        $this->assertSame($ownerId, (int) $ownerHub->agency_user_id);
        $this->assertSame($ownerId, (int) $ownerHub->managed_user_id);
        $this->assertSame($ownerId, (int) $ownerHub->managedUser?->id);
    }

    public function test_create_hub_sets_business_focus_title_layout_as_default(): void
    {
        $ownerId = $this->seedAgencyOwnerWithFreeCapacity();
        $owner = User::query()->findOrFail($ownerId);

        $request = Request::create('/agency/hubs', 'POST');
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();

        $hub = app(AgencyHubContext::class)->createHub($owner, [
            'display_name' => 'New Managed Hub',
            'littlelink_name' => 'new-managed-hub',
            'littlelink_description' => 'default layout check',
        ], $request);

        $managedUserId = (int) $hub->managed_user_id;
        $settings = $this->settingsFor($managedUserId);

        $this->assertSame('wayvio', $settings['template'] ?? null);
        $this->assertSame('wayvio', $settings['theme_template_id'] ?? null);
        $this->assertSame('default', $settings['theme_variant_id'] ?? null);
        $this->assertSame('template', $settings['background_mode'] ?? null);
        $this->assertSame('business', $settings['profile_header_layout'] ?? null);
        $this->assertSame('black', $settings['text_color_mode'] ?? null);
        $this->assertSame('#000000', strtoupper((string) ($settings['text_color_custom'] ?? '')));
        $this->assertStringContainsString('background-color: #079AA2', (string) ($settings['global_custom_button_css'] ?? ''));
        $this->assertStringContainsString('color: #000000', (string) ($settings['global_custom_button_css'] ?? ''));
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
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['agency_user_id', 'status']);
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });
    }

    private function seedAgencyOwnerWithInventory(): int
    {
        $ownerId = 4201;
        $managedActiveId = 4202;
        $managedSuspendedId = 4203;
        $now = now();

        DB::table('tiers')->insert([
            'id' => 3,
            'name' => 'Agency',
            'slug' => 'agency',
            'description' => 'Agency',
            'max_pages' => 3,
            'max_links_per_page' => 200,
            'analytics_enabled' => true,
            'custom_domain_enabled' => true,
            'design_customization_enabled' => true,
            'price_1m' => 3999,
            'price_3m' => 11997,
            'price_6m' => 23994,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            [
                'id' => $ownerId,
                'name' => 'Agency Owner',
                'email' => 'owner@context.test',
                'email_verified_at' => $now,
                'password' => 'x',
                'littlelink_name' => 'owner-context',
                'littlelink_description' => null,
                'role' => 'user',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'de',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $managedActiveId,
                'name' => 'Managed Active',
                'email' => 'active@context.test',
                'email_verified_at' => $now,
                'password' => 'x',
                'littlelink_name' => 'managed-active',
                'littlelink_description' => null,
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'de',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $managedSuspendedId,
                'name' => 'Managed Suspended',
                'email' => 'suspended@context.test',
                'email_verified_at' => $now,
                'password' => 'x',
                'littlelink_name' => 'managed-suspended',
                'littlelink_description' => null,
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'de',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $ownerId,
            'tier_id' => 3,
            'hub_slots_included' => 3, // 2 managed + owner
            'hub_slots_addon' => 0,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedActiveId,
                'display_name' => 'Managed Active',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'agency_user_id' => $ownerId,
                'managed_user_id' => $managedSuspendedId,
                'display_name' => 'Managed Suspended',
                'status' => 'suspended',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $ownerId;
    }

    private function seedAgencyOwnerWithFreeCapacity(): int
    {
        $ownerId = 4301;
        $managedExistingId = 4302;
        $now = now();

        DB::table('tiers')->insert([
            'id' => 33,
            'name' => 'Agency',
            'slug' => 'agency',
            'description' => 'Agency',
            'max_pages' => 4,
            'max_links_per_page' => 200,
            'analytics_enabled' => true,
            'custom_domain_enabled' => true,
            'design_customization_enabled' => true,
            'price_1m' => 3999,
            'price_3m' => 11997,
            'price_6m' => 23994,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            [
                'id' => $ownerId,
                'name' => 'Agency Owner Free',
                'email' => 'owner-free@context.test',
                'email_verified_at' => $now,
                'password' => 'x',
                'littlelink_name' => 'owner-free',
                'littlelink_description' => null,
                'role' => 'user',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'de',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $managedExistingId,
                'name' => 'Managed Existing',
                'email' => 'managed-existing@context.test',
                'email_verified_at' => $now,
                'password' => 'x',
                'littlelink_name' => 'managed-existing',
                'littlelink_description' => null,
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'locale' => 'de',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $ownerId,
            'tier_id' => 33,
            'hub_slots_included' => 4, // 3 managed + owner
            'hub_slots_addon' => 0,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedExistingId,
            'display_name' => 'Managed Existing',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $ownerId;
    }

    private function settingsFor(int $userId): array
    {
        $raw = DB::table('user_settings')->where('user_id', $userId)->value('data');
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
