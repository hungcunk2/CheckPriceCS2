<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$steamId = $argv[1] ?? '76561198959660892';

$response = Illuminate\Support\Facades\Http::timeout(45)
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'application/json',
        'Referer' => 'https://cs.trade/cs2-inventory-value',
    ])
    ->get('https://cdn.cs.trade/tools/api/inventoryValue', [
        'gameid' => '730',
        'steamid' => $steamId,
    ]);

$payload = $response->json();
if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
    fwrite(STDERR, "API error HTTP {$response->status()}\n");
    exit(1);
}

$items = $payload['inventory']['items'] ?? [];
echo 'Total items: '.count($items)."\n";
if ($items !== []) {
    echo 'Keys on first row: '.implode(', ', array_keys($items[0]))."\n\n";
}

$found = 0;
foreach ($items as $row) {
    $hash = (string) ($row['market_hash_name'] ?? '');
    if (! preg_match('/doppler/i', $hash)) {
        continue;
    }
    $found++;
    echo "=== {$hash} ===\n";
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";
    if ($found >= 3) {
        break;
    }
}

if ($found === 0) {
    echo "No Doppler in this inventory.\n";
}
