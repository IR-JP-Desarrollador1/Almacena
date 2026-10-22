<?php

namespace Tests\Unit;

use App\Support\DecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase
{
    public function test_adds_decimal_strings_without_floating_point_rounding(): void
    {
        $this->assertSame('1.19000000', DecimalMath::add('1.00000000', 8, '0.19000000', 8, 8));
        $this->assertSame('10.01', DecimalMath::add('9.999', 3, '0.006', 3, 2));
        $this->assertSame('0.00', DecimalMath::add('0', 0, '0', 0, 2));
    }

    public function test_compares_non_negative_decimal_strings_at_requested_scale(): void
    {
        $this->assertSame(-1, DecimalMath::compare('9.9999', '10.0000', 4));
        $this->assertSame(0, DecimalMath::compare('4.5000', '4.5', 4));
        $this->assertSame(1, DecimalMath::compare('100.0000', '99.9999', 4));
    }

    #[DataProvider('multiplicationCases')]
    public function test_it_multiplies_and_rounds_decimal_strings(
        string $left,
        int $leftScale,
        string $right,
        int $rightScale,
        int $resultScale,
        string $expected,
    ): void {
        $this->assertSame($expected, DecimalMath::multiply($left, $leftScale, $right, $rightScale, $resultScale));
    }

    public static function multiplicationCases(): array
    {
        return [
            'currency conversion' => ['1250.2500', 4, '4000.00000000', 8, 4, '5001000.0000'],
            'half-up rounding' => ['1.23445', 5, '1', 0, 4, '1.2345'],
            'rounding carry' => ['9.99995', 5, '1', 0, 4, '10.0000'],
            'zero' => ['0.0000', 4, '4000', 0, 4, '0.0000'],
        ];
    }

    public function test_it_rejects_non_decimal_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMath::multiply('-1.00', 2, '1', 0, 2);
    }
}
