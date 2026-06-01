<?php

namespace App\Support;

class Currency
{
    public static function cnyToVnd(?float $cny): ?float
    {
        if ($cny === null) {
            return null;
        }

        return round($cny * ExchangeRateStore::cnyToVnd(), 0);
    }

    public static function vndToUsd(?float $vnd): ?float
    {
        if ($vnd === null) {
            return null;
        }

        $rate = ExchangeRateStore::vndToUsd();

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

    /** USD → CNY (qua tỷ giá VND cấu hình). */
    public static function usdToCny(?float $usd): ?float
    {
        if ($usd === null) {
            return null;
        }

        $cnyPerUsd = self::cnyToUsdRate();
        if ($cnyPerUsd <= 0) {
            return null;
        }

        return round($usd / $cnyPerUsd, 2);
    }

    public static function cnyToUsdRate(): float
    {
        $vndPerUsd = ExchangeRateStore::vndToUsd();

        if ($vndPerUsd <= 0) {
            return 0;
        }

        return ExchangeRateStore::cnyToVnd() / $vndPerUsd;
    }

    public static function formatUsd(?float $usd): string
    {
        if ($usd === null) {
            return '—';
        }

        return '$'.number_format($usd, 2);
    }

    public static function formatVnd(?float $vnd): string
    {
        if ($vnd === null) {
            return '—';
        }

        return number_format($vnd).' ₫';
    }

    public static function empireCoinsToVnd(?float $coins): ?float
    {
        $usd = self::empireCoinsToUsd($coins);

        if ($usd === null) {
            return null;
        }

        $vndPerUsd = ExchangeRateStore::vndToUsd();
        if ($vndPerUsd <= 0) {
            return null;
        }

        return round($usd * $vndPerUsd, 0);
    }

    public static function empireCoinsToCny(?float $coins): ?float
    {
        $vnd = self::empireCoinsToVnd($coins);
        $rate = ExchangeRateStore::cnyToVnd();

        if ($vnd === null || $rate <= 0) {
            return null;
        }

        return round($vnd / $rate, 2);
    }

    public static function empireCoinsToUsd(?float $coins): ?float
    {
        if ($coins === null) {
            return null;
        }

        return round($coins * ExchangeRateStore::empireCoinToUsd(), 2);
    }
}
