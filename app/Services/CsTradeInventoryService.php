<?php

namespace App\Services;

use App\Support\InventoryItemFilter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CsTradeInventoryService
{
    public const API_URL = 'https://cdn.cs.trade/tools/api/inventoryValue';

    private const GAME_ID = 730;

    public static function apiUrl(): string
    {
        return self::API_URL;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null
     * }
     */
    public function fetchCached(string $steamId, bool $refresh = false): array
    {
        $cacheKey = 'cstrade_inventory:'.$steamId;
        $ttl = max(300, (int) config('cs2price.steam_inventory_cache_seconds', 86400));

        if (! $refresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ($cached['items'] ?? []) !== []) {
                return $cached;
            }
        }

        $bundle = $this->fetch($steamId);
        if ($bundle['items'] !== []) {
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
    public function fetch(string $steamId): array
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            throw new RuntimeException('Steam ID64 không hợp lệ.');
        }

        $payload = $this->requestInventory($steamId);
        $player = $payload['response']['players'][0] ?? null;
        $inventory = $payload['inventory'] ?? null;

        if (! is_array($inventory) || ! is_array($inventory['items'] ?? null)) {
            throw new RuntimeException('Phản hồi cs.trade không hợp lệ.');
        }

        $items = [];
        $index = 0;

        foreach ($inventory['items'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hash = trim((string) ($row['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }

            // cs.trade chỉ gán price cho vật phẩm có thể giao dịch / có giá thị trường
            $hasPrice = isset($row['price']) && $row['price'] !== null && $row['price'] !== '';

            $item = [
                'assetid' => 'cstrade-'.$steamId.'-'.$index,
                'market_hash_name' => $hash,
                'name' => $hash,
                'icon_url' => $this->iconUrl($row),
                'tradable' => $hasPrice,
                'amount' => 1,
            ];
            $index++;

            if (! InventoryItemFilter::isTradableItem($item)) {
                continue;
            }

            if (! $hasPrice) {
                continue;
            }

            $items[] = $item;
        }

        if ($items === []) {
            throw new RuntimeException('Kho không có skin tradable có thể định giá (cs.trade).');
        }

        return [
            'items' => $items,
            'steam_persona_name' => is_array($player) ? ($player['personaname'] ?? null) : null,
            'steam_avatar_url' => is_array($player) ? ($player['avatarfull'] ?? $player['avatarmedium'] ?? null) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestInventory(string $steamId): array
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://cs.trade/cs2-inventory-value',
                ])
                ->get(self::apiUrl(), [
                    'gameid' => (string) self::GAME_ID,
                    'steamid' => $steamId,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Không kết nối được cs.trade: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('cs.trade trả lỗi HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Phản hồi cs.trade không hợp lệ.');
        }

        if (($payload['success'] ?? false) !== true) {
            $message = (string) ($payload['error'] ?? 'Không lấy được kho đồ.');

            throw new RuntimeException($message);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function iconUrl(array $row): ?string
    {
        $cdn = trim((string) ($row['icon_url_cdn'] ?? ''));
        if ($cdn !== '' && str_starts_with($cdn, 'http')) {
            return $cdn;
        }

        $hash = trim((string) ($row['icon_url'] ?? ''));
        if ($hash === '') {
            return null;
        }

        return 'https://community.akamai.steamstatic.com/economy/image/'.$hash;
    }
}
