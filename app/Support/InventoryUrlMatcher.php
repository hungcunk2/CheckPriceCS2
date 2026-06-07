<?php

namespace App\Support;

use App\Models\TrackedInventory;
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

    public static function steamIdFromUrl(string $url): ?string
    {
        try {
            return app(SteamInventoryService::class)->parseInventoryUrl($url)['steam_id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function steamIdForInventory(TrackedInventory $row): ?string
    {
        $stored = trim((string) ($row->steam_id ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return self::steamIdFromUrl((string) ($row->url ?? ''));
    }

    public static function isSameInventory(string $inputUrl, TrackedInventory $existing): bool
    {
        $inputUrl = trim($inputUrl);
        $existingUrl = trim((string) ($existing->url ?? ''));

        if ($inputUrl === '' || $existingUrl === '') {
            return false;
        }

        if ($inputUrl === $existingUrl) {
            return true;
        }

        if (self::normalize($inputUrl) === self::normalize($existingUrl)) {
            return true;
        }

        $inputSteamId = self::steamIdFromUrl($inputUrl);
        $existingSteamId = self::steamIdForInventory($existing);

        return $inputSteamId !== null
            && $existingSteamId !== null
            && $inputSteamId === $existingSteamId;
    }
}
