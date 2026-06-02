<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('custom_domains')) {
            Schema::drop('custom_domains');
        }
    }

    public function down(): void
    {
        // no-op: legacy table is intentionally removed
    }
};
