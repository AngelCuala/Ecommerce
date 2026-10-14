<?php

namespace App\Support;

/** Philippine mobile numbers: 09XXXXXXXXX or +639XXXXXXXXX. */
class PhoneNumber
{
    public const PATTERN = '/^(09\d{9}|\+639\d{9})$/';

    /** Drop spaces, dashes, dots and brackets people type ("0917 123-4567" → "09171234567"). */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $clean = preg_replace('/[\s\-().]/', '', trim($phone));
        return $clean === '' ? null : $clean;
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match(self::PATTERN, (string) self::normalize($phone));
    }
}
