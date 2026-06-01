<?php

namespace App\Support;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ExchangeRateStore
{
    private const CACHE_KEY = 'exchange_rates:current';

    /**
     * @return array{
     *   cny_to_vnd: float,
     *   vnd_to_usd: float,
     *   empire_coin_to_vnd: float,
     *   empire_coin_to_usd: float,
     *   source: 'database'|'config'
     * }
     */
    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 86400, function (): array {
            try {
                $row = ExchangeRate::query()->orderBy('id')->first();
                if ($row !== null) {
                    $vndToUsd = (float) $row->vnd_to_usd;
                    $coinToUsd = (float) $row->empire_coin_to_usd;
                    if ($coinToUsd <= 0 && (float) $row->empire_coin_to_vnd > 0) {
                        $coinToUsd = self::deriveCoinToUsd((float) $row->empire_coin_to_vnd, $vndToUsd);
                    }
                    $coinToUsd = self::roundCoinToUsd($coinToUsd);

                    return [
                        'cny_to_vnd' => (float) $row->cny_to_vnd,
                        'vnd_to_usd' => $vndToUsd,
                        'empire_coin_to_usd' => $coinToUsd,
                        'empire_coin_to_vnd' => self::coinToVndFromUsd($coinToUsd, $vndToUsd),
                        'source' => 'database',
                    ];
                }
            } catch (\Throwable) {
                // Bảng chưa migrate — dùng .env
            }

            return array_merge(self::fromConfig(), ['source' => 'config']);
        });
    }

    public static function cnyToVnd(): float
    {
        return self::get()['cny_to_vnd'];
    }

    public static function vndToUsd(): float
    {
        return self::get()['vnd_to_usd'];
    }

    /** 1 Empire coin → VND (tính từ coin→USD × 1 USD = ? ₫). */
    public static function empireCoinToVnd(): float
    {
        return self::coinToVndFromUsd(self::empireCoinToUsd(), self::vndToUsd());
    }

    public static function empireCoinToUsd(): float
    {
        return self::get()['empire_coin_to_usd'];
    }

    /**
     * @param  array{cny_to_vnd: float|string, vnd_to_usd: float|string, empire_coin_to_usd: float|string}  $data
     */
    public static function save(array $data): ExchangeRate
    {
        if (! Schema::hasTable('exchange_rates')) {
            throw new \RuntimeException('Bảng exchange_rates chưa tồn tại — chạy: php artisan migrate --force');
        }

        $cnyToVnd = (float) $data['cny_to_vnd'];
        $vndToUsd = (float) $data['vnd_to_usd'];
        $coinToUsd = self::roundCoinToUsd((float) $data['empire_coin_to_usd']);
        $coinToVnd = self::coinToVndFromUsd($coinToUsd, $vndToUsd);

        $row = ExchangeRate::query()->orderBy('id')->first() ?? new ExchangeRate;

        $row->fill([
            'cny_to_vnd' => $cnyToVnd,
            'vnd_to_usd' => $vndToUsd,
            'empire_coin_to_usd' => $coinToUsd,
            'empire_coin_to_vnd' => $coinToVnd,
        ]);
        $row->save();

        Cache::forget(self::CACHE_KEY);

        return $row;
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function roundCoinToUsd(float $coinToUsd): float
    {
        return round($coinToUsd, 6);
    }

    public static function formatCoinToUsd(float $coinToUsd): string
    {
        return rtrim(rtrim(sprintf('%.6F', self::roundCoinToUsd($coinToUsd)), '0'), '.');
    }

    public static function coinToVndFromUsd(float $coinToUsd, float $vndPerUsd): float
    {
        if ($coinToUsd <= 0 || $vndPerUsd <= 0) {
            return 0.0;
        }

        return round(self::roundCoinToUsd($coinToUsd) * $vndPerUsd, 4);
    }

    /**
     * @return array{cny_to_vnd: float, vnd_to_usd: float, empire_coin_to_vnd: float, empire_coin_to_usd: float}
     */
    private static function fromConfig(): array
    {
        $vndPerUsd = (float) config('cs2price.vnd_to_usd', 26700);
        $coinToUsd = self::roundCoinToUsd((float) config('cs2price.empire_coin_to_usd', 0.6143));

        return [
            'cny_to_vnd' => (float) config('cs2price.cny_to_vnd', 3750),
            'vnd_to_usd' => $vndPerUsd,
            'empire_coin_to_usd' => $coinToUsd,
            'empire_coin_to_vnd' => self::coinToVndFromUsd($coinToUsd, $vndPerUsd),
        ];
    }

    private static function deriveCoinToUsd(float $coinToVnd, float $vndPerUsd): float
    {
        if ($vndPerUsd <= 0) {
            return 0.0;
        }

        return round($coinToVnd / $vndPerUsd, 6);
    }
}
