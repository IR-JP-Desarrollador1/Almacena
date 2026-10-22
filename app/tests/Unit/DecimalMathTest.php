<?php

namespace Tests\Unit;

use App\Support\DecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase
{
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
