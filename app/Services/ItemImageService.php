<?php

namespace App\Services;

use App\Support\SteamAvatarUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ItemImageService
{
    public function __construct(
        private Cs2CapCatalogService $catalog,
        private FiveStarsRotatingProxyService $rotatingProxy,
    ) {}

    /**
     * URL cho &lt;img src&gt; — ưu tiên Steam (snapshot / CS2Cap icon), catalog CS2Cap sau.
     */
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function enrichItemRowForDisplay(array $item): array
    {
        $hash = (string) ($item['market_hash_name'] ?? '');
        $rawIcon = $this->resolveRawSteamIconFromItem($item);
        $item['icon_url'] = $this->iconUrlForDisplay($hash, $rawIcon);
        $item['steam_icon_hint'] = $rawIcon ?? '';

        return $item;
    }

    public function iconUrlForDisplay(string $marketHashName, ?string $snapshotSteamIcon = null): ?string
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return null;
        }

        $this->rememberSnapshotSteamIcon($marketHashName, $snapshotSteamIcon);

        $steamUrl = $this->normalizeSteamIconUrl($snapshotSteamIcon)
            ?? $this->steamIconUrlForHash($marketHashName);

        if ($steamUrl !== null) {
            if ($this->useSteamImageProxy()) {
                return $this->proxiedItemImageRoute($marketHashName, $steamUrl);
            }

            return $steamUrl;
        }

        return $this->catalog->imageUrlForHash($marketHashName);
    }

    /**
     * @return array{ok: bool, image_url: string|null, source: string|null}
     */
    public function resolveForBrowser(string $marketHashName, ?string $snapshotSteamIcon = null, bool $preferCatalog = false): array
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return ['ok' => false, 'image_url' => null, 'source' => null];
        }

        if ($preferCatalog) {
            $catalogUrl = $this->catalog->imageUrlForHash($marketHashName);
            if ($catalogUrl !== null) {
                return ['ok' => true, 'image_url' => $catalogUrl, 'source' => 'catalog'];
            }

            return ['ok' => false, 'image_url' => null, 'source' => null];
        }

        $this->rememberSnapshotSteamIcon($marketHashName, $snapshotSteamIcon);

        $steamUrl = $this->normalizeSteamIconUrl($snapshotSteamIcon)
            ?? $this->steamIconUrlForHash($marketHashName);

        if ($steamUrl !== null) {
            if ($this->useSteamImageProxy()) {
                return [
                    'ok' => true,
                    'image_url' => $this->proxiedItemImageRoute($marketHashName, $steamUrl),
                    'source' => 'steam_proxy',
                ];
            }

            return ['ok' => true, 'image_url' => $steamUrl, 'source' => 'steam'];
        }

        $catalogUrl = $this->catalog->imageUrlForHash($marketHashName);
        if ($catalogUrl !== null) {
            return ['ok' => true, 'image_url' => $catalogUrl, 'source' => 'catalog'];
        }

        return ['ok' => false, 'image_url' => null, 'source' => null];
    }

    public function streamSteamImage(string $marketHashName, ?string $iconHint = null): Response
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return response('Not found', 404);
        }

        $steamUrl = $this->normalizeSteamIconUrl($iconHint)
            ?? $this->steamIconUrlForHash($marketHashName);
        if ($steamUrl === null) {
            return $this->redirectOrStreamCatalogImage($marketHashName);
        }

        $response = $this->streamRemoteImage($steamUrl, 'steam_image_blob:v1', [
            'hash' => $marketHashName,
        ]);

        if ($response->getStatusCode() >= 400) {
            $catalogFallback = $this->redirectOrStreamCatalogImage($marketHashName);
            if ($catalogFallback->getStatusCode() < 400) {
                return $catalogFallback;
            }
        }

        return $response;
    }

    /**
     * Avatar Steam — URL CDN Steam trực tiếp (trình duyệt tải, không qua proxy 5Stars).
     */
    public function avatarUrlForDisplay(?string $steamId, ?string $snapshotAvatarUrl = null): ?string
    {
        $steamId = trim((string) $steamId);
        $url = SteamAvatarUrl::normalize($snapshotAvatarUrl);

        if (! SteamAvatarUrl::isUsable($url) && $steamId !== '' && preg_match('/^\d{17}$/', $steamId)) {
            $profile = app(SteamProfileService::class)->fetchProfile($steamId);
            $url = SteamAvatarUrl::normalize($profile['steam_avatar_url'] ?? null);
        }

        if (SteamAvatarUrl::isUsable($url)) {
            return $url;
        }

        if ($steamId !== '' && preg_match('/^\d{17}$/', $steamId)) {
            return route('api.guest.steam-avatar.stream', ['steam_id' => $steamId]);
        }

        return null;
    }

    public function streamSteamAvatar(string $steamId): Response
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return response('Not found', 404);
        }

        $cachedUrl = $this->safeCacheGet($this->steamAvatarCacheKey($steamId));
        $url = is_string($cachedUrl) && $cachedUrl !== '' ? $cachedUrl : null;

        if ($url === null || ! $this->isAllowedSteamAvatarUrl($url)) {
            $profile = app(SteamProfileService::class)->fetchProfile($steamId);
            $url = trim((string) ($profile['steam_avatar_url'] ?? ''));
            if ($url !== '') {
                $this->safeCachePut($this->steamAvatarCacheKey($steamId), $url, 86400 * 7);
            }
        }

        if ($url === '' || ! $this->isAllowedSteamAvatarUrl($url)) {
            return response('Not found', 404);
        }

        return $this->streamRemoteImage($url, 'steam_avatar_blob:v1', ['steam_id' => $steamId]);
    }

    public function steamIconUrlForHash(string $marketHashName): ?string
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return null;
        }

        $cacheKey = 'steam_icon_url:v1:'.md5($marketHashName);
        $cached = $this->safeCacheGet($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        $lookup = $this->catalog->lookupItem($marketHashName);
        $steam = $this->normalizeSteamIconUrl($lookup['steam_icon'] ?? null);
        $this->safeCachePut($cacheKey, $steam ?? '', $steam !== null ? 86400 * 14 : 3600);

        return $steam;
    }

    public function useSteamImageProxy(): bool
    {
        if (! filter_var(config('cs2price.steam_item_image_via_proxy', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->rotatingProxy->isConfigured();
    }

    private function resolveRawSteamIconFromItem(array $item): ?string
    {
        foreach (['steam_icon_url', 'icon_url'] as $key) {
            $value = $item[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (str_contains($value, '/api/guest/item-image')) {
                $extracted = $this->extractIconQueryFromStreamUrl($value);
                if ($extracted !== null) {
                    return $extracted;
                }

                continue;
            }

            $normalized = $this->normalizeSteamIconUrl($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function extractIconQueryFromStreamUrl(string $streamUrl): ?string
    {
        $query = parse_url($streamUrl, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $icon = $params['icon'] ?? null;

        return is_string($icon) && $icon !== ''
            ? $this->normalizeSteamIconUrl($icon)
            : null;
    }

    private function redirectOrStreamCatalogImage(string $marketHashName): Response
    {
        $catalogUrl = $this->catalog->imageUrlForHash($marketHashName);
        if ($catalogUrl === null || $catalogUrl === '') {
            return response('Not found', 404);
        }

        if (str_starts_with($catalogUrl, 'http://') || str_starts_with($catalogUrl, 'https://')) {
            return redirect()->away($catalogUrl);
        }

        return response('Not found', 404);
    }

    private function rememberSnapshotSteamIcon(string $marketHashName, ?string $snapshotSteamIcon): void
    {
        $normalized = $this->normalizeSteamIconUrl($snapshotSteamIcon);
        if ($normalized === null) {
            return;
        }

        $this->safeCachePut('steam_icon_url:v1:'.md5($marketHashName), $normalized, 86400 * 14);
    }

    private function normalizeSteamIconUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://') && ! str_starts_with($url, '//')) {
            $url = 'https://community.cloudflare.steamstatic.com/economy/image/'.$url;
        }

        if (str_ends_with($url, '/economy/image/') || str_ends_with($url, '/economy/image')) {
            return null;
        }

        return str_replace(
            ['https://steamcommunity-a.akamaihd.net/economy/image/', 'https://steamcommunity.akamaihd.net/economy/image/'],
            'https://community.cloudflare.steamstatic.com/economy/image/',
            $url,
        );
    }

    private function proxiedItemImageRoute(string $marketHashName, string $steamUrl): string
    {
        $params = ['market_hash_name' => $marketHashName];
        if ($this->isAllowedSteamItemImageUrl($steamUrl)) {
            $params['icon'] = $steamUrl;
        }

        return route('api.guest.item-image.stream', $params);
    }

    /**
     * @param  array<string, string>  $logContext
     */
    private function streamRemoteImage(string $upstreamUrl, string $cachePrefix, array $logContext = []): Response
    {
        $cacheKey = $cachePrefix.':'.md5($upstreamUrl);
        $cached = $this->safeCacheGet($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return response($cached, 200, [
                'Content-Type' => $this->guessContentType($cached),
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $proxyUrl = $this->useSteamImageProxy() ? $this->rotatingProxy->currentProxyUrl() : null;

        try {
            $client = Http::timeout(25)->withHeaders(['Accept' => 'image/*,*/*;q=0.8']);
            if ($proxyUrl !== null && $proxyUrl !== '') {
                $client = $client->withOptions(['proxy' => $proxyUrl]);
            } elseif ($this->useSteamImageProxy()) {
                Log::warning('steam_image_proxy: thiếu URL proxy — chạy php artisan cs2price:refresh-proxy', $logContext);
            }

            $response = $client->get($upstreamUrl);
        } catch (\Throwable $e) {
            if ($proxyUrl !== null && $proxyUrl !== '') {
                Log::info('steam_image_proxy: retry direct', array_merge($logContext, [
                    'message' => $e->getMessage(),
                ]));

                return $this->streamRemoteImageDirect($upstreamUrl, $cachePrefix, $logContext);
            }

            Log::warning('steam_image_proxy: fetch failed', array_merge($logContext, [
                'message' => $e->getMessage(),
            ]));

            return response('Upstream error', 502);
        }

        if (! $response->successful()) {
            if ($proxyUrl !== null && $proxyUrl !== '') {
                return $this->streamRemoteImageDirect($upstreamUrl, $cachePrefix, $logContext);
            }

            return response('Upstream '.$response->status(), 502);
        }

        $body = $response->body();
        if ($body === '') {
            return response('Empty image', 502);
        }

        $this->safeCachePut($cacheKey, $body, 86400 * 7);

        return response($body, 200, [
            'Content-Type' => $response->header('Content-Type') ?: $this->guessContentType($body),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @param  array<string, string>  $logContext
     */
    private function streamRemoteImageDirect(string $upstreamUrl, string $cachePrefix, array $logContext = []): Response
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Accept' => 'image/*,*/*;q=0.8'])
                ->get($upstreamUrl);
        } catch (\Throwable $e) {
            Log::warning('steam_image_direct: fetch failed', array_merge($logContext, [
                'message' => $e->getMessage(),
            ]));

            return response('Upstream error', 502);
        }

        if (! $response->successful()) {
            return response('Upstream '.$response->status(), 502);
        }

        $body = $response->body();
        if ($body === '') {
            return response('Empty image', 502);
        }

        $this->safeCachePut($cachePrefix.':'.md5($upstreamUrl), $body, 86400 * 7);

        return response($body, 200, [
            'Content-Type' => $response->header('Content-Type') ?: $this->guessContentType($body),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function steamAvatarCacheKey(string $steamId): string
    {
        return 'steam_avatar_url:v1:'.$steamId;
    }

    private function isAllowedSteamItemImageUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https://([a-z0-9.-]+\.)?(steamstatic\.com|akamaihd\.net)(/economy/image/|/economy/image$)#i',
            $url
        ) || str_contains($url, '/economy/image/');
    }

    private function normalizeSteamAvatarUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_contains($url, '/api/guest/steam-avatar')) {
            return null;
        }

        return $url;
    }

    private function isAllowedSteamAvatarUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https://([a-z0-9.-]+\.)?(steamstatic\.com|steamcommunity\.com|akamaihd\.net)/#i',
            $url
        );
    }

    private function guessContentType(string $body): string
    {
        if (str_starts_with($body, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 12), 'WEBP')) {
            return 'image/webp';
        }

        return 'image/jpeg';
    }

    private function safeCacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable $e) {
            Log::debug('item_image: cache read failed', ['key' => $key, 'message' => $e->getMessage()]);

            return null;
        }
    }

    private function safeCachePut(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            Log::warning('item_image: cache write failed', ['key' => $key, 'message' => $e->getMessage()]);
        }
    }
}
