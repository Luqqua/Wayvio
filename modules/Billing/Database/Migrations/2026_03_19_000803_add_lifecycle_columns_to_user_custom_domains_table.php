<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return;
        }

        Schema::table('user_custom_domains', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_custom_domains', 'lifecycle_status')) {
                $table->string('lifecycle_status', 32)->default('active')->after('status')->index();
            }

            if (!Schema::hasColumn('user_custom_domains', 'lifecycle_reason')) {
                $table->string('lifecycle_reason', 32)->nullable()->after('lifecycle_status');
            }

            if (!Schema::hasColumn('user_custom_domains', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('lifecycle_reason');
            }

            if (!Schema::hasColumn('user_custom_domains', 'pending_deletion_at')) {
                $table->timestamp('pending_deletion_at')->nullable()->after('suspended_at');
            }

            if (!Schema::hasColumn('user_custom_domains', 'delete_after_at')) {
                $table->timestamp('delete_after_at')->nullable()->after('pending_deletion_at')->index();
            }

            if (!Schema::hasColumn('user_custom_domains', 'deleted_at_lifecycle')) {
                $table->timestamp('deleted_at_lifecycle')->nullable()->after('delete_after_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return;
        }

        Schema::table('user_custom_domains', function (Blueprint $table): void {
            foreach ([
                'deleted_at_lifecycle',
                'delete_after_at',
                'pending_deletion_at',
                'suspended_at',
                'lifecycle_reason',
                'lifecycle_status',
            ] as $column) {
                if (Schema::hasColumn('user_custom_domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
