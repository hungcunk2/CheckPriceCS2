<?php

namespace App\Services;

use App\Support\Cs2CapApiPool;
use App\Support\Cs2CapHttp;

/**
 * CS2Cap aggregator — Buff theo CNY, Empire theo USD (hai request / skin vì mỗi call chỉ một currency).
 */
class Cs2CapService
{
    /**
     * Buff giá CNY từ CS2Cap (dùng provider=buff163). Hỗ trợ phase nếu item có.
     *
     * @param  list<array<string, mixed>>  $steamItems  items có market_hash_name + phase|null
     * @return array<string, array{sell_min_price: float|null, sell_num: int|null, buff_url: string|null, error: string|null}>
     */
    /**
     * Empire tham khảo USD (guest) — provider csgoempire, có phase.
     *
     * @param  list<array<string, mixed>>  $steamItems
     * @return array<string, array{empire_price_usd: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     */
    public function getEmpireUsdPricesForSteamItems(array $steamItems): array
    {
        $currency = (string) config('cs2price.cs2cap_empire_currency', 'USD');
        $results = [];

        foreach ($steamItems as $item) {
            $hash = trim((string) ($item['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }
            $results[$hash] = $this->emptyEmpireRow();
        }

        foreach ($steamItems as $item) {
            $hash = trim((string) ($item['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }
            $phase = $item['phase'] ?? null;
            $row = $this->fetchQuoteWithPool($hash, 'csgoempire', $currency, $phase);
            $results[$hash] = [
                'empire_price_usd' => $row['amount'] ?? null,
                'listing_count' => $row['quantity'] ?? null,
                'empire_url' => $row['url'] ?? null,
                'error' => $row['error'] ?? null,
            ];
        }

        return $results;
    }

    public function getBuffPricesForSteamItems(array $steamItems): array
    {
        $currency = (string) config('cs2price.cs2cap_buff_currency', 'CNY');

        $results = [];
        foreach ($steamItems as $item) {
            $hash = trim((string) ($item['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }
            $results[$hash] = [
                'sell_min_price' => null,
                'sell_num' => null,
                'buff_url' => null,
                'error' => null,
            ];
        }

        foreach ($steamItems as $item) {
            $hash = trim((string) ($item['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }

            $phase = $item['phase'] ?? null;
            $row = $this->fetchQuoteWithPool($hash, 'buff163', $currency, $phase);
            $results[$hash] = [
                'sell_min_price' => $row['amount'] ?? null,
                'sell_num' => $row['quantity'] ?? null,
                'buff_url' => $row['url'] ?? null,
                'error' => $row['error'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * @return array{
     *   buff: array<string, array{sell_min_price: float|null, sell_num: int|null, buff_url: string|null, error: string|null}>,
     *   empire: array<string, array{empire_price_usd: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     * }
     */
    public function getBuffCnyAndEmpireUsd(array $marketHashNames): array
    {
        $unique = array_values(array_unique(array_filter($marketHashNames)));
        $buff = [];
        $empire = [];

        if ($unique === [] || ! $this->isConfigured()) {
            return ['buff' => $buff, 'empire' => $empire];
        }

        $buffCurrency = (string) config('cs2price.cs2cap_buff_currency', 'CNY');
        $empireCurrency = (string) config('cs2price.cs2cap_empire_currency', 'USD');

        foreach ($unique as $hashName) {
            $buff[$hashName] = $this->emptyBuffRow();
            $empire[$hashName] = $this->emptyEmpireRow();
        }

        foreach ($unique as $hashName) {
            $buffRow = $this->fetchQuote($hashName, 'buff163', $buffCurrency);
            if ($buffRow !== null) {
                $buff[$hashName] = [
                    'sell_min_price' => $buffRow['amount'],
                    'sell_num' => $buffRow['quantity'],
                    'buff_url' => $buffRow['url'],
                    'error' => $buffRow['error'],
                ];
            }

            usleep(50_000);

            $empireRow = $this->fetchQuote($hashName, 'csgoempire', $empireCurrency);
            if ($empireRow !== null) {
                $empire[$hashName] = [
                    'empire_price_usd' => $empireRow['amount'],
                    'listing_count' => $empireRow['quantity'],
                    'empire_url' => $empireRow['url'],
                    'error' => $empireRow['error'],
                ];
            }

            usleep(50_000);
        }

        return ['buff' => $buff, 'empire' => $empire];
    }

    public function isConfigured(): bool
    {
        return filter_var(config('cs2price.cs2cap_enabled', false), FILTER_VALIDATE_BOOL)
            && Cs2CapApiPool::isConfigured();
    }

    /**
     * @return array{amount: float|null, quantity: int|null, url: string|null, error: string|null}|null
     */
    private function fetchQuote(string $marketHashName, string $provider, string $currency): ?array
    {
        $result = $this->fetchQuoteWithPool($marketHashName, $provider, $currency, null);

        return $result;
    }

    private function minorToDecimal(?int $minor, string $currency): ?float
    {
        if ($minor === null) {
            return null;
        }

        // CS2Cap: fiat thường chia 100; VND thường là đồng nguyên (không lẻ).
        $divisor = $currency === 'VND' ? 1 : 100;

        return round($minor / $divisor, $currency === 'VND' ? 0 : 2);
    }

    /**
     * @return array{sell_min_price: null, sell_num: null, buff_url: null, error: null}
     */
    private function emptyBuffRow(): array
    {
        return [
            'sell_min_price' => null,
            'sell_num' => null,
            'buff_url' => null,
            'error' => null,
        ];
    }

    /**
     * @return array{empire_price_usd: null, listing_count: null, empire_url: null, error: null}
     */
    private function emptyEmpireRow(): array
    {
        return [
            'empire_price_usd' => null,
            'listing_count' => null,
            'empire_url' => null,
            'error' => null,
        ];
    }

    /**
     * Gọi CS2Cap với pool key; cooldown nếu 429.
     *
     * @return array{amount: float|null, quantity: int|null, url: string|null, error: string|null}
     */
    private function fetchQuoteWithPool(string $marketHashName, string $provider, string $currency, ?string $phase = null): array
    {
        if (! $this->isConfigured()) {
            return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'CS2Cap chưa cấu hình'];
        }

        $account = Cs2CapApiPool::next();
        if ($account === null) {
            return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'CS2Cap hết quota tháng hoặc chưa cấu hình key'];
        }

        $base = Cs2CapHttp::baseUrl();
        $query = [
            'market_hash_name' => $marketHashName,
            'providers' => $provider,
            'currency' => strtoupper($currency),
            'limit' => 5,
        ];
        if (is_string($phase) && $phase !== '') {
            $query['phase'] = $phase;
        }

        $response = Cs2CapHttp::client($account['api_key'], 25)
            ->get("{$base}/prices", $query);

        Cs2CapApiPool::handleResponse($account['label'], $response);

        if ($response->status() === 429) {
            return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'CS2Cap rate limited'];
        }

        if ($response->status() === 401) {
            return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'CS2Cap key invalid'];
        }

        if (! $response->successful()) {
            return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'CS2Cap HTTP '.$response->status()];
        }

        $items = $response->json('items') ?? [];
        foreach ($items as $row) {
            if (($row['provider'] ?? '') !== $provider) {
                continue;
            }

            $minor = isset($row['lowest_ask']) ? (int) $row['lowest_ask'] : null;

            return [
                'amount' => $this->minorToDecimal($minor, strtoupper($currency)),
                'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                'url' => $row['url'] ?? $row['link'] ?? null,
                'error' => $minor === null ? 'Không có listing' : null,
            ];
        }

        return ['amount' => null, 'quantity' => null, 'url' => null, 'error' => 'Không có giá trên '.$provider];
    }
}
