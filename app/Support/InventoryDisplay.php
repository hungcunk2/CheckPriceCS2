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
}
