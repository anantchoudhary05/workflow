<?php

namespace App\Support;

/** Decimal-safe money helpers using bcmath-free integer cents arithmetic. */
class Money
{
    public static function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function format(mixed $value, string $currency = 'INR'): string
    {
        return $currency.' '.number_format((float) $value, 2);
    }
}
