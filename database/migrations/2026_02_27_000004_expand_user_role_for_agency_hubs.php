<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('user')->change();
        });

        if (!Schema::hasTable('agency_hubs')) {
            return;
        }

        DB::table('users')
            ->whereIn('id', static function ($query): void {
                $query->select('managed_user_id')->from('agency_hubs');
            })
            ->update([
                'role' => 'agency_hub',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('role')
                    ->orWhereNotIn('role', ['user', 'vip', 'admin']);
            })
            ->update([
                'role' => 'user',
                'updated_at' => now(),
            ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['user', 'vip', 'admin'])->default('user')->change();
        });
    }
};
