<?php

namespace App\Support;

use InvalidArgumentException;

class DecimalMath
{
    public static function multiply(string $left, int $leftScale, string $right, int $rightScale, int $resultScale): string
    {
        if ($leftScale < 0 || $rightScale < 0 || $resultScale < 0) {
            throw new InvalidArgumentException('Decimal scales must not be negative.');
        }

        $leftDigits = self::scaledDigits($left, $leftScale);
        $rightDigits = self::scaledDigits($right, $rightScale);
        $product = self::multiplyIntegers($leftDigits, $rightDigits);
        $discardDigits = $leftScale + $rightScale - $resultScale;

        if ($discardDigits > 0) {
            $productLength = strlen($product);
            $integerLength = max(0, $productLength - $discardDigits);
            $rounded = $integerLength > 0 ? substr($product, 0, $integerLength) : '0';
            $fractionDigits = $productLength > $discardDigits
                ? substr($product, $integerLength)
                : str_pad($product, $discardDigits, '0', STR_PAD_LEFT);
            if ($fractionDigits[0] >= '5') {
                $rounded = self::incrementInteger($rounded);
            }
        } else {
            $rounded = $product.str_repeat('0', abs($discardDigits));
        }

        if ($resultScale === 0) {
            return $rounded;
        }

        $rounded = str_pad($rounded, $resultScale + 1, '0', STR_PAD_LEFT);

        return substr($rounded, 0, -$resultScale).'.'.substr($rounded, -$resultScale);
    }

    private static function scaledDigits(string $value, int $scale): string
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Value must be a non-negative decimal string.');
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $digits = ltrim($integer.$fraction, '0');

        return $digits === '' ? '0' : $digits;
    }

    private static function multiplyIntegers(string $left, string $right): string
    {
        $leftDigits = array_reverse(array_map('intval', str_split($left)));
        $rightDigits = array_reverse(array_map('intval', str_split($right)));
        $product = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $leftIndex => $leftDigit) {
            foreach ($rightDigits as $rightIndex => $rightDigit) {
                $product[$leftIndex + $rightIndex] += $leftDigit * $rightDigit;
            }
        }

        for ($index = 0, $length = count($product) - 1; $index < $length; $index++) {
            $carry = intdiv($product[$index], 10);
            $product[$index] %= 10;
            $product[$index + 1] += $carry;
        }

        $digits = ltrim(implode('', array_reverse($product)), '0');

        return $digits === '' ? '0' : $digits;
    }

    private static function incrementInteger(string $digits): string
    {
        $digits = str_split($digits);
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string) ((int) $digits[$index] + 1);

                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        array_unshift($digits, '1');

        return implode('', $digits);
    }
}
