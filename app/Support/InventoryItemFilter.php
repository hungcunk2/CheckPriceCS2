<?php

namespace App\Support;

use Carbon\Carbon;

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

    /**
     * Huy chương / coin / badge Steam — không trade, không phải hold 7 ngày.
     *
     * @param  array<string, mixed>  $desc
     */
    public static function isExcludedPermanentCollectible(array $desc): bool
    {
        $name = (string) ($desc['market_hash_name'] ?? $desc['name'] ?? '');
        if ($name === '') {
            return true;
        }

        if (preg_match(
            '/\b(Service Medal|Veteran Coin|Loyalty Badge|Global Offensive Badge|Ten Year Veteran|Pick[\x{2019}\']Em Trophy|Premier Badge)\b/iu',
            $name
        )) {
            return true;
        }

        if (preg_match('/\b(Medal|Badge|Trophy)\b/i', $name) && ! str_contains($name, ' | ')) {
            return true;
        }

        $type = self::typeTagLabel($desc);
        if ($type !== null && preg_match('/^(Medal|Coin|Badge|Trophy|Collectible)$/i', $type)) {
            return true;
        }

        return false;
    }

    /**
     * Skin / case đang trade hold (khóa 7–8 ngày), bỏ huy chương.
     *
     * @param  array<string, mixed>  $desc
     */
    public static function isTradeHoldDescription(array $desc): bool
    {
        if ((int) ($desc['tradable'] ?? 0) === 1) {
            return false;
        }

        if (self::isExcludedPermanentCollectible($desc)) {
            return false;
        }

        $name = (string) ($desc['name'] ?? $desc['market_hash_name'] ?? '');
        if (self::nameIndicatesNotTradable($name)) {
            return false;
        }

        if (self::hasTradeLockSignal($desc)) {
            return true;
        }

        return self::looksLikeMarketSkinOrContainer($desc);
    }

    /**
     * @param  array<string, mixed>  $desc
     */
    public static function tradeUnlockAt(array $desc): ?string
    {
        if (! empty($desc['cache_expiration'])) {
            try {
                return Carbon::parse((string) $desc['cache_expiration'])->toIso8601String();
            } catch (\Throwable) {
                // fall through
            }
        }

        foreach (self::descriptionTexts($desc) as $text) {
            if (preg_match(
                '/(?:Tradable|Marketable|giao dịch)[\s\/]*(?:After|sau)\s+(.+?)(?:\(|$)/iu',
                $text,
                $m
            )) {
                try {
                    return Carbon::parse(trim($m[1]))->toIso8601String();
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $desc
     */
    private static function hasTradeLockSignal(array $desc): bool
    {
        if (! empty($desc['cache_expiration'])) {
            return true;
        }

        foreach (self::descriptionTexts($desc) as $text) {
            if (preg_match('/Tradable\/?Marketable\s+After|(?:có thể|co the)\s+giao dịch sau|Tradable\s+After/i', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $desc
     */
    private static function looksLikeMarketSkinOrContainer(array $desc): bool
    {
        if (empty($desc['market_hash_name'])) {
            return false;
        }

        $type = self::typeTagLabel($desc);
        if ($type === null) {
            return true;
        }

        return (bool) preg_match(
            '/(Pistol|Rifle|SMG|Shotgun|Machinegun|Knife|Gloves|Container|Sticker|Agent|Charm|Graffiti|Patch|Tool|Equipment)/i',
            $type
        );
    }

    /**
     * @param  array<string, mixed>  $desc
     */
    private static function typeTagLabel(array $desc): ?string
    {
        foreach ($desc['tags'] ?? [] as $tag) {
            if (! is_array($tag)) {
                continue;
            }
            if ((string) ($tag['category'] ?? '') === 'Type') {
                $label = trim((string) ($tag['localized_tag_name'] ?? $tag['name'] ?? ''));

                return $label !== '' ? $label : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $desc
     * @return list<string>
     */
    private static function descriptionTexts(array $desc): array
    {
        $texts = [];
        foreach (array_merge($desc['owner_descriptions'] ?? [], $desc['descriptions'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }
            $plain = trim(strip_tags((string) ($block['value'] ?? '')));
            if ($plain !== '') {
                $texts[] = $plain;
            }
        }

        return $texts;
    }

    private static function nameIndicatesNotTradable(string $name): bool
    {
        return stripos($name, 'Not Tradable') !== false
            || stripos($name, 'Không thể giao dịch') !== false;
    }
}
