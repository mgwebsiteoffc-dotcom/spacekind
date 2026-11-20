<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function scaled(mixed $value, int $places = 2, int $max = 999999999999): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,10}(?:\.\d{1,'.$places.'})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['amount' => 'Use a non-negative decimal with up to '.$places.' decimal places, without commas.']);
        }
        [$whole,$fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $result = (int) $whole * (10 ** $places) + (int) str_pad($fraction, $places, '0');
        if ($result > $max) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds this development build’s supported limit.']);
        }

return $result;
    }

    public static function divide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    public static function format(int $minor): string
    {
        return '₹'.number_format($minor / 100, 2);
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function year(string $date): string
    {
        $y = (int) substr($date, 0, 4);
        if ((int) substr($date, 5, 2) < 4) {
            $y--;
        }

return $y.'-'.substr((string) ($y + 1),2);
    }
}
