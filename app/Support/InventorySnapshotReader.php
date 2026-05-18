<?php

namespace App\Support;

class InventorySnapshotReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsFromInventory(object $inventory): array
    {
        $snapshot = $inventory->last_snapshot ?? null;

        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        $items = [];
        if (is_array($snapshot)) {
            $items = $snapshot['items'] ?? [];
        } elseif (is_object($snapshot)) {
            $items = $snapshot->items ?? [];
            $items = is_array($items) ? $items : (array) $items;
        }

        return InventoryItemFilter::onlyTradable($items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function heldItemsFromInventory(object $inventory): array
    {
        $snapshot = $inventory->last_snapshot ?? null;

        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (! is_array($snapshot)) {
            return [];
        }

        $items = $snapshot['held_items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    public static function hasItems(object $inventory): bool
    {
        return self::itemsFromInventory($inventory) !== []
            || self::heldItemsFromInventory($inventory) !== [];
    }

    public static function heldTotalCnyFromInventory(object $inventory): float
    {
        $snapshot = $inventory->last_snapshot ?? null;

        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        return is_array($snapshot) ? (float) ($snapshot['held_total_cny'] ?? 0) : 0.0;
    }
}
