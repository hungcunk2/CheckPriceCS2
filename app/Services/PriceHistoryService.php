<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;

class PriceHistoryService
{
    public function __construct(
        private PriceHistoryStore $store,
    ) {}

    public function recordFromItems(array $items, ?string $recordedAt = null): void
    {
        $at = $recordedAt ?? now($this->timezone())->toIso8601String();

        foreach ($items as $raw) {
            $item = (array) $raw;
            $hash = (string) ($item['market_hash_name'] ?? '');
            $price = $item['buff_price_cny'] ?? null;

            if ($hash === '' || $price === null) {
                continue;
            }

            $this->store->append($hash, (float) $price, isset($item['sell_num']) ? (int) $item['sell_num'] : null, $at);
        }
    }

    /**
     * @return array{
     *   current_2h: array{price_cny: float|null, sell_num: int|null, recorded_at: string|null, stale: bool},
     *   today_open: array{price_cny: float|null, sell_num: int|null, recorded_at: string|null}|null,
     *   yesterday: array{price_cny: float|null, sell_num: int|null, recorded_at: string|null}|null,
     *   days_7: array{price_cny: float|null, sell_num: int|null, recorded_at: string|null}|null,
     *   price_cny_delta_yesterday: float|null,
     *   price_cny_delta_today_open: float|null,
     *   price_cny_delta_days_7: float|null,
     *   sell_num_delta: int|null
     * }
     */
    public function resolve(string $marketHashName): array
    {
        $points = $this->store->points($marketHashName);
        usort($points, fn ($a, $b) => ($a['recorded_at'] ?? '') <=> ($b['recorded_at'] ?? ''));
        $now = now($this->timezone());
        $windowHours = (int) config('cs2price.price_current_window_hours', 2);

        $current2h = $this->latestSince($points, $now->copy()->subHours($windowHours));
        $latest = $this->latestSince($points, $now->copy()->subYears(10));
        $current = $current2h ?? $latest;

        $todayOpen = $this->firstAfterMidnight($points, $now->copy()->startOfDay());
        $yesterday = $this->firstAfterMidnight($points, $now->copy()->subDay()->startOfDay());
        $days7 = $this->firstAfterMidnight($points, $now->copy()->subDays(7)->startOfDay());

        $priceCnyDeltaYesterday = $this->priceDelta($current, $yesterday);
        $priceCnyDeltaTodayOpen = $this->priceDelta($current, $todayOpen);
        $priceCnyDeltaDays7 = $this->priceDelta($current, $days7);

        $sellNumDelta = null;
        if ($current !== null && $yesterday !== null) {
            $curNum = $current['sell_num'] ?? null;
            $yNum = $yesterday['sell_num'] ?? null;
            if ($curNum !== null && $yNum !== null) {
                $sellNumDelta = $curNum - $yNum;
            }
        }

        return [
            'current_2h' => [
                'price_cny' => $current['price_cny'] ?? null,
                'sell_num' => $current['sell_num'] ?? null,
                'recorded_at' => $current['recorded_at'] ?? null,
                'stale' => $current2h === null && $latest !== null,
            ],
            'today_open' => $todayOpen,
            'yesterday' => $yesterday,
            'days_7' => $days7,
            'price_cny_delta_yesterday' => $priceCnyDeltaYesterday,
            'price_cny_delta_today_open' => $priceCnyDeltaTodayOpen,
            'price_cny_delta_days_7' => $priceCnyDeltaDays7,
            'sell_num_delta' => $sellNumDelta,
        ];
    }

    /**
     * @param  array{price_cny: float, sell_num: int|null, recorded_at: string}|null  $current
     * @param  array{price_cny: float, sell_num: int|null, recorded_at: string}|null  $reference
     */
    private function priceDelta(?array $current, ?array $reference): ?float
    {
        if ($current === null || $reference === null) {
            return null;
        }

        return round($current['price_cny'] - $reference['price_cny'], 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function enrichItems(array $items): array
    {
        return array_map(function ($raw) {
            $item = (array) $raw;
            $hash = (string) ($item['market_hash_name'] ?? '');
            $item['price_history'] = $hash !== '' ? $this->resolve($hash) : null;

            return $item;
        }, $items);
    }

    /**
     * @param  list<array{recorded_at: string, price_cny: float, sell_num: int|null}>  $points
     * @return array{price_cny: float, sell_num: int|null, recorded_at: string}|null
     */
    private function latestSince(array $points, Carbon $since): ?array
    {
        $sinceIso = $since->toIso8601String();
        $found = null;

        foreach ($points as $point) {
            if (($point['recorded_at'] ?? '') < $sinceIso) {
                continue;
            }
            $found = $point;
        }

        return $found ? $this->normalizePoint($found) : null;
    }

    /**
     * @param  list<array{recorded_at: string, price_cny: float, sell_num: int|null}>  $points
     * @return array{price_cny: float, sell_num: int|null, recorded_at: string}|null
     */
    private function firstAfterMidnight(array $points, Carbon $dayStart): ?array
    {
        $start = $dayStart->toIso8601String();
        $end = $dayStart->copy()->addDay()->toIso8601String();

        foreach ($points as $point) {
            $at = $point['recorded_at'] ?? '';
            if ($at >= $start && $at < $end) {
                return $this->normalizePoint($point);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array{price_cny: float, sell_num: int|null, recorded_at: string}
     */
    private function normalizePoint(array $point): array
    {
        return [
            'price_cny' => (float) ($point['price_cny'] ?? 0),
            'sell_num' => isset($point['sell_num']) ? (int) $point['sell_num'] : null,
            'recorded_at' => (string) ($point['recorded_at'] ?? ''),
        ];
    }

    private function timezone(): CarbonTimeZone
    {
        return new CarbonTimeZone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'));
    }
}
