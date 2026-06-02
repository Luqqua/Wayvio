<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_custom_domains') || !Schema::hasColumn('user_custom_domains', 'ssl_status')) {
            return;
        }

        DB::table('user_custom_domains')
            ->where(function ($query): void {
                $query->whereNull('ssl_status')
                    ->orWhereRaw('LOWER(ssl_status) = ?', ['unknown']);
            })
            ->update([
                'ssl_status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op: lifecycle normalization is intentionally irreversible
    }
};
