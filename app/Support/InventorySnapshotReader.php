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

    public static function hasItems(object $inventory): bool
    {
        return self::itemsFromInventory($inventory) !== [];
    }
}
