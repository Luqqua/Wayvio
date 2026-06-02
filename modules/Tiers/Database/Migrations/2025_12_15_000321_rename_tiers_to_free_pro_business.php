<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $map = [
            'tier1' => ['slug' => 'free', 'name' => 'Free'],
            'free' => ['slug' => 'free', 'name' => 'Free'],
            'tier2' => ['slug' => 'pro', 'name' => 'Pro'],
            'basic' => ['slug' => 'pro', 'name' => 'Pro'],
            'pro' => ['slug' => 'pro', 'name' => 'Pro'],
            'tier3' => ['slug' => 'business', 'name' => 'Business'],
            'premium' => ['slug' => 'business', 'name' => 'Business'],
            'business' => ['slug' => 'business', 'name' => 'Business'],
        ];

        foreach ($map as $from => $target) {
            DB::table('tiers')->where('slug', $from)->update($target);
        }
    }

    public function down(): void
    {
        $map = [
            'free' => ['slug' => 'free', 'name' => 'Free'],
            'pro' => ['slug' => 'basic', 'name' => 'Basic'],
            'business' => ['slug' => 'premium', 'name' => 'Premium'],
        ];

        foreach ($map as $from => $target) {
            DB::table('tiers')->where('slug', $from)->update($target);
        }
    }
};
