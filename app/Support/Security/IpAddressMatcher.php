<?php

namespace App\Support\Security;

use Illuminate\Http\Request;

class IpAddressMatcher
{
    /**
     * @param mixed $value
     */
    public static function normalize($value): string
    {
        if ($value === null) {
            return '';
        }

        $candidate = trim((string) $value);
        if ($candidate === '') {
            return '';
        }

        if (str_starts_with($candidate, '::ffff:')) {
            $mapped = substr($candidate, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $candidate = $mapped;
            }
        }

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '';
    }

    public static function requestIp(Request $request): string
    {
        $remoteIp = self::normalize($request->server('REMOTE_ADDR'));
        if ($remoteIp !== '') {
            return $remoteIp;
        }

        return self::normalize($request->ip());
    }

    /**
     * @param array<int,mixed> $entries
     * @return array<int,string>
     */
    public static function sanitizeAllowlist(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $candidate = self::normalizeAllowlistEntry($entry);
            if ($candidate === '') {
                continue;
            }

            $normalized[] = $candidate;
        }

        return array_values(array_unique($normalized));
    }

    public static function isLoopback(string $ip): bool
    {
        $normalizedIp = self::normalize($ip);
        return in_array($normalizedIp, ['127.0.0.1', '::1'], true);
    }

    /**
     * @param array<int,string> $allowlist
     */
    public static function isAllowed(string $ip, array $allowlist): bool
    {
        $normalizedIp = self::normalize($ip);
        if ($normalizedIp === '') {
            return false;
        }

        foreach ($allowlist as $entry) {
            if ($entry === $normalizedIp) {
                return true;
            }

            if (str_contains($entry, '/') && self::ipInCidr($normalizedIp, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $entry
     */
    private static function normalizeAllowlistEntry($entry): string
    {
        $candidate = trim((string) $entry);
        if ($candidate === '') {
            return '';
        }

        if (!str_contains($candidate, '/')) {
            return self::normalize($candidate);
        }

        [$network, $prefix] = array_pad(explode('/', $candidate, 2), 2, '');
        $normalizedNetwork = self::normalize($network);
        if ($normalizedNetwork === '' || $prefix === '' || !ctype_digit($prefix)) {
            return '';
        }

        $networkBin = @inet_pton($normalizedNetwork);
        if ($networkBin === false) {
            return '';
        }

        $maxBits = strlen($networkBin) * 8;
        $prefixLength = (int) $prefix;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return '';
        }

        return $normalizedNetwork . '/' . $prefixLength;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, '');
        if ($network === '' || $prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $normalizedNetwork = self::normalize($network);
        if ($normalizedNetwork === '') {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($normalizedNetwork);

        if ($ipBin === false || $networkBin === false || strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $prefixLength = (int) $prefix;
        $maxBits = strlen($ipBin) * 8;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0) {
            $ipPrefix = substr($ipBin, 0, $fullBytes);
            $networkPrefix = substr($networkBin, 0, $fullBytes);

            if ($ipPrefix !== $networkPrefix) {
                return false;
            }
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBin[$fullBytes]);
        $networkByte = ord($networkBin[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}
