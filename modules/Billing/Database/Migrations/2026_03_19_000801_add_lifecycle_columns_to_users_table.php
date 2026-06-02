<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 32)->default('active')->after('block')->index();
            }

            if (!Schema::hasColumn('users', 'account_status_reason')) {
                $table->string('account_status_reason', 32)->nullable()->after('account_status');
            }

            if (!Schema::hasColumn('users', 'account_status_changed_at')) {
                $table->timestamp('account_status_changed_at')->nullable()->after('account_status_reason');
            }

            if (!Schema::hasColumn('users', 'account_delete_after_at')) {
                $table->timestamp('account_delete_after_at')->nullable()->after('account_status_changed_at')->index();
            }

            if (!Schema::hasColumn('users', 'account_deleted_at')) {
                $table->timestamp('account_deleted_at')->nullable()->after('account_delete_after_at');
            }

            if (!Schema::hasColumn('users', 'analytics_status')) {
                $table->string('analytics_status', 32)->default('active')->after('account_deleted_at')->index();
            }

            if (!Schema::hasColumn('users', 'analytics_status_reason')) {
                $table->string('analytics_status_reason', 32)->nullable()->after('analytics_status');
            }

            if (!Schema::hasColumn('users', 'analytics_suspended_at')) {
                $table->timestamp('analytics_suspended_at')->nullable()->after('analytics_status_reason');
            }

            if (!Schema::hasColumn('users', 'analytics_pending_deletion_at')) {
                $table->timestamp('analytics_pending_deletion_at')->nullable()->after('analytics_suspended_at');
            }

            if (!Schema::hasColumn('users', 'analytics_delete_after_at')) {
                $table->timestamp('analytics_delete_after_at')->nullable()->after('analytics_pending_deletion_at')->index();
            }

            if (!Schema::hasColumn('users', 'analytics_deleted_at')) {
                $table->timestamp('analytics_deleted_at')->nullable()->after('analytics_delete_after_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'analytics_deleted_at',
                'analytics_delete_after_at',
                'analytics_pending_deletion_at',
                'analytics_suspended_at',
                'analytics_status_reason',
                'analytics_status',
                'account_deleted_at',
                'account_delete_after_at',
                'account_status_changed_at',
                'account_status_reason',
                'account_status',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
