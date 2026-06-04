<?php

namespace App\Services;

use App\Support\SteamAvatarUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteamProfileService
{
    /**
     * @return array{steam_persona_name: string|null, steam_avatar_url: string|null}
     */
    public function fetchProfile(string $steamId): array
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return $this->emptyProfile();
        }

        $cacheKey = 'steam_profile:'.$steamId;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['steam_avatar_url'] = $this->normalizeAvatarUrl($cached['steam_avatar_url'] ?? null);
            if (
                ($cached['steam_persona_name'] ?? null) !== null
                && SteamAvatarUrl::isUsable($cached['steam_avatar_url'] ?? null)
            ) {
                return $cached;
            }

            Cache::forget($cacheKey);
        }

        $page = $this->fetchFromInventoryPage($steamId);
        $remote = $this->fetchFromApi($steamId) ?? $this->fetchFromXml($steamId);

        $persona = $this->pickPersonaName($page, $remote);
        $avatar = $this->normalizeAvatarUrl($page['avatar_url'] ?? null)
            ?? $this->normalizeAvatarUrl($remote['avatar_url'] ?? null);

        $result = [
            'steam_persona_name' => $persona,
            'steam_avatar_url' => $avatar,
        ];

        Cache::put($cacheKey, $result, (int) config('cs2price.steam_profile_cache_seconds', 3600));

        return $result;
    }

    /**
     * @return array{persona_name: string|null, avatar_url: string|null}|null
     */
    private function fetchFromInventoryPage(string $steamId): ?array
    {
        $hints = app(SteamInventoryService::class)->fetchProfileFromInventoryPage($steamId);
        if ($hints === null) {
            return null;
        }

        $persona = $hints['persona_name'] ?? null;
        $avatar = $this->normalizeAvatarUrl($hints['avatar_url'] ?? null);
        if ($persona === null && $avatar === null) {
            return null;
        }

        return [
            'persona_name' => $persona,
            'avatar_url' => $avatar,
        ];
    }

    /**
     * @return array{persona_name: string|null, avatar_url: string|null}|null
     */
    private function fetchFromApi(string $steamId): ?array
    {
        $apiKey = config('cs2price.steam_api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $response = $this->http()->get(
                'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/',
                ['key' => $apiKey, 'steamids' => $steamId]
            );
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $player = $response->json('response.players.0');
        if (! is_array($player)) {
            return null;
        }

        return [
            'persona_name' => isset($player['personaname']) ? (string) $player['personaname'] : null,
            'avatar_url' => $player['avatarfull'] ?? $player['avatarmedium'] ?? $player['avatar'] ?? null,
        ];
    }

    /**
     * @return array{persona_name: string|null, avatar_url: string|null}|null
     */
    private function fetchFromXml(string $steamId): ?array
    {
        try {
            $response = $this->http()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                ->get("https://steamcommunity.com/profiles/{$steamId}/?xml=1");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        $persona = null;
        $avatar = null;

        if (preg_match('/<steamID><!\[CDATA\[(.*?)\]\]><\/steamID>/s', $body, $m)) {
            $persona = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (preg_match('/<steamID>([^<]+)<\/steamID>/', $body, $m)) {
            $persona = trim($m[1]);
        }

        foreach (['avatarFull', 'avatarMedium', 'avatarIcon'] as $tag) {
            if (preg_match('/<'.$tag.'><!\[CDATA\[(.*?)\]\]><\/'.$tag.'>/s', $body, $m)) {
                $avatar = trim($m[1]);
                break;
            }
            if (preg_match('/<'.$tag.'>([^<]+)<\/'.$tag.'>/', $body, $m)) {
                $avatar = trim($m[1]);
                break;
            }
        }

        if ($persona === null && $avatar === null) {
            return null;
        }

        return [
            'persona_name' => $persona ?: null,
            'avatar_url' => $avatar ?: null,
        ];
    }

    /**
     * @return array{steam_persona_name: null, steam_avatar_url: null}
     */
    private function emptyProfile(): array
    {
        return [
            'steam_persona_name' => null,
            'steam_avatar_url' => null,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15);
    }

    private function normalizeAvatarUrl(?string $url): ?string
    {
        $url = SteamAvatarUrl::normalize($url);

        return SteamAvatarUrl::isUsable($url) ? $url : null;
    }

    /**
     * @param  array{persona_name: string|null, avatar_url: string|null}|null  $page
     * @param  array{persona_name: string|null, avatar_url: string|null}|null  $remote
     */
    private function pickPersonaName(?array $page, ?array $remote): ?string
    {
        foreach ([$remote['persona_name'] ?? null, $page['persona_name'] ?? null] as $name) {
            if ($this->isUsablePersonaName($name)) {
                return $name;
            }
        }

        return null;
    }

    private function isUsablePersonaName(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) < 2) {
            return false;
        }

        return ! in_array($name, ['»', '«', '‹', '›', '?', '…'], true);
    }
}
