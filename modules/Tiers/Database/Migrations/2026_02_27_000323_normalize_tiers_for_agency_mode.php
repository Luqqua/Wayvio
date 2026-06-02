<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tiers')) {
            return;
        }

        if (!DB::table('tiers')->where('slug', 'free')->exists()) {
            $tier1Id = DB::table('tiers')->where('slug', 'tier1')->value('id');
            if ($tier1Id) {
                DB::table('tiers')->where('id', $tier1Id)->update([
                    'slug' => 'free',
                    'name' => 'Free',
                ]);
            }
        }

        if (!DB::table('tiers')->where('slug', 'pro')->exists()) {
            $tier2Id = DB::table('tiers')->where('slug', 'tier2')->value('id');
            if ($tier2Id) {
                DB::table('tiers')->where('id', $tier2Id)->update([
                    'slug' => 'pro',
                    'name' => 'Pro',
                ]);
            } else {
                $premiumId = DB::table('tiers')->where('slug', 'premium')->value('id');
                if ($premiumId) {
                    DB::table('tiers')->where('id', $premiumId)->update([
                        'slug' => 'pro',
                        'name' => 'Pro',
                    ]);
                }
            }
        }

        if (!DB::table('tiers')->where('slug', 'agency')->exists()) {
            $legacyAgencyId = DB::table('tiers')
                ->whereIn('slug', ['business', 'tier3', 'enterprise'])
                ->orderBy('id')
                ->value('id');

            if ($legacyAgencyId) {
                DB::table('tiers')->where('id', $legacyAgencyId)->update([
                    'slug' => 'agency',
                    'name' => 'Agency',
                ]);
            }
        }

        $now = now();

        DB::table('tiers')->updateOrInsert(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Free tier with one managed hub.',
                'max_pages' => 1,
                'max_links_per_page' => 10,
                'analytics_enabled' => true,
                'custom_domain_enabled' => false,
                'design_customization_enabled' => false,
                'price_1m' => 0,
                'price_3m' => 0,
                'price_6m' => 0,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('tiers')->updateOrInsert(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'description' => 'Basic plan with one managed hub.',
                'max_pages' => 1,
                'max_links_per_page' => 30,
                'analytics_enabled' => true,
                'custom_domain_enabled' => false,
                'design_customization_enabled' => true,
                'price_1m' => 900,
                'price_3m' => 2500,
                'price_6m' => 4800,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('tiers')->updateOrInsert(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Professional tier with one managed hub.',
                'max_pages' => 1,
                'max_links_per_page' => 80,
                'analytics_enabled' => true,
                'custom_domain_enabled' => false,
                'design_customization_enabled' => true,
                'price_1m' => 1500,
                'price_3m' => 4000,
                'price_6m' => 7500,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('tiers')->updateOrInsert(
            ['slug' => 'agency'],
            [
                'name' => 'Agency',
                'description' => 'Enterprise agency tier with managed hub slots and white-label options.',
                'max_pages' => 200,
                'max_links_per_page' => 200,
                'analytics_enabled' => true,
                'custom_domain_enabled' => true,
                'design_customization_enabled' => true,
                'price_1m' => 5900,
                'price_3m' => 15900,
                'price_6m' => 29900,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('tiers')) {
            return;
        }

        DB::table('tiers')->where('slug', 'agency')->update([
            'slug' => 'business',
            'name' => 'Business',
        ]);
    }
};
