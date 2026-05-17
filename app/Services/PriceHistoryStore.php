<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PriceHistoryStore
{
    private const DIR = 'price_history/items';

    public function append(string $marketHashName, float $priceCny, ?int $sellNum, string $recordedAt): void
    {
        if ($marketHashName === '') {
            return;
        }

        $path = $this->path($marketHashName);
        $data = $this->readFile($path);
        $points = $data['points'] ?? [];

        $last = $points[array_key_last($points)] ?? null;
        if ($last
            && ($last['recorded_at'] ?? '') === $recordedAt
            && (float) ($last['price_cny'] ?? 0) === $priceCny
            && (int) ($last['sell_num'] ?? -1) === (int) ($sellNum ?? -1)
        ) {
            return;
        }

        $points[] = [
            'recorded_at' => $recordedAt,
            'price_cny' => round($priceCny, 2),
            'sell_num' => $sellNum,
        ];

        $points = $this->trimPoints($points);

        Storage::disk('local')->put($path, json_encode([
            'market_hash_name' => $marketHashName,
            'points' => $points,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<array{recorded_at: string, price_cny: float, sell_num: int|null}>
     */
    public function points(string $marketHashName): array
    {
        $data = $this->readFile($this->path($marketHashName));

        return is_array($data['points'] ?? null) ? $data['points'] : [];
    }

    private function path(string $marketHashName): string
    {
        return self::DIR.'/'.md5($marketHashName).'.json';
    }

    /**
     * @return array{market_hash_name?: string, points?: list<array<string, mixed>>}
     */
    private function readFile(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function trimPoints(array $points): array
    {
        $maxDays = (int) config('cs2price.price_history_days', 90);
        $cutoff = now()->subDays($maxDays)->toIso8601String();

        $points = array_values(array_filter(
            $points,
            fn ($p) => ($p['recorded_at'] ?? '') >= $cutoff
        ));

        $maxPoints = (int) config('cs2price.price_history_max_points', 3000);
        if (count($points) > $maxPoints) {
            $points = array_slice($points, -$maxPoints);
        }

        return $points;
    }
}
