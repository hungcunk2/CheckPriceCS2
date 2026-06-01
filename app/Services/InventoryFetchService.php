<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class InventoryFetchService
{
    public function __construct(
        private SteamInventoryService $steam,
        private CsTradeInventoryService $csTrade,
        private SteamProfileService $steamProfile,
    ) {}

    /**
     * Thử cs.trade trước; lỗi thì fallback Steam (trừ khi INVENTORY_SOURCE=steam).
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
        if (config('cs2price.inventory_source') === 'steam') {
            return $this->fromSteam($steamId, $refresh);
        }

        try {
            return $this->fromCsTrade($steamId, $refresh);
        } catch (RuntimeException $e) {
            if (! config('cs2price.inventory_fallback_steam', true)) {
                throw $e;
            }

            Log::info('inventory.fetch.cstrade_failed', [
                'steam_id' => $steamId,
                'error' => $e->getMessage(),
            ]);

            return $this->fromSteam($steamId, $refresh, $e->getMessage());
        }
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
    private function fromCsTrade(string $steamId, bool $refresh): array
    {
        $bundle = $this->csTrade->fetchCached($steamId, $refresh);

        $profile = [
            'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
            'steam_avatar_url' => $bundle['steam_avatar_url'] ?? null,
        ];

        if ($profile['steam_persona_name'] === null && $profile['steam_avatar_url'] === null) {
            $profile = $this->steamProfile->fetchProfile($steamId);
        }

        return [
            'items' => $bundle['items'],
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'inventory_source' => 'cstrade',
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
    private function fromSteam(string $steamId, bool $refresh, ?string $fallbackMessage = null): array
    {
        $profile = $this->steamProfile->fetchProfile($steamId);

        return [
            'items' => $this->steam->fetchItemsCached($steamId, $refresh),
            'steam_persona_name' => $profile['steam_persona_name'],
            'steam_avatar_url' => $profile['steam_avatar_url'],
            'inventory_source' => 'steam',
            'inventory_fallback_message' => $fallbackMessage,
        ];
    }
}
