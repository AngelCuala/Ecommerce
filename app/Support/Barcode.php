<?php

namespace App\Support;

/**
 * Code 128 (subset B) barcodes as inline SVG, for printed waybills. Scannable by any
 * ordinary barcode scanner; no external service or image library needed.
 */
class Barcode
{
    /** Bar/space widths for Code 128 symbols 0–106 (106 = stop, which has an extra bar). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
    private const START_B = 104;
    private const STOP = 106;

    /**
     * SVG of $text as a Code 128-B barcode.
     * @param float $module width of the thinnest bar, in px (≈ 0.33 mm at print size 2)
     */
    public static function svg(string $text, int $height = 70, float $module = 2.0): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);   // subset B: printable ASCII only

        $codes = [self::START_B];
        $checksum = self::START_B;
        foreach (str_split($text) as $i => $ch) {
            $value = ord($ch) - 32;
            $codes[] = $value;
            $checksum += $value * ($i + 1);
        }
        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        $quiet = 10 * $module;                                // blank margin scanners need
        $x = $quiet;
        $bars = '';
        foreach ($codes as $code) {
            foreach (str_split(self::PATTERNS[$code]) as $i => $w) {
                $width = (int) $w * $module;
                if ($i % 2 === 0) {                           // even positions are bars
                    $bars .= sprintf('<rect x="%s" y="0" width="%s" height="%d"/>', round($x, 2), round($width, 2), $height);
                }
                $x += $width;
            }
        }
        $total = round($x + $quiet, 2);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %d" width="%s" height="%d" role="img" aria-label="Barcode %s" preserveAspectRatio="none"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $total, $height, $total, $height, e($text), $bars
        );
    }
}
