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
            if (!Schema::hasColumn('deleted_accounts', 'subscription_status_before_delete')) {
                $table->string('subscription_status_before_delete', 32)->nullable()->after('subscription_end_at');
            }
            if (!Schema::hasColumn('deleted_accounts', 'cancel_at_period_end_before_delete')) {
                $table->boolean('cancel_at_period_end_before_delete')->nullable()->after('subscription_status_before_delete');
            }

            if (!Schema::hasColumn('deleted_accounts', 'deletion_source')) {
                $table->string('deletion_source', 64)->nullable()->index()->after('deletion_reason');
            }
            if (!Schema::hasColumn('deleted_accounts', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable()->index()->after('deletion_source');
            }

            if (!Schema::hasColumn('deleted_accounts', 'billing_cancel_status')) {
                $table->string('billing_cancel_status', 64)->nullable()->index()->after('actor_user_id');
            }
            if (!Schema::hasColumn('deleted_accounts', 'billing_canceled')) {
                $table->boolean('billing_canceled')->default(false)->after('billing_cancel_status');
            }
            if (!Schema::hasColumn('deleted_accounts', 'billing_canceled_at')) {
                $table->timestamp('billing_canceled_at')->nullable()->index()->after('billing_canceled');
            }
            if (!Schema::hasColumn('deleted_accounts', 'billing_cancel_http_status')) {
                $table->unsignedSmallInteger('billing_cancel_http_status')->nullable()->after('billing_canceled_at');
            }
            if (!Schema::hasColumn('deleted_accounts', 'billing_cancel_error_code')) {
                $table->string('billing_cancel_error_code', 80)->nullable()->after('billing_cancel_http_status');
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
                'subscription_status_before_delete',
                'cancel_at_period_end_before_delete',
                'deletion_source',
                'actor_user_id',
                'billing_cancel_status',
                'billing_canceled',
                'billing_canceled_at',
                'billing_cancel_http_status',
                'billing_cancel_error_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('deleted_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
