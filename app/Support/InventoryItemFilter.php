<?php

namespace App\Support;

class InventoryItemFilter
{
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
