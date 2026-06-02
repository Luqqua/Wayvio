<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('deleted_accounts')) {
            return;
        }

        Schema::create('deleted_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_customer_id', 191)->nullable()->index();
            $table->string('stripe_subscription_id', 191)->nullable()->index();
            $table->string('plan_identifier', 64)->nullable()->index();
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
            $table->timestamp('account_deleted_at')->nullable()->index();
            $table->string('deletion_reason', 32)->nullable()->index();
            $table->string('deletion_source', 64)->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('deletion_request_id', 64)->nullable()->index();
            $table->string('billing_cancel_status', 64)->nullable()->index();
            $table->boolean('billing_canceled')->default(false);
            $table->timestamp('billing_canceled_at')->nullable()->index();
            $table->string('stripe_cancel_event_id', 191)->nullable()->index();
            $table->timestamp('stripe_canceled_at')->nullable()->index();
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
            $table->timestamp('retained_until')->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_accounts');
    }
};
