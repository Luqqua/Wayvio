<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('deleted_accounts')) {
            return;
        }

        Schema::table('deleted_accounts', function (Blueprint $table): void {
            if (!Schema::hasColumn('deleted_accounts', 'subscription_status_before_cancel')) {
                $table->string('subscription_status_before_cancel', 32)->nullable()->after('cancel_at_period_end_before_delete');
            }
            if (!Schema::hasColumn('deleted_accounts', 'cancel_at_period_end_before_cancel')) {
                $table->boolean('cancel_at_period_end_before_cancel')->nullable()->after('subscription_status_before_cancel');
            }

            if (!Schema::hasColumn('deleted_accounts', 'deletion_request_id')) {
                $table->string('deletion_request_id', 64)->nullable()->index()->after('actor_user_id');
            }
            if (!Schema::hasColumn('deleted_accounts', 'stripe_cancel_event_id')) {
                $table->string('stripe_cancel_event_id', 191)->nullable()->index()->after('billing_canceled_at');
            }
            if (!Schema::hasColumn('deleted_accounts', 'stripe_canceled_at')) {
                $table->timestamp('stripe_canceled_at')->nullable()->index()->after('stripe_cancel_event_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('deleted_accounts')) {
            return;
        }

        Schema::table('deleted_accounts', function (Blueprint $table): void {
            $columns = [
                'subscription_status_before_cancel',
                'cancel_at_period_end_before_cancel',
                'deletion_request_id',
                'stripe_cancel_event_id',
                'stripe_canceled_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('deleted_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
