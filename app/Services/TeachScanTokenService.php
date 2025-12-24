<?php

namespace App\Services;

use App\Models\BookingUser;

class TeachScanTokenService
{
    private const VERSION = 1;

    public static function makeToken(BookingUser $bookingUser): string
    {
        $payload = implode('|', [
            'v' . self::VERSION,
            $bookingUser->id,
            $bookingUser->school_id,
            $bookingUser->client_id,
            $bookingUser->date,
            now()->timestamp,
        ]);

        $signature = hash_hmac('sha256', $payload, self::resolveKey(), true);

        return self::base64UrlEncode($payload) . '.' . self::base64UrlEncode($signature);
    }

    public static function decodeToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return ['valid' => false, 'reason' => 'invalid_format'];
        }

        [$payloadEncoded, $signatureEncoded] = $parts;
        $payloadRaw = self::base64UrlDecode($payloadEncoded);
        $signature = self::base64UrlDecode($signatureEncoded);

        if ($payloadRaw === null || $signature === null) {
            return ['valid' => false, 'reason' => 'invalid_encoding'];
        }

        $expected = hash_hmac('sha256', $payloadRaw, self::resolveKey(), true);
        if (!hash_equals($expected, $signature)) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        $parts = explode('|', $payloadRaw);
        if (count($parts) < 6 || !str_starts_with($parts[0], 'v')) {
            return ['valid' => false, 'reason' => 'invalid_payload'];
        }

        $payload = [
            'version' => (int) substr($parts[0], 1),
            'booking_user_id' => (int) $parts[1],
            'school_id' => (int) $parts[2],
            'client_id' => (int) $parts[3],
            'date' => $parts[4],
            'issued_at' => (int) $parts[5],
        ];

        if ($payload['booking_user_id'] <= 0) {
            return ['valid' => false, 'reason' => 'invalid_payload'];
        }

        return ['valid' => true, 'payload' => $payload];
    }

    private static function resolveKey(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);
        return $decoded === false ? null : $decoded;
    }
}
