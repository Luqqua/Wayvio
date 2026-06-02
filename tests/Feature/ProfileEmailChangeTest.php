<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChangeRequested;
use App\Notifications\EmailChangeVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('app.supported_locales', ['en', 'de']);
        config()->set('app.fallback_locale', 'de');
    }

    public function test_request_email_change_stores_pending_email_and_sends_verification(): void
    {
        Notification::fake();

        DB::table('users')->insert([
            'id' => 3001,
            'name' => 'Email Tester',
            'email' => 'email-tester@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('CurrentPass123'),
            'littlelink_name' => 'email-tester',
            'littlelink_description' => null,
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'de',
            'last_login_locale' => 'de',
            'pending_email' => null,
            'pending_email_token' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail(3001);

        $response = $this->actingAs($user)->from('/studio/profile')->post('/studio/profile/email', [
            'new_email' => 'new-email@example.test',
            'current_password' => 'CurrentPass123',
        ]);

        $response->assertRedirect('/studio/profile');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('new-email@example.test', $user->pending_email);
        $this->assertNotEmpty($user->pending_email_token);
        $this->assertNotSame('new-email@example.test', $user->email);

        Notification::assertSentOnDemand(EmailChangeVerification::class);
        Notification::assertSentTo($user, EmailChangeRequested::class);
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
            $table->string('pending_email')->nullable();
            $table->string('pending_email_token')->nullable();
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
