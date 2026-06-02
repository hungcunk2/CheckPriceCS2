<?php

/**
 * One-off probe — do not commit API keys.
 * Usage: set CS2CAP_OR_API_KEY env then: php scripts/probe-external-prices.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = getenv('PROBE_API_KEY') ?: ($argv[1] ?? '');
if ($apiKey === '') {
    fwrite(STDERR, "Set PROBE_API_KEY or pass key as argv[1]\n");
    exit(1);
}

$url = 'https://steamcommunity.com/id/hamyngungoc/inventory/';
$steam = app(App\Services\SteamInventoryService::class);
$fetch = app(App\Services\InventoryFetchService::class);

try {
    $parsed = $steam->parseInventoryUrl($url);
    echo "Steam ID: {$parsed['steam_id']}\n";
    $bundle = $fetch->fetchBundle($parsed['steam_id'], false);
    $items = $bundle['items'] ?? [];
    echo 'Tradable items: '.count($items)."\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Inventory error: '.$e->getMessage()."\n");
    exit(1);
}

$names = array_values(array_unique(array_column($items, 'market_hash_name')));
$sample = array_slice($names, 0, 5);

function httpJson(string $method, string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    $hdrs = [];
    foreach ($headers as $k => $v) {
        $hdrs[] = is_int($k) ? $v : "{$k}: {$v}";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_ENCODING => '',
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => json_decode((string) $raw, true), 'raw' => $raw];
}

echo "=== CS2Cap (Bearer) — buff163 + csgoempire, currency=CNY ===\n";
$base = 'https://api.cs2c.app/v1';
foreach ($sample as $name) {
    $q = http_build_query([
        'market_hash_name' => $name,
        'providers' => ['buff163', 'csgoempire'],
        'currency' => 'CNY',
        'limit' => 10,
    ]);
    // providers repeat: build manually
    $queryUrl = $base.'/prices?'.http_build_query([
        'market_hash_name' => $name,
        'currency' => 'CNY',
        'limit' => 10,
    ]).'&'.implode('&', ['providers=buff163', 'providers=csgoempire']);
    $r = httpJson('GET', $queryUrl, [
        'Authorization' => 'Bearer '.$apiKey,
        'Accept' => 'application/json',
        'Accept-Encoding' => 'gzip',
    ]);
    echo "\n--- {$name} (HTTP {$r['code']}) ---\n";
    if ($r['code'] !== 200) {
        echo substr((string) $r['raw'], 0, 400)."\n";
        continue;
    }
    foreach ($r['body']['items'] ?? [] as $row) {
        $ask = $row['lowest_ask'] ?? null;
        $dec = isset($ask) ? round($ask / 100, 2) : null;
        echo "  {$row['provider']}: lowest_ask={$ask} (≈{$dec} CNY) qty=".($row['quantity'] ?? '?')."\n";
    }
}

echo "\n=== CS2Cap batch (first 3 names) currency=VND ===\n";
$batchBody = [
    'market_hash_names' => array_slice($names, 0, 3),
    'providers' => ['buff163', 'csgoempire'],
    'currency' => 'VND',
];
$r = httpJson('POST', $base.'/prices/batch', [
    'Authorization' => 'Bearer '.$apiKey,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'Accept-Encoding' => 'gzip',
], $batchBody);
echo "HTTP {$r['code']}\n";
if ($r['code'] === 200) {
    foreach ($r['body']['items'] ?? [] as $block) {
        echo ($block['market_hash_name'] ?? '?').":\n";
        foreach ($block['quotes'] ?? [] as $q) {
            $ask = $q['lowest_ask'] ?? 0;
            echo "  {$q['provider']}: {$ask} minor units VND\n";
        }
    }
} else {
    echo substr((string) $r['raw'], 0, 500)."\n";
}

echo "\n=== PriceForge (X-API-Key) — if key is PriceForge ===\n";
$testName = rawurlencode($sample[0] ?? 'AK-47 | Redline (Field-Tested)');
$r = httpJson('GET', "https://api.priceforge.dev/api/v1/prices/{$testName}", [
    'X-API-Key' => $apiKey,
    'Accept' => 'application/json',
]);
echo "HTTP {$r['code']}\n";
echo substr((string) $r['raw'], 0, 600)."\n";

echo "\nDone. Compared ".count($sample).' sample / '.count($names)." total items.\n";
