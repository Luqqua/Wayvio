<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'pending_email')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('pending_email')->nullable()->after('email_verified_at');
            });
        }

        if (!Schema::hasColumn('users', 'pending_email_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('pending_email_token')->nullable()->after('pending_email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['pending_email_token', 'pending_email'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
