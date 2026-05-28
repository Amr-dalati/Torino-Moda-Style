<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal money helper using integer cents for deterministic arithmetic.
 *
 * - Input/output amounts are decimal strings with exactly 2 fraction digits.
 * - Internally calculations use integer cents.
 */
final class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        if (is_int($amount)) {
            // Interpret as whole units.
            return $amount * 100;
        }

        if (is_float($amount)) {
            // Avoid float math by immediately formatting to a string.
            $amount = number_format($amount, 4, '.', '');
        }

        $raw = trim((string) $amount);
        if ($raw === '') {
            return 0;
        }

        $sign = 1;
        if (str_starts_with($raw, '-')) {
            $sign = -1;
            $raw = substr($raw, 1);
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        // Round to 2 decimals, half-up, based on the 3rd decimal digit.
        $fraction = $fraction === '' ? '0' : $fraction;
        $fraction = preg_replace('/\D/', '', $fraction);

        $f0 = (int) ($fraction[0] ?? '0');
        $f1 = (int) ($fraction[1] ?? '0');
        $f2 = (int) ($fraction[2] ?? '0');

        $cents = ((int) $whole) * 100 + ($f0 * 10) + $f1;
        if ($f2 >= 5) {
            $cents += 1;
        }

        return $sign * $cents;
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;

        return $sign.$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function add(string|int|float|null ...$amounts): string
    {
        $sum = 0;
        foreach ($amounts as $amount) {
            $sum += self::cents($amount);
        }

        return self::format($sum);
    }

    public static function mul(string|int|float|null $amount, int $multiplier): string
    {
        return self::format(self::cents($amount) * $multiplier);
    }
}

