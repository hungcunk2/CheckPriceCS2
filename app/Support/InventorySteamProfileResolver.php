<?php

namespace App\Support;

use App\Services\SteamInventoryService;
use App\Services\SteamProfileService;
use App\Support\SteamAvatarUrl;
use RuntimeException;

class InventorySteamProfileResolver
{
    public function __construct(
        private SteamInventoryService $steam,
        private SteamProfileService $steamProfile,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function mergeIntoAttributes(array $attributes, string $url): array
    {
        try {
            $parsed = $this->steam->parseInventoryUrl($url);
            $profile = $this->steamProfile->fetchProfile($parsed['steam_id']);

            $avatar = SteamAvatarUrl::normalize($profile['steam_avatar_url'] ?? null);

            return array_merge($attributes, [
                'steam_id' => $parsed['steam_id'],
                'steam_persona_name' => $profile['steam_persona_name'],
                'steam_avatar_url' => SteamAvatarUrl::isUsable($avatar) ? $avatar : null,
            ]);
        } catch (RuntimeException) {
            return $attributes;
        }
    }
}
