<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurgeDeletedAccountsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    public function test_command_purges_eligible_deleted_account_rows(): void
    {
        $oldId = $this->seedDeletedAccountRow(now()->subDays(10), now()->subDay());
        $futureId = $this->seedDeletedAccountRow(now()->subDays(3), now()->addDays(20));

        $this->artisan('lifecycle:purge-deleted-accounts --limit=50')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('deleted_accounts', ['id' => $oldId]);
        $this->assertDatabaseHas('deleted_accounts', ['id' => $futureId]);
    }

    public function test_command_dry_run_keeps_data_intact(): void
    {
        $oldId = $this->seedDeletedAccountRow(now()->subDays(10), now()->subDay());

        $this->artisan('lifecycle:purge-deleted-accounts --dry-run --limit=50')
            ->assertExitCode(0);

        $this->assertDatabaseHas('deleted_accounts', ['id' => $oldId]);
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

    private function seedDeletedAccountRow(\Carbon\Carbon $deletedAt, \Carbon\Carbon $retainedUntil): int
    {
        return (int) DB::table('deleted_accounts')->insertGetId([
            'stripe_customer_id' => 'cus_test_' . random_int(100, 999),
            'stripe_subscription_id' => 'sub_test_' . random_int(100, 999),
            'plan_identifier' => 'pro',
            'plan_price' => 12.00,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'account_created_at' => $deletedAt->copy()->subMonth(),
            'account_deleted_at' => $deletedAt,
            'deletion_reason' => 'admin',
            'login_count' => 5,
            'hub_count' => 1,
            'retained_until' => $retainedUntil,
            'created_at' => now(),
        ]);
    }
}
