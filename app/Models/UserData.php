<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserData extends Model
{
    protected $table = 'user_settings';
    protected $fillable = ['user_id', 'data'];

    public static function saveData($userId, $key, $value)
    {
        if (!self::isValidInput($userId, $key)) {
            return "null";
        }
        if (!self::hasStorage()) {
            return "null";
        }
        if (!self::userExists((int) $userId)) {
            return "null";
        }

        $settings = self::mutateSettings((int) $userId, function (array $settings) use ($key, $value) {
            $settings[$key] = $value;
            return $settings;
        });

        return $settings[$key] ?? null;
    }

    public static function getData($userId, $key)
    {
        if (!self::isValidInput($userId, $key)) {
            return "null";
        }
        if (!self::hasStorage()) {
            return "null";
        }

        $settings = self::getSettings((int) $userId);
        if ($settings === null) {
            return "null";
        }

        return array_key_exists($key, $settings) ? $settings[$key] : null;
    }

    public static function removeData($userId, $key)
    {
        if (!self::isValidInput($userId, $key)) {
            return "null";
        }
        if (!self::hasStorage()) {
            return "null";
        }
        if (!self::userExists((int) $userId)) {
            return "null";
        }

        return self::mutateSettings((int) $userId, function (array $settings) use ($key) {
            unset($settings[$key]);
            return $settings;
        });
    }

    public static function invalidateCache(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    private static function isValidInput($userId, $key): bool
    {
        return is_numeric($userId) && (int) $userId > 0 && is_string($key) && trim($key) !== '';
    }

    private static function userExists(int $userId): bool
    {
        return User::query()->where('id', $userId)->exists();
    }

    private static function hasStorage(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('user_settings');
        }

        static $hasTable = null;
        if ($hasTable !== null) {
            return $hasTable;
        }

        $hasTable = Schema::hasTable('user_settings');
        return $hasTable;
    }

    private static function mutateSettings(int $userId, callable $mutator): array
    {
        $settings = DB::transaction(function () use ($userId, $mutator) {
            $record = self::query()->where('user_id', $userId)->lockForUpdate()->first();

            if (!$record) {
                try {
                    $record = new self();
                    $record->user_id = $userId;
                    $record->data = self::encodeSettings([]);
                    $record->save();
                } catch (QueryException $exception) {
                    // Another request may have inserted the row first.
                    $record = self::query()->where('user_id', $userId)->lockForUpdate()->first();
                    if (!$record) {
                        throw $exception;
                    }
                }
            }

            $currentSettings = self::decodeSettings($record->data);
            $updatedSettings = $mutator($currentSettings);
            if (!is_array($updatedSettings)) {
                $updatedSettings = $currentSettings;
            }

            $record->data = self::encodeSettings($updatedSettings);
            $record->save();

            return $updatedSettings;
        });

        self::cacheSettings($userId, $settings);

        return $settings;
    }

    private static function getSettings(int $userId): ?array
    {
        $cached = Cache::get(self::cacheKey($userId));
        if (is_array($cached) && array_key_exists('found', $cached)) {
            return $cached['found'] ? (array) ($cached['data'] ?? []) : null;
        }

        $record = self::query()->where('user_id', $userId)->first();
        if ($record) {
            $settings = self::decodeSettings($record->data);
            self::cacheSettings($userId, $settings);
            return $settings;
        }

        self::cacheMissing($userId);
        return null;
    }

    private static function decodeSettings($raw): array
    {
        if (!is_string($raw)) {
            return [];
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_values($decoded) === $decoded) {
            return [];
        }

        return $decoded;
    }

    private static function encodeSettings(array $settings): string
    {
        $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '{}';
    }

    private static function cacheSettings(int $userId, array $settings): void
    {
        Cache::put(
            self::cacheKey($userId),
            ['found' => true, 'data' => $settings],
            now()->addMinutes(10)
        );
    }

    private static function cacheMissing(int $userId): void
    {
        Cache::put(
            self::cacheKey($userId),
            ['found' => false, 'data' => []],
            now()->addMinutes(10)
        );
    }

    private static function cacheKey(int $userId): string
    {
        return 'user_settings_v2_' . $userId;
    }
}
