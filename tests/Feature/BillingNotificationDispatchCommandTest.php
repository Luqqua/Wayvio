<?php

namespace Tests\Feature;

use App\Notifications\BillingSubscriptionEventNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingNotificationDispatchCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createUsersTable();
        $this->createOutboxTable();
    }

    public function test_command_sends_pending_billing_notification(): void
    {
        config()->set('billing.notifications.enabled', true);
        config()->set('billing.notifications.max_attempts', 3);
        config()->set('billing.notifications.batch_size', 10);

        DB::table('billing_notification_outbox')->insert([
            'user_id' => 101,
            'email' => 'billing-test@example.com',
            'template_key' => 'subscription_started',
            'dedupe_key' => 'dedupe_1',
            'source_event_id' => 'evt_test_1',
            'source_event_type' => 'invoice.paid',
            'payload' => json_encode([
                'plan_name' => 'Pro',
                'current_period_end' => now()->addMonth()->toISOString(),
            ], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempt_count' => 0,
            'scheduled_for' => now()->subMinute(),
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('billing:send-notifications')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(BillingSubscriptionEventNotification::class);

        $row = DB::table('billing_notification_outbox')->where('dedupe_key', 'dedupe_1')->first();
        $this->assertNotNull($row);
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->attempt_count);
        $this->assertNotNull($row->sent_at);
    }

    public function test_command_uses_last_login_locale_when_account_locale_is_missing(): void
    {
        config()->set('billing.notifications.enabled', true);
        config()->set('billing.notifications.max_attempts', 3);
        config()->set('billing.notifications.batch_size', 10);
        config()->set('app.supported_locales', ['en', 'de']);

        DB::table('users')->insert([
            'id' => 501,
            'locale' => null,
            'last_login_locale' => 'en-US',
        ]);

        DB::table('billing_notification_outbox')->insert([
            'user_id' => 501,
            'email' => 'billing-locale-en@example.com',
            'template_key' => 'subscription_started',
            'dedupe_key' => 'dedupe_locale_en',
            'source_event_id' => 'evt_locale_en',
            'source_event_type' => 'invoice.paid',
            'payload' => json_encode([
                'plan_name' => 'Pro',
                'current_period_end' => now()->addMonth()->toISOString(),
            ], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempt_count' => 0,
            'scheduled_for' => now()->subMinute(),
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('billing:send-notifications')->assertExitCode(0);

        Notification::assertSentOnDemand(BillingSubscriptionEventNotification::class, function ($notification, array $channels, $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $mail->subject === 'Your subscription is active';
        });
    }

    public function test_command_falls_back_to_german_when_no_locale_is_available(): void
    {
        config()->set('billing.notifications.enabled', true);
        config()->set('billing.notifications.max_attempts', 3);
        config()->set('billing.notifications.batch_size', 10);
        config()->set('app.supported_locales', ['en', 'de']);

        DB::table('users')->insert([
            'id' => 502,
            'locale' => null,
            'last_login_locale' => null,
        ]);

        DB::table('billing_notification_outbox')->insert([
            'user_id' => 502,
            'email' => 'billing-locale-de@example.com',
            'template_key' => 'subscription_started',
            'dedupe_key' => 'dedupe_locale_de',
            'source_event_id' => 'evt_locale_de',
            'source_event_type' => 'invoice.paid',
            'payload' => json_encode([
                'plan_name' => 'Pro',
                'current_period_end' => now()->addMonth()->toISOString(),
            ], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempt_count' => 0,
            'scheduled_for' => now()->subMinute(),
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('billing:send-notifications')->assertExitCode(0);

        Notification::assertSentOnDemand(BillingSubscriptionEventNotification::class, function ($notification, array $channels, $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $mail->subject === 'Dein Abo ist aktiv';
        });
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

    private function createOutboxTable(): void
    {
        Schema::create('billing_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email', 320);
            $table->string('template_key', 80);
            $table->string('dedupe_key', 191)->unique();
            $table->string('source_event_id', 191)->nullable();
            $table->string('source_event_type', 191)->nullable();
            $table->text('payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('locale', 10)->nullable();
            $table->string('last_login_locale', 10)->nullable();
        });
    }
}
