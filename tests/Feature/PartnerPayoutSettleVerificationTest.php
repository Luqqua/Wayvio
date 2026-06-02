<?php

namespace Tests\Feature;

use App\Services\Partners\PartnersClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\Partners\Services\PartnerManager;
use Tests\TestCase;

class PartnerPayoutSettleVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createPartnerTables();
        config()->set('partners.payout_settle.require_verified_transfer', true);
    }

    public function test_settle_fails_when_transfer_verification_reports_mismatch(): void
    {
        $this->seedPendingBatch(
            batchId: 1,
            partnerUserId: 9001,
            referredUserId: 7001,
            transferId: null,
        );

        $client = Mockery::mock(PartnersClient::class);
        $client->shouldReceive('enabled')->once()->andReturn(true);
        $client->shouldReceive('verifyPayoutTransfer')
            ->once()
            ->with(1, 'tr_test_mismatch')
            ->andReturn([
                'verified' => false,
                'mismatches' => ['amount_mismatch'],
            ]);
        $this->app->instance(PartnersClient::class, $client);

        try {
            app(PartnerManager::class)->settleManualPayoutBatch(1, 'tr_test_mismatch');
            $this->fail('Expected payout settle to fail when verification mismatches.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Stripe transfer verification mismatch',
                collect($exception->errors())->flatten()->first() ?? ''
            );
        }

        $this->assertSame('pending', DB::table('partner_payout_batches')->where('id', 1)->value('status'));
        $this->assertSame('approved', DB::table('partner_commission_ledger')->where('id', 1)->value('status'));
    }

    public function test_settle_marks_batch_paid_after_verified_transfer(): void
    {
        $this->seedPendingBatch(
            batchId: 2,
            partnerUserId: 9002,
            referredUserId: 7002,
            transferId: null,
        );

        $client = Mockery::mock(PartnersClient::class);
        $client->shouldReceive('enabled')->once()->andReturn(true);
        $client->shouldReceive('verifyPayoutTransfer')
            ->once()
            ->with(2, 'tr_test_ok')
            ->andReturn([
                'verified' => true,
                'status' => 'verified',
            ]);
        $this->app->instance(PartnersClient::class, $client);

        $paidAt = CarbonImmutable::parse('2026-03-17 12:34:56');
        $batch = app(PartnerManager::class)->settleManualPayoutBatch(
            2,
            'tr_test_ok',
            $paidAt,
            'phpunit'
        );

        $this->assertSame('paid', $batch->status);
        $this->assertSame('tr_test_ok', $batch->stripe_transfer_id);
        $this->assertSame('paid', DB::table('partner_commission_ledger')->where('id', 2)->value('status'));
        $this->assertNotNull(DB::table('partner_payout_batches')->where('id', 2)->value('paid_at'));
    }

    private function seedPendingBatch(int $batchId, int $partnerUserId, int $referredUserId, ?string $transferId): void
    {
        DB::table('partner_accounts')->insert([
            'id' => $batchId,
            'user_id' => $partnerUserId,
            'status' => 'active',
            'default_commission_rate_bps' => 3000,
            'payout_minimum_cents' => 0,
            'stripe_connect_account_id' => 'acct_test_' . $partnerUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('partner_payout_batches')->insert([
            'id' => $batchId,
            'partner_user_id' => $partnerUserId,
            'currency' => 'eur',
            'net_amount_cents' => 300,
            'status' => 'pending',
            'stripe_transfer_id' => $transferId,
            'transfer_group' => 'partner_batch_' . $batchId,
            'paid_at' => null,
            'settled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('partner_commission_ledger')->insert([
            'id' => $batchId,
            'partner_user_id' => $partnerUserId,
            'referred_user_id' => $referredUserId,
            'partner_attribution_id' => null,
            'billing_record_id' => null,
            'source_event_id' => 'evt_' . $batchId,
            'source_invoice_id' => 'in_' . $batchId,
            'entry_type' => 'commission',
            'gross_amount_cents' => 1000,
            'commission_amount_cents' => 300,
            'currency' => 'eur',
            'status' => 'approved',
            'available_at' => now()->subDay(),
            'payout_batch_id' => $batchId,
            'meta' => null,
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
            $table->string('transfer_group', 120)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
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
