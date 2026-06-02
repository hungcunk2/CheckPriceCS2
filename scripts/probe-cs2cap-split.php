<?php

/**
 * Test CS2Cap: Buff (CNY) + Empire (USD) cho một link kho.
 * Usage: CS2CAP_API_KEY=... php scripts/probe-cs2cap-split.php "https://steamcommunity.com/id/..."
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = getenv('CS2CAP_API_KEY') ?: '';
if ($key === '') {
    fwrite(STDERR, "Set CS2CAP_API_KEY\n");
    exit(1);
}

config([
    'cs2price.cs2cap_enabled' => true,
    'cs2price.cs2cap_api_key' => $key,
    'cs2price.cs2cap_buff_currency' => 'CNY',
    'cs2price.cs2cap_empire_currency' => 'USD',
]);

$url = $argv[1] ?? 'https://steamcommunity.com/id/hamyngungoc/inventory/';
$parsed = app(App\Services\SteamInventoryService::class)->parseInventoryUrl($url);
$items = app(App\Services\InventoryFetchService::class)->fetchBundle($parsed['steam_id'], false)['items'] ?? [];
$names = array_values(array_unique(array_column($items, 'market_hash_name')));
$sample = array_slice($names, 0, 8);

echo "Steam: {$parsed['steam_id']} · {$url}\n";
echo 'Sample '.count($sample).' / '.count($names)." items\n\n";

$cap = app(App\Services\Cs2CapService::class);
$data = $cap->getBuffCnyAndEmpireUsd($sample);

printf("%-55s %12s %12s\n", 'Item', 'Buff ¥', 'Empire $');
echo str_repeat('-', 82)."\n";

foreach ($sample as $hash) {
    $b = $data['buff'][$hash] ?? [];
    $e = $data['empire'][$hash] ?? [];
    $bStr = isset($b['sell_min_price']) ? number_format($b['sell_min_price'], 2) : ($b['error'] ?? '—');
    $eStr = isset($e['empire_price_usd']) ? '$'.number_format($e['empire_price_usd'], 2) : ($e['error'] ?? '—');
    printf("%-55s %12s %12s\n", mb_substr($hash, 0, 55), $bStr, $eStr);
}

echo "\nLưu ý: Empire từ CS2Cap là giá listing USD, không phải coin withdraw API.\n";
