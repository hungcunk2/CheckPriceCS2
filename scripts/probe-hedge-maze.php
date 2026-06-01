<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = $argv[1] ?? getenv('CSGOEMPIRE_API_KEY') ?: '';
$url = 'https://steamcommunity.com/id/hamyngungoc/inventory/';

$steam = app(App\Services\SteamInventoryService::class);
$parsed = $steam->parseInventoryUrl($url);
echo "steam_id={$parsed['steam_id']}\n";

$bundle = app(App\Services\InventoryFetchService::class)->fetchBundle($parsed['steam_id'], true);
$hedgeItems = [];
foreach ($bundle['items'] as $item) {
    if (stripos($item['name'] ?? '', 'Hedge') !== false
        || stripos($item['market_hash_name'] ?? '', 'Hedge') !== false) {
        $hedgeItems[] = $item;
        echo "INVENTORY: ".json_encode($item, JSON_UNESCAPED_UNICODE)."\n";
    }
}

if ($hedgeItems === []) {
    echo "No Hedge item in tradable list.\n";
    exit(1);
}

$hash = $hedgeItems[0]['market_hash_name'];
echo "\nSearch Empire for: {$hash}\n";

$response = Illuminate\Support\Facades\Http::timeout(25)
    ->acceptJson()
    ->withHeaders([
        'Authorization' => 'Bearer '.$apiKey,
        'Accept' => 'application/json',
    ])
    ->get('https://csgoempire.com/api/v2/trading/items', [
        'per_page' => 50,
        'page' => 1,
        'search' => $hash,
        'order' => 'market_value',
        'sort' => 'asc',
        'auction' => 'no',
    ]);

echo "HTTP {$response->status()}\n";
if (! $response->successful()) {
    echo substr($response->body(), 0, 800)."\n";
    exit(1);
}

$data = $response->json('data') ?? [];
$exact = array_values(array_filter($data, fn ($r) => strcasecmp($r['market_name'] ?? '', $hash) === 0));
echo 'Total results: '.count($data).", exact name match: ".count($exact)."\n\n";

foreach (array_slice($exact, 0, 15) as $i => $row) {
    $mv = $row['market_value'] ?? null;
    $wear = $row['wear'] ?? null;
    $coins = $mv >= 1000 ? $mv / 100 : $mv;
    echo sprintf(
        "#%d wear=%s market_value=%s (~%.2f coins) id=%s\n",
        $i + 1,
        $wear !== null ? number_format((float) $wear, 6) : '—',
        $mv,
        $coins,
        $row['id'] ?? '—'
    );
}

if ($exact !== []) {
    $min = min(array_map(fn ($r) => (float) ($r['market_value'] ?? 0), $exact));
    $minCoins = $min >= 1000 ? $min / 100 : $min;
    echo "\nLowest market_value (all wears): {$min} (~{$minCoins} coins)\n";
}
