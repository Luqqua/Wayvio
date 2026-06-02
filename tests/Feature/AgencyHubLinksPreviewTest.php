<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AgencyHubLinksPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        $this->seedData();
        $this->mockAgencyContext();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_links_preview_iframes_use_active_hub_slug(): void
    {
        $owner = User::query()->findOrFail(5001);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($owner)
            ->get('/studio/links');

        $response->assertOk();

        $content = (string) $response->getContent();

        $this->assertMatchesRegularExpression('~id="frPreview1"[^>]*src="[^"]*/hub-main(?:\\?[^"]*)?"~', $content);
        $this->assertMatchesRegularExpression('~id="frPreview2"[^>]*src="[^"]*/hub-main(?:\\?[^"]*)?"~', $content);
        $this->assertDoesNotMatchRegularExpression('~id="frPreview1"[^>]*src="[^"]*/owner-main"~', $content);
        $this->assertDoesNotMatchRegularExpression('~id="frPreview2"[^>]*src="[^"]*/owner-main"~', $content);
    }

    public function test_hub_contact_form_prefers_internal_title_in_studio_list(): void
    {
        $owner = User::query()->findOrFail(5001);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($owner)
            ->get('/studio/links');

        $response->assertOk();
        $response->assertSee('Kontaktformular Startseite');
    }

    private function mockAgencyContext(): void
    {
        $activeUser = User::query()->select('id', 'name', 'littlelink_name')->findOrFail(5002);

        $mock = Mockery::mock(AgencyHubContext::class)->shouldIgnoreMissing();
        $mock->shouldReceive('editingUserId')->andReturn(5002);
        $mock->shouldReceive('isAgencyAccount')->andReturn(true);
        $mock->shouldReceive('sidebarData')->andReturn([
            'is_agency' => true,
            'active_user_id' => 5002,
            'active_user' => $activeUser,
            'hubs' => collect(),
            'slots' => ['used' => 1, 'total' => 2],
        ]);

        $this->app->instance(AgencyHubContext::class, $mock);
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
            $table->string('theme')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('status', 32)->default('active');
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
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->unsignedBigInteger('pending_tier_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_status')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->unsignedInteger('pending_hub_slots_included')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('buttons', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('alt')->nullable();
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('button_id')->nullable();
            $table->string('link')->nullable();
            $table->string('title')->nullable();
            $table->text('custom_css')->nullable();
            $table->string('custom_icon')->nullable();
            $table->string('type')->nullable();
            $table->text('type_params')->nullable();
            $table->unsignedBigInteger('click_number')->default(0);
            $table->string('up_link')->default('no');
            $table->unsignedBigInteger('order')->default(0);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        $now = now();

        DB::table('users')->insert([
            [
                'id' => 5001,
                'name' => 'Agency Owner',
                'email' => 'owner@example.test',
                'password' => bcrypt('secret'),
                'littlelink_name' => 'owner-main',
                'role' => 'user',
                'block' => 'no',
                'theme' => 'default',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5002,
                'name' => 'Managed Hub',
                'email' => 'hub@example.test',
                'password' => bcrypt('secret'),
                'littlelink_name' => 'hub-main',
                'role' => 'agency_hub',
                'block' => 'no',
                'theme' => 'default',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => 5001,
            'managed_user_id' => 5002,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('buttons')->insert([
            'id' => 1,
            'name' => 'custom_website',
            'alt' => 'Website',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('links')->insert([
            [
                'id' => 900001,
                'user_id' => 5002,
                'button_id' => 1,
                'link' => 'https://example.test',
                'title' => 'Hub Link',
                'custom_css' => null,
                'custom_icon' => 'fa-link',
                'type' => 'predefined',
                'type_params' => null,
                'click_number' => 0,
                'up_link' => 'no',
                'order' => 0,
                'is_disabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 900002,
                'user_id' => 5002,
                'button_id' => 1,
                'link' => null,
                'title' => 'Schreib uns',
                'custom_css' => null,
                'custom_icon' => null,
                'type' => 'hub_contact_form',
                'type_params' => json_encode([
                    'internal_title' => 'Kontaktformular Startseite',
                    'form_description' => 'Senden Sie uns eine Nachricht.',
                ]),
                'click_number' => 0,
                'up_link' => 'no',
                'order' => 1,
                'is_disabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
