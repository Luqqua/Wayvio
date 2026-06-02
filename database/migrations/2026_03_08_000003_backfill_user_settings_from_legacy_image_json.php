<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('user_settings')) {
            return;
        }

        DB::table('users')
            ->select('id', 'image')
            ->whereNotNull('image')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                $legacySettingsByUserId = [];
                foreach ($users as $user) {
                    if (!is_string($user->image)) {
                        continue;
                    }

                    $raw = trim($user->image);
                    if ($raw === '' || substr($raw, 0, 1) !== '{') {
                        continue;
                    }

                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded)) {
                        continue;
                    }

                    if ($decoded !== [] && array_values($decoded) === $decoded) {
                        continue;
                    }

                    $legacySettingsByUserId[(int) $user->id] = $decoded;
                }

                if ($legacySettingsByUserId === []) {
                    return;
                }

                $legacyUserIds = array_keys($legacySettingsByUserId);
                $existingUserIds = DB::table('user_settings')
                    ->whereIn('user_id', $legacyUserIds)
                    ->pluck('user_id')
                    ->map(static fn ($id) => (int) $id)
                    ->all();
                $existingLookup = array_fill_keys($existingUserIds, true);

                $now = now();
                $rowsToInsert = [];
                foreach ($legacySettingsByUserId as $userId => $settings) {
                    if (isset($existingLookup[$userId])) {
                        continue;
                    }

                    $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (!is_string($encoded)) {
                        continue;
                    }

                    $rowsToInsert[] = [
                        'user_id' => $userId,
                        'data' => $encoded,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rowsToInsert !== []) {
                    DB::table('user_settings')->insertOrIgnore($rowsToInsert);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Intentionally left blank: backfill should not be reverted.
    }
};
