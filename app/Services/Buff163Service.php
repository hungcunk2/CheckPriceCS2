<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Buff163Service
{
    /**
     * @return array<string, array{
     *   goods_id: int|null,
     *   sell_min_price: float|null,
     *   sell_num: int|null,
     *   buff_url: string|null,
     *   error: string|null
     * }>
     */
    public function getPricesForHashNames(array $marketHashNames): array
    {
        $unique = array_values(array_unique(array_filter($marketHashNames)));
        $results = [];
        $toFetch = [];
        $cacheTtl = $this->cacheStorageTtl();

        foreach ($unique as $hashName) {
            $cached = Cache::get($this->cacheKey($hashName));
            if (is_array($cached) && $this->isCacheFresh($cached)) {
                $results[$hashName] = $this->stripCacheMeta($cached);
            } else {
                $toFetch[] = $hashName;
            }
        }

        usort($toFetch, fn (string $a, string $b) => $this->fetchPriority($a) <=> $this->fetchPriority($b));

        $concurrency = max(1, (int) config('cs2price.buff_concurrency', 2));
        $delayMs = (int) config('cs2price.request_delay_ms', 350);

        foreach (array_chunk($toFetch, $concurrency) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $hashName) {
                    $pool->as($hashName)
                        ->timeout(15)
                        ->withHeaders($this->headers())
                        ->get('https://buff.163.com/api/market/goods', [
                            'game' => 'csgo',
                            'page_num' => 1,
                            'search' => $hashName,
                            'tab' => 'selling',
                        ]);
                }
            });

            foreach ($chunk as $hashName) {
                $response = $responses[$hashName] ?? null;
                $price = $this->fetchPriceWithRetries($hashName, $response);
                if ($this->shouldCachePrice($price)) {
                    Cache::put($this->cacheKey($hashName), $this->withFetchedAt($price), $cacheTtl);
                }
                $results[$hashName] = $price;
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $rateLimited = array_keys(array_filter(
            $results,
            fn ($price) => $this->isRateLimited($price)
        ));

        if ($rateLimited !== []) {
            usleep(2_000_000);
            foreach ($rateLimited as $hashName) {
                $response = Http::timeout(15)
                    ->withHeaders($this->headers())
                    ->get('https://buff.163.com/api/market/goods', [
                        'game' => 'csgo',
                        'page_num' => 1,
                        'search' => $hashName,
                        'tab' => 'selling',
                    ]);
                $price = $this->fetchPriceWithRetries($hashName, $response, maxAttempts: 3);
                if ($this->shouldCachePrice($price)) {
                    Cache::put($this->cacheKey($hashName), $this->withFetchedAt($price), $cacheTtl);
                }
                $results[$hashName] = $price;
                usleep(max($delayMs, 500) * 1000);
            }
        }

        foreach ($unique as $hashName) {
            if (isset($results[$hashName])) {
                continue;
            }
            $cached = Cache::get($this->cacheKey($hashName));
            if (is_array($cached) && ($cached['sell_min_price'] ?? null) !== null) {
                $results[$hashName] = $this->stripCacheMeta($cached);
            }
        }

        return $results;
    }

    /**
     * @return array{
     *   goods_id: int|null,
     *   sell_min_price: float|null,
     *   sell_num: int|null,
     *   buff_url: string|null,
     *   error: string|null
     * }
     */
    private function parsePriceResponse(?Response $response, string $marketHashName): array
    {
        $empty = [
            'goods_id' => null,
            'sell_min_price' => null,
            'sell_num' => null,
            'buff_url' => null,
            'error' => null,
        ];

        if (! $response || $response instanceof \Throwable) {
            return array_merge($empty, ['error' => 'Buff: lỗi kết nối']);
        }

        if (! $response->successful()) {
            $error = match ($response->status()) {
                403 => 'Buff chặn truy cập — thêm BUFF163_SESSION vào .env (cookie đăng nhập buff.163.com)',
                429 => 'Buff tạm chặn (429) — gọi quá nhanh, thử đồng bộ lại sau vài phút',
                default => 'Buff HTTP '.$response->status(),
            };

            return array_merge($empty, ['error' => $error]);
        }

        $body = $response->json();
        if (($body['code'] ?? '') !== 'OK') {
            $message = $body['error'] ?? $body['msg'] ?? 'Buff từ chối (cần cookie session?).';

            return array_merge($empty, ['error' => (string) $message]);
        }

        $items = $body['data']['items'] ?? [];
        $match = collect($items)->first(
            fn ($item) => ($item['market_hash_name'] ?? '') === $marketHashName
        ) ?? ($items[0] ?? null);

        if (! $match) {
            return array_merge($empty, ['error' => 'Không tìm thấy giá']);
        }

        $goodsId = (int) ($match['id'] ?? 0);
        $price = isset($match['sell_min_price']) ? (float) $match['sell_min_price'] : null;
        $sellNum = isset($match['sell_num']) ? (int) $match['sell_num'] : null;

        return [
            'goods_id' => $goodsId ?: null,
            'sell_min_price' => $price,
            'sell_num' => $sellNum,
            'buff_url' => $goodsId ? "https://buff.163.com/goods/{$goodsId}" : null,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *   goods_id: int|null,
     *   sell_min_price: float|null,
     *   sell_num: int|null,
     *   buff_url: string|null,
     *   error: string|null
     * }
     */
    private function fetchPriceWithRetries(string $marketHashName, ?Response $response, int $maxAttempts = 2): array
    {
        $price = $this->parsePriceResponse($response, $marketHashName);
        $attempt = 1;

        while ($this->isRateLimited($price) && $attempt < $maxAttempts) {
            usleep(500_000 * $attempt);
            $response = Http::timeout(15)
                ->withHeaders($this->headers())
                ->get('https://buff.163.com/api/market/goods', [
                    'game' => 'csgo',
                    'page_num' => 1,
                    'search' => $marketHashName,
                    'tab' => 'selling',
                ]);
            $price = $this->parsePriceResponse($response, $marketHashName);
            $attempt++;
        }

        return $price;
    }

    /** 0 = chưa có giá, 1 = có giá nhưng đã quá hạn refresh */
    private function fetchPriority(string $marketHashName): int
    {
        $cached = Cache::get($this->cacheKey($marketHashName));
        if (! is_array($cached) || ($cached['sell_min_price'] ?? null) === null) {
            return 0;
        }

        return $this->isCacheFresh($cached) ? 2 : 1;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function isCacheFresh(array $cached): bool
    {
        if (($cached['sell_min_price'] ?? null) === null) {
            return false;
        }

        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt <= 0) {
            return false;
        }

        return (time() - $fetchedAt) < $this->refreshSeconds();
    }

    private function refreshSeconds(): int
    {
        return (int) config(
            'cs2price.price_refresh_seconds',
            config('cs2price.price_cache_seconds', 7200)
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

    /**
     * @param  array{error: string|null, sell_min_price: float|null}  $price
     */
    private function shouldCachePrice(array $price): bool
    {
        if ($price['error'] !== null) {
            return false;
        }

        return $price['sell_min_price'] !== null;
    }

    /**
     * @param  array{error: string|null}  $price
     */
    private function isRateLimited(array $price): bool
    {
        $error = $price['error'] ?? '';

        return str_contains($error, '429');
    }

    private function cacheKey(string $marketHashName): string
    {
        return 'buff_price:'.md5($marketHashName);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer' => 'https://buff.163.com/',
            'Accept' => 'application/json',
        ];

        $session = config('cs2price.buff_session');
        if ($session) {
            $headers['Cookie'] = str_contains($session, '=')
                ? $session
                : 'session='.$session;
        }

        $csrf = config('cs2price.buff_csrf_token');
        if ($csrf) {
            $headers['X-CSRFToken'] = $csrf;
        }

        return $headers;
    }

    public function cnyToVnd(?float $cny): ?float
    {
        return \App\Support\Currency::cnyToVnd($cny);
    }

    public function cnyToUsd(?float $cny): ?float
    {
        return \App\Support\Currency::cnyToUsd($cny);
    }
}
