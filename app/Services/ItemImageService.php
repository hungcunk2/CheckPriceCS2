<?php

namespace App\Services;

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
        $rawIcon = $item['icon_url'] ?? null;
        $item['icon_url'] = $this->iconUrlForDisplay($hash, is_string($rawIcon) ? $rawIcon : null);
        $item['steam_icon_hint'] = is_string($rawIcon) && $rawIcon !== ''
            && ! str_contains($rawIcon, '/api/guest/item-image')
            ? $rawIcon
            : '';

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
    public function resolveForBrowser(string $marketHashName, ?string $snapshotSteamIcon = null): array
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
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
            return response('Not found', 404);
        }

        return $this->streamRemoteImage($steamUrl, 'steam_image_blob:v1', [
            'hash' => $marketHashName,
        ]);
    }

    /**
     * Avatar Steam (profile) — proxy giống ảnh skin khi bật 5Stars.
     */
    public function avatarUrlForDisplay(?string $steamId, ?string $snapshotAvatarUrl = null): ?string
    {
        $steamId = trim((string) $steamId);
        $url = trim((string) $snapshotAvatarUrl);

        if ($url === '' && $steamId !== '' && preg_match('/^\d{17}$/', $steamId)) {
            $profile = app(SteamProfileService::class)->fetchProfile($steamId);
            $url = trim((string) ($profile['steam_avatar_url'] ?? ''));
        }

        if ($url === '' || ! $this->isAllowedSteamAvatarUrl($url)) {
            return null;
        }

        if ($this->useSteamImageProxy() && $steamId !== '' && preg_match('/^\d{17}$/', $steamId)) {
            Cache::put($this->steamAvatarCacheKey($steamId), $url, 86400 * 7);

            return route('api.guest.steam-avatar.stream', ['steam_id' => $steamId]);
        }

        return $url;
    }

    public function streamSteamAvatar(string $steamId): Response
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return response('Not found', 404);
        }

        $cachedUrl = Cache::get($this->steamAvatarCacheKey($steamId));
        $url = is_string($cachedUrl) && $cachedUrl !== '' ? $cachedUrl : null;

        if ($url === null || ! $this->isAllowedSteamAvatarUrl($url)) {
            $profile = app(SteamProfileService::class)->fetchProfile($steamId);
            $url = trim((string) ($profile['steam_avatar_url'] ?? ''));
            if ($url !== '') {
                Cache::put($this->steamAvatarCacheKey($steamId), $url, 86400 * 7);
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
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        $lookup = $this->catalog->lookupItem($marketHashName);
        $steam = $this->normalizeSteamIconUrl($lookup['steam_icon'] ?? null);
        Cache::put($cacheKey, $steam ?? '', 86400 * 14);

        return $steam;
    }

    public function useSteamImageProxy(): bool
    {
        if (! filter_var(config('cs2price.steam_item_image_via_proxy', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->rotatingProxy->isConfigured();
    }

    private function rememberSnapshotSteamIcon(string $marketHashName, ?string $snapshotSteamIcon): void
    {
        $normalized = $this->normalizeSteamIconUrl($snapshotSteamIcon);
        if ($normalized === null) {
            return;
        }

        Cache::put('steam_icon_url:v1:'.md5($marketHashName), $normalized, 86400 * 14);
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
        $cached = Cache::get($cacheKey);
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

        Cache::put($cacheKey, $body, 86400 * 7);

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

        Cache::put($cachePrefix.':'.md5($upstreamUrl), $body, 86400 * 7);

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
}
