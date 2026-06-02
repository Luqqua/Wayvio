<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = array_values(array_filter((array) config('app.supported_locales', []), 'strlen'));
        $fallback = config('app.fallback_locale', 'en');

        $locale = null;

        if (Auth::check()) {
            $userLocale = Auth::user()->locale;
            if ($userLocale && $this->isSupportedLocale($userLocale, $supported)) {
                $locale = $this->normalizeLocale($userLocale, $supported);
            }
        }

        if (!$locale) {
            // Manual language switch via ?lang=xx — persist in session for guests
            $langParam = $request->query('lang');
            if ($langParam && $this->isSupportedLocale($langParam, $supported)) {
                $normalized = $this->normalizeLocale($langParam, $supported);
                session(['preferred_locale' => $normalized]);
                $locale = $normalized;
            } elseif (session()->has('preferred_locale')) {
                $sessionLocale = session('preferred_locale');
                if ($this->isSupportedLocale($sessionLocale, $supported)) {
                    $locale = $this->normalizeLocale($sessionLocale, $supported);
                }
            }
        }

        if (!$locale) {
            $locale = $this->resolveBrowserLocale($request, $supported);
        }

        if (!$locale) {
            $locale = $fallback;
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function resolveBrowserLocale(Request $request, array $supported): ?string
    {
        if (empty($supported)) {
            return null;
        }

        $supportedMap = $this->supportedLocaleMap($supported);

        foreach ($request->getLanguages() as $language) {
            $normalized = strtolower($language);
            if (isset($supportedMap[$normalized])) {
                return $supportedMap[$normalized];
            }

            $base = strtolower(strtok($normalized, '-'));
            if ($base && isset($supportedMap[$base])) {
                return $supportedMap[$base];
            }
        }

        return null;
    }

    private function isSupportedLocale(string $locale, array $supported): bool
    {
        $supportedMap = $this->supportedLocaleMap($supported);
        $normalized = strtolower($locale);

        return isset($supportedMap[$normalized]);
    }

    private function normalizeLocale(string $locale, array $supported): string
    {
        $supportedMap = $this->supportedLocaleMap($supported);
        $normalized = strtolower($locale);

        return $supportedMap[$normalized] ?? $locale;
    }

    private function supportedLocaleMap(array $supported): array
    {
        $map = [];
        foreach ($supported as $locale) {
            $map[strtolower($locale)] = $locale;
        }

        return $map;
    }
}
