<?php

namespace App\Support;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;

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
                    $coinToUsd = $row->empire_coin_to_usd > 0
                        ? (float) $row->empire_coin_to_usd
                        : self::deriveCoinToUsd((float) $row->empire_coin_to_vnd, (float) $row->vnd_to_usd);

                    return [
                        'cny_to_vnd' => (float) $row->cny_to_vnd,
                        'vnd_to_usd' => (float) $row->vnd_to_usd,
                        'empire_coin_to_vnd' => (float) $row->empire_coin_to_vnd,
                        'empire_coin_to_usd' => $coinToUsd,
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

    public static function empireCoinToVnd(): float
    {
        return self::get()['empire_coin_to_vnd'];
    }

    public static function empireCoinToUsd(): float
    {
        return self::get()['empire_coin_to_usd'];
    }

    /**
     * @param  array{cny_to_vnd: float|string, vnd_to_usd: float|string, empire_coin_to_vnd: float|string}  $data
     */
    public static function save(array $data): ExchangeRate
    {
        $cnyToVnd = (float) $data['cny_to_vnd'];
        $vndToUsd = (float) $data['vnd_to_usd'];
        $coinToVnd = (float) $data['empire_coin_to_vnd'];
        $coinToUsd = $vndToUsd > 0 ? round($coinToVnd / $vndToUsd, 6) : 0.0;

        $row = ExchangeRate::query()->orderBy('id')->first() ?? new ExchangeRate;

        $row->fill([
            'cny_to_vnd' => $cnyToVnd,
            'vnd_to_usd' => $vndToUsd,
            'empire_coin_to_vnd' => $coinToVnd,
            'empire_coin_to_usd' => $coinToUsd,
        ]);
        $row->save();

        Cache::forget(self::CACHE_KEY);

        return $row;
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{cny_to_vnd: float, vnd_to_usd: float, empire_coin_to_vnd: float, empire_coin_to_usd: float}
     */
    private static function fromConfig(): array
    {
        $vndPerUsd = (float) config('cs2price.vnd_to_usd', 26700);
        $coinToUsd = (float) config('cs2price.empire_coin_to_usd', 0.6143);
        $coinToVnd = (float) config('cs2price.empire_coin_to_vnd', $coinToUsd * $vndPerUsd);

        return [
            'cny_to_vnd' => (float) config('cs2price.cny_to_vnd', 3750),
            'vnd_to_usd' => $vndPerUsd,
            'empire_coin_to_vnd' => $coinToVnd,
            'empire_coin_to_usd' => $coinToUsd,
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
