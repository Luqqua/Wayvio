<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Templates\TemplateCatalogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TemplateThemeFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createTables();
        $this->seedUser();
        $this->configureTemplateCatalog();

        config()->set('mod.allowed_commands', ['*']);
        TemplateCatalogService::flushCache();
    }

    protected function tearDown(): void
    {
        TemplateCatalogService::flushCache();
        parent::tearDown();
    }

    public function test_template_assign_command_sets_theme_and_variant_metadata(): void
    {
        $exitCode = Artisan::call('template:assign', [
            'user_id' => 1001,
            'template_id' => 'aurora',
            'variant_id' => 'emerald',
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $user = User::query()->findOrFail(1001);
        $settings = $this->settingsFor(1001);

        $this->assertSame('Aurora', $user->theme);
        $this->assertSame('aurora', $settings['template'] ?? null);
        $this->assertSame('aurora', $settings['theme_template_id'] ?? null);
        $this->assertSame('emerald', $settings['theme_variant_id'] ?? null);
        $this->assertSame('template', $settings['background_mode'] ?? null);
        $this->assertSame('default', $settings['template_background_mode'] ?? null);
    }

    public function test_template_assign_command_rejects_unknown_template(): void
    {
        $exitCode = Artisan::call('template:assign', [
            'user_id' => 1001,
            'template_id' => 'does-not-exist',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('default', (string) User::query()->findOrFail(1001)->theme);
        $this->assertFalse(DB::table('user_settings')->where('user_id', 1001)->exists());
    }

    public function test_edit_theme_route_updates_theme_and_falls_back_to_default_variant(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(1001))
            ->post('/studio/theme', [
                'template_id' => 'aurora',
                'variant_id' => 'not-valid',
            ]);

        $response->assertRedirect('/studio/theme');

        $user = User::query()->findOrFail(1001);
        $settings = $this->settingsFor(1001);

        $this->assertSame('Aurora', $user->theme);
        $this->assertSame('aurora', $settings['theme_template_id'] ?? null);
        $this->assertSame('default', $settings['theme_variant_id'] ?? null);
        $this->assertSame('template', $settings['background_mode'] ?? null);
    }

    public function test_edit_theme_route_supports_legacy_theme_input(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(1001))
            ->post('/studio/theme', [
                'theme' => 'Aurora',
            ]);

        $response->assertRedirect('/studio/theme');

        $settings = $this->settingsFor(1001);
        $this->assertSame('Aurora', (string) User::query()->findOrFail(1001)->theme);
        $this->assertSame('aurora', $settings['theme_template_id'] ?? null);
        $this->assertSame('default', $settings['theme_variant_id'] ?? null);
    }

    public function test_edit_theme_route_rejects_invalid_template_selection(): void
    {
        $response = $this->actingAs(User::query()->findOrFail(1001))
            ->post('/studio/theme', [
                'template_id' => 'invalid-template',
            ]);

        $response->assertRedirect('/studio/theme');
        $this->assertSame('default', (string) User::query()->findOrFail(1001)->theme);
        $this->assertFalse(DB::table('user_settings')->where('user_id', 1001)->exists());
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
    }

    private function seedUser(): void
    {
        DB::table('users')->insert([
            'id' => 1001,
            'name' => 'Template Tester',
            'email' => 'template-tester@example.test',
            'email_verified_at' => now(),
            'password' => 'x',
            'littlelink_name' => 'template-tester',
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'en',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureTemplateCatalog(): void
    {
        config()->set('template-catalog.enabled', true);
        config()->set('template-catalog.include_uncatalogued', false);
        config()->set('template-catalog.templates', [
            'default' => [
                'theme' => 'default',
                'label' => 'Default',
                'enabled' => true,
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                ],
            ],
            'aurora' => [
                'theme' => 'Aurora',
                'label' => 'Aurora',
                'enabled' => true,
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                    'emerald' => [
                        'label' => 'Emerald',
                        'css' => 'variants/emerald.css',
                    ],
                ],
                'default_variant_id' => 'default',
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
