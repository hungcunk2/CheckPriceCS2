<?php

namespace App\Services;

use App\Models\PriceHistoryPoint;
use Illuminate\Support\Facades\DB;

class PriceHistoryStore
{
    public function append(string $marketHashName, float $priceCny, ?int $sellNum, string $recordedAt): void
    {
        if ($marketHashName === '') {
            return;
        }

        $hash = $this->itemHash($marketHashName);
        $priceCny = round($priceCny, 2);
        $at = \Carbon\Carbon::parse($recordedAt);

        $exists = PriceHistoryPoint::query()
            ->where('item_hash', $hash)
            ->where('recorded_at', $at)
            ->where('price_cny', $priceCny)
            ->where('sell_num', $sellNum)
            ->exists();

        if ($exists) {
            return;
        }

        PriceHistoryPoint::query()->create([
            'item_hash' => $hash,
            'market_hash_name' => $marketHashName,
            'recorded_at' => $at,
            'price_cny' => $priceCny,
            'sell_num' => $sellNum,
        ]);

        $this->trimPoints($hash);
    }

    /**
     * @return list<array{recorded_at: string, price_cny: float, sell_num: int|null}>
     */
    public function points(string $marketHashName): array
    {
        $hash = $this->itemHash($marketHashName);

        return PriceHistoryPoint::query()
            ->where('item_hash', $hash)
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (PriceHistoryPoint $p) => [
                'recorded_at' => $p->recorded_at->toIso8601String(),
                'price_cny' => (float) $p->price_cny,
                'sell_num' => $p->sell_num,
            ])
            ->all();
    }

    private function itemHash(string $marketHashName): string
    {
        return md5($marketHashName);
    }

    private function trimPoints(string $itemHash): void
    {
        $maxDays = (int) config('cs2price.price_history_days', 90);
        $cutoff = now()->subDays($maxDays);

        PriceHistoryPoint::query()
            ->where('item_hash', $itemHash)
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        $maxPoints = (int) config('cs2price.price_history_max_points', 3000);
        $count = PriceHistoryPoint::query()->where('item_hash', $itemHash)->count();

        if ($count <= $maxPoints) {
            return;
        }

        $idsToKeep = PriceHistoryPoint::query()
            ->where('item_hash', $itemHash)
            ->orderByDesc('recorded_at')
            ->limit($maxPoints)
            ->pluck('id');

        PriceHistoryPoint::query()
            ->where('item_hash', $itemHash)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
