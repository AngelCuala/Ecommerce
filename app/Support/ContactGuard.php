<?php

namespace App\Support;

/**
 * Keeps buyer–seller deals on ALVY: finds contact details in text (chat, listings) and hides them.
 *
 *   phone    a Philippine mobile number: 09…, +63 9…, 639…, with spaces, dots or dashes, or spelled out
 *            ("zero nine one seven…", "sero siyam isa pito…")
 *   email    name@domain.tld
 *   link     http(s)://…, www.…, or a bare domain (shop.com, page.ph…); ALVY's own links are fine
 *   social   a handle given out on another app ("fb: juan.store", "IG @juanstore", "viber 0917…")
 *   mention  talk of dealing elsewhere ("pm me", "sa facebook", "shopee"…): only a reminder, nothing is hidden
 *
 * The first four are hidden (replaced with MASK) and count as an off-platform attempt; a mention only brings the
 * reminder. Order numbers, waybills (PH…), tracking (ALVY-…), prices and dates are not mistaken for numbers:
 * a phone must start with 09 / 639 and have the right length.
 */
class ContactGuard
{
    public const MASK = '[contact hidden by ALVY]';

    /** Kinds that are hidden and count against a seller. */
    public const HIDDEN = ['phone', 'email', 'link', 'social'];

    private const DIGIT_WORDS = [
        'zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9,
        'sero' => 0, 'isa' => 1, 'dalawa' => 2, 'tatlo' => 3, 'apat' => 4, 'lima' => 5, 'anim' => 6, 'pito' => 7, 'walo' => 8, 'siyam' => 9,
    ];

    private const APPS = 'facebook|fb|messenger|insta(?:gram)?|ig|viber|telegram|tg|whats\s?app|wechat|tiktok|kakao|twitter';

    /** Other apps and shops named in a chat: a reminder only. */
    private const ELSEWHERE = 'facebook|fb|messenger|instagram|viber|telegram|whats\s?app|wechat|tiktok|shopee|lazada|carousell|marketplace';

    /** Our own links (the website, its share links) are not contact details. */
    private const OWN_DOMAINS = ['alvy.trade'];

    /** ['text' => the text with contact details hidden, 'kinds' => ['phone', …]] (kinds sorted, unique). */
    public static function scrub(?string $text): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return ['text' => $text, 'kinds' => []];
        }
        $kinds = [];

        // Emails first (they contain dots that look like domains).
        $text = preg_replace_callback('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', function () use (&$kinds) {
            $kinds[] = 'email';
            return self::MASK;
        }, $text);

        // Phone numbers, in digits or words, with any separators between them.
        $word = implode('|', array_keys(self::DIGIT_WORDS));
        $token = '(?:\d|(?<![a-z])(?:' . $word . ')(?![a-z]))';
        $text = preg_replace_callback('/(?<![\w+])\+?' . $token . '(?:[\s.\-,\/()]*' . $token . '){9,13}(?![\w])/iu', function ($m) use (&$kinds) {
            if (self::isMobile($m[0])) {
                $kinds[] = 'phone';
                return self::MASK;
            }
            return $m[0];
        }, $text);

        // Links.
        $text = preg_replace_callback('~\b(?:https?://|www\.)[^\s<>"]+|\b[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9\-]+)*\.(?:com|net|org|ph|me|ly|io|co|app|link|shop|store|site|online|page|gg|tk)(?:/[^\s<>"]*)?\b~i', function ($m) use (&$kinds) {
            $host = strtolower(preg_replace('~^(?:https?://)?(?:www\.)?([^/\s]+).*$~i', '$1', $m[0]));
            foreach (self::OWN_DOMAINS as $own) {
                if ($host === $own || str_ends_with($host, '.' . $own)) {
                    return $m[0];
                }
            }
            $kinds[] = 'link';
            return self::MASK;
        }, $text);

        // A handle on another app: "fb: juan.store", "IG @juanstore", "telegram - juan_store".
        $text = preg_replace_callback('/\b(' . self::APPS . ')\b\s*(?:[:=\-]|account|acct|name|id|is)?\s*:?\s*@?([\w.]{3,})/iu', function ($m) use (&$kinds) {
            $handle = $m[2];
            // "sa facebook ako" / "fb page" etc.: an ordinary word after the app's name is not a handle.
            if (! preg_match('/[@._\d]/', $m[0]) && ! preg_match('/[:=]/', $m[0])) {
                return $m[0];
            }
            $kinds[] = 'social';
            return $m[1] . ' ' . self::MASK;
        }, $text);
        $text = preg_replace_callback('/(?<![\w@])@[A-Za-z][\w.]{2,}/u', function () use (&$kinds) {
            $kinds[] = 'social';
            return self::MASK;
        }, $text);

        // Talk of dealing elsewhere: a reminder only.
        if (preg_match('/\b(?:pm|dm|text|txt|call|tawag|add|message)\s+(?:me|mo\s+ako|ako|na\s+lang)\b|\b(?:' . self::APPS . '|shopee|lazada|carousell|marketplace)\b|\b(?:direct|diretso|deretso)\s+(?:na\s+)?(?:payment|bayad|transaction|deal)|\boutside\s+(?:the\s+)?(?:app|alvy)\b/iu', $text)) {
            $kinds[] = 'mention';
        }

        $kinds = array_values(array_unique($kinds));
        sort($kinds);

        return ['text' => $text, 'kinds' => $kinds];
    }

    /** Whether any of the kinds is a hidden contact detail (not just a mention). */
    public static function hidesContact(array $kinds): bool
    {
        return (bool) array_intersect($kinds, self::HIDDEN);
    }

    /** The contact details found, without changing anything (for form checks). */
    public static function find(?string $text): array
    {
        return array_values(array_intersect(self::scrub($text)['kinds'], self::HIDDEN));
    }

    /** A buyer's number as a seller sees it: 0917•••4567. */
    public static function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return $phone ? '•••' : '';
        }
        return substr($digits, 0, 4) . '•••' . substr($digits, -4);
    }

    /** 09XXXXXXXXX / 639XXXXXXXXX once spelled-out digits are turned into digits. */
    private static function isMobile(string $run): bool
    {
        $digits = preg_replace_callback('/[a-z]+/iu', fn ($w) => (string) (self::DIGIT_WORDS[strtolower($w[0])] ?? ''), $run);
        $digits = preg_replace('/\D/', '', $digits);

        return (bool) preg_match('/^(?:09\d{9}|639\d{9})$/', $digits);
    }
}
