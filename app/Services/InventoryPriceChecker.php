<?php

namespace App\Services;

use App\Support\Buff163AccountPool;
use App\Support\Currency;
use App\Support\InventoryItemFilter;
use App\Support\PricingTier;
use App\Support\SellVenueCompare;
use RuntimeException;

class InventoryPriceChecker
{
    public function __construct(
        private InventoryFetchService $inventoryFetch,
        private Buff163Service $buff,
        private CsgoEmpireService $empire,
        private ItemPriceCacheStore $dbPriceCache,
    ) {}

    /**
     * @return array{
     *   steam_id: string,
     *   url: string,
     *   label: string,
     *   item_count: int,
     *   total_cny: float,
     *   total_vnd: float,
     *   total_empire_cny: float,
     *   items: list<array<string, mixed>>
     * }
     */
    /**
     * @param  'sync'|'admin'|'http'|'guest'  $empireMode
     */
    public function checkUrl(string $url, ?string $label = null, bool $refreshSteam = false, string $empireMode = 'guest'): array
    {
        $parsed = app(SteamInventoryService::class)->parseInventoryUrl($url);
        $bundle = $this->fetchSteamBundle($parsed['steam_id'], $refreshSteam);

        return $this->finalizeCheckResult($parsed, $bundle, $label, $empireMode);
    }

    /**
     * Bước 1 trang chủ: chỉ lấy danh sách skin (nhanh).
     *
     * @return array{parsed: array<string, string>, bundle: array<string, mixed>}
     */
    public function fetchSteamBundleForUrl(string $url, bool $refreshSteam = false): array
    {
        $parsed = app(SteamInventoryService::class)->parseInventoryUrl($url);
        $bundle = $this->fetchSteamBundle($parsed['steam_id'], $refreshSteam);

        return ['parsed' => $parsed, 'bundle' => $bundle];
    }

    /**
     * Bước 2: giá Buff + Empire cho một nhóm hash (guest tra từng lô).
     *
     * @param  list<array<string, mixed>>  $steamItems
     * @param  list<string>|null  $onlyHashes
     * @return list<array<string, mixed>>
     */
    public function priceSteamItems(array $steamItems, ?PricingTier $tier = null, ?array $onlyHashes = null): array
    {
        $tier ??= PricingTier::current();

        if ($onlyHashes !== null) {
            $wanted = array_fill_keys($onlyHashes, true);
            $steamItems = array_values(array_filter(
                $steamItems,
                fn (array $item) => isset($wanted[$item['market_hash_name'] ?? ''])
            ));
        }

        if ($steamItems === []) {
            return [];
        }

        $steamItems = $this->steamItemsWorthPricing($steamItems);

        if ($steamItems === []) {
            return [];
        }

        $hashNames = array_values(array_unique(array_column($steamItems, 'market_hash_name')));

        $buffPrices = $this->buffPricesForItems($steamItems, $hashNames, $tier);
        $empirePrices = $this->empirePricesForItems($steamItems, $tier);

        return $this->buildItemRows($steamItems, $buffPrices, $empirePrices, $tier);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSteamBundle(string $steamId, bool $refreshSteam): array
    {
        $bundle = $this->inventoryFetch->fetchBundle($steamId, $refreshSteam);
        if (($bundle['items'] ?? []) === []) {
            throw new RuntimeException('Kho không có skin tradable có thể định giá.');
        }

        return $bundle;
    }

    /**
     * @param  array<string, string>  $parsed
     * @param  array<string, mixed>  $bundle
     */
    private function finalizeCheckResult(array $parsed, array $bundle, ?string $label, string $empireMode): array
    {
        $tier = match ($empireMode) {
            'admin', 'sync' => PricingTier::Admin,
            'member' => PricingTier::Member,
            default => PricingTier::current(),
        };

        $steamItems = $this->steamItemsWorthPricing($bundle['items']);
        if ($steamItems === []) {
            throw new RuntimeException('Kho không có skin tradable đủ giá trị (≥ $'.InventoryItemFilter::minUsdUnitValue().').');
        }

        $hashNames = array_values(array_unique(array_column($steamItems, 'market_hash_name')));
        $buffPrices = $this->buffPricesForItems($steamItems, $hashNames, $tier);
        $empirePrices = $this->empirePricesForItems($steamItems, $tier);

        $rows = $this->buildItemRows($steamItems, $buffPrices, $empirePrices, $tier);

        return $this->summarizeResult($parsed, $bundle, $label, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  array<string, array<string, mixed>>  $buffPrices
     * @param  array<string, array<string, mixed>>  $empirePrices
     * @return list<array<string, mixed>>
     */
    private function buildItemRows(array $steamItems, array $buffPrices, array $empirePrices, PricingTier $tier): array
    {
        $rows = [];

        foreach ($steamItems as $item) {
            $hash = $item['market_hash_name'];
            $buff = $buffPrices[$hash] ?? null;
            $empireKey = $this->empireQueryNameForItem($item);
            $empireRow = $empirePrices[$empireKey] ?? ($empirePrices[$hash] ?? null);
            $priceCny = $buff['sell_min_price'] ?? null;
            $amount = $item['amount'];
            $lineCny = $priceCny !== null ? $priceCny * $amount : null;

            $empireCoins = $empireRow['market_value_coins'] ?? null;
            $empireUsdFromCs2cap = $empireRow['empire_price_usd'] ?? null;
            if ($empireCoins !== null) {
                $empireCny = $this->empire->coinsToCny($empireCoins);
                $empireUsd = $this->empire->coinsToUsd($empireCoins);
            } elseif ($empireUsdFromCs2cap !== null) {
                $empireUsd = $empireUsdFromCs2cap;
                $empireCny = Currency::usdToCny($empireUsd);
            } else {
                $empireCny = null;
                $empireUsd = null;
            }
            $lineEmpireCny = $empireCny !== null ? $empireCny * $amount : null;

            $rows[] = [
                'assetid' => $item['assetid'],
                'name' => $item['name'],
                'market_hash_name' => $hash,
                'icon_url' => $item['icon_url'],
                'tradable' => $item['tradable'],
                'amount' => $amount,
                'buff_price_cny' => $priceCny,
                'buff_price_vnd' => $this->buff->cnyToVnd($priceCny),
                'buff_price_usd' => $this->buff->cnyToUsd($priceCny),
                'line_total_cny' => $lineCny,
                'line_total_vnd' => $lineCny !== null ? $this->buff->cnyToVnd($lineCny) : null,
                'line_total_usd' => $lineCny !== null ? $this->buff->cnyToUsd($lineCny) : null,
                'sell_num' => $buff['sell_num'] ?? null,
                'buff_url' => $buff['buff_url'] ?? null,
                'buff_error' => $buff['error'] ?? null,
                'empire_price_coins' => $empireCoins,
                'empire_price_usd' => $empireUsd,
                'empire_price_cny' => $empireCny,
                'empire_price_vnd' => $this->empire->coinsToVnd($empireCoins),
                'empire_listing_count' => $empireRow['listing_count'] ?? null,
                'empire_url' => $empireRow['empire_url'] ?? null,
                'empire_error' => $empireRow['error'] ?? null,
                'empire_price_source' => $tier->usesCs2CapEmpireOnly() ? 'cs2cap_usd' : 'empire_api',
                'line_total_empire_cny' => $lineEmpireCny,
                'best_sell_venue' => SellVenueCompare::bestVenue($priceCny, $empireCny),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @return list<array<string, mixed>>
     */
    public function steamItemsWorthPricing(array $steamItems): array
    {
        if ($steamItems === []) {
            return [];
        }

        $keys = array_values(array_map(function (array $item) {
            return [
                'hash' => (string) ($item['market_hash_name'] ?? ''),
                'phase' => isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null,
            ];
        }, $steamItems));

        $cachedByKey = $this->dbPriceCache->getFresh('buff', $keys);

        return InventoryItemFilter::filterSteamItemsExcludingKnownCheap($steamItems, $cachedByKey);
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  list<string>  $hashNames
     * @return array<string, array<string, mixed>>
     */
    private function buffPricesForItems(array $steamItems, array $hashNames, PricingTier $tier): array
    {
        // DB cache key: hash + phase (phase quan trọng cho Doppler/Gamma).
        $keys = array_values(array_map(function (array $item) {
            return [
                'hash' => (string) ($item['market_hash_name'] ?? ''),
                'phase' => isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null,
            ];
        }, $steamItems));

        $cachedByKey = $this->dbPriceCache->getFresh('buff', $keys);

        $buffPrices = [];
        $missingSteamItems = [];

        foreach ($steamItems as $item) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            $key = $this->dbPriceCache->key($hash, $phase);

            if (isset($cachedByKey[$key]) && ($cachedByKey[$key]['sell_min_price'] ?? null) !== null) {
                $buffPrices[$hash] = $cachedByKey[$key];
            } else {
                $missingSteamItems[] = $item;
            }
        }

        if ($missingSteamItems === []) {
            return $buffPrices;
        }

        $cs2cap = app(Cs2CapService::class);
        $useCs2cap = $tier->usesCs2CapEmpireOnly()
            || (config('cs2price.cs2cap_use_buff', false) && $cs2cap->isConfigured());

        if ($useCs2cap && $cs2cap->isConfigured()) {
            $fetched = $cs2cap->getBuffPricesForSteamItems($missingSteamItems);
            $this->dbPriceCache->putMany('buff', $this->mapBuffPayloadByKey($missingSteamItems, $fetched), 'CNY');

            return $buffPrices + $fetched;
        }

        if ($tier->usesCs2CapEmpireOnly()) {
            foreach ($missingSteamItems as $item) {
                $hash = (string) ($item['market_hash_name'] ?? '');
                if ($hash !== '') {
                    $buffPrices[$hash] = ['sell_min_price' => null, 'sell_num' => null, 'buff_url' => null, 'error' => 'CS2Cap chưa cấu hình'];
                }
            }

            return $buffPrices;
        }

        $missingHashes = array_values(array_unique(array_column($missingSteamItems, 'market_hash_name')));
        $fetched = $this->buff->getPricesForHashNames($missingHashes);
        // Buff163Service không biết phase; cache theo phase nếu có, còn lại phase null.
        $this->dbPriceCache->putMany('buff', $this->mapBuffPayloadByKey($missingSteamItems, $fetched), 'CNY');

        return $buffPrices + $fetched;
    }

    /**
     * @param  list<string>  $hashNames
     * @return array<string, array<string, mixed>>
     */
    private function empirePricesForItems(array $steamItems, PricingTier $tier): array
    {
        if ($tier->usesCs2CapEmpireOnly()) {
            return $this->empireCs2CapUsdForItems($steamItems);
        }

        if (! $this->empire->isEnabled()) {
            return [];
        }

        $empireMode = $tier->empireMode();

        $queryNames = [];
        $keys = [];
        foreach ($steamItems as $item) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            $keys[] = ['hash' => $hash, 'phase' => $phase];
            $queryNames[] = $this->empireQueryName($hash, $phase);
        }
        $queryNames = array_values(array_unique($queryNames));
        $cachedByKey = $this->dbPriceCache->getFresh('empire', $keys);

        $result = [];
        $missing = [];
        foreach ($queryNames as $q) {
            [$hash, $phase] = $this->splitEmpireQueryName($q);
            $key = $this->dbPriceCache->key($hash, $phase);
            if (isset($cachedByKey[$key]) && ($cachedByKey[$key]['market_value_coins'] ?? null) !== null) {
                $result[$q] = $cachedByKey[$key];
            } else {
                $missing[] = $q;
            }
        }

        if ($missing === []) {
            return $result;
        }

        $fetched = $this->empire->getPricesForHashNames($missing, $empireMode);

        $payloadByKey = [];
        foreach ($missing as $q) {
            if (! isset($fetched[$q])) {
                continue;
            }
            [$hash, $phase] = $this->splitEmpireQueryName($q);
            $row = $fetched[$q];
            // Không lưu DB lỗi "hết key" — lần quét sau (hoặc retry trong request) thử lại.
            if ($this->empire->isPoolExhaustedError($row)) {
                continue;
            }
            $payloadByKey[$this->dbPriceCache->key($hash, $phase)] = $row;
        }
        if ($payloadByKey !== []) {
            $this->dbPriceCache->putMany('empire', $payloadByKey, 'COINS');
        }

        return $result + $fetched;
    }

    /**
     * Guest: Empire USD qua CS2Cap (không coin).
     *
     * @return array<string, array<string, mixed>>
     */
    private function empireCs2CapUsdForItems(array $steamItems): array
    {
        $cs2cap = app(Cs2CapService::class);
        if (! $cs2cap->isConfigured()) {
            return [];
        }

        $keys = [];
        foreach ($steamItems as $item) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            $keys[] = ['hash' => $hash, 'phase' => $phase];
        }

        $cachedByKey = $this->dbPriceCache->getFresh('empire_cs2cap', $keys);
        $missingItems = [];
        $result = [];

        foreach ($steamItems as $item) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            $key = $this->dbPriceCache->key($hash, $phase);
            if (isset($cachedByKey[$key]) && ($cachedByKey[$key]['empire_price_usd'] ?? null) !== null) {
                $result[$hash] = $cachedByKey[$key];
            } else {
                $missingItems[] = $item;
            }
        }

        if ($missingItems !== []) {
            $fetched = $cs2cap->getEmpireUsdPricesForSteamItems($missingItems);
            $payloadByKey = [];
            foreach ($missingItems as $item) {
                $hash = (string) ($item['market_hash_name'] ?? '');
                $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
                if ($hash === '' || ! isset($fetched[$hash])) {
                    continue;
                }
                $payloadByKey[$this->dbPriceCache->key($hash, $phase)] = $fetched[$hash];
                $result[$hash] = $fetched[$hash];
            }
            $this->dbPriceCache->putMany('empire_cs2cap', $payloadByKey, 'USD');
        }

        return $result;
    }

    /**
     * Empire cần market_name đúng (có phase): "{hash} - Phase 4"
     */
    private function empireQueryNameForItem(array $item): string
    {
        $hash = (string) ($item['market_hash_name'] ?? '');
        $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;

        return $this->empireQueryName($hash, $phase);
    }

    private function empireQueryName(string $marketHashName, ?string $phase): string
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '' || ! is_string($phase) || trim($phase) === '') {
            return $marketHashName;
        }

        $phase = $this->normalizeEmpirePhase($phase);
        if ($phase === null) {
            return $marketHashName;
        }

        return $marketHashName.' - '.$phase;
    }

    private function normalizeEmpirePhase(string $phase): ?string
    {
        $phase = trim($phase);
        if ($phase === '') {
            return null;
        }

        // CS2Cap trả dạng "Phase 4" (đã đúng) hoặc đôi khi chỉ là "4".
        if (preg_match('/^phase\s*\d+$/i', $phase)) {
            return preg_replace('/^phase\s*/i', 'Phase ', $phase);
        }
        if (preg_match('/^\d+$/', $phase)) {
            return 'Phase '.$phase;
        }

        // Gem names (để mở rộng tương lai).
        $known = ['Ruby', 'Sapphire', 'Black Pearl', 'Emerald'];
        foreach ($known as $k) {
            if (strcasecmp($phase, $k) === 0) {
                return $k;
            }
        }

        // Đã là string khác: dùng nguyên văn.
        return $phase;
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitEmpireQueryName(string $queryName): array
    {
        $queryName = (string) $queryName;
        $pos = strrpos($queryName, ' - ');
        if ($pos === false) {
            return [$queryName, null];
        }

        $hash = substr($queryName, 0, $pos);
        $phase = substr($queryName, $pos + 3);

        return [trim($hash), trim($phase) !== '' ? trim($phase) : null];
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  array<string, array<string, mixed>>  $buffPrices
     * @return array<string, array<string, mixed>> map key => payload
     */
    private function mapBuffPayloadByKey(array $steamItems, array $buffPrices): array
    {
        $out = [];
        foreach ($steamItems as $item) {
            $hash = (string) ($item['market_hash_name'] ?? '');
            if ($hash === '') {
                continue;
            }
            $phase = isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null;
            if (! isset($buffPrices[$hash])) {
                continue;
            }
            $out[$this->dbPriceCache->key($hash, $phase)] = $buffPrices[$hash];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $parsed
     * @param  array<string, mixed>  $bundle
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeResult(array $parsed, array $bundle, ?string $label, array $rows): array
    {
        usort($rows, fn ($a, $b) => ($b['line_total_cny'] ?? 0) <=> ($a['line_total_cny'] ?? 0));

        $totalCny = (float) collect($rows)->sum(fn ($row) => (float) ($row['line_total_cny'] ?? 0));
        $totalEmpireCny = (float) collect($rows)->sum(fn ($row) => (float) ($row['line_total_empire_cny'] ?? 0));
        $empirePricedCount = collect($rows)->whereNotNull('empire_price_coins')->count();
        $pricedCount = collect($rows)->whereNotNull('buff_price_cny')->count();

        return [
            'steam_id' => $parsed['steam_id'],
            'url' => $parsed['url'],
            'label' => $label ?? $parsed['label'],
            'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
            'steam_avatar_url' => $bundle['steam_avatar_url'] ?? null,
            'item_count' => count($rows),
            'priced_count' => $pricedCount,
            'failed_count' => count($rows) - $pricedCount,
            'empire_priced_count' => $empirePricedCount,
            'empire_configured' => $this->empire->isConfigured(),
            'empire_enabled' => $this->empire->isEnabled(),
            'buff_configured' => Buff163AccountPool::isConfigured(),
            'sell_compare_buff_wins' => collect($rows)->where('best_sell_venue', 'buff')->count(),
            'sell_compare_empire_wins' => collect($rows)->where('best_sell_venue', 'empire')->count(),
            'inventory_source' => $bundle['inventory_source'] ?? null,
            'inventory_fallback_message' => $bundle['inventory_fallback_message'] ?? null,
            'total_cny' => round($totalCny, 2),
            'total_vnd' => $this->buff->cnyToVnd($totalCny) ?? 0,
            'total_usd' => $this->buff->cnyToUsd($totalCny) ?? 0,
            'total_empire_cny' => round($totalEmpireCny, 2),
            'total_empire_vnd' => $this->buff->cnyToVnd($totalEmpireCny) ?? 0,
            'total_empire_usd' => $this->buff->cnyToUsd($totalEmpireCny) ?? 0,
            'items' => $rows,
        ];
    }
}
