<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserData;
use App\Services\Templates\TemplateCatalogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileHeaderLayoutSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createTables();
        $this->seedUser();
        $this->configureTemplateCatalog(true);

        TemplateCatalogService::flushCache();
    }

    protected function tearDown(): void
    {
        TemplateCatalogService::flushCache();
        parent::tearDown();
    }

    public function test_edit_page_persists_business_profile_layout_when_switcher_enabled(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => 'Layout Tester',
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');

        $settings = $this->settingsFor(2001);
        $this->assertSame('business', $settings['profile_header_layout'] ?? null);
    }

    public function test_edit_page_persists_business_header_focus_description_layout_when_switcher_enabled(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => 'Layout Tester',
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business_header_focus_description',
            ]);

        $response->assertRedirect('/studio/page');

        $settings = $this->settingsFor(2001);
        $this->assertSame('business_header_focus_description', $settings['profile_header_layout'] ?? null);
    }

    public function test_edit_page_accepts_description_at_75_character_limit(): void
    {
        $description = 'test description 1 test description 2 test description 3 test description 4';

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => 'Layout Tester',
                'pageDescription' => $description,
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');
        $this->assertSame(75, strlen($description));
        $this->assertSame($description, User::query()->findOrFail(2001)->littlelink_description);
    }

    public function test_edit_page_rejects_description_over_75_characters(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => 'Layout Tester',
                'pageDescription' => str_repeat('a', 76),
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');
        $response->assertSessionHasErrors('pageDescription');
        $this->assertSame('Initial description', User::query()->findOrFail(2001)->littlelink_description);
    }

    public function test_edit_page_accepts_title_at_20_character_limit(): void
    {
        $title = 'test name 1 test 2 t';

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => $title,
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');
        $this->assertSame(20, strlen($title));
        $this->assertSame($title, User::query()->findOrFail(2001)->name);
    }

    public function test_edit_page_rejects_title_over_20_characters(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => str_repeat('a', 21),
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');
        $response->assertSessionHasErrors('name');
        $this->assertSame('Layout Tester', User::query()->findOrFail(2001)->name);
    }

    public function test_edit_page_accepts_slug_at_25_character_limit(): void
    {
        $slug = 'slug-12345678901234567890';

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page-settings',
                'littlelink_name' => $slug,
                'name' => 'Layout Tester',
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page-settings');
        $this->assertSame(25, strlen($slug));
        $this->assertSame($slug, User::query()->findOrFail(2001)->littlelink_name);
    }

    public function test_edit_page_rejects_slug_over_25_characters(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page-settings',
                'littlelink_name' => str_repeat('a', 26),
                'name' => 'Layout Tester',
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page-settings');
        $response->assertSessionHasErrors('littlelink_name');
        $this->assertSame('layout-tester', User::query()->findOrFail(2001)->littlelink_name);
    }

    public function test_studio_validate_handle_allows_current_slug_for_authenticated_user(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/validate-handle', [
                'littlelink_name' => 'layout-tester',
            ]);

        $response->assertOk();
        $response->assertJson(['valid' => true]);
    }

    public function test_studio_validate_handle_rejects_slug_used_by_another_user(): void
    {
        DB::table('users')->insert([
            'id' => 2002,
            'name' => 'Second User',
            'email' => 'second-user@example.test',
            'email_verified_at' => now(),
            'password' => 'x',
            'littlelink_name' => 'taken-slug',
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'en',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/validate-handle', [
                'littlelink_name' => 'taken-slug',
            ]);

        $response->assertOk();
        $response->assertJson(['valid' => false]);
    }

    public function test_show_header_prefills_name_and_description_fields(): void
    {
        app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->get('/studio/header');

        $response->assertOk();
        $response->assertSee('value="Layout Tester"', false);
        $response->assertSee('Initial description');
        $response->assertSee('max. 20');
        $response->assertSee('max. 75');
    }

    public function test_edit_page_keeps_existing_layout_when_template_locks_layout_switcher(): void
    {
        User::query()->whereKey(2001)->update(['theme' => 'Paper']);
        UserData::saveData(2001, 'profile_header_layout', 'standard');

        $this->configureTemplateCatalog(false);
        TemplateCatalogService::flushCache();

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/page', [
                'return_to' => '/studio/page',
                'littlelink_name' => 'layout-tester',
                'name' => 'Layout Tester',
                'pageDescription' => 'Business profile description',
                'show_profile_image' => 1,
                'profile_header_layout' => 'business',
            ]);

        $response->assertRedirect('/studio/page');

        $settings = $this->settingsFor(2001);
        $this->assertSame('standard', $settings['profile_header_layout'] ?? null);
    }

    public function test_show_header_displays_business_hero_only_notice(): void
    {
        app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());
        UserData::saveData(2001, 'profile_header_layout', 'business');

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->get('/studio/header');

        $response->assertOk();
        $response->assertSee(__('messages.page.business_hero_only_notice'));
    }

    public function test_edit_header_disables_plain_header_mode_when_business_layout_is_active(): void
    {
        User::query()->whereKey(2001)->update(['role' => 'admin']);
        UserData::saveData(2001, 'profile_header_layout', 'business');
        UserData::saveData(2001, 'header_image', 'https://example.test/header.webp');

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/header', [
                'enable_header' => 1,
                'enable_header_hero' => 0,
                'enable_header_gradient' => 1,
            ]);

        $response->assertRedirect('/studio/header');

        $settings = $this->settingsFor(2001);
        $this->assertFalse((bool) ($settings['header_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_hero_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_gradient_enabled'] ?? false));
    }

    public function test_edit_header_keeps_hero_mode_available_when_business_layout_is_active(): void
    {
        User::query()->whereKey(2001)->update(['role' => 'admin']);
        UserData::saveData(2001, 'profile_header_layout', 'business');
        UserData::saveData(2001, 'header_image', 'https://example.test/header.webp');

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/header', [
                'enable_header' => 0,
                'enable_header_hero' => 1,
                'enable_header_gradient' => 1,
            ]);

        $response->assertRedirect('/studio/header');

        $settings = $this->settingsFor(2001);
        $this->assertTrue((bool) ($settings['header_enabled'] ?? false));
        $this->assertTrue((bool) ($settings['header_hero_enabled'] ?? false));
        $this->assertTrue((bool) ($settings['header_gradient_enabled'] ?? false));
    }

    public function test_edit_header_only_persists_background_fade_for_hero_mode(): void
    {
        User::query()->whereKey(2001)->update(['role' => 'admin']);
        UserData::saveData(2001, 'profile_header_layout', 'standard');
        UserData::saveData(2001, 'header_image', 'https://example.test/header.webp');

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/header', [
                'enable_header' => 1,
                'enable_header_hero' => 0,
                'enable_header_gradient' => 1,
            ]);

        $response->assertRedirect('/studio/header');

        $settings = $this->settingsFor(2001);
        $this->assertTrue((bool) ($settings['header_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_hero_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_gradient_enabled'] ?? false));
    }

    public function test_edit_header_disables_plain_header_mode_when_business_header_focus_description_layout_is_active(): void
    {
        User::query()->whereKey(2001)->update(['role' => 'admin']);
        UserData::saveData(2001, 'profile_header_layout', 'business_header_focus_description');
        UserData::saveData(2001, 'header_image', 'https://example.test/header.webp');

        $response = $this->actingAs(User::query()->findOrFail(2001))
            ->post('/studio/header', [
                'enable_header' => 1,
                'enable_header_hero' => 0,
                'enable_header_gradient' => 1,
            ]);

        $response->assertRedirect('/studio/header');

        $settings = $this->settingsFor(2001);
        $this->assertFalse((bool) ($settings['header_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_hero_enabled'] ?? false));
        $this->assertFalse((bool) ($settings['header_gradient_enabled'] ?? false));
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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_1m')->default(0);
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->unsignedBigInteger('pending_tier_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function seedUser(): void
    {
        DB::table('users')->insert([
            'id' => 2001,
            'name' => 'Layout Tester',
            'email' => 'layout-tester@example.test',
            'email_verified_at' => now(),
            'password' => 'x',
            'littlelink_name' => 'layout-tester',
            'littlelink_description' => 'Initial description',
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'en',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureTemplateCatalog(bool $paperLayoutSwitcherEnabled): void
    {
        config()->set('template-catalog.enabled', true);
        config()->set('template-catalog.include_uncatalogued', false);
        config()->set('template-catalog.templates', [
            'default' => [
                'theme' => 'default',
                'label' => 'Default',
                'enabled' => true,
                'capabilities' => [
                    'profile_layout_switcher' => true,
                ],
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                ],
            ],
            'paper' => [
                'theme' => 'Paper',
                'label' => 'Paper',
                'enabled' => true,
                'capabilities' => [
                    'profile_layout_switcher' => $paperLayoutSwitcherEnabled,
                ],
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                ],
            ],
        ]);
    }

    private function settingsFor(int $userId): array
    {
        $raw = DB::table('user_settings')->where('user_id', $userId)->value('data');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
