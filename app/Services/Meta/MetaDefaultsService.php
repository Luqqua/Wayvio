<?php

namespace App\Services\Meta;

use App\Models\User;
use App\Services\Domains\DomainUrlResolver;

class MetaDefaultsService
{
    public function __construct(
        private readonly DomainUrlResolver $domainUrlResolver,
    ) {
    }

    /**
     * @return array{title:string,description:string,keywords:string,robots:string,twitter_card:string,og_locale:string}
     */
    public function defaultsForPage(User $pageUser, ?string $displayName = null, ?string $pageDescription = null): array
    {
        $defaults = (array) config('meta.defaults', []);
        $resolvedLocale = $this->resolvePageLocale($pageUser);
        $localeBase = $this->localeBase($resolvedLocale);

        $localizedDefaults = [];
        if (isset($defaults['locales']) && is_array($defaults['locales'])) {
            $localizedCandidate = $defaults['locales'][$localeBase] ?? null;
            if (is_array($localizedCandidate)) {
                $localizedDefaults = $localizedCandidate;
            }
        }

        $resolvedDisplayName = trim((string) ($displayName ?? ($pageUser->name ?: $pageUser->littlelink_name ?: '')));
        $appName = trim((string) config('app.name', env('APP_NAME', 'Wayvio')));
        $bioText = trim(strip_tags((string) ($pageDescription ?? ($pageUser->littlelink_description ?? ''))));

        $applyPattern = function (?string $pattern) use ($resolvedDisplayName, $bioText, $appName): string {
            $raw = strtr((string) ($pattern ?? ''), [
                ':username' => $resolvedDisplayName,
                ':bio' => $bioText,
                ':app' => $appName,
            ]);

            $normalized = preg_replace('/\s+/', ' ', $raw);
            if (!is_string($normalized)) {
                $normalized = $raw;
            }

            return trim(strip_tags($normalized));
        };

        $titlePattern = $this->firstString(
            $localizedDefaults['title_pattern'] ?? null,
            $defaults['title_pattern'] ?? null
        );

        $descriptionPattern = $this->firstString(
            $localizedDefaults['description_pattern'] ?? null,
            $defaults['description_pattern'] ?? null
        );

        $keywordsPattern = $this->firstString(
            $localizedDefaults['keywords_pattern'] ?? null,
            $defaults['keywords_pattern'] ?? null
        );

        $defaultTitle = $applyPattern($titlePattern ?? ':username');
        if ($defaultTitle === '') {
            $defaultTitle = $resolvedDisplayName !== '' ? $resolvedDisplayName : $appName;
        }

        $defaultDescription = $descriptionPattern !== null
            ? $applyPattern($descriptionPattern)
            : trim($resolvedDisplayName . ($bioText !== '' ? ' - ' . $bioText : ''));

        if ($defaultDescription === '') {
            $defaultDescription = $resolvedDisplayName !== ''
                ? $resolvedDisplayName
                : $appName;
        }

        $defaultKeywords = $keywordsPattern !== null
            ? $applyPattern($keywordsPattern)
            : $this->fallbackKeywords($resolvedDisplayName, $localeBase, $appName);

        if ($defaultKeywords === '') {
            $defaultKeywords = $this->fallbackKeywords($resolvedDisplayName, $localeBase, $appName);
        }

        return [
            'title' => $defaultTitle,
            'description' => $defaultDescription,
            'keywords' => $defaultKeywords,
            'robots' => $this->firstString($localizedDefaults['robots'] ?? null, $defaults['robots'] ?? null) ?? 'index,follow',
            'twitter_card' => $this->firstString($localizedDefaults['twitter_card'] ?? null, $defaults['twitter_card'] ?? null) ?? 'summary_large_image',
            'og_locale' => $this->resolveOgLocale($resolvedLocale, $localeBase, $localizedDefaults, $defaults),
        ];
    }

    public function resolvePageLocale(User $pageUser): string
    {
        $supportedLocales = array_values(array_filter(
            (array) config('app.supported_locales', []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        $map = [];
        foreach ($supportedLocales as $locale) {
            $normalized = strtolower(trim((string) $locale));
            if ($normalized !== '') {
                $map[$normalized] = trim((string) $locale);
            }
        }

        $resolved = $this->normalizeLocaleCandidate(
            is_string($pageUser->locale ?? null) ? (string) $pageUser->locale : null,
            $map
        );
        if ($resolved !== null) {
            return $resolved;
        }

        $owner = $this->domainUrlResolver->ownerForPageUser($pageUser);
        if ($owner && (int) $owner->id !== (int) $pageUser->id) {
            $resolved = $this->normalizeLocaleCandidate(
                is_string($owner->locale ?? null) ? (string) $owner->locale : null,
                $map
            );
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $resolved = $this->normalizeLocaleCandidate((string) app()->getLocale(), $map);
        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = $this->normalizeLocaleCandidate((string) config('app.fallback_locale', 'en'), $map);
        if ($resolved !== null) {
            return $resolved;
        }

        return $map['de'] ?? (array_values($map)[0] ?? 'de');
    }

    private function normalizeLocaleCandidate(?string $locale, array $supportedMap): ?string
    {
        $candidate = strtolower(trim((string) $locale));
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace('_', '-', $candidate);
        $resolved = null;

        if (isset($supportedMap[$candidate])) {
            $resolved = $supportedMap[$candidate];
        }

        if ($resolved === null) {
            $base = strtok($candidate, '-');
            if (is_string($base) && $base !== '' && isset($supportedMap[$base])) {
                $resolved = $supportedMap[$base];
            }
        }

        if ($resolved === null) {
            $direct = trim(str_replace('_', '-', (string) $locale));
            if ($direct !== '' && is_dir(lang_path($direct))) {
                $resolved = $direct;
            }
        }

        if ($resolved === null) {
            return null;
        }

        $resolved = trim((string) $resolved);
        if ($resolved === '') {
            return null;
        }

        if (is_dir(lang_path($resolved))) {
            return $resolved;
        }

        $lower = strtolower($resolved);
        if (is_dir(lang_path($lower))) {
            return $lower;
        }

        return $resolved;
    }

    private function localeBase(string $locale): string
    {
        $normalized = strtolower(trim(str_replace('_', '-', $locale)));
        if ($normalized === '') {
            return 'en';
        }

        $base = strtok($normalized, '-');

        return (is_string($base) && $base !== '') ? $base : $normalized;
    }

    private function fallbackKeywords(string $displayName, string $localeBase, string $appName): string
    {
        $terms = [];

        if ($displayName !== '') {
            $terms[] = $displayName;
            $terms[] = $displayName . ' links';
            $terms[] = $displayName . ($localeBase === 'de' ? ' profil' : ' profile');
        }

        $terms[] = 'link in bio';
        if ($appName !== '') {
            $terms[] = $appName;
        }

        $seen = [];
        $deduplicated = [];
        foreach ($terms as $term) {
            $clean = trim(strip_tags((string) $term));
            if ($clean === '') {
                continue;
            }

            $hash = strtolower($clean);
            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $deduplicated[] = $clean;
        }

        return implode(', ', $deduplicated);
    }

    private function firstString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function resolveOgLocale(string $resolvedLocale, string $localeBase, array $localizedDefaults, array $defaults): string
    {
        $localizedOgLocale = $this->firstString($localizedDefaults['og_locale'] ?? null);
        if ($localizedOgLocale !== null) {
            return $localizedOgLocale;
        }

        $ogLocaleMap = is_array($defaults['og_locale_map'] ?? null) ? $defaults['og_locale_map'] : [];
        $normalizedMap = [];
        foreach ($ogLocaleMap as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalizedKey = strtolower(trim(str_replace('_', '-', $key)));
            $normalizedValue = trim($value);
            if ($normalizedKey !== '' && $normalizedValue !== '') {
                $normalizedMap[$normalizedKey] = $normalizedValue;
            }
        }

        $exactKey = strtolower(trim(str_replace('_', '-', $resolvedLocale)));
        if (isset($normalizedMap[$exactKey])) {
            return $normalizedMap[$exactKey];
        }

        if (isset($normalizedMap[$localeBase])) {
            return $normalizedMap[$localeBase];
        }

        $globalDefault = $this->firstString($defaults['og_locale'] ?? null);
        if ($globalDefault !== null) {
            return $globalDefault;
        }

        return $this->formatOgLocale($resolvedLocale);
    }

    private function formatOgLocale(string $locale): string
    {
        $normalized = strtolower(trim(str_replace('_', '-', $locale)));
        if ($normalized === '') {
            return 'en_US';
        }

        $parts = explode('-', $normalized);
        $language = trim((string) ($parts[0] ?? ''));
        $region = trim((string) ($parts[1] ?? ''));

        if ($language === '') {
            return 'en_US';
        }

        if ($region !== '' && preg_match('/^[a-z]{2}$/', $region) === 1) {
            return $language . '_' . strtoupper($region);
        }

        return match ($language) {
            'de' => 'de_DE',
            'en' => 'en_US',
            default => $language,
        };
    }
}
