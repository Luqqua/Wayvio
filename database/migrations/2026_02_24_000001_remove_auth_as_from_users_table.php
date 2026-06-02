<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'auth_as')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('auth_as');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'auth_as')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('auth_as')->nullable();
            });
        }
    }
};
