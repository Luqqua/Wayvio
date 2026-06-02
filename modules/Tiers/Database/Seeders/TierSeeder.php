<?php

namespace Modules\Tiers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\TierResolver;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        $resolver = app(TierResolver::class);
        $plans = $resolver->plans();

        foreach ($plans as $tier) {
            Tier::updateOrCreate(['slug' => $tier['slug']], $resolver->databasePayload($tier));
        }
    }
}
