<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Partners\Services\PartnerManager;
use Tests\TestCase;

class PartnerPaymentGateBillingReasonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createPartnerTables();
    }

    public function test_upgrade_invoice_does_not_unlock_second_payment_gate(): void
    {
        config()->set('partners.payment_gate.allow_legacy_null_meta', false);

        $this->insertCommissionEntry(
            partnerUserId: 9001,
            referredUserId: 7001,
            invoiceId: 'in_create_1',
            eventId: 'evt_create_1',
            billingReason: 'subscription_create',
        );
        $this->insertCommissionEntry(
            partnerUserId: 9001,
            referredUserId: 7001,
            invoiceId: 'in_upgrade_1',
            eventId: 'evt_upgrade_1',
            billingReason: 'subscription_update',
        );

        $updated = app(PartnerManager::class)->approveMaturedCommissions();

        $this->assertSame(0, $updated);
        $this->assertSame(
            2,
            DB::table('partner_commission_ledger')->where('status', 'pending')->count()
        );
    }

    public function test_subscription_create_and_cycle_unlock_second_payment_gate(): void
    {
        config()->set('partners.payment_gate.allow_legacy_null_meta', false);

        $this->insertCommissionEntry(
            partnerUserId: 9002,
            referredUserId: 7002,
            invoiceId: 'in_create_2',
            eventId: 'evt_create_2',
            billingReason: 'subscription_create',
        );
        $this->insertCommissionEntry(
            partnerUserId: 9002,
            referredUserId: 7002,
            invoiceId: 'in_cycle_2',
            eventId: 'evt_cycle_2',
            billingReason: 'subscription_cycle',
        );

        $updated = app(PartnerManager::class)->approveMaturedCommissions();

        $this->assertSame(2, $updated);
        $this->assertSame(
            2,
            DB::table('partner_commission_ledger')->where('status', 'approved')->count()
        );
    }

    public function test_legacy_null_meta_requires_explicit_opt_in(): void
    {
        $this->insertCommissionEntry(
            partnerUserId: 9003,
            referredUserId: 7003,
            invoiceId: 'in_legacy_a',
            eventId: 'evt_legacy_a',
            billingReason: null,
        );
        $this->insertCommissionEntry(
            partnerUserId: 9003,
            referredUserId: 7003,
            invoiceId: 'in_legacy_b',
            eventId: 'evt_legacy_b',
            billingReason: null,
        );

        config()->set('partners.payment_gate.allow_legacy_null_meta', false);
        $firstRunUpdated = app(PartnerManager::class)->approveMaturedCommissions();
        $this->assertSame(0, $firstRunUpdated);

        config()->set('partners.payment_gate.allow_legacy_null_meta', true);
        $secondRunUpdated = app(PartnerManager::class)->approveMaturedCommissions();
        $this->assertSame(2, $secondRunUpdated);
    }

    private function insertCommissionEntry(
        int $partnerUserId,
        int $referredUserId,
        string $invoiceId,
        string $eventId,
        ?string $billingReason
    ): void {
        $meta = null;
        if ($billingReason !== null) {
            $meta = json_encode([
                'invoice_billing_reason' => $billingReason,
                'payment_gate_eligible' => in_array($billingReason, ['subscription_create', 'subscription_cycle'], true),
            ], JSON_UNESCAPED_SLASHES);
        }

        DB::table('partner_commission_ledger')->insert([
            'partner_user_id' => $partnerUserId,
            'referred_user_id' => $referredUserId,
            'partner_attribution_id' => null,
            'billing_record_id' => null,
            'source_event_id' => $eventId,
            'source_invoice_id' => $invoiceId,
            'entry_type' => 'commission',
            'gross_amount_cents' => 1000,
            'commission_amount_cents' => 300,
            'currency' => 'usd',
            'status' => 'pending',
            'available_at' => now()->subDay(),
            'payout_batch_id' => null,
            'meta' => $meta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function createPartnerTables(): void
    {
        Schema::create('partner_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('default_commission_rate_bps')->default(3000);
            $table->unsignedInteger('payout_minimum_cents')->default(5000);
            $table->string('stripe_connect_account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_invite_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->string('code', 64)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamps();
        });

        Schema::create('partner_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('invite_code_id')->nullable();
            $table->string('source', 32)->default('code');
            $table->unsignedInteger('commission_rate_bps')->default(3000);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('attributed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_payout_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->string('currency', 3)->default('usd');
            $table->integer('net_amount_cents')->default(0);
            $table->string('status', 32)->default('pending');
            $table->string('stripe_transfer_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_commission_ledger', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->unsignedBigInteger('partner_attribution_id')->nullable()->index();
            $table->unsignedBigInteger('billing_record_id')->nullable()->index();
            $table->string('source_event_id')->nullable()->unique();
            $table->string('source_invoice_id')->nullable()->index();
            $table->string('entry_type', 32)->default('commission')->index();
            $table->integer('gross_amount_cents');
            $table->integer('commission_amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('available_at')->nullable()->index();
            $table->unsignedBigInteger('payout_batch_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
}

