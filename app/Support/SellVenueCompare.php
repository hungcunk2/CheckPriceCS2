<?php

namespace App\Support;

class SellVenueCompare
{
    /**
     * Nơi bán có giá quy đổi CNY cao hơn (null nếu thiếu một trong hai giá).
     */
    public static function bestVenue(?float $buffCny, ?float $empireCny): ?string
    {
        if ($buffCny === null || $empireCny === null) {
            return null;
        }

        $avg = ($buffCny + $empireCny) / 2;
        if ($avg > 0 && abs($buffCny - $empireCny) / $avg < 0.01) {
            return 'tie';
        }

        return $buffCny >= $empireCny ? 'buff' : 'empire';
    }

    public static function label(?string $venue): string
    {
        return match ($venue) {
            'buff' => 'Buff163',
            'empire' => 'Empire',
            'tie' => 'Tương đương',
            default => '—',
        };
    }
}
