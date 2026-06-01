<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CsgoEmpireHealthService
{
    private const CACHE_KEY = 'empire_health:last';

    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $last = Cache::get(self::CACHE_KEY);

        return [
            'enabled' => (bool) config('cs2price.empire_enabled', false),
            'configured' => app(CsgoEmpireService::class)->isConfigured(),
            'coin_to_usd' => \App\Support\ExchangeRateStore::empireCoinToUsd(),
            'coin_to_vnd' => \App\Support\ExchangeRateStore::empireCoinToVnd(),
            'max_fetches' => (int) config('cs2price.empire_max_fetches_per_check', 30),
            'last_check' => is_array($last) ? $last : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        $checkedAt = now()->toIso8601String();
        $started = microtime(true);
        $empire = app(CsgoEmpireService::class);

        if (! $empire->isEnabled()) {
            $result = [
                'status' => 'error',
                'message' => 'Empire tắt — bật EMPIRE_ENABLED=true trong .env',
                'checked_at' => $checkedAt,
            ];
            Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        }

        if (! $empire->isConfigured()) {
            $result = [
                'status' => 'error',
                'message' => 'Thiếu CSGOEMPIRE_API_KEY',
                'checked_at' => $checkedAt,
            ];
            Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        }

        $price = $empire->probe();
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($price['error'] !== null) {
            $result = [
                'status' => 'error',
                'message' => $price['error'],
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        } else {
            $coins = $price['market_value_coins'];
            $cny = $empire->coinsToCny($coins);
            $result = [
                'status' => 'ok',
                'message' => sprintf(
                    'OK — %.2f coin (≈ ¥%s), %d listing',
                    $coins,
                    $cny !== null ? number_format($cny, 2) : '—',
                    $price['listing_count'] ?? 0
                ),
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        }

        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }
}
