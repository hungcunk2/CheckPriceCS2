<?php

/**
 * Print 1 image URL from CS2Cap inventory lookup.
 *
 * Usage:
 *   CS2CAP_API_KEY=... php scripts/probe-cs2cap-one-image.php 76561198110769133
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = (string) (getenv('CS2CAP_API_KEY') ?: '');
if ($key === '') {
    fwrite(STDERR, "Missing CS2CAP_API_KEY env var\n");
    exit(1);
}

$steamId = (string) ($argv[1] ?? '');
if (! preg_match('/^\d{17}$/', $steamId)) {
    fwrite(STDERR, "Usage: CS2CAP_API_KEY=... php scripts/probe-cs2cap-one-image.php <steamId64>\n");
    exit(1);
}

config([
    'cs2price.cs2cap_enabled' => true,
    'cs2price.cs2cap_use_inventory' => true,
    'cs2price.cs2cap_api_key' => null,
]);

// Use pool env config for this probe
\App\Support\Cs2CapApiPool::setCooldown('probe', 5);

$base = rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');
$resp = Illuminate\Support\Facades\Http::timeout(30)->withHeaders([
    'Authorization' => 'Bearer '.$key,
    'Accept' => 'application/json',
])->get("{$base}/inventory/steam/lookup", ['steam_id' => $steamId]);

if (! $resp->successful()) {
    fwrite(STDERR, "HTTP ".$resp->status().": ".$resp->body()."\n");
    exit(1);
}

$data = $resp->json('data') ?? [];
if (! is_array($data) || $data === []) {
    fwrite(STDERR, "Empty inventory\n");
    exit(1);
}

$row = null;
foreach ($data as $r) {
    if (is_array($r) && ! empty($r['market_hash_name'])) {
        $row = $r;
        break;
    }
}
if (! is_array($row)) {
    fwrite(STDERR, "No item row found\n");
    exit(1);
}

$svc = app(\App\Services\Cs2CapInventoryService::class);
$ref = new ReflectionClass($svc);
$m = $ref->getMethod('normalizeIconUrl');
$m->setAccessible(true);
$icon = $m->invoke($svc, $row['icon_url'] ?? null);

echo "market_hash_name=".$row['market_hash_name']."\n";
echo "icon_url_raw=".($row['icon_url'] ?? '')."\n";
echo "icon_url_normalized=".($icon ?? '')."\n";

