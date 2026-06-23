<?php

namespace App\Services;

use App\Support\Cs2CapApiPool;
use App\Support\Cs2CapHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class Cs2CapInventoryService
{
    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null
     * }
     */
    public function fetchCached(string $steamIdOrVanity, bool $refresh = false): array
    {
        $cacheKey = 'cs2cap_inventory:'.$steamIdOrVanity;
        $ttl = max(300, (int) config('cs2price.steam_inventory_cache_seconds', 14400));

        if (! $refresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ($cached['items'] ?? []) !== []) {
                return $cached;
            }
        }

        $bundle = $this->fetch($steamIdOrVanity);
        if (($bundle['items'] ?? []) !== []) {
            Cache::put($cacheKey, $bundle, $ttl);
        }

        return $bundle;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null
     * }
     */
    public function fetch(string $steamIdOrVanity): array
    {
        if (! Cs2CapApiPool::isConfigured()) {
            throw new RuntimeException(Cs2CapApiPool::unusableReason());
        }

        $account = Cs2CapApiPool::next();
        if ($account === null) {
            throw new RuntimeException(Cs2CapApiPool::unusableReason());
        }

        $response = Cs2CapHttp::get($account['api_key'], '/inventory/steam/lookup', [
            'steam_id' => $steamIdOrVanity,
        ], 30);

        Cs2CapApiPool::handleResponse($account['label'], $response);

        if ($response->status() === 401) {
            throw new RuntimeException('CS2Cap API key không hợp lệ.');
        }
        if ($response->status() === 403) {
            if (Cs2CapHttp::isIpBanned($response)) {
                throw new RuntimeException('CS2Cap cấm IP server (ACCOUNT_IP_BANNED) — bật proxy 5Stars (CS2CAP_USE_PROXY=true).');
            }
            throw new RuntimeException('CS2Cap không cho phép tra kho (403).');
        }
        if (! $response->successful()) {
            throw new RuntimeException('CS2Cap inventory lỗi HTTP '.$response->status().'.');
        }

        $data = $response->json('data') ?? [];
        if (! is_array($data)) {
            throw new RuntimeException('Phản hồi CS2Cap inventory không hợp lệ.');
        }

        $items = [];
        $i = 0;
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $hash = trim((string) ($row['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }
            $items[] = [
                'assetid' => (string) ($row['assetid'] ?? ('cs2cap-'.$steamIdOrVanity.'-'.$i)),
                'market_hash_name' => $hash,
                'name' => (string) ($row['name'] ?? $hash),
                'icon_url' => $this->normalizeIconUrl($row['icon_url'] ?? null),
                'tradable' => (bool) ($row['tradable'] ?? false),
                'amount' => (int) ($row['quantity'] ?? 1),
                // quan trọng: phase để tra giá doppler/gamma
                'phase' => $row['phase'] ?? null,
            ];
            $i++;
        }

        // Endpoint inventory CS2Cap không trả profile; dùng SteamProfileService phía ngoài như cs.trade.
        return [
            'items' => $items,
            'steam_persona_name' => null,
            'steam_avatar_url' => null,
        ];
    }

    private function normalizeIconUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // CS2Cap/Steam sometimes returns icon_url as a hash-only string (no scheme/host).
        if (! str_contains($url, '://') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/')) {
            $url = 'https://community.cloudflare.steamstatic.com/economy/image/'.$url;
        }

        // Một số response có icon_url bị cụt kiểu ".../economy/image/" -> 404.
        if (preg_match('#/economy/image/?$#', $url)) {
            return null;
        }

        // Chuẩn hóa về steamstatic cloudflare (ổn định).
        $url = str_replace('https://steamcommunity-a.akamaihd.net/economy/image/', 'https://community.cloudflare.steamstatic.com/economy/image/', $url);
        $url = str_replace('https://steamcommunity.akamaihd.net/economy/image/', 'https://community.cloudflare.steamstatic.com/economy/image/', $url);
        $url = str_replace('https://steamcommunity.com/economy/image/', 'https://community.cloudflare.steamstatic.com/economy/image/', $url);
        $url = str_replace('http://steamcommunity-a.akamaihd.net/economy/image/', 'https://community.cloudflare.steamstatic.com/economy/image/', $url);
        $url = str_replace('http://steamcommunity.akamaihd.net/economy/image/', 'https://community.cloudflare.steamstatic.com/economy/image/', $url);

        return $url;
    }
}

