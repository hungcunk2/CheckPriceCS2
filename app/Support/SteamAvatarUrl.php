<?php

namespace App\Support;

class SteamAvatarUrl
{
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_contains($url, '/api/guest/steam-avatar') || str_contains($url, '/steam-avatar/stream')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (str_starts_with($url, 'http://')) {
            $url = 'https://'.substr($url, 7);
        }

        return $url;
    }

    public static function isAllowed(string $url): bool
    {
        $url = self::normalize($url);
        if ($url === null) {
            return false;
        }

        return (bool) preg_match(
            '#^https://([a-z0-9.-]+\.)?(steamstatic\.com|steamcommunity\.com|akamaihd\.net)(/|$)#i',
            $url
        );
    }

    public static function isUsable(?string $url): bool
    {
        $url = self::normalize($url);

        return $url !== null && self::isAllowed($url);
    }
}
