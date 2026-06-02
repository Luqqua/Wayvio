<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class FooterBrandingVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('display.credit', true);
        config()->set('display.credit_footer', true);
        config()->set('display.footer', false);
        config()->set('media.disk', 'media_local');
        config()->set('app.url', 'https://wayvio.example.test');
        Storage::fake('media_local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        URL::forceRootUrl(null);
        parent::tearDown();
    }

    public function test_footer_shows_wayvio_branding_when_hide_credit_is_unset(): void
    {
        $user = $this->createUser(101, 'admin');
        $this->mockAgencyContext(false);
        URL::forceRootUrl('https://hub.example.test');

        $html = $this->renderFooter($user);

        $this->assertStringContainsString('wayvio-branding', $html);
        $this->assertStringContainsString('Wayvio logo', $html);
        $this->assertStringContainsString('WAYVIO', $html);
        $this->assertStringContainsString('href="https://wayvio.example.test/report?id=101"', $html);
        $this->assertStringNotContainsString('href="https://hub.example.test/report?id=101"', $html);
        $this->assertStringNotContainsString('<div class="ls-footer-credit-spacer"></div>', $html);
    }

    public function test_footer_credit_setting_can_show_wayvio_branding_when_global_credit_is_disabled(): void
    {
        config()->set('display.credit', false);
        config()->set('display.credit_footer', true);
        $user = $this->createUser(102, 'admin');
        $this->mockAgencyContext(false);

        $html = $this->renderFooter($user);

        $this->assertStringContainsString('wayvio-branding', $html);
        $this->assertStringContainsString('WAYVIO', $html);
    }

    public function test_footer_uses_agency_branding_for_active_hub(): void
    {
        $owner = $this->createUser(201, 'admin');
        $hub = $this->createUser(202, 'agency_hub');
        $brandingPath = 'tenants/201/users/201/agency-branding/logo.png';

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $owner->id,
            'managed_user_id' => $hub->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_settings')->insert([
            'user_id' => $owner->id,
            'data' => json_encode([
                'agency_branding_asset' => $brandingPath,
                'agency_branding_link' => 'https://agency.example.test',
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('media_local')->put($brandingPath, 'fake-logo');
        $this->mockAgencyContext(true, (int) $owner->id);

        $html = $this->renderFooter($hub);

        $this->assertStringContainsString('agency-branding-image', $html);
        $this->assertStringContainsString($brandingPath, $html);
        $this->assertStringContainsString('https://agency.example.test', $html);
        $this->assertStringNotContainsString('Wayvio logo', $html);
    }

    public function test_footer_shows_user_legal_links_when_global_footer_is_disabled(): void
    {
        config()->set('display.footer', false);
        $user = $this->createUser(301, 'admin');
        $this->mockAgencyContext(false);

        DB::table('links')->insert([
            'user_id' => $user->id,
            'type' => 'imprint',
            'button_id' => 1,
            'is_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_settings')->insert([
            'user_id' => $user->id,
            'data' => json_encode([
                'legal_privacy_layer_model' => [
                    'layer1' => ['controller_name' => 'Footer Test'],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->renderFooter($user);

        $this->assertStringContainsString('href="https://wayvio.example.test/footer-test-301/imprint"', $html);
        $this->assertStringContainsString('href="https://wayvio.example.test/footer-test-301/privacy"', $html);
        $this->assertStringContainsString('href="https://wayvio.example.test/report?id=301"', $html);
    }

    private function renderFooter(User $user): string
    {
        return view('wayvio.modules.footer', [
            'userinfo' => $user,
            'info' => (object) ['theme' => 'default'],
        ])->render();
    }

    private function mockAgencyContext(bool $isAgencyAccount, ?int $agencyUserId = null): void
    {
        $agencyContext = Mockery::mock(AgencyHubContext::class)->shouldIgnoreMissing();
        $agencyContext
            ->shouldReceive('isAgencyAccount')
            ->andReturnUsing(static function (User $user) use ($isAgencyAccount, $agencyUserId): bool {
                if (!$isAgencyAccount) {
                    return false;
                }

                return $agencyUserId === null || (int) $user->id === $agencyUserId;
            });

        $this->app->instance(AgencyHubContext::class, $agencyContext);
    }

    private function createUser(int $id, string $role): User
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Footer Test ' . $id,
            'email' => 'footer-test-' . $id . '@example.test',
            'password' => null,
            'role' => $role,
            'littlelink_name' => 'footer-test-' . $id,
            'littlelink_description' => null,
            'theme' => 'default',
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
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
            $table->string('littlelink_description')->nullable();
            $table->string('theme')->nullable();
            $table->string('locale')->nullable();
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

        Schema::create('links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type')->nullable();
            $table->unsignedInteger('button_id')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });
    }
}
