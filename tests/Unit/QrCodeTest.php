<?php

namespace Tests\Unit;

use App\Support\QrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** The waybill QR code generator (App\Support\QrCode). */
class QrCodeTest extends TestCase
{
    private static function fingerprint(array $matrix): string
    {
        return sha1(implode('|', array_map(fn ($row) => implode('', array_map(fn ($b) => $b ? '1' : '0', $row)), $matrix)));
    }

    public function test_waybill_codes_match_the_reference_encoder_module_for_module(): void
    {
        // Checked against ZXing's own QR encoder (same mode, version and mask): identical, module for module.
        $this->assertSame('aa2fdeb9f0115c970f6e34b621d33833905fc554', self::fingerprint((new QrCode())->encode('PH261028882405611')));
        $this->assertSame('6097027cf64bb337c22e1bcb908f96d4c1a5f1ff', self::fingerprint((new QrCode())->encode('ALVY-5C378D10E1')));
    }

    public function test_size_follows_the_content(): void
    {
        $this->assertCount(21, (new QrCode())->encode('PH261028882405611'));           // alphanumeric mode: version 1
        $this->assertCount(25, (new QrCode())->encode('https://alvy.test/x'));         // lowercase: byte mode, version 2
        $this->assertCount(41, (new QrCode())->encode(str_repeat('q', 106)));          // the largest supported: version 6
    }

    public function test_corner_squares_and_timing_pattern_are_in_place(): void
    {
        $m = (new QrCode())->encode('PH261028882405611');
        $n = count($m);
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$x0, $y0]) {
            for ($i = 0; $i < 7; $i++) {
                $this->assertTrue($m[$y0][$x0 + $i] && $m[$y0 + 6][$x0 + $i] && $m[$y0 + $i][$x0] && $m[$y0 + $i][$x0 + 6], 'finder border');
            }
            $this->assertFalse($m[$y0 + 1][$x0 + 1], 'finder light ring');
            $this->assertTrue($m[$y0 + 3][$x0 + 3], 'finder centre');
        }
        for ($i = 8; $i < $n - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], 'timing row');
            $this->assertSame($i % 2 === 0, $m[$i][6], 'timing column');
        }
        $this->assertTrue($m[$n - 8][8], 'dark module');
    }

    public function test_svg_has_a_white_background_and_the_requested_size(): void
    {
        $svg = QrCode::svg('PH261028882405611', 100, 2);
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 25 25"', $svg);   // 21 + 2×2 quiet zone
        $this->assertStringContainsString('<rect width="25" height="25" fill="#fff"/>', $svg);
        $this->assertStringContainsString('aria-label="QR code"', $svg);
    }

    public function test_too_long_text_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QrCode())->encode(str_repeat('q', 200));
    }
}
