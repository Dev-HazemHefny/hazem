<?php

namespace App\Support;

class Money
{
    public static function toCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    public static function format(int $cents, string $currency = 'USD'): string
    {
        return sprintf('%s %s', number_format(self::fromCents($cents), 2), strtoupper($currency));
    }
}
