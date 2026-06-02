<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsSingleSourceCommand extends Command
{
    protected $signature = 'settings:single-source
        {--apply : Persist changes (default is dry-run)}
        {--clear-legacy-json : Clear users.image when legacy settings JSON is detected}';

    protected $description = 'Migrate/audit legacy settings stored in users.image and enforce user_settings as single source of truth.';

    public function handle(): int
    {
        if (!Schema::hasTable('user_settings')) {
            $this->error('Table user_settings does not exist. Run migrations first.');
            return Command::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $clearLegacyJson = (bool) $this->option('clear-legacy-json');

        $this->line('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'));
        $this->line('Legacy users.image cleanup: ' . ($clearLegacyJson ? 'ENABLED' : 'DISABLED'));

        $stats = [
            'users_scanned' => 0,
            'legacy_json_candidate_rows' => 0,
            'legacy_json_rows' => 0,
            'malformed_legacy_rows' => 0,
            'existing_user_settings_rows' => 0,
            'missing_user_settings_rows' => 0,
            'would_insert_rows' => 0,
            'would_insert_empty_rows' => 0,
            'inserted_rows' => 0,
            'divergent_rows' => 0,
            'legacy_rows_marked_for_clear' => 0,
            'cleared_legacy_rows' => 0,
        ];
        $divergentSampleIds = [];

        User::query()
            ->select(['id', 'image'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$stats, &$divergentSampleIds, $apply, $clearLegacyJson): void {
                $legacySettingsByUserId = [];
                $legacyRawByUserId = [];
                $malformedUserIds = [];

                foreach ($users as $user) {
                    $stats['users_scanned']++;
                    if (!is_string($user->image)) {
                        continue;
                    }

                    $rawImage = trim($user->image);
                    if ($rawImage === '' || substr($rawImage, 0, 1) !== '{') {
                        continue;
                    }

                    $stats['legacy_json_candidate_rows']++;
                    $userId = (int) $user->id;
                    $legacyRawByUserId[$userId] = (string) $user->image;

                    $legacySettings = self::decodeLegacySettings($rawImage);
                    if ($legacySettings === null) {
                        $stats['malformed_legacy_rows']++;
                        $malformedUserIds[$userId] = true;
                        continue;
                    }

                    $legacySettingsByUserId[$userId] = $legacySettings;
                    $stats['legacy_json_rows']++;
                }

                if ($legacyRawByUserId === []) {
                    return;
                }

                $userIds = array_keys($legacyRawByUserId);
                $storedSettingsRows = DB::table('user_settings')
                    ->whereIn('user_id', $userIds)
                    ->pluck('data', 'user_id');

                $rowsToInsert = [];
                $rowsToClearLegacy = [];

                foreach ($userIds as $userId) {
                    $hasStoredSettings = $storedSettingsRows->has($userId);

                    if ($hasStoredSettings) {
                        $stats['existing_user_settings_rows']++;

                        if (!isset($malformedUserIds[$userId])) {
                            $legacySettings = $legacySettingsByUserId[$userId];
                            $storedSettings = self::decodeStoredSettings($storedSettingsRows->get($userId));
                            if (self::normalizedHash($storedSettings) !== self::normalizedHash($legacySettings)) {
                                $stats['divergent_rows']++;
                                if (count($divergentSampleIds) < 20) {
                                    $divergentSampleIds[] = $userId;
                                }
                            }
                        }
                    } else {
                        $stats['missing_user_settings_rows']++;

                        if (isset($malformedUserIds[$userId])) {
                            $stats['would_insert_empty_rows']++;
                            if ($apply) {
                                $rowsToInsert[$userId] = [
                                    'user_id' => $userId,
                                    'data' => self::encodeSettings([]),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        } else {
                            $legacySettings = $legacySettingsByUserId[$userId];
                            $stats['would_insert_rows']++;
                            if ($apply) {
                                $rowsToInsert[$userId] = [
                                    'user_id' => $userId,
                                    'data' => self::encodeSettings($legacySettings),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        }
                    }

                    if ($clearLegacyJson) {
                        $stats['legacy_rows_marked_for_clear']++;
                        if ($apply) {
                            $rowsToClearLegacy[$userId] = $legacyRawByUserId[$userId];
                        }
                    }
                }

                if (!$apply) {
                    return;
                }

                DB::transaction(function () use ($rowsToInsert, $rowsToClearLegacy, &$stats): void {
                    if ($rowsToInsert !== []) {
                        $inserted = DB::table('user_settings')->insertOrIgnore(array_values($rowsToInsert));
                        $stats['inserted_rows'] += (int) $inserted;
                    }

                    if ($rowsToClearLegacy !== []) {
                        $cleared = 0;
                        foreach ($rowsToClearLegacy as $userId => $legacyRaw) {
                            $cleared += DB::table('users')
                                ->where('id', $userId)
                                ->where('image', $legacyRaw)
                                ->update(['image' => null]);
                        }
                        $stats['cleared_legacy_rows'] += $cleared;
                    }
                });

                foreach (array_keys($rowsToInsert) as $userId) {
                    UserData::invalidateCache((int) $userId);
                }
            }, 'id');

        $this->table(
            ['Metric', 'Value'],
            [
                ['users_scanned', (string) $stats['users_scanned']],
                ['legacy_json_candidate_rows', (string) $stats['legacy_json_candidate_rows']],
                ['legacy_json_rows', (string) $stats['legacy_json_rows']],
                ['malformed_legacy_rows', (string) $stats['malformed_legacy_rows']],
                ['existing_user_settings_rows', (string) $stats['existing_user_settings_rows']],
                ['missing_user_settings_rows', (string) $stats['missing_user_settings_rows']],
                ['would_insert_rows', (string) $stats['would_insert_rows']],
                ['would_insert_empty_rows', (string) $stats['would_insert_empty_rows']],
                ['inserted_rows', (string) $stats['inserted_rows']],
                ['divergent_rows', (string) $stats['divergent_rows']],
                ['legacy_rows_marked_for_clear', (string) $stats['legacy_rows_marked_for_clear']],
                ['cleared_legacy_rows', (string) $stats['cleared_legacy_rows']],
            ]
        );

        if ($stats['divergent_rows'] > 0) {
            $sample = implode(', ', $divergentSampleIds);
            $this->warn('Divergent rows detected. Sample user IDs: ' . $sample);
        }

        if (!$apply) {
            $this->warn('Dry-run complete. Run again with --apply to persist changes.');
        }

        return Command::SUCCESS;
    }

    private static function decodeLegacySettings($raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '' || substr($raw, 0, 1) !== '{') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        if ($decoded === []) {
            return [];
        }

        if (array_values($decoded) === $decoded) {
            return null;
        }

        return $decoded;
    }

    private static function decodeStoredSettings($raw): array
    {
        if (!is_string($raw)) {
            return [];
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        if ($decoded !== [] && array_values($decoded) === $decoded) {
            return [];
        }

        return $decoded;
    }

    private static function encodeSettings(array $settings): string
    {
        $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '{}';
    }

    private static function normalizedHash(array $settings): string
    {
        $normalized = self::normalizeForCompare($settings);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '{}';
    }

    private static function normalizeForCompare($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (self::isList($value)) {
            return array_map(static fn ($item) => self::normalizeForCompare($item), $value);
        }

        $normalized = $value;
        ksort($normalized);
        foreach ($normalized as $key => $item) {
            $normalized[$key] = self::normalizeForCompare($item);
        }

        return $normalized;
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
