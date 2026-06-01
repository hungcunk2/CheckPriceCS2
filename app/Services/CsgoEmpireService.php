<?php

namespace App\Services;

use App\Support\Currency;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CsgoEmpireService
{
    private const API_BASE = 'https://csgoempire.com/api/v2/trading/items';

    /**
     * @return array<string, array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }>
     */
    public function getPricesForHashNames(array $marketHashNames, bool $forSync = false): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $unique = array_values(array_unique(array_filter($marketHashNames)));
        $results = [];
        $toFetch = [];

        foreach ($unique as $hashName) {
            $cached = Cache::get($this->cacheKey($hashName));
            if (is_array($cached) && $this->isCacheFresh($cached)) {
                $results[$hashName] = $this->stripCacheMeta($cached);
            } else {
                $toFetch[] = $hashName;
            }
        }

        usort($toFetch, fn (string $a, string $b) => $this->fetchPriority($a) <=> $this->fetchPriority($b));

        $maxFetches = $forSync
            ? (int) config('cs2price.empire_max_fetches_per_sync', 0)
            : (int) config('cs2price.empire_max_fetches_per_check', 15);
        $fetched = 0;
        $delayMs = max(3200, (int) config('cs2price.empire_search_delay_ms', 3500));

        foreach ($toFetch as $hashName) {
            if ($maxFetches > 0 && $fetched >= $maxFetches) {
                $results[$hashName] = $this->skippedPrice(
                    'Empire: bỏ qua (giới hạn '.$maxFetches.' item mới/lần — thử lại sau hoặc đồng bộ cron)'
                );
                continue;
            }

            if ($fetched > 0) {
                usleep($delayMs * 1000);
            }

            $price = $this->fetchPrice($hashName);
            $fetched++;

            if ($this->shouldCachePrice($price)) {
                Cache::put($this->cacheKey($hashName), $this->withFetchedAt($price), $this->cacheStorageTtl());
            } elseif ($this->isNotFound($price)) {
                Cache::put(
                    $this->cacheKey($hashName),
                    $this->withFetchedAt($price),
                    (int) config('cs2price.empire_not_found_cache_seconds', 3600)
                );
            } elseif ($this->isTransientError($price)) {
                Cache::put(
                    $this->cacheKey($hashName),
                    $this->withFetchedAt($price),
                    (int) config('cs2price.empire_error_cache_seconds', 300)
                );
                Log::warning('csgoempire.price', ['item' => $hashName, 'error' => $price['error']]);
            }

            $results[$hashName] = $price;
        }

        return $results;
    }

    public function isEnabled(): bool
    {
        return filter_var(config('cs2price.empire_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && filled(config('cs2price.empire_api_key'));
    }

    public function coinsToUsd(?float $coins): ?float
    {
        return Currency::empireCoinsToUsd($coins);
    }

    public function coinsToCny(?float $coins): ?float
    {
        return Currency::empireCoinsToCny($coins);
    }

    public function coinsToVnd(?float $coins): ?float
    {
        return Currency::empireCoinsToVnd($coins);
    }

    /**
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }
     */
    public function probe(string $marketHashName = 'AK-47 | Redline (Field-Tested)'): array
    {
        if (! $this->isEnabled()) {
            return $this->errorPrice('Empire tắt (EMPIRE_ENABLED=false)');
        }

        return $this->fetchPrice($marketHashName);
    }

    /**
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }
     */
    private function fetchPrice(string $marketHashName): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders($this->headers())
            ->get(self::API_BASE, [
                'per_page' => 50,
                'page' => 1,
                'search' => $marketHashName,
                'order' => 'market_value',
                'sort' => 'asc',
                'auction' => 'no',
            ]);

        return $this->parseSearchResponse($response, $marketHashName);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'CheckPriceCS2/1.0 (+https://checkpricecs2.io.vn)',
        ];

        $key = config('cs2price.empire_api_key');
        if (filled($key)) {
            $headers['Authorization'] = 'Bearer '.$key;
        }

        return $headers;
    }

    /**
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }
     */
    private function parseSearchResponse(?Response $response, string $marketHashName): array
    {
        $empty = [
            'market_value_coins' => null,
            'listing_count' => null,
            'empire_url' => $this->marketUrl($marketHashName),
            'error' => null,
        ];

        if (! $response || $response instanceof \Throwable) {
            return array_merge($empty, ['error' => 'Empire: lỗi kết nối']);
        }

        if ($response->status() === 429) {
            return array_merge($empty, ['error' => 'Empire rate limit (429)']);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->httpErrorMessage($response)]);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $snippet = trim(strip_tags(substr($response->body(), 0, 200)));

            return array_merge($empty, [
                'error' => $this->isBlockedHtml($response->body())
                    ? 'Empire chặn IP server (cần proxy hoặc IP khác)'
                    : 'Empire: phản hồi không hợp lệ'.($snippet !== '' ? ' — '.$snippet : ''),
            ]);
        }

        $rows = $body['data'] ?? [];
        if (! is_array($rows)) {
            return array_merge($empty, ['error' => 'Empire: không có dữ liệu']);
        }

        $matches = array_values(array_filter($rows, function ($row) use ($marketHashName) {
            if (! is_array($row)) {
                return false;
            }

            return strcasecmp((string) ($row['market_name'] ?? ''), $marketHashName) === 0;
        }));

        if ($matches === []) {
            return array_merge($empty, ['error' => 'Không có listing trên Empire']);
        }

        $coins = null;
        foreach ($matches as $row) {
            $value = $this->marketValueToCoins((float) ($row['market_value'] ?? 0));
            if ($value <= 0) {
                continue;
            }
            $coins = $coins === null ? $value : min($coins, $value);
        }

        if ($coins === null) {
            return array_merge($empty, ['error' => 'Không có listing trên Empire']);
        }

        return [
            'market_value_coins' => round($coins, 2),
            'listing_count' => count($matches),
            'empire_url' => $this->marketUrl($marketHashName),
            'error' => null,
        ];
    }

    private function marketValueToCoins(float $marketValue): float
    {
        if ($marketValue <= 0) {
            return 0;
        }

        // API trả cent coin (48882) hoặc đôi khi coin thập phân (488.82).
        if ($marketValue >= 1000 || fmod($marketValue, 1.0) === 0.0) {
            return $marketValue / 100;
        }

        return $marketValue;
    }

    private function marketUrl(string $marketHashName): string
    {
        return 'https://csgoempire.com/withdraw/steam/market?search='.rawurlencode($marketHashName);
    }

    /**
     * @param  array{error: string|null, market_value_coins: float|null}  $price
     */
    private function isNotFound(array $price): bool
    {
        $error = (string) ($price['error'] ?? '');

        return str_contains($error, 'Không có listing');
    }

    /**
     * @param  array{error: string|null}  $price
     */
    private function isTransientError(array $price): bool
    {
        if ($price['error'] === null || $this->isNotFound($price)) {
            return false;
        }

        return true;
    }

    private function isBlockedHtml(string $body): bool
    {
        $lower = mb_strtolower($body);

        return str_contains($lower, 'country blocked')
            || str_contains($lower, 'ip or country blocked')
            || str_contains($lower, 'access denied');
    }

    private function httpErrorMessage(Response $response): string
    {
        if ($this->isBlockedHtml($response->body())) {
            return 'Empire chặn IP server (cần proxy hoặc IP khác)';
        }

        $body = $response->json();
        $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;

        return $message
            ? 'Empire: '.$message
            : 'Empire HTTP '.$response->status();
    }

    /**
     * @param  array{error: string|null, market_value_coins: float|null}  $price
     */
    private function shouldCachePrice(array $price): bool
    {
        return $price['error'] === null && $price['market_value_coins'] !== null;
    }

    private function fetchPriority(string $marketHashName): int
    {
        $cached = Cache::get($this->cacheKey($marketHashName));
        if (! is_array($cached) || ($cached['market_value_coins'] ?? null) === null) {
            return 0;
        }

        return $this->isCacheFresh($cached) ? 2 : 1;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function isCacheFresh(array $cached): bool
    {
        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt <= 0) {
            return false;
        }

        if (($cached['market_value_coins'] ?? null) !== null) {
            return (time() - $fetchedAt) < $this->refreshSeconds();
        }

        if ($this->isNotFound($cached)) {
            return (time() - $fetchedAt) < (int) config('cs2price.empire_not_found_cache_seconds', 3600);
        }

        if (! empty($cached['error'])) {
            return (time() - $fetchedAt) < (int) config('cs2price.empire_error_cache_seconds', 300);
        }

        return false;
    }

    private function refreshSeconds(): int
    {
        return (int) config(
            'cs2price.empire_price_refresh_seconds',
            config('cs2price.price_cache_seconds', 14400)
        );
    }

    private function cacheStorageTtl(): int
    {
        return max($this->refreshSeconds() * 2, 3600);
    }

    /**
     * @param  array<string, mixed>  $price
     * @return array<string, mixed>
     */
    private function withFetchedAt(array $price): array
    {
        $price['fetched_at'] = time();

        return $price;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array<string, mixed>
     */
    private function stripCacheMeta(array $cached): array
    {
        unset($cached['fetched_at']);

        return $cached;
    }

    private function cacheKey(string $marketHashName): string
    {
        return 'empire_price:'.md5($marketHashName);
    }

    /**
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }
     */
    private function skippedPrice(string $message): array
    {
        return [
            'market_value_coins' => null,
            'listing_count' => null,
            'empire_url' => null,
            'error' => $message,
        ];
    }

    /**
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }
     */
    private function errorPrice(string $message): array
    {
        return [
            'market_value_coins' => null,
            'listing_count' => null,
            'empire_url' => null,
            'error' => $message,
        ];
    }
}
