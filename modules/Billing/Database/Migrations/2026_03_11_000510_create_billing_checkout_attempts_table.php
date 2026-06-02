<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_checkout_attempts')) {
            return;
        }

        Schema::create('billing_checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('tier_id')->nullable()->index();
            $table->unsignedTinyInteger('period_months')->default(1);
            $table->unsignedTinyInteger('requested_hub_count')->nullable();
            $table->string('stripe_checkout_session_id', 191)->unique();
            $table->string('stripe_customer_id', 191)->nullable()->index();
            $table->string('stripe_subscription_id', 191)->nullable()->index();
            $table->string('stripe_invoice_id', 191)->nullable()->index();
            $table->string('checkout_url', 2048)->nullable();
            $table->string('source', 32)->default('checkout-session');
            $table->string('status', 32)->default('created')->index();
            $table->string('webhook_status', 80)->nullable()->index();
            $table->string('last_event_id', 191)->nullable()->index();
            $table->string('last_event_type', 191)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_attempts');
    }
};
