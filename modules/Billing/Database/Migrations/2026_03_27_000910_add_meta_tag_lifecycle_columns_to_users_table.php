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
            if (!Schema::hasColumn('users', 'meta_tags_status')) {
                $table->string('meta_tags_status', 32)->default('active')->index();
            }

            if (!Schema::hasColumn('users', 'meta_tags_status_reason')) {
                $table->string('meta_tags_status_reason', 32)->nullable();
            }

            if (!Schema::hasColumn('users', 'meta_tags_suspended_at')) {
                $table->timestamp('meta_tags_suspended_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'meta_tags_pending_deletion_at')) {
                $table->timestamp('meta_tags_pending_deletion_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'meta_tags_delete_after_at')) {
                $table->timestamp('meta_tags_delete_after_at')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'meta_tags_deleted_at')) {
                $table->timestamp('meta_tags_deleted_at')->nullable();
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
                'meta_tags_deleted_at',
                'meta_tags_delete_after_at',
                'meta_tags_pending_deletion_at',
                'meta_tags_suspended_at',
                'meta_tags_status_reason',
                'meta_tags_status',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
