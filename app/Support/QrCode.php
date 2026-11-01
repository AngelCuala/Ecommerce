<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * QR codes as inline SVG, for printed waybills (ISO/IEC 18004). Byte mode, error correction level M
 * (about 15% of the code may be damaged and it still reads), versions 1–6: up to 106 characters, plenty
 * for a waybill or tracking number. No external service or image library needed, like Barcode.
 */
class QrCode
{
    /** Per version, level M: [error-correction codewords per block, number of blocks, data codewords per block]. */
    private const VERSIONS = [
        1 => [10, 1, 16],
        2 => [16, 1, 28],
        3 => [26, 1, 44],
        4 => [18, 2, 32],
        5 => [24, 2, 43],
        6 => [16, 4, 27],
    ];
    /** Alignment pattern centres (versions 2–6 have one, away from the finders). */
    private const ALIGN = [2 => 18, 3 => 22, 4 => 26, 5 => 30, 6 => 34];
    private const LEVEL_M = 0;   // format bits for level M

    /** @var array<int, array<int, bool>> */
    private array $modules = [];
    /** @var array<int, array<int, bool>> modules that are not data (finders, timing, format…) */
    private array $reserved = [];
    private int $size = 0;

    /**
     * SVG of $text as a QR code.
     * @param int $px    width and height of the drawn code in px (including the quiet zone)
     * @param int $quiet blank modules around the code (the standard asks for 4)
     */
    public static function svg(string $text, int $px = 120, int $quiet = 4): string
    {
        $matrix = (new self())->encode($text);
        $n = count($matrix);
        $total = $n + 2 * $quiet;
        $path = '';
        foreach ($matrix as $y => $row) {
            for ($x = 0; $x < $n; $x++) {
                if (! $row[$x]) {
                    continue;
                }
                $run = 1;   // a horizontal run of dark modules is one rectangle
                while ($x + $run < $n && $row[$x + $run]) {
                    $run++;
                }
                $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h' . $run . 'v1h-' . $run . 'z';
                $x += $run - 1;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px . '" viewBox="0 0 ' . $total . ' ' . $total
            . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    /** The module matrix (true = dark), without the quiet zone. $mask forces one of the 8 masks (tests). */
    public function encode(string $text, ?int $mask = null): array
    {
        $segment = $this->segment($text);
        $version = null;
        foreach (self::VERSIONS as $v => [$ec, $blocks, $dataPerBlock]) {
            if (count($segment) <= $blocks * $dataPerBlock * 8) {   // mode + length + data must fit
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('Text too long for a QR code here (max about 100 characters).');
        }

        $codewords = $this->addErrorCorrection($this->dataCodewords($segment, $version), $version);
        $this->size = 17 + 4 * $version;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->reserved = $this->modules;
        $this->drawFunctionPatterns($version);
        $this->placeData($codewords);

        // Pick the mask that gives the most readable code (lowest penalty), as the standard asks.
        $best = $mask;
        $bestScore = PHP_INT_MAX;
        for ($try = $best === null ? 0 : 8; $try < 8; $try++) {
            $mask = $try;
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $mask;
            }
            $this->applyMask($mask);   // undo (XOR)
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);

        return $this->modules;
    }

    // ── Data ─────────────────────────────────────────────────────

    /** Byte mode segment, terminator and padding, as data codewords. */
    /**
     * The text as one segment of bits: alphanumeric mode (denser: a smaller code with bigger modules) when it
     * only uses 0–9, A–Z, space and $%*+-./: (every waybill and tracking number), byte mode otherwise.
     * The same choice ZXing makes, so a waybill's code is what ZXing itself would draw.
     */
    private function segment(string $text): array
    {
        $bits = [];
        $put = function (int $value, int $length) use (&$bits) {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        if ($text !== '' && strspn($text, $chars) === strlen($text)) {
            $put(0b0010, 4);                 // alphanumeric mode
            $put(strlen($text), 9);          // character count (9 bits for versions 1–9)
            foreach (str_split($text, 2) as $pair) {
                strlen($pair) === 2
                    ? $put(strpos($chars, $pair[0]) * 45 + strpos($chars, $pair[1]), 11)
                    : $put(strpos($chars, $pair), 6);
            }
            return $bits;
        }
        $bytes = array_values(unpack('C*', $text) ?: []);
        $put(0b0100, 4);                     // byte mode
        $put(count($bytes), 8);              // character count (8 bits for versions 1–9)
        foreach ($bytes as $b) {
            $put($b, 8);
        }
        return $bits;
    }

    /** Segment, terminator and padding, as data codewords. */
    private function dataCodewords(array $bits, int $version): array
    {
        [, $blocks, $perBlock] = self::VERSIONS[$version];
        $capacity = $blocks * $perBlock * 8;
        $put = function (int $value, int $length) use (&$bits) {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $put(0, min(4, $capacity - count($bits)));   // terminator
        while (count($bits) % 8) {
            $bits[] = 0;
        }
        $words = array_map(fn ($chunk) => bindec(implode('', $chunk)), array_chunk($bits, 8));
        for ($pad = 0; count($words) < $capacity / 8; $pad ^= 1) {
            $words[] = $pad ? 0x11 : 0xEC;
        }

        return $words;
    }

    /** Split into blocks, add Reed–Solomon error correction, interleave. */
    private function addErrorCorrection(array $data, int $version): array
    {
        [$ecLen, $blocks, $perBlock] = self::VERSIONS[$version];
        $generator = $this->rsGenerator($ecLen);
        $dataBlocks = array_chunk($data, $perBlock);
        $ecBlocks = array_map(fn ($block) => $this->rsRemainder($block, $generator), $dataBlocks);

        $out = [];
        for ($i = 0; $i < $perBlock; $i++) {
            foreach ($dataBlocks as $block) {
                $out[] = $block[$i];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /** GF(256) multiply, field polynomial x^8 + x^4 + x^3 + x^2 + 1. */
    private function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /** Generator polynomial coefficients (highest degree first, leading 1 left out). */
    private function rsGenerator(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMul($root, 0x02);
        }

        return $result;
    }

    private function rsRemainder(array $data, array $generator): array
    {
        $result = array_fill(0, count($generator), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($generator as $i => $coef) {
                $result[$i] ^= $this->gfMul($coef, $factor);
            }
        }

        return $result;
    }

    // ── Matrix ───────────────────────────────────────────────────

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $version): void
    {
        // Timing patterns.
        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        // Finder patterns with their separators.
        foreach ([[3, 3], [$this->size - 4, 3], [3, $this->size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                        $d = max(abs($dx), abs($dy));
                        $this->set($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        // Alignment pattern.
        if (isset(self::ALIGN[$version])) {
            $c = self::ALIGN[$version];
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $this->set($c + $dx, $c + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
        // Reserve the format areas (drawn per mask) and the dark module.
        $this->drawFormatBits(0);
    }

    /** Format information (level M + mask, BCH-protected) in both places, and the dark module. */
    private function drawFormatBits(int $mask): void
    {
        $data = (self::LEVEL_M << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $this->size - 15 + $i, $bit($i));
        }
        $this->set(8, $this->size - 8, true);   // dark module
    }

    /** Codeword bits in the zigzag order, two columns at a time from the bottom right. */
    private function placeData(array $codewords): void
    {
        $i = 0;
        $total = count($codewords) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;   // skip the vertical timing pattern
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (! $this->reserved[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                    // Remainder bits (left over after the last codeword) stay light.
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($flip) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    /**
     * Readability penalty for a mask, scored exactly as ZXing (the scanner's engine) scores it:
     * runs of 5+ same-colour modules, 2×2 blocks, finder-like 1011101 patterns next to 4 light
     * modules, and how far the code is from half dark. The lowest score wins.
     */
    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $score = 0;

        // Rule 1: runs of 5 or more in rows and columns (3 points, +1 per extra module).
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $same = 0;
                $prev = null;
                for ($b = 0; $b < $n; $b++) {
                    $bit = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                    if ($bit === $prev) {
                        $same++;
                        continue;
                    }
                    if ($same >= 5) {
                        $score += 3 + ($same - 5);
                    }
                    $same = 1;
                    $prev = $bit;
                }
                if ($same >= 5) {
                    $score += 3 + ($same - 5);
                }
            }
        }

        // Rule 2: 2×2 blocks of one colour (3 points each).
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: 1011101 with 4 light modules before or after it (40 points each), rows and columns.
        $white = function (array $line, int $from, int $to): bool {
            for ($i = max($from, 0); $i < min($to, count($line)); $i++) {
                if ($line[$i]) {
                    return false;
                }
            }
            return true;
        };
        for ($a = 0; $a < $n; $a++) {
            $row = $m[$a];
            $col = array_column($m, $a);
            foreach ([$row, $col] as $line) {
                for ($b = 0; $b + 6 < $n; $b++) {
                    if ($line[$b] && ! $line[$b + 1] && $line[$b + 2] && $line[$b + 3] && $line[$b + 4] && ! $line[$b + 5] && $line[$b + 6]
                        && ($white($line, $b - 4, $b) || $white($line, $b + 7, $b + 11))) {
                        $score += 40;
                    }
                }
            }
        }

        // Rule 4: 10 points per 5% away from half dark.
        $dark = 0;
        foreach ($m as $row) {
            $dark += count(array_filter($row));
        }
        $score += intdiv(abs($dark * 2 - $n * $n) * 10, $n * $n) * 10;

        return $score;
    }

}
