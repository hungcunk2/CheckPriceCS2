<?php

namespace App\Support;

use App\Services\SteamInventoryService;

final class InventoryUrlMatcher
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $path = $path !== '/' ? rtrim($path, '/') : $path;
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    /** Lấy SteamID64 từ URL — không gọi API (profiles, inventory, cs.trade). */
    public static function steamIdFromUrlLocal(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        if (str_ends_with($host, 'cs.trade')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $steamId = preg_replace('/\D/', '', (string) ($query['steam_id'] ?? $query['steamid'] ?? ''));

            return preg_match('/^\d{17}$/', (string) $steamId) ? $steamId : null;
        }

        if (preg_match('#/profiles/(\d{17})(?:/inventory)?#', $path, $m)) {
            return $m[1];
        }

        if (preg_match('#/inventory/(\d{17})#', $path, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Vanity /id/... cần gọi Steam API — chỉ dùng khi local không parse được. */
    public static function steamIdFromUrl(string $url): ?string
    {
        $local = self::steamIdFromUrlLocal($url);
        if ($local !== null) {
            return $local;
        }

        try {
            return app(SteamInventoryService::class)->parseInventoryUrl($url)['steam_id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public static function urlVariants(string $url): array
    {
        $trimmed = trim($url);
        $normalized = self::normalize($url);

        return array_values(array_unique(array_filter([$trimmed, $normalized])));
    }
}
