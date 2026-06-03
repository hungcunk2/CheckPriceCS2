<?php

use App\Services\Cs2CapInventoryService;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$steam = $argv[1] ?? '76561198110769133';

/** @var Cs2CapInventoryService $inv */
$inv = app(Cs2CapInventoryService::class);

$bundle = $inv->fetch($steam);
$items = $bundle['items'] ?? [];

echo "Steam: {$steam}\n";
echo "Items: " . count($items) . "\n\n";

$hits = 0;
foreach ($items as $row) {
    $hash = (string) ($row['market_hash_name'] ?? '');
    $name = (string) ($row['name'] ?? '');
    $phase = $row['phase'] ?? null;

    $hay = strtolower($hash . ' ' . $name);
    if (str_contains($hay, 'doppler')) {
        echo "- {$hash}\n";
        echo "  phase: " . (is_scalar($phase) ? (string) $phase : json_encode($phase)) . "\n\n";
        $hits++;
    }
}

if ($hits === 0) {
    echo "No doppler items found in this inventory.\n";
}

