<?php

namespace App\Services;

use App\Support\InventoryItemFilter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SteamInventoryService
{
    private const APP_ID = 730;

    private const CONTEXT_ID = 2;

    /**
     * @return array{steam_id: string, label: string, url: string}
     */
    public function parseInventoryUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Link kho đồ không được để trống.');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Link không hợp lệ: {$url}");
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $label = $url;

        if (preg_match('#/profiles/(\d{17})(?:/inventory)?#', $path, $m)) {
            return [
                'steam_id' => $m[1],
                'label' => $label,
                'url' => $url,
            ];
        }

        if (preg_match('#/inventory/(\d{17})#', $path, $m)) {
            return [
                'steam_id' => $m[1],
                'label' => $label,
                'url' => $url,
            ];
        }

        if (preg_match('#/id/([^/]+)(?:/inventory)?#', $path, $m)) {
            return [
                'steam_id' => $this->resolveVanityToSteamId($m[1]),
                'label' => $label,
                'url' => $url,
            ];
        }

        throw new RuntimeException('Không nhận diện được link Steam. Dùng dạng steamcommunity.com/profiles/... hoặc /id/...');
    }

    /**
     * @return list<array{
     *   assetid: string,
     *   market_hash_name: string,
     *   name: string,
     *   icon_url: string|null,
     *   tradable: bool,
     *   amount: int
     * }>
     */
    public function fetchItems(string $steamId): array
    {
        $items = [];
        $startAssetId = null;
        $descriptionMap = [];

        do {
            $query = [
                'l' => 'english',
                'count' => min(2000, max(75, (int) config('cs2price.steam_inventory_page_size', 2000))),
            ];
            if ($startAssetId) {
                $query['start_assetid'] = $startAssetId;
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ])
                ->get("https://steamcommunity.com/inventory/{$steamId}/".self::APP_ID.'/'.self::CONTEXT_ID, $query);

            if ($response->status() === 403) {
                throw new RuntimeException('Kho đồ đang private hoặc Steam chặn truy cập. Hãy mở public inventory.');
            }

            if (! $response->successful()) {
                throw new RuntimeException('Không lấy được kho đồ Steam (HTTP '.$response->status().').');
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Phản hồi Steam không hợp lệ.');
            }

            if (($payload['success'] ?? 0) !== 1) {
                $message = $payload['error'] ?? 'Kho đồ trống hoặc không truy cập được.';
                throw new RuntimeException((string) $message);
            }

            foreach ($payload['descriptions'] ?? [] as $desc) {
                $key = ($desc['classid'] ?? '').'_'.($desc['instanceid'] ?? '0');
                $descriptionMap[$key] = $desc;
            }

            foreach ($payload['assets'] ?? [] as $asset) {
                $key = ($asset['classid'] ?? '').'_'.($asset['instanceid'] ?? '0');
                $desc = $descriptionMap[$key] ?? null;
                if (! $desc || empty($desc['market_hash_name'])) {
                    continue;
                }

                if (! InventoryItemFilter::isTradableDescription($desc)) {
                    continue;
                }

                $items[] = [
                    'assetid' => (string) ($asset['assetid'] ?? ''),
                    'market_hash_name' => (string) $desc['market_hash_name'],
                    'name' => (string) ($desc['name'] ?? $desc['market_hash_name']),
                    'icon_url' => isset($desc['icon_url'])
                        ? 'https://community.cloudflare.steamstatic.com/economy/image/'.$desc['icon_url']
                        : null,
                    'tradable' => (bool) ($desc['tradable'] ?? false),
                    'amount' => (int) ($asset['amount'] ?? 1),
                ];
            }

            $lastAsset = collect($payload['assets'] ?? [])->last();
            $startAssetId = $lastAsset['assetid'] ?? null;
            $more = (bool) ($payload['more_items'] ?? false);
        } while ($more && $startAssetId);

        return $items;
    }

    private function resolveVanityToSteamId(string $vanity): string
    {
        $apiKey = config('cs2price.steam_api_key');
        if ($apiKey) {
            $response = Http::timeout(15)->get('https://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/', [
                'key' => $apiKey,
                'vanityurl' => $vanity,
            ]);
            if ($response->successful()) {
                $steamId = $response->json('response.steamid');
                if ($steamId) {
                    return (string) $steamId;
                }
            }
        }

        try {
            $xmlResponse = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://steamcommunity.com/id/{$vanity}/?xml=1");

            if ($xmlResponse->successful() && preg_match('/<steamID64>(\d{17})<\/steamID64>/', $xmlResponse->body(), $m)) {
                return $m[1];
            }
        } catch (ConnectionException) {
            // fall through
        }

        throw new RuntimeException("Không resolve được Steam ID cho vanity: {$vanity}");
    }
}
