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
    public function checkUrl(string $url, ?string $label = null, bool $refreshSteam = false): array
    {
        $parsed = app(SteamInventoryService::class)->parseInventoryUrl($url);
        $steamId = $parsed['steam_id'];

        $bundle = $this->inventoryFetch->fetchBundle($steamId, $refreshSteam);
        $steamItems = $bundle['items'];
        $profile = [
            'steam_persona_name' => $bundle['steam_persona_name'],
            'steam_avatar_url' => $bundle['steam_avatar_url'],
        ];

        if ($steamItems === []) {
            throw new RuntimeException('Kho không có skin tradable có thể định giá.');
        }

        $hashNames = array_column($steamItems, 'market_hash_name');
        $buffPrices = $this->buff->getPricesForHashNames($hashNames);
        $empirePrices = $this->empire->isEnabled()
            ? $this->empire->getPricesForHashNames($hashNames)
            : [];

        $rows = [];
        $totalCny = 0.0;
        $totalEmpireCny = 0.0;
        $empirePricedCount = 0;
        $buffWins = 0;
        $empireWins = 0;

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

            if ($lineCny !== null) {
                $totalCny += $lineCny;
            }
            if ($lineEmpireCny !== null) {
                $totalEmpireCny += $lineEmpireCny;
                $empirePricedCount++;
            }

            $bestVenue = SellVenueCompare::bestVenue($priceCny, $empireCny);
            if ($bestVenue === 'buff') {
                $buffWins++;
            } elseif ($bestVenue === 'empire') {
                $empireWins++;
            }

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
                'empire_listing_count' => $empireRow['listing_count'] ?? null,
                'empire_url' => $empireRow['empire_url'] ?? null,
                'empire_error' => $empireRow['error'] ?? null,
                'line_total_empire_cny' => $lineEmpireCny,
                'best_sell_venue' => $bestVenue,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['line_total_cny'] ?? 0) <=> ($a['line_total_cny'] ?? 0));

        $pricedCount = collect($rows)->whereNotNull('buff_price_cny')->count();
        $failedCount = count($rows) - $pricedCount;

        return [
            'steam_id' => $steamId,
            'url' => $parsed['url'],
            'label' => $label ?? $parsed['label'],
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'item_count' => count($rows),
            'priced_count' => $pricedCount,
            'failed_count' => $failedCount,
            'empire_priced_count' => $empirePricedCount,
            'empire_configured' => $this->empire->isConfigured(),
            'empire_enabled' => $this->empire->isEnabled(),
            'buff_configured' => Buff163AccountPool::isConfigured(),
            'sell_compare_buff_wins' => $buffWins,
            'sell_compare_empire_wins' => $empireWins,
            'inventory_source' => $bundle['inventory_source'],
            'inventory_fallback_message' => $bundle['inventory_fallback_message'],
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
