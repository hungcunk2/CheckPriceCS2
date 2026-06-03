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
                return route('api.guest.item-image.stream', [
                    'market_hash_name' => $marketHashName,
                ]);
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
                    'image_url' => route('api.guest.item-image.stream', [
                        'market_hash_name' => $marketHashName,
                    ]),
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

    public function streamSteamImage(string $marketHashName): Response
    {
        $marketHashName = trim($marketHashName);
        if ($marketHashName === '') {
            return response('Not found', 404);
        }

        $steamUrl = $this->steamIconUrlForHash($marketHashName);
        if ($steamUrl === null) {
            return response('Not found', 404);
        }

        $cacheKey = 'steam_image_blob:v1:'.md5($steamUrl);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return response($cached, 200, [
                'Content-Type' => $this->guessContentType($cached),
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        try {
            $response = $this->httpThroughRotatingProxy()
                ->withHeaders(['Accept' => 'image/*,*/*;q=0.8'])
                ->get($steamUrl);
        } catch (\Throwable $e) {
            Log::warning('steam_image_proxy: fetch failed', [
                'hash' => $marketHashName,
                'message' => $e->getMessage(),
            ]);

            return response('Upstream error', 502);
        }

        if (! $response->successful()) {
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

        return $this->rotatingProxy->isEnabled();
    }

    private function httpThroughRotatingProxy(): PendingRequest
    {
        $request = Http::timeout(25);
        $options = $this->rotatingProxy->httpProxyOptions();
        if ($options !== []) {
            $request = $request->withOptions($options);
        }

        return $request;
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
