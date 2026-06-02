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
            if (!Schema::hasColumn('user_subscriptions', 'lifecycle_last_tier_id')) {
                $table->unsignedBigInteger('lifecycle_last_tier_id')->nullable()->after('pending_hub_slots_included');
            }

            if (!Schema::hasColumn('user_subscriptions', 'lifecycle_last_hub_slots_included')) {
                $table->unsignedInteger('lifecycle_last_hub_slots_included')->nullable()->after('lifecycle_last_tier_id');
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_failed_at')) {
                $table->timestamp('payment_failed_at')->nullable()->after('lifecycle_last_hub_slots_included')->index();
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_warning_sent_at')) {
                $table->timestamp('payment_warning_sent_at')->nullable()->after('payment_failed_at');
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_restricted_at')) {
                $table->timestamp('payment_restricted_at')->nullable()->after('payment_warning_sent_at');
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_deletion_warning_sent_at')) {
                $table->timestamp('payment_deletion_warning_sent_at')->nullable()->after('payment_restricted_at');
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_pending_deletion_at')) {
                $table->timestamp('payment_pending_deletion_at')->nullable()->after('payment_deletion_warning_sent_at');
            }

            if (!Schema::hasColumn('user_subscriptions', 'payment_delete_after_at')) {
                $table->timestamp('payment_delete_after_at')->nullable()->after('payment_pending_deletion_at')->index();
            }

            if (!Schema::hasColumn('user_subscriptions', 'agency_grace_period_started_at')) {
                $table->timestamp('agency_grace_period_started_at')->nullable()->after('payment_delete_after_at');
            }

            if (!Schema::hasColumn('user_subscriptions', 'agency_grace_period_ends_at')) {
                $table->timestamp('agency_grace_period_ends_at')->nullable()->after('agency_grace_period_started_at')->index();
            }

            if (!Schema::hasColumn('user_subscriptions', 'agency_over_quota_count')) {
                $table->unsignedInteger('agency_over_quota_count')->nullable()->after('agency_grace_period_ends_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            foreach ([
                'agency_over_quota_count',
                'agency_grace_period_ends_at',
                'agency_grace_period_started_at',
                'payment_delete_after_at',
                'payment_pending_deletion_at',
                'payment_deletion_warning_sent_at',
                'payment_restricted_at',
                'payment_warning_sent_at',
                'payment_failed_at',
                'lifecycle_last_hub_slots_included',
                'lifecycle_last_tier_id',
            ] as $column) {
                if (Schema::hasColumn('user_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
