<?php

namespace App\Services\Compliance;

use Illuminate\Support\Facades\Log;

class LegalAgreementTextService
{
    /**
     * @return array{text:string,path:string,sha256:string,locale:?string}|null
     */
    public function loadAgreementText(string $agreementType, ?string $preferredLocale = null): ?array
    {
        $type = $this->normalizeType($agreementType);
        if (!in_array($type, ['agb', 'avv', 'privacy', 'imprint'], true)) {
            return null;
        }

        $requestedLocale = $this->normalizeLegalLocale($preferredLocale);
        $candidatePaths = $this->candidatePathsForType($type, $requestedLocale);

        foreach ($candidatePaths as $candidate) {
            $path = $candidate['path'];
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $raw = @file_get_contents($path);
            if (!is_string($raw)) {
                continue;
            }

            $text = trim(str_replace(["\r\n", "\r"], "\n", $raw));
            if ($text === '') {
                continue;
            }

            $text = $this->applyProviderPlaceholderReplacements($text);

            return [
                'text' => $text,
                'path' => $path,
                'sha256' => hash('sha256', $text),
                'locale' => $candidate['locale'],
            ];
        }

        Log::warning('Legal agreement text file not found or unreadable', [
            'agreement_type' => $type,
            'preferred_locale' => $requestedLocale,
            'candidates' => array_map(static fn (array $candidate): string => $candidate['path'], $candidatePaths),
        ]);

        return null;
    }

    /**
     * @return array<int,array{path:string,locale:?string}>
     */
    private function candidatePathsForType(string $type, ?string $requestedLocale): array
    {
        $candidates = [];
        $seen = [];

        if ($requestedLocale !== null) {
            $this->addCandidatePath(
                $candidates,
                $seen,
                $this->configuredLocalizedPathForType($type, $requestedLocale),
                $requestedLocale
            );
        }

        $this->addCandidatePath(
            $candidates,
            $seen,
            $this->configuredPathForType($type),
            null
        );

        if ($requestedLocale !== null) {
            $this->addCandidatePath(
                $candidates,
                $seen,
                $this->localizedFallbackPathForType($type, $requestedLocale),
                $requestedLocale
            );
        }

        $this->addCandidatePath(
            $candidates,
            $seen,
            $this->defaultFallbackPathForType($type),
            null
        );

        return $candidates;
    }

    /**
     * @param array<int,array{path:string,locale:?string}> $candidates
     * @param array<string,bool> $seen
     */
    private function addCandidatePath(array &$candidates, array &$seen, ?string $rawPath, ?string $locale): void
    {
        if (!is_string($rawPath) || trim($rawPath) === '') {
            return;
        }

        $resolvedPath = $this->resolvePath($rawPath);
        if ($resolvedPath === null || isset($seen[$resolvedPath])) {
            return;
        }

        $seen[$resolvedPath] = true;
        $candidates[] = [
            'path' => $resolvedPath,
            'locale' => $locale,
        ];
    }

    private function resolvePath(string $path): ?string
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return base_path($normalized);
    }

    private function normalizeType(string $agreementType): string
    {
        $type = strtolower(trim($agreementType));

        return match ($type) {
            'datenschutz' => 'privacy',
            'impressum' => 'imprint',
            default => $type,
        };
    }

    private function configuredPathForType(string $type): string
    {
        if (in_array($type, ['privacy', 'imprint'], true)) {
            return trim((string) config("legal.documents.{$type}.text_file", ''));
        }

        return trim((string) config("legal.agreements.{$type}.text_file", ''));
    }

    private function configuredLocalizedPathForType(string $type, string $locale): string
    {
        if (in_array($type, ['privacy', 'imprint'], true)) {
            return trim((string) config("legal.documents.{$type}.text_file_{$locale}", ''));
        }

        return trim((string) config("legal.agreements.{$type}.text_file_{$locale}", ''));
    }

    private function localizedFallbackPathForType(string $type, string $locale): string
    {
        if ($locale !== 'en') {
            return '';
        }

        return match ($type) {
            'privacy' => base_path('../legal_docs/wayvio_datenschutz_en.txt'),
            'imprint' => base_path('../legal_docs/wayvio_impressum_en.txt'),
            default => base_path('../legal_docs/wayvio_' . $type . '_en.txt'),
        };
    }

    private function defaultFallbackPathForType(string $type): string
    {
        return match ($type) {
            'privacy' => base_path('../legal_docs/wayvio_datenschutz.txt'),
            'imprint' => base_path('../legal_docs/wayvio_impressum.txt'),
            default => base_path('../legal_docs/wayvio_' . $type . '.txt'),
        };
    }

    private function normalizeLegalLocale(?string $locale): ?string
    {
        $candidate = strtolower(trim((string) $locale));
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace('_', '-', $candidate);
        $base = strtok($candidate, '-');
        if (!is_string($base) || $base === '') {
            return null;
        }

        return in_array($base, ['de', 'en'], true) ? $base : null;
    }

    private function applyProviderPlaceholderReplacements(string $text): string
    {
        $replacements = [
            '[VORNAME NACHNAME]' => trim((string) config('legal.provider.name', '')),
            '[STRASSE NR, PLZ ORT]' => trim((string) config('legal.provider.address', '')),
            '[EMAIL@DOMAIN.DE]' => trim((string) config('legal.provider.email', '')),
        ];

        $usableReplacements = [];
        foreach ($replacements as $placeholder => $value) {
            if ($value === '') {
                continue;
            }
            $usableReplacements[$placeholder] = $value;
        }

        if ($usableReplacements === []) {
            return $text;
        }

        return strtr($text, $usableReplacements);
    }
}
