<?php

namespace App\Services;

use App\Models\ItemCatalogImage;
use App\Support\Cs2CapApiPool;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Cs2CapCatalogService
{
    /**
     * Chỉ đọc DB cache (không gọi API).
     *
     * @param  list<string>  $marketHashNames
     * @return array<string, string> map hash => image_url
     */
    public function cachedImageUrlsForHashes(array $marketHashNames): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $marketHashNames))));
        if ($names === []) {
            return [];
        }

        $ttl = max(60, (int) config('cs2price.cs2cap_catalog_image_cache_seconds', 86400 * 30));
        $cutoff = CarbonImmutable::now()->subSeconds($ttl);

        $rows = ItemCatalogImage::query()
            ->whereIn('market_hash_name', $names)
            ->whereNotNull('fetched_at')
            ->where('fetched_at', '>=', $cutoff)
            ->get(['market_hash_name', 'image_url']);

        $out = [];
        foreach ($rows as $row) {
            $img = $row->image_url;
            if (is_string($img) && trim($img) !== '') {
                $out[$row->market_hash_name] = $img;
            }
        }

        return $out;
    }

    /**
     * @return array{catalog_image: string|null, steam_icon: string|null}
     */
    public function lookupItem(string $marketHashName): array
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return ['catalog_image' => null, 'steam_icon' => null];
        }

        $processKey = 'cs2cap_item_lookup:v1:'.md5($marketHashName);
        $cached = Cache::get($processKey);
        if (is_array($cached)) {
            return [
                'catalog_image' => $cached['catalog_image'] ?? null,
                'steam_icon' => $cached['steam_icon'] ?? null,
            ];
        }

        $account = Cs2CapApiPool::next();
        if ($account === null) {
            return ['catalog_image' => null, 'steam_icon' => null];
        }

        $base = rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');
        $resp = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer '.$account['api_key'],
                'Accept' => 'application/json',
            ])
            ->get("{$base}/items", [
                'market_hash_name' => $marketHashName,
                'limit' => 1,
            ]);

        $this->handleRateLimit($resp, $account['label']);

        if (! $resp->successful()) {
            Cache::put($processKey, ['catalog_image' => null, 'steam_icon' => null], 3600);

            return ['catalog_image' => null, 'steam_icon' => null];
        }

        $items = $resp->json('items') ?? [];
        $first = is_array($items) ? ($items[0] ?? null) : null;
        $result = [
            'catalog_image' => is_array($first) && is_string($first['image_url'] ?? null) && trim($first['image_url']) !== ''
                ? trim($first['image_url'])
                : null,
            'steam_icon' => is_array($first) ? ($first['icon_url'] ?? null) : null,
        ];

        Cache::put($processKey, $result, 86400);

        return $result;
    }

    /**
     * Lấy image_url (CDN CS2Cap) theo market_hash_name.
     */
    public function imageUrlForHash(string $marketHashName): ?string
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return null;
        }

        // 1) DB cache (bền, dùng lại giữa các lần quét)
        $ttl = max(60, (int) config('cs2price.cs2cap_catalog_image_cache_seconds', 86400 * 30));
        $cutoff = CarbonImmutable::now()->subSeconds($ttl);
        $db = ItemCatalogImage::query()
            ->where('market_hash_name', $marketHashName)
            ->whereNotNull('fetched_at')
            ->where('fetched_at', '>=', $cutoff)
            ->first();

        if ($db) {
            $img = $db->image_url;
            return is_string($img) && trim($img) !== '' ? $img : null;
        }

        // 2) Process cache (tránh query DB liên tục trong 1 request burst)
        $cacheKey = 'cs2cap_catalog_image:v1:'.md5($marketHashName);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        $lookup = $this->lookupItem($marketHashName);
        $imageUrl = $lookup['catalog_image'];

        if (is_string($imageUrl) && trim($imageUrl) !== '') {
            Cache::put($cacheKey, $imageUrl, $ttl);
            ItemCatalogImage::query()->updateOrCreate(
                ['market_hash_name' => $marketHashName],
                ['image_url' => $imageUrl, 'fetched_at' => now()]
            );

            return $imageUrl;
        }

        Cache::put($cacheKey, '', min($ttl, 86400));
        ItemCatalogImage::query()->updateOrCreate(
            ['market_hash_name' => $marketHashName],
            ['image_url' => null, 'fetched_at' => now()]
        );

        return null;
    }

    private function handleRateLimit(Response $response, string $label): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $retryAfter = (int) ($response->header('Retry-After') ?? 0);
        $cooldown = $retryAfter > 0 ? $retryAfter : (int) config('cs2price.cs2cap_cooldown_seconds', 30);
        Cs2CapApiPool::setCooldown($label, $cooldown);
    }
}

