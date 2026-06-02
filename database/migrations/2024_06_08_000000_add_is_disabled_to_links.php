<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('links', 'is_disabled')) {
            Schema::table('links', function (Blueprint $table) {
                $table->boolean('is_disabled')->default(false)->after('up_link');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('links', 'is_disabled')) {
            Schema::table('links', function (Blueprint $table) {
                $table->dropColumn('is_disabled');
            });
        }
    }
};
