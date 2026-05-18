<?php

namespace App\Support;

class InventoryWeaponStats
{
    /**
     * @param  list<array<string, mixed>|object>  $items
     * @return list<array{key: string, label: string, count: int}>
     */
    public static function summarize(array $items): array
    {
        $counts = [];

        foreach ($items as $raw) {
            $item = (object) $raw;
            $hash = (string) ($item->market_hash_name ?? $item->name ?? '');
            if ($hash === '') {
                continue;
            }

            $key = self::categoryKey($hash);
            $amount = max(1, (int) ($item->amount ?? 1));
            $counts[$key] = ($counts[$key] ?? 0) + $amount;
        }

        $rows = [];
        foreach (self::categoryOrder() as $key => $label) {
            if (($counts[$key] ?? 0) > 0) {
                $rows[] = [
                    'key' => $key,
                    'label' => $label,
                    'count' => $counts[$key],
                ];
            }
            unset($counts[$key]);
        }

        foreach ($counts as $key => $count) {
            if ($count > 0) {
                $rows[] = [
                    'key' => $key,
                    'label' => self::categoryOrder()[$key] ?? 'Khác',
                    'count' => $count,
                ];
            }
        }

        return $rows;
    }

    public static function categoryKey(string $marketHashName): string
    {
        $original = $marketHashName;
        $prefix = self::weaponPrefix($marketHashName);

        if (preg_match('/★/u', $original)) {
            if (stripos($original, 'Gloves') !== false || stripos($original, 'Hand Wraps') !== false) {
                return 'gloves';
            }

            return 'knife';
        }

        if (preg_match('/\bSticker\b/i', $original)) {
            return 'sticker';
        }

        if (preg_match('/\b(Graffiti|Patch|Pin|Music Kit|Case Key|Storage Unit)\b/i', $original)) {
            return 'other';
        }

        if (preg_match('/\b(Case|Capsule|Package|Souvenir Package|Terminal)\b/i', $original)) {
            return 'case';
        }

        if (self::isAgentItem($original, $prefix)) {
            return 'agent';
        }

        return self::weaponCategoryFromPrefix($prefix) ?? 'other';
    }

    private static function isAgentItem(string $marketHashName, string $weaponPrefix): bool
    {
        if (preg_match('/\bAgent\b/i', $marketHashName)) {
            return true;
        }

        if (self::weaponCategoryFromPrefix($weaponPrefix) !== null) {
            return false;
        }

        if (str_contains($marketHashName, ' | ')) {
            return ! self::hasSkinWearCondition($marketHashName);
        }

        return true;
    }

    private static function hasSkinWearCondition(string $marketHashName): bool
    {
        return (bool) preg_match(
            '/\((?:Factory New|Minimal Wear|Field-Tested|Well-Worn|Battle-Scarred)\)\s*$/i',
            $marketHashName
        );
    }

    private static function weaponCategoryFromPrefix(string $prefix): ?string
    {
        return match (true) {
            str_starts_with($prefix, 'AK-47') => 'ak47',
            str_starts_with($prefix, 'M4A4') => 'm4',
            str_starts_with($prefix, 'M4A1-S') => 'm4',
            str_starts_with($prefix, 'AWP') => 'awp',
            str_starts_with($prefix, 'Glock-18') => 'glock',
            str_starts_with($prefix, 'USP-S'), str_starts_with($prefix, 'USP ') => 'usp',
            str_starts_with($prefix, 'Desert Eagle') => 'deagle',
            str_starts_with($prefix, 'P250') => 'p250',
            str_starts_with($prefix, 'Five-SeveN') => 'fiveseven',
            str_starts_with($prefix, 'CZ75-Auto') => 'cz75',
            str_starts_with($prefix, 'Tec-9') => 'tec9',
            str_starts_with($prefix, 'P2000') => 'p2000',
            str_starts_with($prefix, 'Dual Berettas') => 'dual',
            str_starts_with($prefix, 'R8 Revolver') => 'revolver',
            str_starts_with($prefix, 'Galil AR') => 'galil',
            str_starts_with($prefix, 'FAMAS') => 'famas',
            str_starts_with($prefix, 'SG 553') => 'sg553',
            str_starts_with($prefix, 'AUG') => 'aug',
            str_starts_with($prefix, 'SSG 08') => 'ssg08',
            str_starts_with($prefix, 'SCAR-20') => 'scar20',
            str_starts_with($prefix, 'G3SG1') => 'g3sg1',
            str_starts_with($prefix, 'MAC-10') => 'smg',
            str_starts_with($prefix, 'MP9') => 'smg',
            str_starts_with($prefix, 'MP7') => 'smg',
            str_starts_with($prefix, 'UMP-45') => 'smg',
            str_starts_with($prefix, 'P90') => 'smg',
            str_starts_with($prefix, 'PP-Bizon') => 'smg',
            str_starts_with($prefix, 'MP5-SD') => 'smg',
            str_starts_with($prefix, 'Nova') => 'shotgun',
            str_starts_with($prefix, 'XM1014') => 'shotgun',
            str_starts_with($prefix, 'Sawed-Off') => 'shotgun',
            str_starts_with($prefix, 'MAG-7') => 'shotgun',
            str_starts_with($prefix, 'M249') => 'heavy',
            str_starts_with($prefix, 'Negev') => 'heavy',
            str_starts_with($prefix, 'Zeus x27') => 'zeus',
            default => null,
        };
    }

    private static function weaponPrefix(string $marketHashName): string
    {
        $name = preg_replace('/\s*\([^)]+\)\s*$/', '', $marketHashName) ?? $marketHashName;
        $name = preg_replace('/^★\s*/u', '', $name) ?? $name;
        $name = preg_replace('/^StatTrak™\s*/', '', $name) ?? $name;
        $name = preg_replace('/^Souvenir\s*/', '', $name) ?? $name;

        if (str_contains($name, ' | ')) {
            $name = explode(' | ', $name, 2)[0];
        }

        return trim($name);
    }

    /**
     * @return array<string, string>
     */
    private static function categoryOrder(): array
    {
        return [
            'knife' => 'Dao',
            'gloves' => 'Găng tay',
            'ak47' => 'AK-47',
            'm4' => 'M4',
            'awp' => 'AWP',
            'glock' => 'Glock',
            'usp' => 'USP',
            'deagle' => 'Deagle',
            'p250' => 'P250',
            'fiveseven' => 'Five-SeveN',
            'cz75' => 'CZ75',
            'tec9' => 'Tec-9',
            'p2000' => 'P2000',
            'dual' => 'Dual Berettas',
            'revolver' => 'R8',
            'galil' => 'Galil',
            'famas' => 'FAMAS',
            'sg553' => 'SG 553',
            'aug' => 'AUG',
            'ssg08' => 'SSG 08',
            'scar20' => 'SCAR-20',
            'g3sg1' => 'G3SG1',
            'smg' => 'SMG',
            'shotgun' => 'Shotgun',
            'heavy' => 'Máy',
            'zeus' => 'Zeus',
            'agent' => 'Nhân vật',
            'sticker' => 'Sticker',
            'case' => 'Hòm / capsule',
            'other' => 'Khác',
        ];
    }
}
