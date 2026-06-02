<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfilePasswordChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\CheckBlockedUser::class,
            \App\Http\Middleware\EnsurePendingTosAccepted::class,
            \App\Http\Middleware\EnsureTwoFactorVerified::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Modules\AnalyticsPremium\Http\Middleware\TrackPremiumAnalyticsMiddleware::class,
            \Modules\CustomDomains\Http\Middleware\DomainRoutingMiddleware::class,
            \Modules\Tiers\Http\Middleware\EnforceTierLimits::class,
            \Modules\Tiers\Http\Middleware\SubscriptionMiddleware::class,
        ]);
        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_password_change_succeeds_when_notification_delivery_fails(): void
    {
        $this->app->instance(Dispatcher::class, new class implements Dispatcher {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('Mail transport unavailable.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new \RuntimeException('Mail transport unavailable.');
            }
        });

        DB::table('users')->insert([
            'id' => 3101,
            'name' => 'Password Tester',
            'email' => 'password-tester@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('CurrentPass123'),
            'littlelink_name' => 'password-tester',
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'de',
            'last_login_locale' => 'de',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail(3101);

        $response = $this->withSession([])->actingAs($user)->from('/studio/profile')->post('/studio/profile/password', [
            'current_password' => 'CurrentPass123',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ]);

        $response->assertRedirect('/studio/profile');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass123', $user->password));
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
            $table->string('last_login_locale')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
