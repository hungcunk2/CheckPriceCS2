<?php

namespace App\Support;

use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;

class InventoryResultPersister
{
    public function __construct(
        private TrackedInventoryStore $store,
        private PriceHistoryService $priceHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function persist(array $result, ?int $id = null, ?bool $isPublic = null): object
    {
        $label = $result['label'] ?? null;
        if ($label && str_starts_with($label, 'http')) {
            $label = null;
        }

        $payload = [
            'label' => $label,
            'url' => $result['url'],
            'steam_id' => $result['steam_id'],
            'steam_persona_name' => $result['steam_persona_name'] ?? null,
            'steam_avatar_url' => $result['steam_avatar_url'] ?? null,
            'last_checked_at' => now()->toIso8601String(),
            'last_total_cny' => $result['total_cny'],
            'last_total_vnd' => (int) $result['total_vnd'],
            'item_count' => $result['item_count'],
            'priced_count' => $result['priced_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'last_snapshot' => [
                'total_cny' => $result['total_cny'],
                'total_vnd' => $result['total_vnd'],
                'total_empire_cny' => $result['total_empire_cny'] ?? null,
                'total_empire_vnd' => $result['total_empire_vnd'] ?? null,
                'empire_priced_count' => $result['empire_priced_count'] ?? 0,
                'sell_compare_buff_wins' => $result['sell_compare_buff_wins'] ?? 0,
                'sell_compare_empire_wins' => $result['sell_compare_empire_wins'] ?? 0,
                'inventory_empty' => (bool) ($result['inventory_empty'] ?? false),
                'items' => $result['items'] ?? [],
            ],
        ];

        if ($isPublic !== null) {
            $payload['is_public'] = $isPublic;
        }

        $row = $this->store->upsert($payload, $id);

        $this->priceHistory->recordFromItems($result['items'] ?? [], $payload['last_checked_at']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function persistForUser(array $result, int $userId, int $id, ?bool $isPublic = null): object
    {
        $label = $result['label'] ?? null;
        if ($label && str_starts_with($label, 'http')) {
            $label = null;
        }

        $payload = [
            'label' => $label,
            'url' => $result['url'],
            'steam_id' => $result['steam_id'],
            'steam_persona_name' => $result['steam_persona_name'] ?? null,
            'steam_avatar_url' => $result['steam_avatar_url'] ?? null,
            'last_checked_at' => now()->toIso8601String(),
            'last_total_cny' => $result['total_cny'],
            'last_total_vnd' => (int) $result['total_vnd'],
            'item_count' => $result['item_count'],
            'priced_count' => $result['priced_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'last_snapshot' => [
                'total_cny' => $result['total_cny'],
                'total_vnd' => $result['total_vnd'],
                'total_empire_cny' => $result['total_empire_cny'] ?? null,
                'total_empire_vnd' => $result['total_empire_vnd'] ?? null,
                'empire_priced_count' => $result['empire_priced_count'] ?? 0,
                'sell_compare_buff_wins' => $result['sell_compare_buff_wins'] ?? 0,
                'sell_compare_empire_wins' => $result['sell_compare_empire_wins'] ?? 0,
                'inventory_empty' => (bool) ($result['inventory_empty'] ?? false),
                'items' => $result['items'] ?? [],
            ],
        ];

        if ($isPublic !== null) {
            $payload['is_public'] = $isPublic;
        }

        $row = $this->store->upsertForUser($userId, $payload, $id);

        $this->priceHistory->recordFromItems($result['items'] ?? [], $payload['last_checked_at']);

        return $row;
    }
}
