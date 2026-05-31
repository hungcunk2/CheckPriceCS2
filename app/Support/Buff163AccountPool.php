<?php

namespace App\Support;

use App\Services\BuffAccountStore;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Buff163AccountPool
{
    /**
     * @return list<array{label: string, session: string, csrf: string|null}>
     */
    public static function accounts(): array
    {
        if (self::usesDatabase()) {
            return app(BuffAccountStore::class)->activeForPool();
        }

        return self::envAccounts();
    }

    public static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('buff_accounts')
                && app(BuffAccountStore::class)->hasAny();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isConfigured(): bool
    {
        return self::accounts() !== [];
    }

    /**
     * @return list<array{label: string, session: string, csrf: string|null}>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::accounts(),
            fn (array $account) => ! Cache::has(self::cooldownKey($account['label']))
        ));
    }

    public static function markCooldown(array $account, int $seconds, ?int $httpStatus = null): void
    {
        $seconds = max(60, min($seconds, 3600));
        Cache::put(self::cooldownKey($account['label']), time() + $seconds, $seconds);

        Log::warning('buff163: account cooldown', [
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

        if ($response instanceof Response && $response->status() === 403) {
            return 600;
        }

        return 300;
    }

    /**
     * @param  array{label: string, session: string, csrf: string|null}  $account
     * @return array<string, string>
     */
    public static function headers(array $account): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer' => 'https://buff.163.com/',
            'Accept' => 'application/json',
        ];

        $session = $account['session'];
        $headers['Cookie'] = str_contains($session, '=')
            ? $session
            : 'session='.$session;

        if (! empty($account['csrf'])) {
            $headers['X-CSRFToken'] = $account['csrf'];
        }

        return $headers;
    }

    public static function cooldownRemaining(string $label): ?int
    {
        $until = Cache::get(self::cooldownKey($label));
        if (! is_int($until) && ! is_numeric($until)) {
            return null;
        }

        $remaining = (int) $until - time();

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * @return list<array{label: string, session: string, csrf: string|null}>
     */
    private static function envAccounts(): array
    {
        $accounts = [];

        $primarySession = trim((string) config('cs2price.buff_session', ''));
        if ($primarySession !== '') {
            $accounts[] = [
                'label' => (string) config('cs2price.buff_account_label', 'acc-1'),
                'session' => $primarySession,
                'csrf' => self::nullableString(config('cs2price.buff_csrf_token')),
            ];
        }

        foreach (config('cs2price.buff_extra_accounts', []) as $index => $extra) {
            $session = trim((string) ($extra['session'] ?? ''));
            if ($session === '') {
                continue;
            }

            $accounts[] = [
                'label' => (string) ($extra['label'] ?? 'acc-'.($index + 2)),
                'session' => $session,
                'csrf' => self::nullableString($extra['csrf'] ?? null),
            ];
        }

        return $accounts;
    }

    private static function cooldownKey(string $label): string
    {
        return 'buff163_account_cooldown:'.md5($label);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
