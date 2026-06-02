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
            if (!Schema::hasColumn('users', 'agb_accepted_at')) {
                $table->timestamp('agb_accepted_at')->nullable()->after('email_verified_at')->index();
            }

            if (!Schema::hasColumn('users', 'agb_version')) {
                $table->string('agb_version', 40)->nullable()->after('agb_accepted_at')->index();
            }

            if (!Schema::hasColumn('users', 'avv_accepted_at')) {
                $table->timestamp('avv_accepted_at')->nullable()->after('agb_version')->index();
            }

            if (!Schema::hasColumn('users', 'avv_version')) {
                $table->string('avv_version', 40)->nullable()->after('avv_accepted_at')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'avv_version')) {
                $table->dropColumn('avv_version');
            }

            if (Schema::hasColumn('users', 'avv_accepted_at')) {
                $table->dropColumn('avv_accepted_at');
            }

            if (Schema::hasColumn('users', 'agb_version')) {
                $table->dropColumn('agb_version');
            }

            if (Schema::hasColumn('users', 'agb_accepted_at')) {
                $table->dropColumn('agb_accepted_at');
            }
        });
    }
};
