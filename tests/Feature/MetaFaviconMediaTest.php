<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Meta\CustomMetaPolicyService;
use App\Services\Uploads\MediaStorageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MetaFaviconMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('media.disk', 'media_local');
        config()->set('media.convert_uploads_to_webp', false);
        Storage::fake('media_local');
        Gate::before(fn () => true);
        $this->mockMetaAccess(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_favicon_upload_stores_file_on_media_disk(): void
    {
        $user = $this->createUser(701);
        $this->actingAs($user);

        $response = $this->post('/studio/meta/favicon', [
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ]);

        $response->assertRedirect('/studio/meta');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertIsString($settings['favicon_media_key'] ?? null);
        $this->assertStringContainsString('/favicons/', $settings['favicon_media_key']);
        Storage::disk('media_local')->assertExists($settings['favicon_media_key']);
    }

    public function test_meta_settings_view_keeps_favicon_form_outside_meta_form(): void
    {
        $user = $this->createUser(705);
        $this->actingAs($user);
        $this->withViewErrors([]);

        $html = view('studio.meta', [
            'user' => $user,
            'formValues' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'og_title' => '',
                'og_description' => '',
                'robots' => 'index,follow',
                'canonical_url' => '',
                'twitter_card' => 'summary_large_image',
                'og_locale' => 'de_DE',
            ],
            'defaults' => [
                'title' => 'Default title',
                'description' => 'Default description',
                'keywords' => '',
                'robots' => 'index,follow',
                'twitter_card' => 'summary_large_image',
                'og_locale' => 'de_DE',
            ],
            'canCustomize' => true,
            'requiredTierLabel' => 'Pro',
            'hasStoredOverrides' => false,
        ])->render();

        $metaFormOpen = strpos($html, 'id="meta-save-form"');
        $metaFormClose = strpos($html, '</form>', $metaFormOpen);
        $faviconFormOpen = strpos($html, 'id="favicon-upload-form"');
        $faviconFormClose = strpos($html, '</form>', $faviconFormOpen);

        $this->assertIsInt($metaFormOpen);
        $this->assertIsInt($metaFormClose);
        $this->assertIsInt($faviconFormOpen);
        $this->assertIsInt($faviconFormClose);
        $this->assertLessThan($faviconFormOpen, $metaFormClose);
        $this->assertStringContainsString('action="' . route('meta.favicon.save') . '"', $html);
        $this->assertStringContainsString('name="favicon"', substr($html, $faviconFormOpen, $faviconFormClose - $faviconFormOpen));
        $this->assertStringContainsString('name="title" class="form-control" form="meta-save-form"', $html);
        $this->assertStringContainsString('id="favicon-current-preview"', $html);
        $this->assertStringContainsString('No custom favicon uploaded yet.', $html);
    }

    public function test_meta_settings_view_shows_current_favicon_preview(): void
    {
        $user = $this->createUser(706);
        $this->actingAs($user);
        $this->withViewErrors([]);

        $faviconPath = 'tenants/706/users/706/favicons/current.png';
        Storage::disk('media_local')->put($faviconPath, 'favicon');
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode(['favicon_media_key' => $faviconPath], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = view('studio.meta', [
            'user' => $user,
            'formValues' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'og_title' => '',
                'og_description' => '',
                'robots' => 'index,follow',
                'canonical_url' => '',
                'twitter_card' => 'summary_large_image',
                'og_locale' => 'de_DE',
            ],
            'defaults' => [
                'title' => 'Default title',
                'description' => 'Default description',
                'keywords' => '',
                'robots' => 'index,follow',
                'twitter_card' => 'summary_large_image',
                'og_locale' => 'de_DE',
            ],
            'canCustomize' => true,
            'requiredTierLabel' => 'Pro',
            'hasStoredOverrides' => false,
        ])->render();

        $this->assertStringContainsString('id="favicon-current-preview"', $html);
        $this->assertStringContainsString($faviconPath, $html);
        $this->assertStringContainsString('Custom favicon is active.', $html);
        $this->assertStringContainsString('form="delete-favicon-form"', $html);
    }

    public function test_favicon_upload_replaces_existing_file(): void
    {
        $user = $this->createUser(702);
        $this->actingAs($user);

        $oldPath = 'tenants/702/users/702/favicons/old.png';
        Storage::disk('media_local')->put($oldPath, 'old');
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode(['favicon_media_key' => $oldPath], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/studio/meta/favicon', [
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ]);

        $response->assertRedirect('/studio/meta');

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertIsArray($settings);
        $this->assertIsString($settings['favicon_media_key'] ?? null);
        $this->assertNotSame($oldPath, $settings['favicon_media_key']);
        Storage::disk('media_local')->assertMissing($oldPath);
        Storage::disk('media_local')->assertExists($settings['favicon_media_key']);
    }

    public function test_favicon_delete_removes_file_and_setting(): void
    {
        $user = $this->createUser(703);
        $this->actingAs($user);

        $oldPath = 'tenants/703/users/703/favicons/old.png';
        Storage::disk('media_local')->put($oldPath, 'old');
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode(['favicon_media_key' => $oldPath], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete('/studio/meta/favicon');

        $response->assertRedirect('/studio/meta');
        Storage::disk('media_local')->assertMissing($oldPath);

        $settings = json_decode((string) DB::table('user_settings')->where('user_id', $user->id)->value('data'), true);
        $this->assertTrue($settings === null || !array_key_exists('favicon_media_key', $settings));
    }

    public function test_favicon_storage_errors_are_logged_and_return_validation_error(): void
    {
        $user = $this->createUser(704);
        $this->actingAs($user);

        $mediaStorage = Mockery::mock(MediaStorageService::class);
        $mediaStorage->shouldReceive('faviconDataKey')->andReturn('favicon_media_key');
        $mediaStorage->shouldReceive('storeUploadedFile')->andThrow(new RuntimeException('Unable to create a directory.'));
        $this->app->instance(MediaStorageService::class, $mediaStorage);

        Log::shouldReceive('error')
            ->once()
            ->with('Favicon save failed', Mockery::on(static function (array $context) use ($user): bool {
                return ($context['user_id'] ?? null) === (int) $user->id
                    && ($context['target_user_id'] ?? null) === (int) $user->id
                    && str_contains((string) ($context['message'] ?? ''), 'Unable to create a directory');
            }));

        $response = $this
            ->from('/studio/meta')
            ->post('/studio/meta/favicon', [
                'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ]);

        $response->assertRedirect('/studio/meta');
        $response->assertSessionHasErrors('favicon');
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
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->string('littlelink_name')->nullable();
            $table->text('littlelink_description')->nullable();
            $table->json('meta_overrides')->nullable();
            $table->string('meta_tags_status')->nullable();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('status')->default('active');
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

        DB::table('tiers')->insert([
            'id' => 1,
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Business',
            'max_pages' => 10,
            'max_links_per_page' => 200,
            'analytics_enabled' => true,
            'custom_domain_enabled' => true,
            'design_customization_enabled' => true,
            'price_1m' => 3500,
            'price_3m' => 9500,
            'price_6m' => 18000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(int $id): User
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Favicon User ' . $id,
            'email' => 'favicon-' . $id . '@example.test',
            'password' => null,
            'role' => 'admin',
            'littlelink_name' => 'favicon-' . $id,
            'littlelink_description' => null,
            'meta_tags_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function mockMetaAccess(bool $enabled): void
    {
        $agencyContext = Mockery::mock(AgencyHubContext::class)->shouldIgnoreMissing();
        $agencyContext->shouldReceive('editingUserId')->andReturnUsing(static fn (User $user): int => (int) $user->id);
        $this->app->instance(AgencyHubContext::class, $agencyContext);

        $customMetaPolicy = Mockery::mock(CustomMetaPolicyService::class);
        $customMetaPolicy->shouldReceive('isFeatureIncludedByTier')->andReturn($enabled);
        $this->app->instance(CustomMetaPolicyService::class, $customMetaPolicy);
    }
}
