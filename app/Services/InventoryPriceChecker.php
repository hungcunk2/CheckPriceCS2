<?php

namespace App\Services;

use RuntimeException;

class InventoryPriceChecker
{
    public function __construct(
        private SteamInventoryService $steam,
        private Buff163Service $buff,
        private SteamProfileService $steamProfile,
    ) {}

    /**
     * @return array{
     *   steam_id: string,
     *   url: string,
     *   label: string,
     *   item_count: int,
     *   held_count: int,
     *   total_cny: float,
     *   held_total_cny: float,
     *   total_vnd: float,
     *   items: list<array<string, mixed>>,
     *   held_items: list<array<string, mixed>>
     * }
     */
    public function checkUrl(string $url, ?string $label = null): array
    {
        $parsed = $this->steam->parseInventoryUrl($url);
        $steamId = $parsed['steam_id'];
        $profile = $this->steamProfile->fetchProfile($steamId);
        $steamItems = $this->steam->fetchItems($steamId);
        $heldSteamItems = $this->steam->fetchHeldItems($steamId);

        if ($steamItems === [] && $heldSteamItems === []) {
            throw new RuntimeException('Kho không có skin tradable hoặc đang hold có thể định giá.');
        }

        $hashNames = array_values(array_unique(array_merge(
            array_column($steamItems, 'market_hash_name'),
            array_column($heldSteamItems, 'market_hash_name'),
        )));
        $buffPrices = $this->buff->getPricesForHashNames($hashNames);

        $rows = $this->buildItemRows($steamItems, $buffPrices);
        $heldRows = $this->buildItemRows($heldSteamItems, $buffPrices, onHold: true);

        usort($rows, fn ($a, $b) => ($b['line_total_cny'] ?? 0) <=> ($a['line_total_cny'] ?? 0));
        usort($heldRows, fn ($a, $b) => ($a['trade_unlock_at'] ?? '') <=> ($b['trade_unlock_at'] ?? ''));

        $totalCny = array_sum(array_map(fn ($r) => $r['line_total_cny'] ?? 0, $rows));
        $heldTotalCny = array_sum(array_map(fn ($r) => $r['line_total_cny'] ?? 0, $heldRows));
        $pricedCount = collect($rows)->whereNotNull('buff_price_cny')->count();
        $failedCount = count($rows) - $pricedCount;

        return [
            'steam_id' => $steamId,
            'url' => $parsed['url'],
            'label' => $label ?? $parsed['label'],
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'item_count' => count($rows),
            'held_count' => count($heldRows),
            'priced_count' => $pricedCount,
            'failed_count' => $failedCount,
            'buff_configured' => (bool) config('cs2price.buff_session'),
            'total_cny' => round($totalCny, 2),
            'held_total_cny' => round($heldTotalCny, 2),
            'total_vnd' => $this->buff->cnyToVnd($totalCny) ?? 0,
            'total_usd' => $this->buff->cnyToUsd($totalCny) ?? 0,
            'items' => $rows,
            'held_items' => $heldRows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @param  array<string, array<string, mixed>>  $buffPrices
     * @return list<array<string, mixed>>
     */
    private function buildItemRows(array $steamItems, array $buffPrices, bool $onHold = false): array
    {
        $rows = [];

        foreach ($steamItems as $item) {
            $hash = $item['market_hash_name'];
            $buff = $buffPrices[$hash] ?? null;
            $priceCny = $buff['sell_min_price'] ?? null;
            $amount = $item['amount'];
            $lineCny = $priceCny !== null ? $priceCny * $amount : null;

            $row = [
                'assetid' => $item['assetid'],
                'name' => $item['name'],
                'market_hash_name' => $hash,
                'icon_url' => $item['icon_url'],
                'tradable' => $item['tradable'],
                'on_hold' => $onHold,
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
            ];

            if (! empty($item['trade_unlock_at'])) {
                $row['trade_unlock_at'] = $item['trade_unlock_at'];
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
