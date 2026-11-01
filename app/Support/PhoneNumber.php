<?php

namespace App\Support;

/** Philippine mobile numbers: 11 digits, 09XXXXXXXXX (+639XXXXXXXXX is turned into 09XXXXXXXXX). */
class PhoneNumber
{
    public const PATTERN = '/^09\d{9}$/';

    public const MESSAGE = 'Mobile numbers are 11 digits starting with 09 (e.g. 09171234567).';

    /** Drop spaces, dashes, dots and brackets people type ("0917 123-4567" → "09171234567"). */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $clean = preg_replace('/[\s\-().]/', '', trim($phone));
        if (preg_match('/^\+?639\d{9}$/', $clean)) {
            $clean = '0' . substr($clean, -10);
        }
        return $clean === '' ? null : $clean;
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match(self::PATTERN, (string) self::normalize($phone));
    }
}
