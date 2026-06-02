<?php

namespace App\Support;

use App\Services\Cs2CapApiKeyStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Cs2CapApiPool
{
    /**
     * @return list<array{label: string, api_key: string}>
     */
    public static function accounts(): array
    {
        if (self::usesDatabase()) {
            return app(Cs2CapApiKeyStore::class)->activeForPool();
        }

        return self::envAccounts();
    }

    public static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('cs2cap_api_keys')
                && app(Cs2CapApiKeyStore::class)->hasAny();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isConfigured(): bool
    {
        return self::accounts() !== [];
    }

    /**
     * @return list<array{label: string, api_key: string}>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::accounts(),
            fn (array $account) => ! Cache::has(self::cooldownKey($account['label']))
        ));
    }

    /**
     * @return array{label: string, api_key: string}|null
     */
    public static function next(): ?array
    {
        $available = self::available();
        if ($available === []) {
            return null;
        }

        $index = (int) Cache::get('cs2cap_api_pool:cursor', 0);
        $account = $available[$index % count($available)];
        Cache::put('cs2cap_api_pool:cursor', ($index + 1) % count($available), 86400);

        return $account;
    }

    public static function setCooldown(string $label, int $seconds): void
    {
        $seconds = max(5, min(3600, $seconds));
        Cache::put(self::cooldownKey($label), true, $seconds);
    }

    public static function cooldownSeconds(string $label): ?int
    {
        $key = self::cooldownKey($label);
        if (! Cache::has($key)) {
            return null;
        }

        // Laravel doesn't expose remaining TTL for file cache; return a hint.
        return (int) config('cs2price.cs2cap_cooldown_seconds', 30);
    }

    private static function cooldownKey(string $label): string
    {
        return 'cs2cap_api_pool:cooldown:'.md5($label);
    }

    /**
     * @return list<array{label: string, api_key: string}>
     */
    private static function envAccounts(): array
    {
        $rows = [];
        $primary = trim((string) config('cs2price.cs2cap_api_key', ''));
        if ($primary !== '') {
            $rows[] = ['label' => (string) config('cs2price.cs2cap_key_label', 'cs2cap-1'), 'api_key' => $primary];
        }

        foreach (config('cs2price.cs2cap_extra_api_keys', []) as $extra) {
            $key = trim((string) ($extra['api_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = ['label' => (string) ($extra['label'] ?? 'cs2cap-extra'), 'api_key' => $key];
        }

        return $rows;
    }
}

