<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CsTradeHealthService
{
    private const CACHE_KEY = 'cstrade_health:last';

    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $last = Cache::get(self::CACHE_KEY);

        return [
            'api_url' => CsTradeInventoryService::apiUrl(),
            'probe_steam_id' => (string) config('cs2price.cstrade_probe_steam_id'),
            'prefer_cstrade' => config('cs2price.inventory_source') !== 'steam',
            'fallback_steam' => (bool) config('cs2price.inventory_fallback_steam', true),
            'last_check' => is_array($last) ? $last : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        $steamId = (string) config('cs2price.cstrade_probe_steam_id');
        $checkedAt = now()->toIso8601String();
        $started = microtime(true);

        try {
            $bundle = app(CsTradeInventoryService::class)->fetch($steamId);
            $itemCount = count($bundle['items']);
            $ms = (int) round((microtime(true) - $started) * 1000);

            $result = [
                'status' => 'ok',
                'http_status' => 200,
                'message' => "Hoạt động — {$itemCount} skin tradable (probe {$steamId})",
                'item_count' => $itemCount,
                'latency_ms' => $ms,
                'persona' => $bundle['steam_persona_name'] ?? null,
                'checked_at' => $checkedAt,
            ];
        } catch (\Throwable $e) {
            $result = [
                'status' => 'error',
                'http_status' => null,
                'message' => $e->getMessage(),
                'item_count' => null,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'persona' => null,
                'checked_at' => $checkedAt,
            ];
        }

        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }
}
