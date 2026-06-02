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
            if (!Schema::hasColumn('users', 'tos_accepted_at')) {
                $table->timestamp('tos_accepted_at')->nullable()->after('email_verified_at')->index();
            }

            if (!Schema::hasColumn('users', 'tos_version')) {
                $table->string('tos_version', 120)->nullable()->after('tos_accepted_at')->index();
            }

            if (!Schema::hasColumn('users', 'tos_content_hash')) {
                $table->char('tos_content_hash', 64)->nullable()->after('tos_version')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'tos_content_hash')) {
                $table->dropColumn('tos_content_hash');
            }

            if (Schema::hasColumn('users', 'tos_version')) {
                $table->dropColumn('tos_version');
            }

            if (Schema::hasColumn('users', 'tos_accepted_at')) {
                $table->dropColumn('tos_accepted_at');
            }
        });
    }
};
