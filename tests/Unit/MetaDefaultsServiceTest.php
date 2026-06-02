<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Meta\MetaDefaultsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaDefaultsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('app.name', 'Wayvio');
        config()->set('app.supported_locales', ['en', 'de']);
        config()->set('app.fallback_locale', 'de');
        app()->setLocale('de');

        config()->set('meta.defaults', [
            'title_pattern' => ':username | Wayvio',
            'description_pattern' => ':username auf Wayvio: alle wichtigen Links und Infos auf einen Blick.',
            'keywords_pattern' => ':username, :username profil, Wayvio',
            'robots' => 'index,follow',
            'twitter_card' => 'summary_large_image',
            'og_locale' => 'de_DE',
            'og_locale_map' => [
                'en' => 'en_US',
                'de' => 'de_DE',
            ],
            'locales' => [
                'en' => [
                    'description_pattern' => ':username on Wayvio - all key links and info in one place.',
                    'keywords_pattern' => ':username, :username profile, Wayvio',
                    'og_locale' => 'en_US',
                ],
                'de' => [
                    'description_pattern' => ':username auf Wayvio: alle wichtigen Links und Infos auf einen Blick.',
                    'keywords_pattern' => ':username, :username profil, Wayvio',
                    'og_locale' => 'de_DE',
                ],
            ],
        ]);
    }

    public function test_defaults_follow_page_locale_for_german_profile(): void
    {
        $user = $this->seedUser(
            id: 1001,
            locale: 'de',
            name: 'Anna Muster',
            littlelinkName: 'anna-muster'
        );

        $defaults = app(MetaDefaultsService::class)->defaultsForPage($user);

        $this->assertSame('Anna Muster | Wayvio', $defaults['title']);
        $this->assertSame('Anna Muster auf Wayvio: alle wichtigen Links und Infos auf einen Blick.', $defaults['description']);
        $this->assertSame('Anna Muster, Anna Muster profil, Wayvio', $defaults['keywords']);
        $this->assertSame('de_DE', $defaults['og_locale']);
    }

    public function test_hub_defaults_fallback_to_owner_locale(): void
    {
        $owner = $this->seedUser(
            id: 1101,
            locale: 'en',
            name: 'Agency Owner',
            littlelinkName: 'agency-owner'
        );
        $hub = $this->seedUser(
            id: 1102,
            locale: null,
            name: 'Hub Profile',
            littlelinkName: 'hub-profile',
            role: 'agency_hub'
        );

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $owner->id,
            'managed_user_id' => $hub->id,
            'display_name' => 'Hub Profile',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaults = app(MetaDefaultsService::class)->defaultsForPage($hub);

        $this->assertSame('Hub Profile on Wayvio - all key links and info in one place.', $defaults['description']);
        $this->assertSame('en_US', $defaults['og_locale']);
    }

    public function test_defaults_fallback_to_german_when_page_locale_is_missing(): void
    {
        $user = $this->seedUser(
            id: 1151,
            locale: null,
            name: 'Max Beispiel',
            littlelinkName: 'max-beispiel'
        );

        $defaults = app(MetaDefaultsService::class)->defaultsForPage($user);

        $this->assertSame('Max Beispiel auf Wayvio: alle wichtigen Links und Infos auf einen Blick.', $defaults['description']);
        $this->assertSame('de_DE', $defaults['og_locale']);
    }

    public function test_keywords_fallback_is_generated_when_pattern_is_missing(): void
    {
        config()->set('meta.defaults.keywords_pattern', null);
        config()->set('meta.defaults.locales.de.keywords_pattern', null);

        $user = $this->seedUser(
            id: 1201,
            locale: 'de',
            name: 'Lena',
            littlelinkName: 'lena'
        );

        $defaults = app(MetaDefaultsService::class)->defaultsForPage($user);

        $this->assertSame('Lena, Lena links, Lena profil, link in bio, Wayvio', $defaults['keywords']);
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
    }

    private function seedUser(
        int $id,
        ?string $locale,
        string $name,
        string $littlelinkName,
        string $role = 'user'
    ): User {
        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => 'meta-defaults-' . $id . '@example.test',
            'password' => 'x',
            'littlelink_name' => $littlelinkName,
            'littlelink_description' => null,
            'role' => $role,
            'block' => 'no',
            'theme' => 'default',
            'locale' => $locale,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
