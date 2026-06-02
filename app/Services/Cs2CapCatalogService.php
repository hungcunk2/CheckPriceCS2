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

        $account = Cs2CapApiPool::next();
        if ($account === null) {
            return null;
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
            Cache::put($cacheKey, '', 3600);
            ItemCatalogImage::query()->updateOrCreate(
                ['market_hash_name' => $marketHashName],
                ['image_url' => null, 'fetched_at' => now()]
            );
            return null;
        }

        $items = $resp->json('items') ?? [];
        $first = is_array($items) ? ($items[0] ?? null) : null;
        $imageUrl = is_array($first) ? ($first['image_url'] ?? null) : null;

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

