<?php

namespace App\Support;

class SteamImageUrl
{
    public static function large(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_contains($url, 'steamstatic.com/economy/image/')) {
            $base = preg_replace('#/(?:\d+x\d+|\d+fx\d+f)(?:\?.*)?$#', '', $url) ?? $url;

            return rtrim($base, '/').'/512fx512f';
        }

        if (str_contains($url, 'steamstatic.com') && str_contains($url, 'avatar')) {
            return preg_replace('/_[a-z]+\.jpg$/i', '_full.jpg', $url) ?? $url;
        }

        return $url;
    }
}
