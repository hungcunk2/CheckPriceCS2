<?php

use App\Services\Cs2CapCatalogService;
use App\Services\Cs2CapInventoryService;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$steam = $argv[1] ?? '76561198110769133';
$limit = (int)($argv[2] ?? 10);
if ($limit <= 0) $limit = 10;

/** @var Cs2CapInventoryService $inv */
$inv = app(Cs2CapInventoryService::class);
/** @var Cs2CapCatalogService $cat */
$cat = app(Cs2CapCatalogService::class);

echo "Steam: {$steam}\n";
echo "Limit: {$limit}\n\n";

$bundle = $inv->fetch($steam);
$items = $bundle['items'] ?? [];

echo "Inventory items: " . count($items) . "\n\n";

$i = 0;
foreach ($items as $row) {
    $hash = (string)($row['market_hash_name'] ?? '');
    if ($hash === '') continue;

    $icon = $row['icon_url'] ?? null;
    $img = $cat->imageUrlForHash($hash);

    echo "== {$hash}\n";
    echo "icon_url: " . ($icon ?: '(null)') . "\n";
    echo "catalog image_url: " . ($img ?: '(null)') . "\n\n";

    $i++;
    if ($i >= $limit) break;
}

