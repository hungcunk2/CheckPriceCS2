<?php

namespace App\Services;

use RuntimeException;

class InventoryFetchService
{
    public function __construct(
        private SteamInventoryService $steam,
        private Cs2CapInventoryService $cs2cap,
        private SteamProfileService $steamProfile,
    ) {}

    /**
     * Mặc định dùng CS2Cap inventory (có phase). Nếu CS2Cap lỗi, fallback Steam.
     *
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null,
     *   inventory_source: string,
     *   inventory_fallback_message: string|null
     * }
     */
    public function fetchBundle(string $steamId, bool $refresh = false): array
    {
        if (config('cs2price.cs2cap_use_inventory', false) || config('cs2price.inventory_source') === 'cs2cap') {
            try {
                return $this->fromCs2Cap($steamId, $refresh);
            } catch (RuntimeException $e) {
                // CS2Cap lỗi / hết key / kho private → fallback Steam để site vẫn chạy.
                $bundle = $this->fromSteam($steamId, $refresh);
                $bundle['inventory_fallback_message'] = 'CS2Cap lỗi: '.$e->getMessage();

                return $bundle;
            }
        }

        return $this->fromSteam($steamId, $refresh);
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null,
     *   inventory_source: string,
     *   inventory_fallback_message: string|null
     * }
     */
    private function fromSteam(string $steamId, bool $refresh): array
    {
        $profile = $this->steamProfile->fetchProfile($steamId);

        return [
            'items' => $this->steam->fetchItemsCached($steamId, $refresh),
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'inventory_source' => 'steam',
            'inventory_fallback_message' => null,
        ];
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   steam_persona_name: string|null,
     *   steam_avatar_url: string|null,
     *   inventory_source: string,
     *   inventory_fallback_message: string|null
     * }
     */
    private function fromCs2Cap(string $steamId, bool $refresh): array
    {
        $bundle = $this->cs2cap->fetchCached($steamId, $refresh);

        $profile = $this->steamProfile->fetchProfile($steamId);

        return [
            'items' => $bundle['items'],
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'inventory_source' => 'cs2cap',
            'inventory_fallback_message' => null,
        ];
    }
}
