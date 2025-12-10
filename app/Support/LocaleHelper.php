<?php

namespace App\Support;

use App\Models\Language;

/**
 * Centralizes how we resolve the locale for outbound communications.
 * Priority: explicit preference -> booking client main -> user/client -> fallback config.
 */
class LocaleHelper
{
    /**
     * Resolve the locale code that should be used for a message.
     */
    public static function resolve(?string $preferredLocale = null, $user = null, $booking = null): string
    {
        $supported = self::supportedLocales();

        $candidates = [
            self::normalize($preferredLocale, $supported),
            self::normalize(self::fromBooking($booking), $supported),
            self::normalize(self::fromEntity($user), $supported),
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return config('app.fallback_locale', config('app.locale', 'en'));
    }

    private static function fromBooking($booking): ?string
    {
        if (!$booking) {
            return null;
        }

        // Prefer direct locale attribute if one ever exists
        if (!empty($booking->locale)) {
            return (string) $booking->locale;
        }

        if (method_exists($booking, 'clientMain') || isset($booking->clientMain)) {
            $code = self::fromEntity($booking->clientMain ?? null);
            if ($code) {
                return $code;
            }
        }

        return null;
    }

    private static function fromEntity($entity): ?string
    {
        if (!$entity) {
            return null;
        }

        if (isset($entity->language1) && $entity->language1) {
            return $entity->language1->code ?? null;
        }

        $languageId = $entity->language1_id ?? null;
        if ($languageId) {
            $language = Language::find($languageId);
            return $language?->code;
        }

        return null;
    }

    private static function normalize(?string $locale, array $supported): ?string
    {
        if (!$locale) {
            return null;
        }

        $locale = strtolower(substr($locale, 0, 2));

        if (empty($supported) || in_array($locale, $supported, true)) {
            return $locale;
        }

        return null;
    }

    private static function supportedLocales(): array
    {
        $configured = config('app.supported_locales');

        if (is_array($configured) && !empty($configured)) {
            return array_values(array_map('strtolower', $configured));
        }

        return ['de', 'en', 'es', 'fr', 'it'];
    }
}
