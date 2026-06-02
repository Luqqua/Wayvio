<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\Exceptions\AccountDeletionBlockedException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_delete_user_cancels_subscription_via_internal_api_before_local_deletion(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/cancel-on-account-delete' => Http::response([
                'status' => 'subscription_canceled',
            ], 200),
        ]);

        $user = $this->seedUserWithSubscription('active');

        app(AccountDeletionService::class)->deleteUser($user);

        $this->assertHardDeletionApplied((int) $user->id, 'admin');
        $this->assertDatabaseHas('deleted_accounts', [
            'stripe_subscription_id' => 'sub_test_123',
            'subscription_status_before_delete' => 'active',
            'cancel_at_period_end_before_delete' => 0,
            'subscription_status_before_cancel' => 'active',
            'cancel_at_period_end_before_cancel' => 0,
            'deletion_source' => 'unknown',
            'billing_cancel_status' => 'subscription_canceled',
            'billing_canceled' => 1,
            'billing_cancel_http_status' => null,
        ]);
        $this->assertNotNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('billing_canceled_at'));
        $this->assertNotNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('deletion_request_id'));
        $this->assertNotNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('stripe_canceled_at'));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($user): bool {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:8001/api/billing/cancel-on-account-delete'
                && (int) ($payload['user_id'] ?? 0) === (int) $user->id;
        });
    }

    public function test_self_delete_billing_guard_cancels_subscription_and_writes_self_delete_audit_context(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/cancel-on-account-delete' => Http::response([
                'status' => 'subscription_canceled',
                'canceled' => true,
            ], 200),
        ]);

        $user = $this->seedUserWithSubscription('active');

        app(AccountDeletionService::class)->ensureStripeCancellationForSelfDelete($user);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('links', ['user_id' => $user->id]);
        $this->assertDatabaseHas('user_subscriptions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('account_deletion_audit_log', [
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'action' => 'account_delete_billing',
            'source' => 'self_delete',
            'billing_status' => 'subscription_canceled',
            'canceled' => 1,
        ]);

        Http::assertSentCount(1);
    }

    public function test_deleted_account_snapshot_persist_is_idempotent_for_same_deletion_request_id(): void
    {
        $service = app(AccountDeletionService::class);
        $requestId = 'req-fixed-delete-001';
        $retainedUntil = now()->addYear();

        $persist = new \ReflectionMethod(AccountDeletionService::class, 'persistDeletedAccountSnapshot');
        $persist->setAccessible(true);

        $persist->invoke($service, [
            'stripe_subscription_id' => 'sub_req_fixed',
            'deletion_request_id' => $requestId,
            'billing_cancel_status' => 'subscription_canceled',
            'billing_canceled' => true,
            'stripe_cancel_event_id' => null,
            'retained_until' => $retainedUntil,
            'created_at' => now(),
        ]);

        $persist->invoke($service, [
            'stripe_subscription_id' => 'sub_req_fixed',
            'deletion_request_id' => $requestId,
            'billing_cancel_status' => 'subscription_canceled',
            'billing_canceled' => true,
            'stripe_cancel_event_id' => 'evt_after_retry',
            'retained_until' => $retainedUntil,
            'created_at' => now(),
        ]);

        $rows = DB::table('deleted_accounts')
            ->where('deletion_request_id', $requestId)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('evt_after_retry', $rows->first()->stripe_cancel_event_id);
    }

    public function test_delete_user_is_blocked_when_billing_cancel_request_fails(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/cancel-on-account-delete' => Http::response([
                'error' => [
                    'code' => 'stripe_unreachable',
                ],
            ], 503),
        ]);

        $user = $this->seedUserWithSubscription('active');

        try {
            app(AccountDeletionService::class)->deleteUser($user);
            $this->fail('Expected AccountDeletionBlockedException to be thrown.');
        } catch (AccountDeletionBlockedException $e) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('links', ['user_id' => $user->id]);
            $this->assertDatabaseHas('user_subscriptions', ['user_id' => $user->id]);
            $this->assertDatabaseMissing('deleted_accounts', ['stripe_subscription_id' => 'sub_test_123']);
        }

        Http::assertSentCount(1);
    }

    public function test_delete_user_is_blocked_when_billing_reports_no_active_subscription_but_local_state_is_active(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/cancel-on-account-delete' => Http::response([
                'status' => 'no_active_subscription',
                'canceled' => false,
            ], 200),
        ]);

        $user = $this->seedUserWithSubscription('active');

        try {
            app(AccountDeletionService::class)->deleteUser($user);
            $this->fail('Expected AccountDeletionBlockedException to be thrown.');
        } catch (AccountDeletionBlockedException $e) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('links', ['user_id' => $user->id]);
            $this->assertDatabaseHas('user_subscriptions', ['user_id' => $user->id]);
            $this->assertDatabaseMissing('deleted_accounts', ['stripe_subscription_id' => 'sub_test_123']);
        }

        Http::assertSentCount(1);
    }

    public function test_delete_user_proceeds_when_billing_reports_no_active_subscription_and_local_state_is_not_active(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/cancel-on-account-delete' => Http::response([
                'status' => 'no_active_subscription',
                'canceled' => false,
            ], 200),
        ]);

        $user = $this->seedUserWithSubscription('canceled');

        app(AccountDeletionService::class)->deleteUser($user);

        $this->assertHardDeletionApplied((int) $user->id, 'admin');
        $this->assertDatabaseHas('deleted_accounts', [
            'stripe_subscription_id' => 'sub_test_123',
            'subscription_status_before_delete' => 'canceled',
            'cancel_at_period_end_before_delete' => 0,
            'subscription_status_before_cancel' => 'canceled',
            'cancel_at_period_end_before_cancel' => 0,
            'billing_cancel_status' => 'no_active_subscription',
            'billing_canceled' => 0,
        ]);
        $this->assertNotNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('deletion_request_id'));
        $this->assertNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('billing_canceled_at'));
        $this->assertNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('stripe_canceled_at'));
        Http::assertSentCount(1);
    }

    public function test_delete_user_is_blocked_when_billing_bridge_is_disabled_and_subscription_looks_active(): void
    {
        config()->set('internal-api.billing.enabled', false);

        Http::fake();
        $user = $this->seedUserWithSubscription('active');

        try {
            app(AccountDeletionService::class)->deleteUser($user);
            $this->fail('Expected AccountDeletionBlockedException to be thrown.');
        } catch (AccountDeletionBlockedException $e) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('links', ['user_id' => $user->id]);
            $this->assertDatabaseHas('user_subscriptions', ['user_id' => $user->id]);
            $this->assertDatabaseMissing('deleted_accounts', ['stripe_subscription_id' => 'sub_test_123']);
        }

        Http::assertNothingSent();
    }

    public function test_delete_user_proceeds_when_billing_bridge_is_disabled_and_subscription_is_already_canceled(): void
    {
        config()->set('internal-api.billing.enabled', false);

        Http::fake();
        $user = $this->seedUserWithSubscription('canceled');

        app(AccountDeletionService::class)->deleteUser($user, [
            'source' => 'self_delete',
            'deletion_reason' => 'self',
            'actor_user_id' => (int) $user->id,
        ]);

        $this->assertHardDeletionApplied((int) $user->id, 'self');
        $this->assertDatabaseHas('deleted_accounts', [
            'stripe_subscription_id' => 'sub_test_123',
            'subscription_status_before_cancel' => 'canceled',
            'cancel_at_period_end_before_cancel' => 0,
            'deletion_source' => 'self_delete',
            'actor_user_id' => (int) $user->id,
            'billing_cancel_status' => 'billing_bridge_disabled',
            'billing_canceled' => 0,
        ]);
        $this->assertNotNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('deletion_request_id'));
        $this->assertNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('billing_canceled_at'));
        $this->assertNull(DB::table('deleted_accounts')->where('stripe_subscription_id', 'sub_test_123')->value('stripe_canceled_at'));
        Http::assertNothingSent();
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
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->timestamp('agb_accepted_at')->nullable();
            $table->string('agb_version', 40)->nullable();
            $table->timestamp('avv_accepted_at')->nullable();
            $table->string('avv_version', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->nullable();
            $table->unsignedInteger('price_1m')->default(0);
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tier_id')->default(2);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_status', 32)->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('stripe_payment_id');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('usd');
            $table->unsignedBigInteger('tier_id')->default(2);
            $table->unsignedTinyInteger('period_months')->default(1);
            $table->timestamps();
        });

        Schema::create('account_deletion_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->string('source', 64)->default('unknown');
            $table->string('billing_status', 64)->nullable();
            $table->boolean('canceled')->default(false);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('compliance_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type', 80)->nullable();
            $table->string('status', 32)->default('success');
            $table->string('source', 32)->default('web');
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type', 80)->nullable();
            $table->string('old_status', 64)->nullable();
            $table->string('new_status', 64)->nullable();
            $table->string('reason', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('deleted_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('plan_identifier')->nullable();
            $table->decimal('plan_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('billing_interval', 16)->nullable();
            $table->timestamp('subscription_start_at')->nullable();
            $table->timestamp('subscription_end_at')->nullable();
            $table->string('subscription_status_before_delete', 32)->nullable();
            $table->boolean('cancel_at_period_end_before_delete')->nullable();
            $table->string('subscription_status_before_cancel', 32)->nullable();
            $table->boolean('cancel_at_period_end_before_cancel')->nullable();
            $table->timestamp('account_created_at')->nullable();
            $table->timestamp('account_deleted_at')->nullable();
            $table->string('deletion_reason', 32)->nullable();
            $table->string('deletion_source', 64)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('deletion_request_id', 64)->nullable();
            $table->string('billing_cancel_status', 64)->nullable();
            $table->boolean('billing_canceled')->default(false);
            $table->timestamp('billing_canceled_at')->nullable();
            $table->string('stripe_cancel_event_id', 191)->nullable();
            $table->timestamp('stripe_canceled_at')->nullable();
            $table->unsignedSmallInteger('billing_cancel_http_status')->nullable();
            $table->string('billing_cancel_error_code', 80)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->unsignedInteger('hub_count')->default(0);
            $table->timestamp('agb_accepted_at')->nullable();
            $table->string('agb_version', 40)->nullable();
            $table->timestamp('avv_accepted_at')->nullable();
            $table->string('avv_version', 40)->nullable();
            $table->json('plan_history_json')->nullable();
            $table->timestamp('retained_until');
            $table->timestamp('created_at')->nullable();
            $table->unique('deletion_request_id');
            $table->unique('stripe_cancel_event_id');
        });
    }

    private function seedUserWithSubscription(string $stripeStatus): User
    {
        $userId = 101;
        $now = now();

        DB::table('tiers')->insert([
            'id' => 2,
            'slug' => 'pro',
            'price_1m' => 1200,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Delete Me',
            'email' => 'deleteme@example.test',
            'password' => 'x',
            'role' => 'user',
            'block' => 'no',
            'agb_accepted_at' => $now->copy()->subDays(10),
            'agb_version' => '2026-03',
            'avv_accepted_at' => $now->copy()->subDays(10),
            'avv_version' => '2026-03',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('links')->insert([
            'id' => 501,
            'user_id' => $userId,
            'is_disabled' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => $userId,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => $stripeStatus,
            'cancel_at_period_end' => false,
            'expires_at' => $now->copy()->addMonth(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('billing_records')->insert([
            'user_id' => $userId,
            'stripe_payment_id' => 'pi_test_123',
            'amount' => 1200,
            'currency' => 'eur',
            'tier_id' => 2,
            'period_months' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('compliance_audit_log')->insert([
            'user_id' => $userId,
            'actor_user_id' => $userId,
            'event_type' => 'login_success',
            'metadata' => json_encode(['auth_method' => 'password'], JSON_UNESCAPED_SLASHES),
            'created_at' => $now->copy()->subDay(),
        ]);

        DB::table('audit_log')->insert([
            'user_id' => $userId,
            'metadata' => json_encode([
                'previous_plan' => 'basic',
                'new_plan' => 'pro',
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => $now->copy()->subDays(5),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function assertHardDeletionApplied(int $userId, string $expectedReason): void
    {
        $this->assertDatabaseMissing('users', [
            'id' => $userId,
        ]);
        $this->assertDatabaseMissing('links', ['user_id' => $userId]);
        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $userId]);
        $this->assertDatabaseMissing('billing_records', ['user_id' => $userId]);
        $this->assertDatabaseMissing('account_deletion_audit_log', ['user_id' => $userId]);
        $this->assertDatabaseMissing('compliance_audit_log', ['user_id' => $userId]);

        $this->assertDatabaseHas('deleted_accounts', [
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'plan_identifier' => 'pro',
            'deletion_reason' => $expectedReason,
            'login_count' => 1,
            'hub_count' => 0,
            'agb_version' => '2026-03',
            'avv_version' => '2026-03',
        ]);
    }
}
