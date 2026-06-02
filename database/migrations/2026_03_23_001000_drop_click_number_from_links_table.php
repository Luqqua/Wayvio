<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('links') || !Schema::hasColumn('links', 'click_number')) {
            return;
        }

        Schema::table('links', function (Blueprint $table): void {
            $table->dropColumn('click_number');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('links') || Schema::hasColumn('links', 'click_number')) {
            return;
        }

        Schema::table('links', function (Blueprint $table): void {
            $table->integer('click_number')->default(0)->after('order');
        });
    }
};
