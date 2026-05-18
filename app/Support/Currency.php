<?php

namespace App\Support;

class Currency
{
    public static function cnyToVnd(?float $cny): ?float
    {
        if ($cny === null) {
            return null;
        }

        return round($cny * (float) config('cs2price.cny_to_vnd'), 0);
    }

    public static function vndToUsd(?float $vnd): ?float
    {
        if ($vnd === null) {
            return null;
        }

        $rate = (float) config('cs2price.vnd_to_usd');

        if ($rate <= 0) {
            return null;
        }

        return round($vnd / $rate, 2);
    }

    /** CNY → VND → USD */
    public static function cnyToUsd(?float $cny): ?float
    {
        $vnd = self::cnyToVnd($cny);

        return self::vndToUsd($vnd);
    }

    public static function cnyToUsdRate(): float
    {
        $vndPerUsd = (float) config('cs2price.vnd_to_usd');

        if ($vndPerUsd <= 0) {
            return 0;
        }

        return (float) config('cs2price.cny_to_vnd') / $vndPerUsd;
    }

    public static function formatUsd(?float $usd): string
    {
        if ($usd === null) {
            return '—';
        }

        return '$'.number_format($usd, 2);
    }
}
