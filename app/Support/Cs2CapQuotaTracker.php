<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

final class Cs2CapQuotaTracker
{
    public static function recordFromResponse(string $label, Response $response): void
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        $limit = $response->header('X-RateLimit-Limit');
        $reset = $response->header('X-RateLimit-Reset');
        $tier = $response->header('X-RateLimit-Tier');

        $ttl = self::ttlUntilReset($reset);

        if ($remaining !== null && $remaining !== '' && ctype_digit((string) $remaining)) {
            Cache::put(self::key($label, 'remaining'), (int) $remaining, $ttl);
        }

        if ($limit !== null && $limit !== '' && ctype_digit((string) $limit)) {
            Cache::put(self::key($label, 'limit'), (int) $limit, $ttl);
        }

        if ($reset !== null && $reset !== '' && ctype_digit((string) $reset)) {
            Cache::put(self::key($label, 'reset'), (int) $reset, $ttl);
        }

        if (is_string($tier) && $tier !== '') {
            Cache::put(self::key($label, 'tier'), $tier, max($ttl, 3600));
        }

        if ($response->status() !== 429) {
            return;
        }

        $code = (string) ($response->json('code') ?? '');
        $remainingInt = ($remaining !== null && $remaining !== '' && ctype_digit((string) $remaining))
            ? (int) $remaining
            : null;

        if ($code === 'RATE_LIMIT_MONTHLY_QUOTA_EXCEEDED' || $remainingInt === 0) {
            self::markExhausted($label, $ttl);
        }
    }

    public static function isExhausted(string $label): bool
    {
        if (Cache::has(self::exhaustedKey($label))) {
            return true;
        }

        $remaining = Cache::get(self::key($label, 'remaining'));

        return $remaining !== null && (int) $remaining <= 0;
    }

    public static function markExhausted(string $label, int $ttlSeconds = 86400): void
    {
        Cache::put(self::exhaustedKey($label), true, max(60, $ttlSeconds));
        Cache::put(self::key($label, 'remaining'), 0, max(60, $ttlSeconds));
    }

    /**
     * @return array{tier: string|null, quota_remaining: int|null, quota_limit: int|null, quota_reset: int|null}|null
     */
    public static function snapshot(string $label): ?array
    {
        $remaining = Cache::get(self::key($label, 'remaining'));
        $limit = Cache::get(self::key($label, 'limit'));

        if ($remaining === null && $limit === null) {
            return null;
        }

        return [
            'tier' => Cache::get(self::key($label, 'tier')),
            'quota_remaining' => $remaining !== null ? (int) $remaining : null,
            'quota_limit' => $limit !== null ? (int) $limit : null,
            'quota_reset' => Cache::get(self::key($label, 'reset')),
        ];
    }

    private static function key(string $label, string $field): string
    {
        return 'cs2cap_quota:'.md5($label).':'.$field;
    }

    private static function exhaustedKey(string $label): string
    {
        return 'cs2cap_quota:'.md5($label).':exhausted';
    }

    private static function ttlUntilReset(?string $resetHeader): int
    {
        if ($resetHeader !== null && $resetHeader !== '' && ctype_digit((string) $resetHeader)) {
            $ttl = (int) $resetHeader - time();

            return max(60, min($ttl, 86400 * 35));
        }

        return 86400;
    }
}
