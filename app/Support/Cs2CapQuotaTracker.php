<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

final class Cs2CapQuotaTracker
{
    /** RPM tối đa theo tier CS2Cap (Quant) — header nhỏ hơn hoặc bằng là quota/phút, không phải tháng. */
    private const MAX_RPM_LIMIT = 300;

    public static function recordFromResponse(string $label, Response $response): void
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        $limit = $response->header('X-RateLimit-Limit');
        $reset = $response->header('X-RateLimit-Reset');
        $tier = $response->header('X-RateLimit-Tier');

        $limitInt = self::parseHeaderInt($limit);
        $resetInt = self::parseHeaderInt($reset);
        $monthlyHeaders = self::looksLikeMonthlyQuotaHeaders($limitInt, $resetInt);
        $ttl = self::ttlUntilReset($resetInt);

        if ($monthlyHeaders) {
            $remainingInt = self::parseHeaderInt($remaining);
            if ($remainingInt !== null) {
                Cache::put(self::key($label, 'remaining'), $remainingInt, $ttl);
            }

            if ($limitInt !== null) {
                Cache::put(self::key($label, 'limit'), $limitInt, $ttl);
            }

            if ($resetInt !== null && self::isUnixTimestamp($resetInt)) {
                Cache::put(self::key($label, 'reset'), $resetInt, $ttl);
            }
        }

        if (is_string($tier) && $tier !== '') {
            Cache::put(self::key($label, 'tier'), $tier, max($ttl, 3600));
        }

        if ($response->successful()) {
            $remainingInt = $monthlyHeaders ? self::parseHeaderInt($remaining) : null;
            if ($remainingInt !== null && $remainingInt > 0) {
                Cache::forget(self::exhaustedKey($label));
            }

            return;
        }

        if ($response->status() !== 429) {
            return;
        }

        $code = (string) ($response->json('code') ?? '');
        $remainingInt = $monthlyHeaders ? self::parseHeaderInt($remaining) : null;

        if ($code === 'RATE_LIMIT_MONTHLY_QUOTA_EXCEEDED') {
            self::markExhausted($label, $ttl);

            return;
        }

        if ($monthlyHeaders && $remainingInt === 0 && $limitInt !== null && $limitInt > self::MAX_RPM_LIMIT) {
            self::markExhausted($label, $ttl);
        }
    }

    /** Key vừa xác thực OK — gỡ cờ exhausted do RPM / cache cũ. */
    public static function acknowledgeValidKey(string $label): void
    {
        Cache::forget(self::exhaustedKey($label));

        $limit = Cache::get(self::key($label, 'limit'));
        if ($limit === null || (int) $limit <= self::MAX_RPM_LIMIT) {
            Cache::forget(self::key($label, 'remaining'));
        }
    }

    public static function forget(string $label): void
    {
        foreach (['remaining', 'limit', 'reset', 'tier', 'effective_quota'] as $field) {
            Cache::forget(self::key($label, $field));
        }

        Cache::forget(self::exhaustedKey($label));
    }

    public static function recordEffectiveQuota(string $label, int $effectiveQuota): void
    {
        Cache::put(self::key($label, 'effective_quota'), $effectiveQuota, 86400 * 35);
    }

    public static function isExhausted(string $label): bool
    {
        if (Cache::has(self::exhaustedKey($label))) {
            return true;
        }

        $remaining = Cache::get(self::key($label, 'remaining'));
        if ($remaining === null) {
            return false;
        }

        $limit = Cache::get(self::key($label, 'limit'));
        if ($limit !== null && (int) $limit <= self::MAX_RPM_LIMIT) {
            return false;
        }

        if ($limit === null || (int) $limit <= self::MAX_RPM_LIMIT) {
            return false;
        }

        return (int) $remaining <= 0;
    }

    public static function forgetAll(): int
    {
        $cleared = 0;

        foreach (Cs2CapApiPool::accounts() as $account) {
            self::forget((string) $account['label']);
            $cleared++;
        }

        return $cleared;
    }

    public static function markExhausted(string $label, int $ttlSeconds = 86400): void
    {
        Cache::put(self::exhaustedKey($label), true, max(60, $ttlSeconds));
        Cache::put(self::key($label, 'remaining'), 0, max(60, $ttlSeconds));
    }

    /**
     * @return array{tier: string|null, effective_quota: int|null, quota_remaining: int|null, quota_limit: int|null, quota_reset: int|null}|null
     */
    public static function snapshot(string $label): ?array
    {
        $remaining = Cache::get(self::key($label, 'remaining'));
        $limit = Cache::get(self::key($label, 'limit'));
        $effectiveQuota = Cache::get(self::key($label, 'effective_quota'));

        if ($limit !== null && (int) $limit <= self::MAX_RPM_LIMIT) {
            $remaining = null;
            $limit = null;
        }

        if ($remaining === null && $limit === null && $effectiveQuota === null) {
            return null;
        }

        $limitInt = $limit !== null ? (int) $limit : null;
        $reset = Cache::get(self::key($label, 'reset'));
        $resetInt = $reset !== null ? (int) $reset : null;

        return [
            'tier' => Cache::get(self::key($label, 'tier')),
            'effective_quota' => $effectiveQuota !== null ? (int) $effectiveQuota : null,
            'quota_remaining' => $remaining !== null ? (int) $remaining : null,
            'quota_limit' => $limitInt,
            'quota_reset' => self::isUnixTimestamp($resetInt) ? $resetInt : null,
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

    private static function ttlUntilReset(?int $reset): int
    {
        if ($reset === null) {
            return 86400;
        }

        if (self::isUnixTimestamp($reset)) {
            $ttl = $reset - time();

            return max(60, min($ttl, 86400 * 35));
        }

        return max(60, min($reset, 86400));
    }

    private static function parseHeaderInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! ctype_digit((string) $value)) {
            return null;
        }

        return (int) $value;
    }

    private static function looksLikeMonthlyQuotaHeaders(?int $limit, ?int $reset): bool
    {
        // Chỉ tin quota tháng khi limit lớn (vd. 1000/mo). $reset unix cũng có trên RPM — bỏ qua.
        return $limit !== null && $limit > self::MAX_RPM_LIMIT;
    }

    private static function isUnixTimestamp(?int $value): bool
    {
        return $value !== null && $value >= 1_000_000_000;
    }
}
