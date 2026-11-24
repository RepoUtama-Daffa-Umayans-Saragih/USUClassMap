<?php

namespace App\Support;

class WhatsAppPhoneNormalizer
{
    /**
     * Normalize Indonesian/international phone inputs (e.g. "+62 857-6973-7410", "0857...", "857...")
     * into digits-only international format ("6285769737410") compatible with wa.me and WAHA.
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/[^\d]/', '', (string) $raw);
        if ($digits === '' || strlen($digits) < 9) {
            return null;
        }

        if (str_starts_with($digits, '08')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Format phone number consistently for human display without double '+' signs.
     */
    public static function formatDisplay(?string $raw): string
    {
        $digits = self::normalize($raw);
        if (!$digits) {
            return '-';
        }

        if (str_starts_with($digits, '62') && strlen($digits) >= 11) {
            $local = substr($digits, 2);
            $part1 = substr($local, 0, 3);
            $part2 = substr($local, 3, 4);
            $part3 = substr($local, 7);

            return '+62 ' . $part1 . '-' . $part2 . ($part3 !== '' ? '-' . $part3 : '');
        }

        return '+' . $digits;
    }

    /**
     * Format national local display number (e.g. "0857-6973-7410").
     */
    public static function formatNational(?string $raw): ?string
    {
        $digits = self::normalize($raw);
        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $local = '0' . substr($digits, 2);
            if (strlen($local) >= 10) {
                return substr($local, 0, 4) . '-' . substr($local, 4, 4) . '-' . substr($local, 8);
            }
            return $local;
        }

        return $digits;
    }

    /**
     * Build a safe wa.me link with normalized phone digits and optional prefilled message.
     */
    public static function toWaMeUrl(?string $raw, ?string $text = null, string $fallback = '6285769737410'): string
    {
        $digits = self::normalize($raw) ?? self::normalize($fallback) ?? '6285769737410';
        $url = 'https://wa.me/' . $digits;

        if ($text !== null && $text !== '') {
            $url .= '?text=' . rawurlencode($text);
        }

        return $url;
    }
}
