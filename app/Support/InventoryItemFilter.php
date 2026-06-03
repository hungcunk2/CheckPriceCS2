<?php

namespace App\Support;

use App\Services\ItemPriceCacheStore;

class InventoryItemFilter
{
    public static function minUsdUnitValue(): float
    {
        return max(0, (float) config('cs2price.min_item_usd_value', 1));
    }

    public static function isBelowMinUsd(?float $usdPerUnit): bool
    {
        $min = self::minUsdUnitValue();

        if ($min <= 0 || $usdPerUnit === null) {
            return false;
        }

        return $usdPerUnit < $min;
    }

    /**
     * @param  array<string, mixed>|null  $buffPayload
     */
    public static function usdPerUnitFromBuffPayload(?array $buffPayload): ?float
    {
        if ($buffPayload === null) {
            return null;
        }

        $cny = isset($buffPayload['sell_min_price']) ? (float) $buffPayload['sell_min_price'] : null;

        return Currency::cnyToUsd($cny);
    }

    /**
     * @param  array<string, mixed>  $row  Snapshot / priced row
     */
    public static function isWorthListingRow(array $row): bool
    {
        if (self::minUsdUnitValue() <= 0) {
            return true;
        }

        $usd = $row['buff_price_usd'] ?? null;
        if ($usd === null && isset($row['buff_price_cny'])) {
            $usd = Currency::cnyToUsd((float) $row['buff_price_cny']);
        }
        if ($usd === null) {
            $usd = isset($row['empire_price_usd']) ? (float) $row['empire_price_usd'] : null;
        }

        return ! self::isBelowMinUsd($usd !== null ? (float) $usd : null);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function onlyWorthListing(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $row) => self::isWorthListingRow($row)));
    }

    /**
     * Bỏ skin đã biết (cache Buff) đơn giá dưới ngưỡng — không gọi Buff/Empire cho chúng.
     *
     * @param  list<array<string, mixed>>  $steamItems
     * @param  array<string, array<string, mixed>>  $cachedBuffByKey
     * @return list<array<string, mixed>>
     */
    public static function filterSteamItemsExcludingKnownCheap(array $steamItems, array $cachedBuffByKey): array
    {
        if (self::minUsdUnitValue() <= 0 || $steamItems === []) {
            return $steamItems;
        }

        return array_values(array_filter($steamItems, static function (array $item) use ($cachedBuffByKey) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                return false;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            $key = app(ItemPriceCacheStore::class)->key($hash, $phase);
            if (! isset($cachedBuffByKey[$key])) {
                return true;
            }

            $usd = self::usdPerUnitFromBuffPayload($cachedBuffByKey[$key]);

            return ! self::isBelowMinUsd($usd);
        }));
    }
    /**
     * @param  array<string, mixed>  $desc  Steam inventory description
     */
    public static function isTradableDescription(array $desc): bool
    {
        if ((int) ($desc['tradable'] ?? 0) !== 1) {
            return false;
        }

        $name = (string) ($desc['name'] ?? $desc['market_hash_name'] ?? '');
        if (self::nameIndicatesNotTradable($name)) {
            return false;
        }

        foreach ($desc['tags'] ?? [] as $tag) {
            if (! is_array($tag)) {
                continue;
            }
            $category = (string) ($tag['category'] ?? '');
            $label = (string) ($tag['localized_tag_name'] ?? $tag['name'] ?? '');
            if ($category === 'Tradable' && in_array($label, ['No', 'Không', '不可交易'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item  Row / snapshot item
     */
    public static function isTradableItem(array $item): bool
    {
        if (array_key_exists('tradable', $item) && ! $item['tradable']) {
            return false;
        }

        $name = (string) ($item['name'] ?? $item['market_hash_name'] ?? '');

        return ! self::nameIndicatesNotTradable($name);
    }

    /**
     * @param  list<array<string, mixed>|object>  $items
     * @return list<array<string, mixed>>
     */
    public static function onlyTradable(array $items): array
    {
        return array_values(array_filter($items, function ($item) {
            return self::isTradableItem((array) $item);
        }));
    }

    private static function nameIndicatesNotTradable(string $name): bool
    {
        return stripos($name, 'Not Tradable') !== false
            || stripos($name, 'Không thể giao dịch') !== false;
    }
}
