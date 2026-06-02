<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$steamId = $argv[1] ?? '76561198959660892';
$url = "https://steamcommunity.com/inventory/{$steamId}/730/2?l=english&count=500";

$response = Illuminate\Support\Facades\Http::timeout(30)
    ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Accept' => 'application/json'])
    ->get($url);

$payload = $response->json();
if (($payload['success'] ?? 0) !== 1) {
    fwrite(STDERR, "Steam fail\n");
    exit(1);
}

$descMap = [];
foreach ($payload['descriptions'] ?? [] as $desc) {
    $key = ($desc['classid'] ?? '').'_'.($desc['instanceid'] ?? '0');
    $descMap[$key] = $desc;
}

foreach ($payload['assets'] ?? [] as $asset) {
    $key = ($asset['classid'] ?? '').'_'.($asset['instanceid'] ?? '0');
    $desc = $descMap[$key] ?? null;
    if (! $desc || ! preg_match('/doppler/i', (string) ($desc['market_hash_name'] ?? ''))) {
        continue;
    }
    echo "=== {$desc['market_hash_name']} asset {$asset['assetid']} ===\n";
    echo "desc keys: ".implode(', ', array_keys($desc))."\n";
    if (isset($asset['asset_properties'])) {
        echo "asset_properties: ".json_encode($asset['asset_properties'], JSON_PRETTY_PRINT)."\n";
    }
    echo "market_name_inside_group: ".($desc['market_name_inside_group'] ?? '—')."\n";
    echo "market_bucket_group_name: ".($desc['market_bucket_group_name'] ?? '—')."\n";
    echo "tags: ".json_encode($desc['tags'] ?? [], JSON_UNESCAPED_UNICODE)."\n";
    echo "descriptions: ".json_encode($desc['descriptions'] ?? [], JSON_UNESCAPED_UNICODE)."\n";
    echo "\n";
    static $n = 0;
    if (++$n >= 2) {
        break;
    }
}
