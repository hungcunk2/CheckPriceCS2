<?php

namespace App\Support;

use App\Services\EmpireApiKeyStore;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CsgoEmpireApiPool
{
    /**
     * @return list<array{label: string, api_key: string}>
     */
    public static function accounts(): array
    {
        if (self::usesDatabase()) {
            return app(EmpireApiKeyStore::class)->activeForPool();
        }

        return self::envAccounts();
    }

    public static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('empire_api_keys')
                && app(EmpireApiKeyStore::class)->hasAny();
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

        $index = (int) Cache::get('empire_api_pool:cursor', 0);
        $account = $available[$index % count($available)];
        Cache::put('empire_api_pool:cursor', ($index + 1) % count($available), 86400);

        return $account;
    }

    /**
     * @return array<string, string>
     */
    public static function headers(?array $account = null): array
    {
        $account ??= self::next();

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'CheckPriceCS2/1.0 (+https://checkpricecs2.io.vn)',
        ];

        if ($account !== null && ($account['api_key'] ?? '') !== '') {
            $headers['Authorization'] = 'Bearer '.$account['api_key'];
        }

        return $headers;
    }

    public static function cooldownRemaining(string $label): ?int
    {
        $until = Cache::get(self::cooldownKey($label));
        if (! is_int($until)) {
            return null;
        }

        $remaining = $until - time();

        return $remaining > 0 ? $remaining : null;
    }

    public static function markCooldown(array $account, int $seconds, ?int $httpStatus = null): void
    {
        $seconds = max(60, min($seconds, 3600));
        Cache::put(self::cooldownKey($account['label']), time() + $seconds, $seconds);

        Log::warning('csgoempire: api key cooldown', [
            'account' => $account['label'],
            'seconds' => $seconds,
            'http_status' => $httpStatus,
        ]);
    }

    public static function cooldownSecondsForResponse(?Response $response, int $attempt = 1): int
    {
        if ($response instanceof Response && $response->status() === 429) {
            $header = $response->header('Retry-After');
            if ($header !== null && $header !== '' && ctype_digit((string) $header)) {
                return min((int) $header, 600);
            }

            return min(300, 60 * $attempt);
        }

        if ($response instanceof Response && in_array($response->status(), [403, 401], true)) {
            return 600;
        }

        return 300;
    }

    /**
     * @return list<array{label: string, api_key: string}>
     */
    private static function envAccounts(): array
    {
        $accounts = [];
        $primary = trim((string) config('cs2price.empire_api_key', ''));
        if ($primary !== '') {
            $accounts[] = [
                'label' => (string) config('cs2price.empire_account_label', 'empire-1'),
                'api_key' => $primary,
            ];
        }

        foreach (config('cs2price.empire_extra_api_keys', []) as $extra) {
            if (! is_array($extra)) {
                continue;
            }
            $key = trim((string) ($extra['api_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $accounts[] = [
                'label' => (string) ($extra['label'] ?? 'empire-extra'),
                'api_key' => $key,
            ];
        }

        $seen = [];
        $unique = [];
        foreach ($accounts as $account) {
            if (isset($seen[$account['api_key']])) {
                continue;
            }
            $seen[$account['api_key']] = true;
            $unique[] = $account;
        }

        return $unique;
    }

    private static function cooldownKey(string $label): string
    {
        return 'empire_api_cooldown:'.md5($label);
    }
}
