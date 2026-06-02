<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_subscriptions', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('tier_id');
            }

            if (!Schema::hasColumn('user_subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            }

            if (!Schema::hasColumn('user_subscriptions', 'stripe_status')) {
                $table->string('stripe_status', 32)->nullable()->after('stripe_subscription_id');
            }

            if (!Schema::hasColumn('user_subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('stripe_status');
            }

            if (!Schema::hasColumn('user_subscriptions', 'pending_tier_id')) {
                $table->foreignId('pending_tier_id')->nullable()->after('cancel_at_period_end')->constrained('tiers')->nullOnDelete();
            }

            if (!Schema::hasColumn('user_subscriptions', 'pending_hub_slots_included')) {
                $table->unsignedInteger('pending_hub_slots_included')->nullable()->after('pending_tier_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('user_subscriptions', 'pending_hub_slots_included')) {
                $table->dropColumn('pending_hub_slots_included');
            }

            if (Schema::hasColumn('user_subscriptions', 'pending_tier_id')) {
                $table->dropConstrainedForeignId('pending_tier_id');
            }

            if (Schema::hasColumn('user_subscriptions', 'cancel_at_period_end')) {
                $table->dropColumn('cancel_at_period_end');
            }

            if (Schema::hasColumn('user_subscriptions', 'stripe_status')) {
                $table->dropColumn('stripe_status');
            }

            if (Schema::hasColumn('user_subscriptions', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }

            if (Schema::hasColumn('user_subscriptions', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }
        });
    }
};
