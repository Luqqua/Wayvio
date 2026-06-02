<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Map existing slugs to new names/slugs
        DB::table('tiers')->where('slug', 'tier1')->update(['slug' => 'free', 'name' => 'Free']);
        DB::table('tiers')->where('slug', 'tier2')->update(['slug' => 'basic', 'name' => 'Basic']);
        DB::table('tiers')->where('slug', 'tier3')->update(['slug' => 'premium', 'name' => 'Premium']);
    }

    public function down(): void
    {
        DB::table('tiers')->where('slug', 'free')->update(['slug' => 'tier1', 'name' => 'Tier1 Free']);
        DB::table('tiers')->where('slug', 'basic')->update(['slug' => 'tier2', 'name' => 'Tier2 Pro']);
        DB::table('tiers')->where('slug', 'premium')->update(['slug' => 'tier3', 'name' => 'Tier3 Business']);
    }
};
