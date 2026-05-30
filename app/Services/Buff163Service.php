<?php

namespace App\Services;

use App\Support\Buff163AccountPool;
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
            $pending = $chunk;

            while ($pending !== []) {
                $account = $this->nextAvailableAccount();
                if ($account === null) {
                    foreach ($pending as $hashName) {
                        $results[$hashName] = $this->rateLimitedPrice('Hết acc Buff khả dụng — thử lại sau vài phút');
                    }
                    break;
                }

                $responses = Http::pool(function (Pool $pool) use ($pending, $account) {
                    foreach ($pending as $hashName) {
                        $pool->as($hashName)
                            ->timeout(15)
                            ->withHeaders(Buff163AccountPool::headers($account))
                            ->get('https://buff.163.com/api/market/goods', $this->queryFor($hashName));
                    }
                });

                $rotateAccount = false;
                $retry = [];

                foreach ($pending as $hashName) {
                    $response = $responses[$hashName] ?? null;
                    if ($response instanceof Response && $this->shouldRotateAccount($response)) {
                        $rotateAccount = true;
                        $retry[] = $hashName;
                        continue;
                    }

                    $price = $this->parsePriceResponse($response, $hashName);
                    if ($this->shouldRotateAccount($response, $price)) {
                        $rotateAccount = true;
                        $retry[] = $hashName;
                        continue;
                    }

                    if ($this->shouldCachePrice($price)) {
                        Cache::put($this->cacheKey($hashName), $this->withFetchedAt($price), $cacheTtl);
                    }
                    $results[$hashName] = $price;
                }

                if ($rotateAccount) {
                    $this->cooldownAccount($account, $responses[$retry[0] ?? $pending[0]] ?? null);
                    $pending = $retry;
                    usleep(500_000);
                    continue;
                }

                $pending = [];
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
                $price = $this->requestPrice($hashName);
                if ($this->shouldCachePrice($price)) {
                    Cache::put($this->cacheKey($hashName), $this->withFetchedAt($price), $cacheTtl);
                }
                $results[$hashName] = $price;
                usleep(max($delayMs, 1200) * 1000);
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
    private function requestPrice(string $marketHashName): array
    {
        $tried = 0;
        $maxTries = max(1, count(Buff163AccountPool::accounts()));

        while ($tried < $maxTries) {
            $account = $this->nextAvailableAccount();
            if ($account === null) {
                break;
            }

            $response = Http::timeout(15)
                ->withHeaders(Buff163AccountPool::headers($account))
                ->get('https://buff.163.com/api/market/goods', $this->queryFor($marketHashName));

            $price = $this->parsePriceResponse($response, $marketHashName);
            if ($this->shouldRotateAccount($response, $price)) {
                $this->cooldownAccount($account, $response);
                $tried++;
                usleep(500_000);
                continue;
            }

            return $price;
        }

        return $this->rateLimitedPrice('Hết acc Buff khả dụng — thử lại sau vài phút');
    }

    /**
     * @return array{game: string, page_num: int, search: string, tab: string}
     */
    private function queryFor(string $marketHashName): array
    {
        return [
            'game' => 'csgo',
            'page_num' => 1,
            'search' => $marketHashName,
            'tab' => 'selling',
        ];
    }

    /**
     * @return array{label: string, session: string, csrf: string|null}|null
     */
    private function nextAvailableAccount(): ?array
    {
        return Buff163AccountPool::available()[0] ?? null;
    }

    private function cooldownAccount(array $account, ?Response $response): void
    {
        Buff163AccountPool::markCooldown(
            $account,
            Buff163AccountPool::cooldownSecondsForResponse($response),
            $response instanceof Response ? $response->status() : null
        );
    }

    private function shouldRotateAccount(?Response $response, ?array $price = null): bool
    {
        if ($response instanceof Response && in_array($response->status(), [403, 429], true)) {
            return true;
        }

        return $price !== null && $this->isRateLimited($price);
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
    private function rateLimitedPrice(string $message): array
    {
        return [
            'goods_id' => null,
            'sell_min_price' => null,
            'sell_num' => null,
            'buff_url' => null,
            'error' => $message,
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
                403 => 'Buff chặn truy cập (403) — kiểm tra cookie session acc Buff',
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

        return str_contains($error, '429') || str_contains($error, 'Hết acc Buff');
    }

    private function cacheKey(string $marketHashName): string
    {
        return 'buff_price:'.md5($marketHashName);
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
