<?php

namespace App\Services;

use Illuminate\Support\Str;

class TwoFactorService
{
    protected string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $randomBytes = random_bytes($length);

        return $this->base32Encode($randomBytes);
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }

    public function getOtpAuthUrl(string $email, string $secret, ?string $issuer = null): string
    {
        $labelIssuer = rawurlencode($issuer ?? config('app.name', 'Wayvio'));
        $labelEmail = rawurlencode($email);

        return "otpauth://totp/{$labelIssuer}:{$labelEmail}?secret={$secret}&issuer={$labelIssuer}&period=30&digits=6";
    }

    public function verify(string $secret, string $code, int $window = 1, int $period = 30): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', $code);

        if ($normalizedCode === '' || !ctype_digit($normalizedCode)) {
            return false;
        }

        $binarySecret = $this->base32Decode($secret);
        $timeSlice = (int) floor(time() / $period);

        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->totp($binarySecret, $timeSlice + $i);

            if (hash_equals($calculated, $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    protected function totp(string $binarySecret, int $timeSlice): string
    {
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $code = $truncated % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    protected function base32Encode(string $binary): string
    {
        $binaryString = '';

        foreach (str_split($binary) as $char) {
            $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binaryString, 5);
        $encoded = '';

        foreach ($chunks as $chunk) {
            $padded = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $this->base32Chars[bindec($padded)];
        }

        return $encoded;
    }

    protected function base32Decode(string $secret): string
    {
        $cleanSecret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $charMap = array_flip(str_split($this->base32Chars));

        $binaryString = '';
        foreach (str_split($cleanSecret) as $char) {
            if (!isset($charMap[$char])) {
                continue;
            }
            $binaryString .= str_pad(decbin($charMap[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($binaryString, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
