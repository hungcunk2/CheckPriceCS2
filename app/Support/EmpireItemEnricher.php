<?php

namespace App\Support;

use App\Services\CsgoEmpireService;
use Illuminate\Support\Facades\Cache;

class EmpireItemEnricher
{
    /**
     * Bổ sung giá Empire từ cache (và tùy chọn gọi API cho item chưa có cache).
     *
     * @param  list<array<string, mixed>|object>  $items
     * @return list<array<string, mixed>>
     */
    public static function enrich(array $items, bool $fetchMissing = false): array
    {
        if (! Cs2PriceFeatures::empireEnabled() || $items === []) {
            return self::normalizeList($items);
        }

        $empire = app(CsgoEmpireService::class);
        $normalized = self::normalizeList($items);
        $toFetch = [];

        foreach ($normalized as $index => $item) {
            if (($item['empire_price_coins'] ?? null) !== null) {
                continue;
            }

            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }

            $cached = Cache::get('empire_price:'.md5($hash));
            if (is_array($cached) && self::isUsableCache($cached)) {
                $normalized[$index] = self::mergeEmpireRow($item, $cached, $empire);
                continue;
            }

            if ($fetchMissing) {
                $toFetch[] = $hash;
            } elseif (($item['empire_price_coins'] ?? null) === null && empty($item['empire_error'])) {
                $item['empire_error'] = 'Chưa tra Empire — bấm đồng bộ (⟳) trên kho';
                $normalized[$index] = $item;
            }
        }

        if ($fetchMissing && $toFetch !== []) {
            $prices = $empire->getPricesForHashNames(array_values(array_unique($toFetch)), forSync: true);
            foreach ($normalized as $index => $item) {
                $hash = (string) ($item['market_hash_name'] ?? '');
                if ($hash === '' || ($item['empire_price_coins'] ?? null) !== null) {
                    continue;
                }
                $row = $prices[$hash] ?? null;
                if (is_array($row)) {
                    $normalized[$index] = self::mergeEmpireRow($item, $row, $empire);
                }
            }
        }

        foreach ($normalized as $index => $item) {
            if (($item['empire_price_coins'] ?? null) !== null && ! isset($item['best_sell_venue'])) {
                $normalized[$index]['best_sell_venue'] = SellVenueCompare::bestVenue(
                    isset($item['buff_price_cny']) ? (float) $item['buff_price_cny'] : null,
                    isset($item['empire_price_cny']) ? (float) $item['empire_price_cny'] : null,
                );
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>|object>  $items
     * @return list<array<string, mixed>>
     */
    private static function normalizeList(array $items): array
    {
        return array_values(array_map(
            fn ($item) => is_array($item) ? $item : (array) $item,
            $items
        ));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $empireRow
     * @return array<string, mixed>
     */
    private static function mergeEmpireRow(array $item, array $empireRow, CsgoEmpireService $empire): array
    {
        $coins = $empireRow['market_value_coins'] ?? null;
        $amount = (int) ($item['amount'] ?? 1);
        $empireCny = $empire->coinsToCny($coins !== null ? (float) $coins : null);
        $lineEmpireCny = $empireCny !== null ? $empireCny * $amount : null;

        $item['empire_price_coins'] = $coins;
        $item['empire_price_usd'] = $empire->coinsToUsd($coins !== null ? (float) $coins : null);
        $item['empire_price_cny'] = $empireCny;
        $item['empire_price_vnd'] = $empire->coinsToVnd($coins !== null ? (float) $coins : null);
        $item['empire_listing_count'] = $empireRow['listing_count'] ?? null;
        $item['empire_url'] = $empireRow['empire_url'] ?? null;
        $item['empire_error'] = $empireRow['error'] ?? null;
        $item['line_total_empire_cny'] = $lineEmpireCny;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private static function isUsableCache(array $cached): bool
    {
        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt <= 0) {
            return ($cached['market_value_coins'] ?? null) !== null || ! empty($cached['error']);
        }

        $age = time() - $fetchedAt;
        if (($cached['market_value_coins'] ?? null) !== null) {
            return $age < max(3600, (int) config('cs2price.empire_price_refresh_seconds', 14400));
        }

        $error = (string) ($cached['error'] ?? '');
        if (str_contains($error, 'Không có listing')) {
            return $age < (int) config('cs2price.empire_not_found_cache_seconds', 3600);
        }

        if ($error !== '') {
            return $age < (int) config('cs2price.empire_error_cache_seconds', 300);
        }

        return false;
    }
}
