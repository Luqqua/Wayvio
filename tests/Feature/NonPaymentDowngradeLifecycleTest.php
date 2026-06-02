<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Lifecycle\AccountLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NonPaymentDowngradeLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('tiers.default_free_slug', 'free');
        config()->set('tiers.order', ['free', 'basic', 'pro', 'agency']);
        config()->set('billing.lifecycle.pending_deletion_grace_days', 1);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_non_payment_is_downgraded_to_free_and_does_not_delete_account(): void
    {
        $now = Carbon::parse('2026-03-26 12:00:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('user_custom_domains')->insert([
            'id' => 501,
            'user_id' => $userId,
            'page_id' => $userId,
            'domain' => 'customer.example.test',
            'lifecycle_status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('analytics_resource_states')->insert([
            'id' => 801,
            'user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_resource_states')->insert([
            'id' => 851,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_submissions')->insert([
            'id' => 861,
            'tenant_owner_user_id' => $userId,
            'hub_user_id' => $userId,
            'retention_until' => $now->copy()->addDays(7),
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subscription = DB::table('user_subscriptions')->where('id', $subscriptionId)->first();
        $this->assertNotNull($subscription);

        app(AccountLifecycleService::class)->processPaymentTimeline(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $user = DB::table('users')->where('id', $userId)->first();
        $updatedSubscription = DB::table('user_subscriptions')->where('id', $subscriptionId)->first();
        $domain = DB::table('user_custom_domains')->where('user_id', $userId)->first();
        $analytics = DB::table('analytics_resource_states')->where('user_id', $userId)->first();
        $formsState = DB::table('forms_resource_states')->where('user_id', $userId)->first();

        $this->assertNotNull($user);
        $this->assertNotNull($updatedSubscription);
        $this->assertNotNull($domain);
        $this->assertNotNull($analytics);
        $this->assertNotNull($formsState);

        $this->assertSame('active', (string) $user->account_status);
        $this->assertNull($user->account_delete_after_at);

        $this->assertSame(1, (int) $updatedSubscription->tier_id);
        $this->assertNotNull($updatedSubscription->payment_pending_deletion_at);
        $this->assertNotNull($updatedSubscription->payment_delete_after_at);

        $this->assertSame('suspended', (string) $domain->lifecycle_status);
        $this->assertSame('downgrade', (string) $domain->lifecycle_reason);
        $this->assertNull($domain->delete_after_at);

        $this->assertSame('active', (string) $analytics->status);
        $this->assertNull($analytics->reason);
        $this->assertNull($analytics->delete_after_at);
        $this->assertSame('suspended', (string) $formsState->status);
        $this->assertSame('downgrade', (string) $formsState->reason);
        $this->assertNotNull($formsState->suspended_at);
        $this->assertNull($formsState->pending_deletion_at);
        $this->assertNull($formsState->delete_after_at);
        $this->assertSame('suspended', (string) $user->meta_tags_status);
        $this->assertSame('downgrade', (string) $user->meta_tags_status_reason);
        $this->assertNotNull($user->meta_tags_suspended_at);
        $this->assertNull($user->meta_tags_pending_deletion_at);
        $this->assertNull($user->meta_tags_delete_after_at);
        $this->assertNotNull($user->meta_overrides);

        $this->assertTrue(
            DB::table('audit_log')
                ->where('user_id', $userId)
                ->where('event_type', 'non_payment_downgraded_to_free')
                ->exists()
        );
        $this->assertTrue(
            DB::table('billing_notification_outbox')
                ->where('user_id', $userId)
                ->where('template_key', 'non_payment_downgraded_to_free')
                ->exists()
        );
        $this->assertFalse(
            DB::table('audit_log')
                ->where('user_id', $userId)
                ->where('event_type', 'account_deleted')
                ->exists()
        );
    }

    public function test_non_payment_restriction_suspends_meta_tags_before_finalization(): void
    {
        $now = Carbon::parse('2026-03-26 12:10:00');
        Carbon::setTestNow($now);

        [, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(8),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        app(AccountLifecycleService::class)->processPaymentTimeline(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $user = DB::table('users')->where('id', 1001)->first();
        $this->assertNotNull($user);
        $this->assertSame('suspended', (string) $user->meta_tags_status);
        $this->assertSame('downgrade', (string) $user->meta_tags_status_reason);
        $this->assertNotNull($user->meta_tags_suspended_at);
        $this->assertNull($user->meta_tags_pending_deletion_at);
        $this->assertNull($user->meta_tags_delete_after_at);
        $this->assertNotNull($user->meta_overrides);
    }

    public function test_non_payment_recovery_keeps_free_tier_restrictions_until_upgrade(): void
    {
        $now = Carbon::parse('2026-03-26 12:20:00');
        Carbon::setTestNow($now);

        [, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        $service = app(AccountLifecycleService::class);
        $service->processPaymentTimeline(\Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId));

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'stripe_status' => 'active',
            'updated_at' => $now,
        ]);

        $service->processPaymentTimeline(\Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId));

        $user = DB::table('users')->where('id', 1001)->first();
        $forms = DB::table('forms_resource_states')->where('user_id', 1001)->first();
        $this->assertNotNull($user);
        $this->assertNotNull($forms);
        $this->assertSame('suspended', (string) $user->meta_tags_status);
        $this->assertSame('downgrade', (string) $user->meta_tags_status_reason);
        $this->assertNull($user->meta_tags_pending_deletion_at);
        $this->assertNull($user->meta_tags_delete_after_at);
        $this->assertNotNull($user->meta_overrides);
        $this->assertSame('suspended', (string) $forms->status);
        $this->assertSame('downgrade', (string) $forms->reason);
        $this->assertNull($forms->pending_deletion_at);
        $this->assertNull($forms->delete_after_at);
    }

    public function test_effective_downgrade_to_free_suspends_meta_tags_immediately(): void
    {
        $now = Carbon::parse('2026-03-26 12:25:00');
        Carbon::setTestNow($now);

        [, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDays(1),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 1,
            'lifecycle_last_tier_id' => 2,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->syncSubscriptionLifecycle(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $user = DB::table('users')->where('id', 1001)->first();
        $this->assertNotNull($user);
        $this->assertSame('suspended', (string) $user->meta_tags_status);
        $this->assertSame('downgrade', (string) $user->meta_tags_status_reason);
        $this->assertNotNull($user->meta_tags_suspended_at);
        $this->assertNotNull($user->meta_overrides);
    }

    public function test_missing_lifecycle_baseline_repairs_free_hub_entitlements(): void
    {
        $now = Carbon::parse('2026-03-26 12:27:00');
        Carbon::setTestNow($now);

        [$ownerId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        $managedUserId = 1101;
        $this->seedManagedHubForOwner($ownerId, $managedUserId, 'repair-hub-1');

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 1,
            'hub_slots_included' => 1,
            'hub_slots_addon' => 0,
            'lifecycle_last_tier_id' => null,
            'lifecycle_last_hub_slots_included' => null,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->syncSubscriptionLifecycle(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $hub = DB::table('agency_hubs')->where('managed_user_id', $managedUserId)->first();
        $subscription = DB::table('user_subscriptions')->where('id', $subscriptionId)->first();

        $this->assertNotNull($hub);
        $this->assertSame('suspended', (string) $hub->status);
        $this->assertSame('downgrade', (string) $hub->lifecycle_reason);
        $this->assertNotNull($hub->suspended_at);

        $this->assertNotNull($subscription);
        $this->assertSame(1, (int) $subscription->lifecycle_last_tier_id);

        $reason = app(AccountLifecycleService::class)->publicPageUnavailableReason($managedUserId);
        $this->assertSame('hub_suspended', $reason);
    }

    public function test_steady_state_free_subscription_repairs_active_hubs_from_legacy_drift(): void
    {
        $now = Carbon::parse('2026-03-26 12:29:00');
        Carbon::setTestNow($now);

        [$ownerId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        $managedUserId = 1102;
        $this->seedManagedHubForOwner($ownerId, $managedUserId, 'repair-hub-2');

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 1,
            'hub_slots_included' => 1,
            'hub_slots_addon' => 0,
            'lifecycle_last_tier_id' => 1,
            'lifecycle_last_hub_slots_included' => 1,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->syncSubscriptionLifecycle(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $hub = DB::table('agency_hubs')->where('managed_user_id', $managedUserId)->first();
        $this->assertNotNull($hub);
        $this->assertSame('suspended', (string) $hub->status);
        $this->assertSame('downgrade', (string) $hub->lifecycle_reason);
        $this->assertNotNull($hub->suspended_at);
    }

    public function test_legacy_pending_deletion_state_is_not_implicitly_reactivated(): void
    {
        $now = Carbon::parse('2026-03-26 12:30:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'unpaid',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: $now->copy()->subDays(87),
            paymentRestrictedAt: $now->copy()->subDays(83),
            paymentDeletionWarningSentAt: $now->copy()->subDays(60),
            paymentPendingDeletionAt: $now->copy()->subDays(30),
            paymentDeleteAfterAt: $now->copy()->subDays(23),
            accountStatus: 'pending_deletion',
            accountDeleteAfterAt: $now->copy()->subDays(22),
        );

        app(AccountLifecycleService::class)->processPaymentTimeline(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $user = DB::table('users')->where('id', $userId)->first();
        $subscription = DB::table('user_subscriptions')->where('id', $subscriptionId)->first();

        $this->assertNotNull($user);
        $this->assertNotNull($subscription);

        $this->assertSame('pending_deletion', (string) $user->account_status);
        $this->assertNotNull($user->account_delete_after_at);

        $this->assertNotNull($subscription->payment_pending_deletion_at);
        $this->assertNotNull($subscription->payment_delete_after_at);
        $this->assertSame(2, (int) $subscription->tier_id);

        $this->assertFalse(
            DB::table('audit_log')
                ->where('user_id', $userId)
                ->where('event_type', 'account_deleted')
                ->exists()
        );
    }

    public function test_pending_non_payment_resources_are_deleted_without_account_deletion(): void
    {
        $now = Carbon::parse('2026-03-26 13:00:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('user_custom_domains')->insert([
            'id' => 511,
            'user_id' => $userId,
            'page_id' => $userId,
            'domain' => 'cleanup.example.test',
            'lifecycle_status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('analytics_resource_states')->insert([
            'id' => 811,
            'user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_resource_states')->insert([
            'id' => 8511,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('analytics_events_extended')->insert([
            'id' => 901,
            'user_id' => $userId,
            'event_name' => 'view',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_submissions')->insert([
            ['id' => 9101, 'tenant_owner_user_id' => $userId, 'hub_user_id' => $userId, 'retention_until' => $now->copy()->addDays(180), 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9102, 'tenant_owner_user_id' => 1999, 'hub_user_id' => 1999, 'retention_until' => $now->copy()->addDays(180), 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('analytics_events_extended')->insert([
            'id' => 902,
            'user_id' => 1999,
            'event_name' => 'keep',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('analytics_site_tiers')->insert([
            ['id' => 1001, 'site_id' => $userId, 'tier_slug' => 'pro', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 1002, 'site_id' => 1999, 'tier_slug' => 'free', 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach ($this->analyticsRollupTables() as $index => $table) {
            DB::table($table)->insert([
                ['id' => (10000 + ($index * 10) + 1), 'site_id' => $userId, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (10000 + ($index * 10) + 2), 'site_id' => 1999, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        $service = app(AccountLifecycleService::class);
        $service->processPaymentTimeline(\Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId));

        DB::table('user_custom_domains')
            ->where('user_id', $userId)
            ->update([
                'lifecycle_status' => 'pending_deletion',
                'lifecycle_reason' => 'non_payment',
                'pending_deletion_at' => $now->copy()->subDay(),
                'delete_after_at' => $now->copy()->subMinute(),
            ]);
        DB::table('analytics_resource_states')
            ->where('user_id', $userId)
            ->update([
                'status' => 'pending_deletion',
                'reason' => 'non_payment',
                'pending_deletion_at' => $now->copy()->subDay(),
                'delete_after_at' => $now->copy()->subMinute(),
            ]);
        DB::table('forms_resource_states')
            ->where('user_id', $userId)
            ->update([
                'status' => 'pending_deletion',
                'reason' => 'non_payment',
                'pending_deletion_at' => $now->copy()->subDay(),
                'delete_after_at' => $now->copy()->subMinute(),
            ]);
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'meta_tags_status' => 'pending_deletion',
                'meta_tags_status_reason' => 'non_payment',
                'meta_tags_pending_deletion_at' => $now->copy()->subDay(),
                'meta_tags_delete_after_at' => $now->copy()->subMinute(),
            ]);

        $service->handlePendingDeletions();

        $this->assertSame(1, DB::table('users')->where('id', $userId)->count());
        $this->assertSame('active', (string) DB::table('users')->where('id', $userId)->value('account_status'));
        $this->assertSame(0, DB::table('user_custom_domains')->where('user_id', $userId)->count());
        $this->assertSame(0, DB::table('forms_submissions')->where('tenant_owner_user_id', $userId)->count());
        $this->assertSame(1, DB::table('forms_submissions')->where('tenant_owner_user_id', 1999)->count());
        $this->assertSame(0, DB::table('analytics_events_extended')->where('user_id', $userId)->count());
        $this->assertSame(1, DB::table('analytics_events_extended')->where('user_id', 1999)->count());
        $this->assertSame(0, DB::table('analytics_site_tiers')->where('site_id', $userId)->count());
        $this->assertSame(1, DB::table('analytics_site_tiers')->where('site_id', 1999)->count());
        foreach ($this->analyticsRollupTables() as $table) {
            $this->assertSame(0, DB::table($table)->where('site_id', $userId)->count(), $table . ' must be deleted');
            $this->assertSame(1, DB::table($table)->where('site_id', 1999)->count(), $table . ' must keep other site rows');
        }
        $this->assertSame(
            'deleted',
            (string) DB::table('analytics_resource_states')->where('user_id', $userId)->value('status')
        );
        $this->assertSame(
            'deleted',
            (string) DB::table('forms_resource_states')->where('user_id', $userId)->value('status')
        );
        $this->assertSame('deleted', (string) DB::table('users')->where('id', $userId)->value('meta_tags_status'));
        $this->assertNull(DB::table('users')->where('id', $userId)->value('meta_overrides'));
        $this->assertFalse(
            DB::table('audit_log')
                ->where('user_id', $userId)
                ->where('event_type', 'account_deleted')
                ->exists()
        );
    }

    public function test_legacy_non_payment_suspended_resources_enter_pending_deletion_with_unified_window(): void
    {
        $now = Carbon::parse('2026-03-26 13:15:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(40),
            paymentWarningSentAt: null,
            paymentRestrictedAt: $now->copy()->subDays(31),
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: $now->copy()->subDays(31),
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );
        $this->assertSame(1, DB::table('user_subscriptions')->where('id', $subscriptionId)->count());

        DB::table('user_custom_domains')->insert([
            'id' => 521,
            'user_id' => $userId,
            'page_id' => $userId,
            'domain' => 'legacy-non-payment.example.test',
            'lifecycle_status' => 'suspended',
            'lifecycle_reason' => 'non_payment',
            'suspended_at' => $now->copy()->subDays(31),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now->copy()->subDays(31),
            'updated_at' => $now->copy()->subDays(31),
        ]);

        DB::table('analytics_resource_states')->insert([
            'id' => 821,
            'user_id' => $userId,
            'status' => 'suspended',
            'reason' => 'non_payment',
            'suspended_at' => $now->copy()->subDays(31),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now->copy()->subDays(31),
            'updated_at' => $now->copy()->subDays(31),
        ]);
        DB::table('forms_resource_states')->insert([
            'id' => 1821,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'suspended',
            'reason' => 'non_payment',
            'suspended_at' => $now->copy()->subDays(31),
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now->copy()->subDays(31),
            'updated_at' => $now->copy()->subDays(31),
        ]);

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'meta_tags_status' => 'suspended',
                'meta_tags_status_reason' => 'non_payment',
                'meta_tags_suspended_at' => $now->copy()->subDays(31),
                'meta_tags_pending_deletion_at' => null,
                'meta_tags_delete_after_at' => null,
                'updated_at' => $now,
            ]);

        app(AccountLifecycleService::class)->handleSuspendedResources();

        $domain = DB::table('user_custom_domains')->where('user_id', $userId)->first();
        $analytics = DB::table('analytics_resource_states')->where('user_id', $userId)->first();
        $forms = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $user = DB::table('users')->where('id', $userId)->first();

        $this->assertNotNull($domain);
        $this->assertNotNull($analytics);
        $this->assertNotNull($forms);
        $this->assertNotNull($user);

        $this->assertSame('pending_deletion', (string) $domain->lifecycle_status);
        $this->assertSame('downgrade', (string) $domain->lifecycle_reason);
        $this->assertNotNull($domain->delete_after_at);
        $this->assertSame($now->copy()->addDay()->toDateTimeString(), Carbon::parse($domain->delete_after_at)->toDateTimeString());

        $this->assertSame('pending_deletion', (string) $analytics->status);
        $this->assertSame('downgrade', (string) $analytics->reason);
        $this->assertNotNull($analytics->delete_after_at);
        $this->assertSame($now->copy()->addDay()->toDateTimeString(), Carbon::parse($analytics->delete_after_at)->toDateTimeString());

        $this->assertSame('pending_deletion', (string) $forms->status);
        $this->assertSame('downgrade', (string) $forms->reason);
        $this->assertNotNull($forms->delete_after_at);
        $this->assertSame($now->copy()->addDay()->toDateTimeString(), Carbon::parse($forms->delete_after_at)->toDateTimeString());

        $this->assertSame('pending_deletion', (string) $user->meta_tags_status);
        $this->assertSame('downgrade', (string) $user->meta_tags_status_reason);
        $this->assertNotNull($user->meta_tags_delete_after_at);
        $this->assertSame($now->copy()->addDay()->toDateTimeString(), Carbon::parse($user->meta_tags_delete_after_at)->toDateTimeString());
    }

    public function test_non_payment_downgrade_keeps_forms_active_when_free_tier_allows_forms(): void
    {
        config()->set('forms.allowed_tiers', ['free', 'basic', 'pro', 'agency']);

        $now = Carbon::parse('2026-03-26 14:00:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('forms_resource_states')->insert([
            'id' => 2601,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->processPaymentTimeline(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $forms = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $this->assertNotNull($forms);
        $this->assertSame('active', (string) $forms->status);
        $this->assertNull($forms->reason);
        $this->assertNull($forms->suspended_at);
        $this->assertNull($forms->pending_deletion_at);
        $this->assertNull($forms->delete_after_at);
    }

    public function test_voluntary_downgrade_to_free_and_reupgrade_reactivate_forms_before_deletion(): void
    {
        config()->set('billing.lifecycle.downgrade_retention_days', 1);

        $now = Carbon::parse('2026-03-26 14:20:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('forms_resource_states')->insert([
            'id' => 2602,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_submissions')->insert([
            'id' => 2603,
            'tenant_owner_user_id' => $userId,
            'hub_user_id' => $userId,
            'retention_until' => $now->copy()->addHours(6),
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 1,
            'lifecycle_last_tier_id' => 2,
            'updated_at' => $now,
        ]);

        $service = app(AccountLifecycleService::class);
        $service->syncSubscriptionLifecycle(\Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId));

        $formsSuspended = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $this->assertNotNull($formsSuspended);
        $this->assertSame('suspended', (string) $formsSuspended->status);
        $this->assertSame('downgrade', (string) $formsSuspended->reason);
        $this->assertNotNull($formsSuspended->suspended_at);
        $this->assertTrue(
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2603)->value('retention_until'))
                ->greaterThanOrEqualTo($now->copy()->addDays(2))
        );

        DB::table('forms_resource_states')->where('user_id', $userId)->update([
            'suspended_at' => $now->copy()->subDays(2),
            'updated_at' => $now,
        ]);

        $service->handleSuspendedResources();

        $formsPending = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $this->assertNotNull($formsPending);
        $this->assertSame('pending_deletion', (string) $formsPending->status);
        $this->assertNotNull($formsPending->delete_after_at);
        $this->assertSame(1, DB::table('forms_submissions')->where('tenant_owner_user_id', $userId)->count());

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 2,
            'lifecycle_last_tier_id' => 1,
            'updated_at' => $now,
        ]);

        $service->syncSubscriptionLifecycle(\Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId));

        $formsReactivated = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $this->assertNotNull($formsReactivated);
        $this->assertSame('active', (string) $formsReactivated->status);
        $this->assertNull($formsReactivated->reason);
        $this->assertNull($formsReactivated->pending_deletion_at);
        $this->assertNull($formsReactivated->delete_after_at);
        $this->assertSame(1, DB::table('forms_submissions')->where('tenant_owner_user_id', $userId)->count());
    }

    public function test_downgrade_to_basic_rebases_forms_retention_with_31_day_grace_and_90_day_limit(): void
    {
        $now = Carbon::parse('2026-03-26 14:30:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('forms_submissions')->insert([
            [
                'id' => 2701,
                'tenant_owner_user_id' => $userId,
                'hub_user_id' => $userId,
                'retention_until' => $now->copy()->subDays(120)->addDays(365),
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(120),
                'updated_at' => $now,
            ],
            [
                'id' => 2702,
                'tenant_owner_user_id' => $userId,
                'hub_user_id' => $userId,
                'retention_until' => $now->copy()->subDays(95)->addDays(365),
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(95),
                'updated_at' => $now,
            ],
            [
                'id' => 2703,
                'tenant_owner_user_id' => $userId,
                'hub_user_id' => $userId,
                'retention_until' => $now->copy()->subDays(20)->addDays(365),
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(20),
                'updated_at' => $now,
            ],
        ]);

        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 4,
            'lifecycle_last_tier_id' => 2,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->syncSubscriptionLifecycle(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $graceUntil = $now->copy()->addDays(31);
        $this->assertSame(
            $graceUntil->toDateTimeString(),
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2701)->value('retention_until'))->toDateTimeString()
        );
        $this->assertSame(
            $graceUntil->toDateTimeString(),
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2702)->value('retention_until'))->toDateTimeString()
        );

        $recentExpected = $now->copy()->subDays(20)->addDays(90);
        $this->assertSame(
            $recentExpected->toDateTimeString(),
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2703)->value('retention_until'))->toDateTimeString()
        );
    }

    public function test_upgrade_from_basic_to_pro_extends_forms_retention_to_365_days(): void
    {
        $now = Carbon::parse('2026-03-26 14:30:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        // Two submissions with Basic retention (90d)
        DB::table('forms_submissions')->insert([
            [
                'id' => 2801,
                'tenant_owner_user_id' => $userId,
                'hub_user_id' => $userId,
                'retention_until' => $now->copy()->subDays(30)->addDays(90),
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(30),
                'updated_at' => $now,
            ],
            [
                'id' => 2802,
                'tenant_owner_user_id' => $userId,
                'hub_user_id' => $userId,
                'retention_until' => $now->copy()->subDays(80)->addDays(90),
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(80),
                'updated_at' => $now,
            ],
        ]);

        // Simulate upgrade: current = Pro (id=2), previous = Basic (id=4)
        DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
            'tier_id' => 2,
            'lifecycle_last_tier_id' => 4,
            'updated_at' => $now,
        ]);

        app(AccountLifecycleService::class)->syncSubscriptionLifecycle(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $this->assertSame(
            $now->copy()->subDays(30)->addDays(365)->toDateTimeString(),
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2801)->value('retention_until'))->toDateTimeString(),
            'Recent message (30d old) should be extended to created_at + 365d'
        );
        $this->assertSame(
            $now->copy()->subDays(80)->addDays(365)->toDateTimeString(),
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2802)->value('retention_until'))->toDateTimeString(),
            'Older message (80d old) should be extended to created_at + 365d'
        );
    }

    public function test_suspend_forms_does_not_reset_existing_pending_deletion_state(): void
    {
        $now = Carbon::parse('2026-03-26 14:40:00');
        Carbon::setTestNow($now);

        [$userId, $subscriptionId] = $this->seedUserAndSubscription(
            stripeStatus: 'past_due',
            paymentFailedAt: $now->copy()->subDays(90),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        $existingPendingAt = $now->copy()->subDay();
        $existingDeleteAfterAt = $now->copy()->addDays(3);
        DB::table('forms_resource_states')->insert([
            'id' => 2604,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'pending_deletion',
            'reason' => 'non_payment',
            'suspended_at' => $now->copy()->subDays(30),
            'pending_deletion_at' => $existingPendingAt,
            'delete_after_at' => $existingDeleteAfterAt,
            'deleted_at' => null,
            'created_at' => $now->copy()->subDays(30),
            'updated_at' => $now->copy()->subDay(),
        ]);

        app(AccountLifecycleService::class)->processPaymentTimeline(
            \Modules\Tiers\Models\UserSubscription::query()->findOrFail($subscriptionId)
        );

        $forms = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $this->assertNotNull($forms);
        $this->assertSame('pending_deletion', (string) $forms->status);
        $this->assertSame('non_payment', (string) $forms->reason);
        $this->assertSame($existingPendingAt->toDateTimeString(), Carbon::parse((string) $forms->pending_deletion_at)->toDateTimeString());
        $this->assertSame($existingDeleteAfterAt->toDateTimeString(), Carbon::parse((string) $forms->delete_after_at)->toDateTimeString());
    }

    public function test_schedule_self_deletion_marks_forms_pending_deletion_and_extends_retention(): void
    {
        $now = Carbon::parse('2026-03-26 15:00:00');
        Carbon::setTestNow($now);

        [$userId, ] = $this->seedUserAndSubscription(
            stripeStatus: 'active',
            paymentFailedAt: $now->copy()->subDay(),
            paymentWarningSentAt: null,
            paymentRestrictedAt: null,
            paymentDeletionWarningSentAt: null,
            paymentPendingDeletionAt: null,
            paymentDeleteAfterAt: null,
            accountStatus: 'active',
            accountDeleteAfterAt: null,
        );

        DB::table('forms_resource_states')->insert([
            'id' => 2605,
            'user_id' => $userId,
            'tenant_owner_user_id' => $userId,
            'status' => 'active',
            'reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('forms_submissions')->insert([
            'id' => 2606,
            'tenant_owner_user_id' => $userId,
            'hub_user_id' => $userId,
            'retention_until' => $now->copy()->addDay(),
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = User::query()->findOrFail($userId);
        app(AccountLifecycleService::class)->scheduleSelfDeletion($user);

        $deleteAt = $now->copy()->addDays(7);
        $forms = DB::table('forms_resource_states')->where('user_id', $userId)->first();
        $updatedUser = DB::table('users')->where('id', $userId)->first();

        $this->assertNotNull($forms);
        $this->assertNotNull($updatedUser);
        $this->assertSame('pending_deletion', (string) $updatedUser->account_status);
        $this->assertSame($deleteAt->toDateTimeString(), Carbon::parse((string) $updatedUser->account_delete_after_at)->toDateTimeString());
        $this->assertSame('pending_deletion', (string) $forms->status);
        $this->assertSame('manual', (string) $forms->reason);
        $this->assertSame($deleteAt->toDateTimeString(), Carbon::parse((string) $forms->delete_after_at)->toDateTimeString());
        $this->assertTrue(
            Carbon::parse((string) DB::table('forms_submissions')->where('id', 2606)->value('retention_until'))
                ->greaterThanOrEqualTo($deleteAt)
        );
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
            $table->json('meta_overrides')->nullable();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('locale')->nullable();
            $table->string('account_status', 32)->default('active');
            $table->string('account_status_reason', 32)->nullable();
            $table->timestamp('account_status_changed_at')->nullable();
            $table->timestamp('account_delete_after_at')->nullable();
            $table->timestamp('account_deleted_at')->nullable();
            $table->string('analytics_status', 32)->nullable();
            $table->string('analytics_status_reason', 32)->nullable();
            $table->timestamp('analytics_suspended_at')->nullable();
            $table->timestamp('analytics_pending_deletion_at')->nullable();
            $table->timestamp('analytics_delete_after_at')->nullable();
            $table->timestamp('analytics_deleted_at')->nullable();
            $table->string('meta_tags_status', 32)->default('active');
            $table->string('meta_tags_status_reason', 32)->nullable();
            $table->timestamp('meta_tags_suspended_at')->nullable();
            $table->timestamp('meta_tags_pending_deletion_at')->nullable();
            $table->timestamp('meta_tags_delete_after_at')->nullable();
            $table->timestamp('meta_tags_deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->string('stripe_status', 32)->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->unsignedBigInteger('pending_tier_id')->nullable();
            $table->unsignedInteger('pending_hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_included')->nullable();
            $table->unsignedInteger('hub_slots_addon')->default(0);
            $table->unsignedBigInteger('lifecycle_last_tier_id')->nullable();
            $table->unsignedInteger('lifecycle_last_hub_slots_included')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('payment_warning_sent_at')->nullable();
            $table->timestamp('payment_restricted_at')->nullable();
            $table->timestamp('payment_deletion_warning_sent_at')->nullable();
            $table->timestamp('payment_pending_deletion_at')->nullable();
            $table->timestamp('payment_delete_after_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('display_name', 160)->nullable();
            $table->string('status', 32)->default('active');
            $table->string('lifecycle_reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at_lifecycle')->nullable();
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('lifecycle_status', 32)->default('active');
            $table->string('lifecycle_reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at_lifecycle')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status', 32)->default('active');
            $table->string('reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forms_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('reason', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forms_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable();
            $table->unsignedBigInteger('hub_user_id')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_events_extended', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('event_name', 120)->nullable();
            $table->timestamps();
        });

        foreach ($this->analyticsRollupTables() as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->timestamps();
            });
        }

        Schema::create('analytics_site_tiers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('tier_slug', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type', 80);
            $table->string('old_status', 64)->nullable();
            $table->string('new_status', 64)->nullable();
            $table->string('reason', 32);
            $table->text('metadata')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('compliance_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type', 80);
            $table->string('status', 32)->default('success');
            $table->string('source', 32)->default('web');
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('billing_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 191);
            $table->string('template_key', 80);
            $table->string('dedupe_key', 64)->unique();
            $table->unsignedBigInteger('source_event_id')->nullable();
            $table->string('source_event_type', 191)->nullable();
            $table->text('payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return list<string>
     */
    private function analyticsRollupTables(): array
    {
        return [
            'analytics_rollup_events',
            'analytics_rollup_events_daily',
            'analytics_rollup_events_monthly',
            'analytics_rollup_links',
            'analytics_rollup_links_monthly',
            'analytics_rollup_referrers',
            'analytics_rollup_referrers_monthly',
            'analytics_rollup_geo',
            'analytics_rollup_geo_monthly',
            'analytics_rollup_utm',
            'analytics_rollup_utm_monthly',
        ];
    }

    /**
     * @return array{int,int}
     */
    private function seedUserAndSubscription(
        string $stripeStatus,
        Carbon $paymentFailedAt,
        ?Carbon $paymentWarningSentAt,
        ?Carbon $paymentRestrictedAt,
        ?Carbon $paymentDeletionWarningSentAt,
        ?Carbon $paymentPendingDeletionAt,
        ?Carbon $paymentDeleteAfterAt,
        string $accountStatus,
        ?Carbon $accountDeleteAfterAt,
    ): array {
        $userId = 1001;
        $subscriptionId = 2001;
        $now = now();

        DB::table('tiers')->insert([
            ['id' => 1, 'name' => 'Free', 'slug' => 'free', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Pro', 'slug' => 'pro', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Agency', 'slug' => 'agency', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Basic', 'slug' => 'basic', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Lifecycle User',
            'email' => 'lifecycle@example.test',
            'password' => 'x',
            'littlelink_name' => 'lifecycle-user',
            'meta_overrides' => json_encode([
                'title' => 'Custom Lifecycle Title',
                'description' => 'Custom Lifecycle Description',
            ], JSON_UNESCAPED_SLASHES),
            'role' => User::ROLE_USER,
            'block' => 'no',
            'locale' => 'de',
            'account_status' => $accountStatus,
            'account_status_reason' => $accountStatus === 'active' ? null : 'non_payment',
            'account_status_changed_at' => $now,
            'account_delete_after_at' => $accountDeleteAfterAt,
            'meta_tags_status' => 'active',
            'meta_tags_status_reason' => null,
            'meta_tags_suspended_at' => null,
            'meta_tags_pending_deletion_at' => null,
            'meta_tags_delete_after_at' => null,
            'meta_tags_deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_subscriptions')->insert([
            'id' => $subscriptionId,
            'user_id' => $userId,
            'tier_id' => 2,
            'stripe_status' => $stripeStatus,
            'cancel_at_period_end' => false,
            'pending_tier_id' => null,
            'pending_hub_slots_included' => null,
            'hub_slots_included' => 1,
            'hub_slots_addon' => 0,
            'lifecycle_last_tier_id' => 2,
            'lifecycle_last_hub_slots_included' => 1,
            'payment_failed_at' => $paymentFailedAt,
            'payment_warning_sent_at' => $paymentWarningSentAt,
            'payment_restricted_at' => $paymentRestrictedAt,
            'payment_deletion_warning_sent_at' => $paymentDeletionWarningSentAt,
            'payment_pending_deletion_at' => $paymentPendingDeletionAt,
            'payment_delete_after_at' => $paymentDeleteAfterAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$userId, $subscriptionId];
    }

    private function seedManagedHubForOwner(int $ownerId, int $managedUserId, string $slug): void
    {
        $now = now();

        DB::table('users')->insert([
            'id' => $managedUserId,
            'name' => 'Managed Hub ' . $managedUserId,
            'email' => 'managed-' . $managedUserId . '@example.test',
            'password' => 'x',
            'littlelink_name' => $slug,
            'role' => User::ROLE_AGENCY_HUB,
            'block' => 'no',
            'locale' => 'de',
            'account_status' => 'active',
            'account_status_reason' => null,
            'account_status_changed_at' => $now,
            'account_delete_after_at' => null,
            'meta_tags_status' => 'active',
            'meta_tags_status_reason' => null,
            'meta_tags_suspended_at' => null,
            'meta_tags_pending_deletion_at' => null,
            'meta_tags_delete_after_at' => null,
            'meta_tags_deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agency_hubs')->insert([
            'agency_user_id' => $ownerId,
            'managed_user_id' => $managedUserId,
            'display_name' => 'Hub ' . $managedUserId,
            'status' => 'active',
            'lifecycle_reason' => null,
            'suspended_at' => null,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'deleted_at_lifecycle' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
