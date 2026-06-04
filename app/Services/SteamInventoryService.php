<?php

namespace App\Services;

use App\Support\InventoryItemFilter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_ends_with($host, 'cs.trade')) {
            return $this->parseCsTradeUrl($url);
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
     * Link cs.trade (vd. .../cs2-inventory-value?steam_id=7656...) — chỉ lấy SteamID64, không gọi cs.trade.
     *
     * @return array{steam_id: string, label: string, url: string}
     */
    private function parseCsTradeUrl(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $steamId = preg_replace('/\D/', '', (string) ($query['steam_id'] ?? $query['steamid'] ?? ''));

        if (! preg_match('/^\d{17}$/', $steamId)) {
            throw new RuntimeException(
                'Link cs.trade cần có tham số steam_id (17 chữ số). Ví dụ: https://cs.trade/vi/cs2-inventory-value?steam_id=76561198959660892'
            );
        }

        return [
            'steam_id' => $steamId,
            'label' => $url,
            'url' => 'https://steamcommunity.com/profiles/'.$steamId.'/inventory',
        ];
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
    /**
     * Lấy list skin: dùng cache nếu còn hạn (giảm 429). $refreshSteam=true bỏ qua cache.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Avatar + tên trên trang kho Steam (cùng link steamcommunity.com/.../inventory).
     *
     * @return array{persona_name: string|null, avatar_url: string|null}|null
     */
    public function fetchProfileFromInventoryPage(string $steamId): ?array
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get("https://steamcommunity.com/profiles/{$steamId}/inventory/");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->parseProfileFromInventoryHtml($response->body());
    }

    /**
     * @return array{persona_name: string|null, avatar_url: string|null}|null
     */
    private function parseProfileFromInventoryHtml(string $html): ?array
    {
        $persona = null;
        $avatar = null;

        if (preg_match('/g_rgProfileData\s*=\s*/', $html, $m, PREG_OFFSET_CAPTURE)) {
            $jsonStr = $this->extractJsonObjectFrom($html, $m[0][1] + strlen($m[0][0]));
            if ($jsonStr !== null) {
                $json = json_decode($jsonStr, true);
                if (is_array($json)) {
                    $persona = isset($json['strProfileName']) ? trim((string) $json['strProfileName']) : null;
                    foreach (['strAvatarFull', 'strAvatarMedium', 'strAvatar'] as $key) {
                        if (! empty($json[$key]) && is_string($json[$key])) {
                            $avatar = trim($json[$key]);
                            break;
                        }
                    }
                }
            }
        }

        if ($persona === null || $persona === '') {
            if (preg_match('/<span[^>]*class="[^"]*actual_persona_name[^"]*"[^>]*>([^<]+)</i', $html, $m)) {
                $persona = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<span[^>]*class="[^"]*profile_small_header_name[^"]*"[^>]*>.*?<span[^>]*>([^<]+)</is', $html, $m)) {
                $persona = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($avatar === null || $avatar === '') {
            if (preg_match('/<img[^>]+class="[^"]*profile_header_image[^"]*"[^>]+src="([^"]+)"/i', $html, $m)) {
                $avatar = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<img[^>]+src="(https:\/\/avatars\.[^"]+)"[^>]+class="[^"]*profile_header_image/i', $html, $m)) {
                $avatar = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($avatar === null || $avatar === '') {
            return null;
        }

        if ($persona === null || $persona === '' || mb_strlen($persona) < 2) {
            $persona = null;
        }

        return [
            'persona_name' => $persona,
            'avatar_url' => $avatar,
        ];
    }

    private function extractJsonObjectFrom(string $html, int $offset): ?string
    {
        $len = strlen($html);
        if ($offset >= $len || ($html[$offset] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $offset; $i < $len; $i++) {
            $ch = $html[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;

                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $offset, $i - $offset + 1);
                }
            }
        }

        return null;
    }

    public function fetchItemsCached(string $steamId, bool $refreshSteam = false): array
    {
        $cacheKey = 'steam_inventory_items:'.$steamId;
        $ttl = max(300, (int) config('cs2price.steam_inventory_cache_seconds', 14400));

        if (! $refreshSteam) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        $items = $this->fetchItems($steamId);
        if ($items !== []) {
            Cache::put($cacheKey, $items, $ttl);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchItems(string $steamId): array
    {
        $items = [];
        $startAssetId = null;
        $descriptionMap = [];
        $pageDelayMs = max(0, (int) config('cs2price.steam_request_delay_ms', 1500));

        do {
            $query = [
                'l' => 'english',
                'count' => min(2000, max(75, (int) config('cs2price.steam_inventory_page_size', 2000))),
            ];
            if ($startAssetId) {
                $query['start_assetid'] = $startAssetId;
            }

            $payload = $this->requestInventoryPage($steamId, $query);

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

            if ($more && $startAssetId && $pageDelayMs > 0) {
                usleep($pageDelayMs * 1000);
            }
        } while ($more && $startAssetId);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function requestInventoryPage(string $steamId, array $query): array
    {
        $url = "https://steamcommunity.com/inventory/{$steamId}/".self::APP_ID.'/'.self::CONTEXT_ID;
        $maxAttempts = 4;
        $response = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ])
                ->get($url, $query);

            if ($response->status() !== 429) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep($this->backoffMicrosFor429($response, $attempt));
            }
        }

        if ($response->status() === 403) {
            throw new RuntimeException('Kho đồ đang private hoặc Steam chặn truy cập. Hãy mở public inventory.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException(
                'Steam tạm chặn (429) — gọi kho quá nhanh. Đợi 10–15 phút rồi sync lại từng kho, không bấm liên tục nhiều kho.'
            );
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

        return $payload;
    }

    private function backoffMicrosFor429(Response $response, int $attempt): int
    {
        $header = $response->header('Retry-After');
        if ($header !== null && $header !== '' && ctype_digit((string) $header)) {
            $sec = min((int) $header, 120);

            return max(2_000_000, $sec * 1_000_000);
        }

        return min(10_000_000, 2_000_000 * (2 ** ($attempt - 1)));
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
