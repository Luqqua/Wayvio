<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingCheckoutFlagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->useSqliteInMemory();
        $this->createBillingTables();

        config()->set('tiers.order', ['free', 'pro', 'agency']);
    }

    public function test_checkout_uses_internal_api_when_billing_ms_enabled(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/checkout-session' => Http::response([
                'url' => 'https://checkout.stripe.test/session_123',
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 2,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertOk()->assertJson([
            'url' => 'https://checkout.stripe.test/session_123',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:8001/api/billing/checkout-session'
                && is_array($payload)
                && str_contains((string) ($payload['success_url'] ?? ''), '{CHECKOUT_SESSION_ID}');
        });
    }

    public function test_checkout_returns_503_when_billing_ms_disabled(): void
    {
        config()->set('internal-api.billing.enabled', false);

        Http::fake();

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 2,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertStatus(503)->assertJson([
            'error' => 'billing_api_disabled',
        ]);

        Http::assertNothingSent();
    }

    public function test_checkout_returns_503_when_internal_api_is_unavailable(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/checkout-session' => Http::response([
                'error' => [
                    'code' => 'internal_error',
                ],
            ], 503),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 2,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertStatus(503)->assertJson([
            'error' => 'billing_api_unavailable',
        ]);

        Http::assertSentCount(1);
    }

    public function test_checkout_confirm_forwards_pending_status_from_internal_api(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/checkout-confirm' => Http::response([
                'status' => 'pending',
                'code' => 'awaiting_webhook',
                'message' => 'Payment completed in Stripe. Waiting for webhook confirmation.',
                'retryable' => true,
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/checkout/confirm', [
            'session_id' => 'cs_test_123',
        ]);

        $response->assertOk()->assertJson([
            'status' => 'pending',
            'code' => 'awaiting_webhook',
            'retryable' => true,
        ]);

        Http::assertSentCount(1);
    }

    public function test_checkout_confirm_propagates_internal_validation_error(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/checkout-confirm' => Http::response([
                'error' => [
                    'code' => 'invalid_checkout_confirmation_payload',
                    'message' => 'Invalid checkout confirmation payload',
                ],
            ], 422),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/checkout/confirm', [
            'session_id' => 'invalid',
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Invalid checkout confirmation payload',
            'message' => 'Invalid checkout confirmation payload',
        ]);

        Http::assertSentCount(1);
    }

    public function test_checkout_rejects_non_monthly_periods(): void
    {
        config()->set('internal-api.billing.enabled', true);

        Http::fake();

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 2,
            'period' => 3,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['period']);

        Http::assertNothingSent();
    }

    public function test_active_paid_subscription_must_use_change_flow_instead_of_checkout(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake();

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 3,
            'hub_count' => 6,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Use the monthly plan change action for existing subscriptions.',
        ]);

        Http::assertNothingSent();
    }

    public function test_free_user_can_start_agency_monthly_checkout(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/checkout-session' => Http::response([
                'url' => 'https://checkout.stripe.test/session_agency_123',
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/checkout/session', [
            'tier_id' => 3,
            'hub_count' => 6,
            'terms_acknowledged' => true,
            'withdrawal_waiver_acknowledged' => true,
        ]);

        $response->assertOk()->assertJson([
            'url' => 'https://checkout.stripe.test/session_agency_123',
        ]);

        Http::assertSentCount(1);
    }

    public function test_change_subscription_uses_internal_api_for_paid_users(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/change-subscription' => Http::response([
                'status' => 'subscription_updated',
                'message' => 'Upgrade applied immediately.',
            ], 200),
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/change-plan', [
            'tier_id' => 3,
            'hub_count' => 6,
        ]);

        $response->assertOk()->assertJson([
            'status' => 'subscription_updated',
            'message' => 'Upgrade applied immediately.',
        ]);

        Http::assertSentCount(1);
    }

    public function test_change_subscription_preview_uses_internal_api_for_paid_users(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/change-subscription-preview' => Http::response([
                'status' => 'preview_ready',
                'action_status' => 'subscription_updated',
                'is_upgrade' => true,
                'is_downgrade' => false,
                'target_hub_count' => 6,
                'amount_due_now' => 2190,
                'proration_amount' => 2190,
                'currency' => 'eur',
            ], 200),
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/change-plan/preview', [
            'tier_id' => 3,
            'hub_count' => 6,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'preview_ready')
            ->assertJsonPath('is_upgrade', true)
            ->assertJsonPath('amount_due_now', 2190)
            ->assertJsonPath('currency', 'eur');

        Http::assertSentCount(1);
    }

    public function test_change_subscription_rejects_free_users(): void
    {
        config()->set('internal-api.billing.enabled', true);

        Http::fake();

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/change-plan', [
            'tier_id' => 2,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Start a new monthly subscription via checkout first.',
        ]);

        Http::assertNothingSent();
    }

    public function test_change_subscription_allows_current_plan_when_a_change_is_pending(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/change-subscription' => Http::response([
                'status' => 'subscription_change_reverted',
                'message' => 'The scheduled cancellation or downgrade was removed.',
            ], 200),
        ]);

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => false,
            'pending_tier_id' => 1,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/change-plan', [
            'tier_id' => 2,
        ]);

        $response->assertOk()->assertJson([
            'status' => 'subscription_change_reverted',
            'message' => 'The scheduled cancellation or downgrade was removed.',
        ]);
    }

    public function test_change_subscription_rejects_when_same_target_is_already_scheduled(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake();

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => false,
            'pending_tier_id' => 1,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->postJson('/billing/change-plan', [
            'tier_id' => 1,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'This plan change is already scheduled for the next renewal.',
        ]);

        Http::assertNothingSent();
    }

    public function test_subscription_dashboard_keeps_portal_for_free_users_with_saved_stripe_customer(): void
    {
        config()->set('internal-api.billing.enabled', false);

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'canceled',
            'cancel_at_period_end' => false,
            'pending_tier_id' => null,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('starts_new_subscription', true)
            ->assertJsonPath('manage_in_portal', true)
            ->assertJsonPath('has_paid_entitlement', false);
    }

    public function test_subscription_dashboard_describes_scheduled_cancellation(): void
    {
        config()->set('internal-api.billing.enabled', false);

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => true,
            'pending_tier_id' => 1,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('manage_in_portal', true)
            ->assertJsonPath('has_pending_change', true);

        $this->assertStringContainsString(
            'Cancellation is scheduled',
            (string) $response->json('downgrade_notice')
        );
    }

    public function test_subscription_dashboard_prefers_refreshed_billing_state_over_stale_local_cancellation_flags(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => true,
            'pending_tier_id' => 1,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'http://127.0.0.1:8001/api/billing/subscription-state*' => Http::response([
                'subscription' => [
                    'user_id' => 101,
                    'tier_id' => 2,
                    'tier_slug' => 'pro',
                    'tier_name' => 'Pro',
                    'stripe_status' => 'active',
                    'cancel_at_period_end' => false,
                    'pending_tier_id' => null,
                    'pending_hub_slots_included' => null,
                ],
                'portal_available' => true,
                'upgrade_previews' => [],
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('cancel_at_period_end', false)
            ->assertJsonPath('has_pending_change', false)
            ->assertJsonPath('pending_change_target_slug', '');

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_starts_with($request->url(), 'http://127.0.0.1:8001/api/billing/subscription-state')
                && (string) ($query['refresh'] ?? '') === '1';
        });
    }

    public function test_subscription_dashboard_clears_stale_pending_free_target_even_when_local_cancel_flag_is_already_false(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => false,
            'pending_tier_id' => 1,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'http://127.0.0.1:8001/api/billing/subscription-state*' => Http::response([
                'subscription' => [
                    'user_id' => 101,
                    'tier_id' => 2,
                    'tier_slug' => 'pro',
                    'tier_name' => 'Pro',
                    'stripe_status' => 'active',
                    'cancel_at_period_end' => false,
                    'pending_tier_id' => null,
                    'pending_hub_slots_included' => null,
                ],
                'portal_available' => true,
                'upgrade_previews' => [],
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('cancel_at_period_end', false)
            ->assertJsonPath('has_pending_change', false)
            ->assertJsonPath('pending_change_target_slug', '');
    }

    public function test_subscription_dashboard_surfaces_paid_upgrade_proration_preview(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        DB::table('user_subscriptions')->insert([
            'user_id' => 101,
            'tier_id' => 2,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'cancel_at_period_end' => false,
            'pending_tier_id' => null,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => null,
            'hub_slots_addon' => 0,
            'expires_at' => now()->addDays(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'http://127.0.0.1:8001/api/billing/subscription-state*' => Http::response([
                'subscription' => [
                    'user_id' => 101,
                    'tier_id' => 2,
                    'tier_slug' => 'pro',
                    'tier_name' => 'Pro',
                    'cancel_at_period_end' => false,
                    'pending_tier_id' => null,
                    'pending_hub_slots_included' => null,
                ],
                'portal_available' => true,
                'upgrade_previews' => [
                    [
                        'tier_id' => 3,
                        'tier_slug' => 'agency',
                        'tier_name' => 'Agency',
                        'hub_count' => 2,
                        'amount_due_now' => 1234,
                        'proration_amount' => 1234,
                        'currency' => 'eur',
                        'line_items' => [],
                        'unavailable_reason' => '',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('starts_new_subscription', false)
            ->assertJsonPath('plan_cards.2.slug', 'agency')
            ->assertJsonPath('plan_cards.2.is_paid_upgrade', true)
            ->assertJsonPath('plan_cards.2.upgrade_preview_available', true)
            ->assertJsonPath('plan_cards.2.upgrade_preview_amount_due_now', 1234)
            ->assertJsonPath('plan_cards.2.upgrade_preview_currency', 'eur');
    }

    public function test_subscription_dashboard_does_not_mark_free_user_targets_as_paid_upgrades(): void
    {
        config()->set('internal-api.billing.enabled', true);
        config()->set('internal-api.billing.base_url', 'http://127.0.0.1:8001');
        config()->set('internal-api.billing.api_key', 'test-key');

        Http::fake([
            'http://127.0.0.1:8001/api/billing/subscription-state*' => Http::response([
                'subscription' => null,
                'portal_available' => false,
                'upgrade_previews' => [
                    [
                        'tier_id' => 2,
                        'tier_slug' => 'pro',
                        'tier_name' => 'Pro',
                        'hub_count' => 1,
                        'amount_due_now' => 777,
                        'proration_amount' => 777,
                        'currency' => 'eur',
                        'line_items' => [],
                        'unavailable_reason' => '',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->user());

        $response = $this->getJson('/dashboard/subscription');

        $response->assertOk()
            ->assertJsonPath('starts_new_subscription', true)
            ->assertJsonPath('plan_cards.1.slug', 'pro')
            ->assertJsonPath('plan_cards.1.is_upgrade', true)
            ->assertJsonPath('plan_cards.1.is_paid_upgrade', false)
            ->assertJsonPath('plan_cards.1.upgrade_preview_amount_due_now', null);
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

    private function createBillingTables(): void
    {
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
            $table->unsignedBigInteger('tier_id');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_status', 32)->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->unsignedBigInteger('pending_tier_id')->nullable();
            $table->unsignedInteger('pending_hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('billing_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('stripe_payment_id')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->unsignedTinyInteger('period_months')->default(1);
            $table->timestamps();
        });

        DB::table('tiers')->insert([
            'id' => 1,
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Free',
            'max_pages' => 1,
            'max_links_per_page' => 10,
            'analytics_enabled' => true,
            'custom_domain_enabled' => false,
            'design_customization_enabled' => false,
            'price_1m' => 0,
            'price_3m' => 0,
            'price_6m' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tiers')->insert([
            'id' => 2,
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Pro',
            'max_pages' => 3,
            'max_links_per_page' => 50,
            'analytics_enabled' => true,
            'custom_domain_enabled' => false,
            'design_customization_enabled' => true,
            'price_1m' => 1500,
            'price_3m' => 4000,
            'price_6m' => 7500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tiers')->insert([
            'id' => 3,
            'name' => 'Agency',
            'slug' => 'agency',
            'description' => 'Agency',
            'max_pages' => 50,
            'max_links_per_page' => 200,
            'analytics_enabled' => true,
            'custom_domain_enabled' => true,
            'design_customization_enabled' => true,
            'price_1m' => 5900,
            'price_3m' => 15900,
            'price_6m' => 29900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(): User
    {
        $user = new User();
        $user->id = 101;
        $user->name = 'Billing Test User';
        $user->email = 'billing-test@example.com';
        $user->role = 'user';

        return $user;
    }
}
