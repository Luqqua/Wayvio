<?php

namespace App\Support;

use Illuminate\Http\Request;

class EmailLocaleResolver
{
    public static function resolve(
        ?string $accountLocale = null,
        ?string $lastLoginLocale = null,
        ?string $browserLocale = null
    ): string {
        $supported = self::supportedLocales();

        return self::normalize($accountLocale, $supported)
            ?? self::normalize($lastLoginLocale, $supported)
            ?? self::normalize($browserLocale, $supported)
            ?? self::fallbackLocale($supported);
    }

    public static function resolveFromRequest(Request $request): ?string
    {
        $supported = self::supportedLocales();

        foreach ($request->getLanguages() as $language) {
            $resolved = self::normalize((string) $language, $supported);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public static function isEnglish(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'en');
    }

    public static function isGerman(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'de');
    }

    /**
     * @param array<int, string> $supported
     */
    private static function normalize(?string $locale, array $supported): ?string
    {
        $candidate = strtolower(trim((string) $locale));
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace('_', '-', $candidate);
        $supportedMap = self::supportedLocaleMap($supported);

        if (isset($supportedMap[$candidate])) {
            return $supportedMap[$candidate];
        }

        $base = strtok($candidate, '-');
        if (is_string($base) && $base !== '' && isset($supportedMap[$base])) {
            return $supportedMap[$base];
        }

        return null;
    }

    /**
     * @param array<int, string> $supported
     */
    private static function fallbackLocale(array $supported): string
    {
        $supportedMap = self::supportedLocaleMap($supported);
        if (isset($supportedMap['de'])) {
            return $supportedMap['de'];
        }

        foreach ($supported as $locale) {
            if (self::isGerman($locale)) {
                return $locale;
            }
        }

        return 'de';
    }

    /**
     * @return array<int, string>
     */
    private static function supportedLocales(): array
    {
        $supported = array_values(array_filter((array) config('app.supported_locales', []), static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }));

        return array_map(static fn ($locale): string => trim((string) $locale), $supported);
    }

    /**
     * @param array<int, string> $supported
     * @return array<string, string>
     */
    private static function supportedLocaleMap(array $supported): array
    {
        $map = [];
        foreach ($supported as $locale) {
            $map[strtolower($locale)] = $locale;
        }

        return $map;
    }
}
