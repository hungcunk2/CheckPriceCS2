<?php

namespace App\Support;

class InventoryDisplay
{
    public static function title(object $inventory): string
    {
        $label = trim((string) ($inventory->label ?? ''));
        $steamName = trim((string) ($inventory->steam_persona_name ?? ''));

        if ($label !== '' && $steamName !== '') {
            return $label.' | '.$steamName;
        }

        if ($label !== '') {
            return $label;
        }

        if ($steamName !== '') {
            return $steamName;
        }

        return 'Kho #'.(int) ($inventory->id ?? 0);
    }

    public static function avatarUrl(object $inventory): ?string
    {
        return app(\App\Services\ItemImageService::class)->avatarUrlForDisplay(
            (string) ($inventory->steam_id ?? ''),
            $inventory->steam_avatar_url ?? null,
        );
    }

    public static function listIdentityHtml(object $inventory): string
    {
        return view('partials.inventory-list-identity', ['inventory' => $inventory])->render();
    }

    public static function hasLoadedInventory(object $inventory): bool
    {
        if (! empty($inventory->last_checked_at)) {
            return true;
        }

        $snap = $inventory->last_snapshot ?? null;
        if (is_string($snap)) {
            $snap = json_decode($snap, true);
        }

        return is_array($snap);
    }

    public static function isInventoryEmpty(object $inventory): bool
    {
        if ((int) ($inventory->item_count ?? 0) > 0) {
            return false;
        }

        if (! self::hasLoadedInventory($inventory)) {
            return false;
        }

        $snap = $inventory->last_snapshot ?? null;
        if (is_string($snap)) {
            $snap = json_decode($snap, true);
        }

        if (is_array($snap) && array_key_exists('inventory_empty', $snap)) {
            return (bool) $snap['inventory_empty'];
        }

        return true;
    }

    public static function itemCountLabel(object $inventory, int $displayItemCount = 0): string
    {
        if (self::isInventoryEmpty($inventory)) {
            return 'Kho hiện chưa có item';
        }

        $count = (int) ($inventory->item_count ?? 0);

        if ($displayItemCount > 0) {
            return (string) $displayItemCount;
        }

        if ($count > 0) {
            return (string) $count;
        }

        return self::hasLoadedInventory($inventory) ? 'Kho hiện chưa có item' : '—';
    }
}
