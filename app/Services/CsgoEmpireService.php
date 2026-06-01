<?php

namespace App\Services;

use App\Support\Currency;
use App\Support\CsgoEmpireApiPool;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CsgoEmpireService
{
    private const API_BASE = 'https://csgoempire.com/api/v2/trading/items';

    private const BULK_INDEX_CACHE_KEY = 'empire_bulk_index:v1';

    /**
     * @return array<string, array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null
     * }>
     */
    /**
     * @param  'sync'|'admin'|'http'|'guest'  $mode  sync=cron, admin=⟳ 1 kho, http=giới hạn, guest=trang chủ
     */
    public function getPricesForHashNames(array $marketHashNames, string $mode = 'guest'): array
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

        $toFetch = $this->resolveViaBulkIndex($toFetch, $results, $mode);

        $searchLimit = match ($mode) {
            'sync', 'admin' => $this->adminSearchLimit(count($toFetch)),
            'http' => $this->httpSearchLimit(count($toFetch)),
            default => (int) config('cs2price.empire_max_fetches_per_check', 15),
        };
        $fetched = 0;
        $delayMs = $this->searchDelayMs($mode);

        foreach ($toFetch as $hashName) {
            if ($searchLimit > 0 && $fetched >= $searchLimit) {
                $results[$hashName] = $this->skippedPrice(
                    'Empire: bỏ qua (giới hạn '.$searchLimit.' item/lần — thử lại sau)'
                );
                continue;
            }

            if ($fetched > 0) {
                usleep($delayMs * 1000);
            }

            $price = $this->fetchPrice($hashName);
            $fetched++;

            $this->storePriceCache($hashName, $price);
            $results[$hashName] = $price;
        }

        return $results;
    }

    /**
     * Quét listing theo trang (không search) — nhanh hơn nhiều so với từng skin.
     *
     * @param  list<string>  $stillNeeded
     * @param  array<string, array<string, mixed>>  $results
     * @return list<string>
     */
    /**
     * @param  'sync'|'http'|'guest'  $fetchMode
     */
    private function resolveViaBulkIndex(array $stillNeeded, array &$results, string $fetchMode): array
    {
        if ($stillNeeded === []) {
            return [];
        }

        $configMode = (string) config('cs2price.empire_fetch_mode', 'auto');
        if ($configMode === 'search') {
            return $stillNeeded;
        }
        if ($configMode === 'auto' && $fetchMode === 'guest') {
            // Trang chủ: chỉ dùng bulk cache sẵn có, không quét nhiều trang.
            $index = Cache::get(self::BULK_INDEX_CACHE_KEY);
            if (! is_array($index)) {
                return $stillNeeded;
            }
        } else {
            $index = $this->getBulkListingIndex($stillNeeded, $fetchMode);
            if ($index === []) {
                return $stillNeeded;
            }
        }

        $remaining = [];
        foreach ($stillNeeded as $hashName) {
            $key = mb_strtolower($hashName);
            $hit = $index[$key] ?? null;
            if (! is_array($hit) || ($hit['market_value_coins'] ?? null) === null) {
                if ($configMode === 'paginate') {
                    $price = array_merge($this->notFoundPrice($hashName), ['error' => 'Không có listing trên Empire']);
                    $this->storePriceCache($hashName, $price);
                    $results[$hashName] = $price;
                } else {
                    $remaining[] = $hashName;
                }
                continue;
            }

            $price = [
                'market_value_coins' => $hit['market_value_coins'],
                'listing_count' => $hit['listing_count'] ?? null,
                'empire_url' => $hit['empire_url'] ?? $this->marketUrl($hashName),
                'error' => null,
            ];
            $this->storePriceCache($hashName, $price);
            $results[$hashName] = $price;
        }

        return $remaining;
    }

    /**
     * @return array{market_value_coins: null, listing_count: null, empire_url: string, error: null}
     */
    private function notFoundPrice(string $marketHashName): array
    {
        return [
            'market_value_coins' => null,
            'listing_count' => null,
            'empire_url' => $this->marketUrl($marketHashName),
            'error' => null,
        ];
    }

    /**
     * @param  list<string>  $wantedNames
     * @return array<string, array{market_value_coins: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     */
    /**
     * @param  'sync'|'http'|'guest'  $fetchMode
     */
    private function getBulkListingIndex(array $wantedNames, string $fetchMode = 'guest'): array
    {
        $ttl = max(60, (int) config('cs2price.empire_bulk_cache_seconds', 600));
        $wantedKeys = array_fill_keys(array_map(mb_strtolower(...), $wantedNames), true);

        $cached = Cache::get(self::BULK_INDEX_CACHE_KEY);
        if (is_array($cached) && ($cached['fetched_at'] ?? 0) > time() - $ttl) {
            $items = is_array($cached['items'] ?? null) ? $cached['items'] : [];

            return $wantedKeys === [] ? $items : $this->filterIndexForWanted($items, $wantedKeys);
        }

        $built = $this->buildBulkListingIndex($wantedKeys, $fetchMode);
        $merged = $built;
        if (is_array($cached) && is_array($cached['items'] ?? null)) {
            $merged = array_merge($cached['items'], $built);
        }
        Cache::put(self::BULK_INDEX_CACHE_KEY, [
            'fetched_at' => time(),
            'items' => $merged,
        ], $ttl);

        return $wantedKeys === [] ? $merged : $this->filterIndexForWanted($merged, $wantedKeys);
    }

    /**
     * @param  array<string, true>  $wantedKeys
     * @return array<string, array{market_value_coins: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     */
    /**
     * @param  'sync'|'http'|'guest'  $fetchMode
     */
    private function buildBulkListingIndex(array $wantedKeys, string $fetchMode = 'guest'): array
    {
        $index = [];
        $perPage = (int) config('cs2price.empire_bulk_per_page', 0);
        $hasKey = CsgoEmpireApiPool::isConfigured();
        if ($perPage <= 0) {
            $perPage = $hasKey ? 2500 : 200;
        }
        $perPage = min($perPage, $hasKey ? 2500 : 200);

        $maxPages = max(1, (int) config('cs2price.empire_bulk_max_pages', 25));
        $accounts = CsgoEmpireApiPool::available();
        if ($fetchMode === 'http') {
            $httpBase = max(1, (int) config('cs2price.empire_http_max_pages', 12));
            $keyCount = max(1, count($accounts));
            $maxPages = min($maxPages, $httpBase * min($keyCount, 10));
        } elseif ($fetchMode === 'admin') {
            $adminBase = max(1, (int) config('cs2price.empire_admin_max_pages', 15));
            $keyCount = max(1, count($accounts));
            $maxPages = min($maxPages, $adminBase * min($keyCount, 10));
        }
        $delayMs = max(500, (int) config('cs2price.empire_page_delay_ms', 550));
        $trackWanted = $wantedKeys !== [];
        $found = [];
        $parallel = filter_var(config('cs2price.empire_bulk_parallel', true), FILTER_VALIDATE_BOOL);

        if ($parallel && count($accounts) > 1) {
            return $this->buildBulkListingIndexParallel(
                $index,
                $wantedKeys,
                $found,
                $trackWanted,
                $perPage,
                $maxPages,
                $delayMs,
                $accounts
            );
        }

        for ($page = 1; $page <= $maxPages; $page++) {
            if ($page > 1) {
                usleep($delayMs * 1000);
            }

            $account = CsgoEmpireApiPool::next();
            if ($account === null) {
                break;
            }

            $response = Http::timeout(45)
                ->acceptJson()
                ->withHeaders(CsgoEmpireApiPool::headers($account))
                ->get(self::API_BASE, [
                    'per_page' => $perPage,
                    'page' => $page,
                    'order' => 'market_value',
                    'sort' => 'asc',
                    'auction' => 'no',
                ]);

            if ($response->status() === 429 || $response->status() === 403) {
                CsgoEmpireApiPool::markCooldown(
                    $account,
                    CsgoEmpireApiPool::cooldownSecondsForResponse($response),
                    $response->status()
                );
                continue;
            }

            if (! $this->ingestBulkPage($response, $index, $wantedKeys, $found, $trackWanted)) {
                break;
            }

            if ($trackWanted && count($found) >= count($wantedKeys)) {
                break;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, true>  $wantedKeys
     * @param  array<string, true>  $found
     * @param  list<array{label: string, api_key: string}>  $accounts
     * @return array<string, array<string, mixed>>
     */
    private function buildBulkListingIndexParallel(
        array $index,
        array $wantedKeys,
        array &$found,
        bool $trackWanted,
        int $perPage,
        int $maxPages,
        int $delayMs,
        array $accounts,
    ): array {
        $keyCount = count($accounts);
        $pages = range(1, $maxPages);

        foreach (array_chunk($pages, $keyCount) as $chunkIndex => $pageChunk) {
            if ($chunkIndex > 0) {
                usleep($delayMs * 1000);
            }

            $responses = Http::pool(function (Pool $pool) use ($pageChunk, $accounts, $perPage) {
                foreach ($pageChunk as $i => $page) {
                    $account = $accounts[$i % count($accounts)];
                    $pool->as((string) $page)
                        ->timeout(45)
                        ->acceptJson()
                        ->withHeaders(CsgoEmpireApiPool::headers($account))
                        ->get(self::API_BASE, [
                            'per_page' => $perPage,
                            'page' => $page,
                            'order' => 'market_value',
                            'sort' => 'asc',
                            'auction' => 'no',
                        ]);
                }
            });

            $stop = false;
            foreach ($pageChunk as $i => $page) {
                $response = $responses[(string) $page] ?? null;
                $account = $accounts[$i % count($accounts)];

                if ($response instanceof Response && in_array($response->status(), [429, 403], true)) {
                    CsgoEmpireApiPool::markCooldown(
                        $account,
                        CsgoEmpireApiPool::cooldownSecondsForResponse($response),
                        $response->status()
                    );
                    continue;
                }

                if (! $this->ingestBulkPage($response, $index, $wantedKeys, $found, $trackWanted)) {
                    $stop = true;
                }
            }

            if ($trackWanted && count($found) >= count($wantedKeys)) {
                break;
            }
            if ($stop) {
                break;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, true>  $wantedKeys
     * @param  array<string, true>  $found
     */
    private function ingestBulkPage(
        mixed $response,
        array &$index,
        array $wantedKeys,
        array &$found,
        bool $trackWanted,
    ): bool {
        if (! $response instanceof Response) {
            return false;
        }

        if (! $response->successful()) {
            Log::warning('csgoempire.bulk', [
                'status' => $response->status(),
                'error' => $this->httpErrorMessage($response),
            ]);

            return false;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return false;
        }

        $rows = $body['data'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['market_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $coins = $this->marketValueToCoins((float) ($row['market_value'] ?? 0));
            if ($coins <= 0) {
                continue;
            }

            if (! isset($index[$key]) || $coins < (float) $index[$key]['market_value_coins']) {
                $index[$key] = [
                    'market_value_coins' => round($coins, 2),
                    'listing_count' => 1,
                    'empire_url' => $this->marketUrl($name),
                    'error' => null,
                ];
            } else {
                $index[$key]['listing_count'] = (int) ($index[$key]['listing_count'] ?? 0) + 1;
            }

            if ($trackWanted && isset($wantedKeys[$key])) {
                $found[$key] = true;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, true>  $wantedKeys
     * @return array<string, array<string, mixed>>
     */
    private function filterIndexForWanted(array $index, array $wantedKeys): array
    {
        if ($wantedKeys === []) {
            return $index;
        }

        return array_intersect_key($index, $wantedKeys);
    }

    /**
     * @param  array{market_value_coins: float|null, listing_count: int|null, empire_url: string|null, error: string|null}  $price
     */
    private function storePriceCache(string $hashName, array $price): void
    {
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
    }

    public function isEnabled(): bool
    {
        return filter_var(config('cs2price.empire_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && CsgoEmpireApiPool::isConfigured();
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
     * Kiểm tra đúng một API key (không xoay pool).
     *
     * @param  array{label: string, api_key: string}  $account
     * @return array{
     *   market_value_coins: float|null,
     *   listing_count: int|null,
     *   empire_url: string|null,
     *   error: string|null,
     *   http_status: int|null
     * }
     */
    public function probeForAccount(array $account, string $marketHashName = 'AK-47 | Redline (Field-Tested)'): array
    {
        if (! $this->isEnabled()) {
            return array_merge($this->errorPrice('Empire tắt (EMPIRE_ENABLED=false)'), ['http_status' => null]);
        }

        if (trim((string) ($account['api_key'] ?? '')) === '') {
            return array_merge($this->errorPrice('API key trống'), ['http_status' => null]);
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(CsgoEmpireApiPool::headers($account))
                ->get(self::API_BASE, [
                    'per_page' => 50,
                    'page' => 1,
                    'search' => $marketHashName,
                    'order' => 'market_value',
                    'sort' => 'asc',
                    'auction' => 'no',
                ]);
        } catch (\Throwable $e) {
            return array_merge($this->errorPrice('Lỗi kết nối: '.$e->getMessage()), ['http_status' => null]);
        }

        if ($response->status() === 429 || $response->status() === 403) {
            CsgoEmpireApiPool::markCooldown(
                $account,
                CsgoEmpireApiPool::cooldownSecondsForResponse($response),
                $response->status()
            );
        } elseif ($response->status() === 401) {
            CsgoEmpireApiPool::markCooldown($account, 600, 401);
        }

        $price = $this->parseSearchResponse($response, $marketHashName);

        return array_merge($price, ['http_status' => $response->status()]);
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
        $tried = 0;
        $maxTries = max(1, count(CsgoEmpireApiPool::accounts()));

        while ($tried < $maxTries) {
            $account = CsgoEmpireApiPool::next();
            if ($account === null) {
                break;
            }

            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(CsgoEmpireApiPool::headers($account))
                ->get(self::API_BASE, [
                    'per_page' => 50,
                    'page' => 1,
                    'search' => $marketHashName,
                    'order' => 'market_value',
                    'sort' => 'asc',
                    'auction' => 'no',
                ]);

            if ($response->status() === 429 || $response->status() === 403) {
                CsgoEmpireApiPool::markCooldown(
                    $account,
                    CsgoEmpireApiPool::cooldownSecondsForResponse($response, $tried + 1),
                    $response->status()
                );
                $tried++;
                continue;
            }

            return $this->parseSearchResponse($response, $marketHashName);
        }

        return $this->errorPrice('Hết API key Empire khả dụng — thử lại sau');
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
    /**
     * Nút ⟳ admin: mỗi key ~10 search; 5 key → tối đa ~50 skin/lần.
     */
    private function httpSearchLimit(int $remainingCount): int
    {
        if ($remainingCount === 0) {
            return 0;
        }

        $cap = (int) config('cs2price.empire_http_max_searches', 0);
        if ($cap <= 0) {
            $perKey = max(1, (int) config('cs2price.empire_http_max_searches_per_key', 10));
            $keys = max(1, count(CsgoEmpireApiPool::available()));
            $cap = $keys * $perKey;
        }

        return min($remainingCount, $cap);
    }

    private function searchDelayMs(string $mode): int
    {
        $base = max(3200, (int) config('cs2price.empire_search_delay_ms', 3500));
        if (! in_array($mode, ['http', 'admin'], true)) {
            return $base;
        }

        $keys = max(1, count(CsgoEmpireApiPool::available()));

        return max(800, (int) floor($base / $keys));
    }

    /**
     * ⟳ admin / cron: mặc định search hết skin còn thiếu trong kho (0 = không giới hạn).
     */
    private function adminSearchLimit(int $remainingCount): int
    {
        $cap = (int) config('cs2price.empire_admin_max_searches', 0);
        if ($cap <= 0) {
            return 0;
        }

        return min($remainingCount, $cap);
    }

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
