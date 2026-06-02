<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('account_deletion_audit_log')) {
            return;
        }

        Schema::create('account_deletion_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('source', 64)->default('unknown')->index();
            $table->string('billing_status', 64)->nullable()->index();
            $table->boolean('canceled')->default(false)->index();
            $table->string('stripe_customer_id', 191)->nullable()->index();
            $table->string('stripe_subscription_id', 191)->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_audit_log');
    }
};
