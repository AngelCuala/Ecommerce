<?php

namespace App\Support;

/**
 * The color names products are tagged with (from their pictures) and the words buyers use for them,
 * in English and Tagalog: "red", "pula", "pulang" all mean red.
 */
final class ProductColors
{
    public const PALETTE = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'brown',
        'black', 'white', 'gray', 'beige', 'gold', 'silver'];

    private const WORDS = [
        'red' => 'red', 'pula' => 'red', 'pulang' => 'red', 'maroon' => 'red', 'crimson' => 'red',
        'orange' => 'orange', 'kahel' => 'orange',
        'yellow' => 'yellow', 'dilaw' => 'yellow', 'dilaw-na' => 'yellow',
        'green' => 'green', 'berde' => 'green', 'berdeng' => 'green', 'luntian' => 'green',
        'blue' => 'blue', 'asul' => 'blue', 'asul-na' => 'blue', 'bughaw' => 'blue', 'navy' => 'blue',
        'purple' => 'purple', 'violet' => 'purple', 'lila' => 'purple', 'ube' => 'purple',
        'pink' => 'pink', 'rosas' => 'pink', 'rosas-na' => 'pink',
        'brown' => 'brown', 'kayumanggi' => 'brown', 'tsokolate-na' => 'brown',
        'black' => 'black', 'itim' => 'black', 'itim-na' => 'black',
        'white' => 'white', 'puti' => 'white', 'puting' => 'white',
        'gray' => 'gray', 'grey' => 'gray', 'abo' => 'gray', 'kulay-abo' => 'gray',
        'beige' => 'beige', 'cream' => 'beige', 'krema' => 'beige', 'nude' => 'beige',
        'gold' => 'gold', 'golden' => 'gold', 'ginto' => 'gold', 'gintong' => 'gold',
        'silver' => 'silver', 'pilak' => 'silver',
    ];

    /** The palette color a buyer's word means ("pula" → "red"), or null when it isn't a color word. */
    public static function fromWord(string $word): ?string
    {
        return self::WORDS[mb_strtolower(trim($word))] ?? null;
    }

    /** Keeps only palette colors, without repeats: what the AI returns is cleaned with this. */
    public static function clean(array $colors): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($c) => self::fromWord((string) $c), $colors))));
    }
}
