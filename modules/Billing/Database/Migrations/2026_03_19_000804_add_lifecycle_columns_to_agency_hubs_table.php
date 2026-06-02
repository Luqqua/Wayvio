<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('agency_hubs')) {
            return;
        }

        Schema::table('agency_hubs', function (Blueprint $table): void {
            if (!Schema::hasColumn('agency_hubs', 'lifecycle_reason')) {
                $table->string('lifecycle_reason', 32)->nullable()->after('status');
            }

            if (!Schema::hasColumn('agency_hubs', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('lifecycle_reason');
            }

            if (!Schema::hasColumn('agency_hubs', 'pending_deletion_at')) {
                $table->timestamp('pending_deletion_at')->nullable()->after('suspended_at');
            }

            if (!Schema::hasColumn('agency_hubs', 'delete_after_at')) {
                $table->timestamp('delete_after_at')->nullable()->after('pending_deletion_at')->index();
            }

            if (!Schema::hasColumn('agency_hubs', 'deleted_at_lifecycle')) {
                $table->timestamp('deleted_at_lifecycle')->nullable()->after('delete_after_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('agency_hubs')) {
            return;
        }

        Schema::table('agency_hubs', function (Blueprint $table): void {
            foreach ([
                'deleted_at_lifecycle',
                'delete_after_at',
                'pending_deletion_at',
                'suspended_at',
                'lifecycle_reason',
            ] as $column) {
                if (Schema::hasColumn('agency_hubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
