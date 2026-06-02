<?php

namespace App\Services;

use App\Support\Buff163AccountPool;
use App\Support\SellVenueCompare;
use RuntimeException;

class InventoryPriceChecker
{
    public function __construct(
        private InventoryFetchService $inventoryFetch,
        private Buff163Service $buff,
        private CsgoEmpireService $empire,
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
    public function priceSteamItems(array $steamItems, string $empireMode = 'guest', ?array $onlyHashes = null): array
    {
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

        $hashNames = array_column($steamItems, 'market_hash_name');
        $buffPrices = $this->buff->getPricesForHashNames($hashNames);
        $empirePrices = $this->empire->isEnabled()
            ? $this->empire->getPricesForHashNames($hashNames, $empireMode)
            : [];

        return $this->buildItemRows($steamItems, $buffPrices, $empirePrices);
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
        $steamItems = $bundle['items'];
        $hashNames = array_column($steamItems, 'market_hash_name');
        $buffPrices = $this->buffPricesForItems($steamItems, $hashNames);
        $empirePrices = $this->empire->isEnabled()
            ? $this->empire->getPricesForHashNames($hashNames, $empireMode)
            : [];

        $rows = $this->buildItemRows($steamItems, $buffPrices, $empirePrices);

        return $this->summarizeResult($parsed, $bundle, $label, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  array<string, array<string, mixed>>  $buffPrices
     * @param  array<string, array<string, mixed>>  $empirePrices
     * @return list<array<string, mixed>>
     */
    private function buildItemRows(array $steamItems, array $buffPrices, array $empirePrices): array
    {
        $rows = [];

        foreach ($steamItems as $item) {
            $hash = $item['market_hash_name'];
            $buff = $buffPrices[$hash] ?? null;
            $empireRow = $empirePrices[$hash] ?? null;
            $priceCny = $buff['sell_min_price'] ?? null;
            $amount = $item['amount'];
            $lineCny = $priceCny !== null ? $priceCny * $amount : null;

            $empireCoins = $empireRow['market_value_coins'] ?? null;
            $empireCny = $this->empire->coinsToCny($empireCoins);
            $empireUsd = $this->empire->coinsToUsd($empireCoins);
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
                'line_total_empire_cny' => $lineEmpireCny,
                'best_sell_venue' => SellVenueCompare::bestVenue($priceCny, $empireCny),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  list<string>  $hashNames
     * @return array<string, array<string, mixed>>
     */
    private function buffPricesForItems(array $steamItems, array $hashNames): array
    {
        if (config('cs2price.cs2cap_use_buff', false)) {
            $cs2cap = app(\App\Services\Cs2CapService::class);
            if ($cs2cap->isConfigured()) {
                return $cs2cap->getBuffPricesForSteamItems($steamItems);
            }
        }

        return $this->buff->getPricesForHashNames($hashNames);
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
