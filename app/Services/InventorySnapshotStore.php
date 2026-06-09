<?php

namespace App\Services;

use App\Models\InventoryItemSnapshot;
use App\Models\InventoryValueSnapshot;
use Carbon\Carbon;

class InventorySnapshotStore
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function record(int $inventoryId, array $result, ?string $recordedAt = null): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $at = Carbon::parse($recordedAt ?? now());
        $items = $result['items'] ?? [];

        InventoryValueSnapshot::query()->create([
            'inventory_id' => $inventoryId,
            'total_cny' => (float) ($result['total_cny'] ?? 0),
            'total_vnd' => (int) ($result['total_vnd'] ?? 0),
            'total_empire_cny' => isset($result['total_empire_cny']) ? (float) $result['total_empire_cny'] : null,
            'total_empire_vnd' => isset($result['total_empire_vnd']) ? (int) $result['total_empire_vnd'] : null,
            'item_count' => (int) ($result['item_count'] ?? count($items)),
            'recorded_at' => $at,
        ]);

        $rows = [];
        $now = now();

        foreach ($items as $raw) {
            $item = (array) $raw;
            $assetId = trim((string) ($item['assetid'] ?? ''));
            $hash = trim((string) ($item['market_hash_name'] ?? ''));

            if ($assetId === '' || $hash === '') {
                continue;
            }

            $rows[] = [
                'inventory_id' => $inventoryId,
                'asset_id' => $assetId,
                'market_hash_name' => $hash,
                'display_name' => (string) ($item['name'] ?? $hash),
                'amount' => max(1, (int) ($item['amount'] ?? 1)),
                'buff_price_cny' => isset($item['buff_price_cny']) ? (float) $item['buff_price_cny'] : null,
                'line_total_cny' => isset($item['line_total_cny']) ? (float) $item['line_total_cny'] : null,
                'empire_price_cny' => isset($item['empire_price_cny']) ? (float) $item['empire_price_cny'] : null,
                'line_total_empire_cny' => isset($item['line_total_empire_cny']) ? (float) $item['line_total_empire_cny'] : null,
                'recorded_at' => $at,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            InventoryItemSnapshot::query()->insert($chunk);
        }

        $this->trimOldSnapshots($inventoryId);
    }

    public function closestValueSnapshot(int $inventoryId, Carbon $target): ?InventoryValueSnapshot
    {
        if (! $this->tablesExist()) {
            return null;
        }

        return InventoryValueSnapshot::query()
            ->where('inventory_id', $inventoryId)
            ->where('recorded_at', '<=', $target)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * @return array<string, InventoryItemSnapshot>
     */
    public function itemSetAt(int $inventoryId, Carbon $target): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $anchor = InventoryItemSnapshot::query()
            ->where('inventory_id', $inventoryId)
            ->where('recorded_at', '<=', $target)
            ->orderByDesc('recorded_at')
            ->value('recorded_at');

        if ($anchor === null) {
            return [];
        }

        $rows = InventoryItemSnapshot::query()
            ->where('inventory_id', $inventoryId)
            ->where('recorded_at', $anchor)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->asset_id] = $row;
        }

        return $map;
    }

    /**
     * @return list<array{date: string, total_cny: float, total_vnd: int, total_empire_cny: float, total_empire_vnd: int}>
     */
    public function dailyPortfolioTotals(Carbon $since): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $rows = InventoryValueSnapshot::query()
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['inventory_id', 'total_cny', 'total_vnd', 'total_empire_cny', 'total_empire_vnd', 'recorded_at']);

        /** @var array<string, array{total_cny: float, total_vnd: int, total_empire_cny: float, total_empire_vnd: int, latest_at: string}> $byDay */
        $byDay = [];

        foreach ($rows as $row) {
            $day = $row->recorded_at->timezone($this->timezone())->toDateString();
            $key = $day.'|'.$row->inventory_id;

            if (! isset($byDay[$key]) || $row->recorded_at->toIso8601String() >= $byDay[$key]['latest_at']) {
                $byDay[$key] = [
                    'total_cny' => (float) $row->total_cny,
                    'total_vnd' => (int) $row->total_vnd,
                    'total_empire_cny' => (float) ($row->total_empire_cny ?? 0),
                    'total_empire_vnd' => (int) ($row->total_empire_vnd ?? 0),
                    'latest_at' => $row->recorded_at->toIso8601String(),
                ];
            }
        }

        /** @var array<string, array{total_cny: float, total_vnd: int, total_empire_cny: float, total_empire_vnd: int}> $aggregated */
        $aggregated = [];

        foreach ($byDay as $compoundKey => $totals) {
            [$day] = explode('|', $compoundKey, 2);
            if (! isset($aggregated[$day])) {
                $aggregated[$day] = [
                    'total_cny' => 0.0,
                    'total_vnd' => 0,
                    'total_empire_cny' => 0.0,
                    'total_empire_vnd' => 0,
                ];
            }
            $aggregated[$day]['total_cny'] += $totals['total_cny'];
            $aggregated[$day]['total_vnd'] += $totals['total_vnd'];
            $aggregated[$day]['total_empire_cny'] += $totals['total_empire_cny'];
            $aggregated[$day]['total_empire_vnd'] += $totals['total_empire_vnd'];
        }

        ksort($aggregated);

        $out = [];
        foreach ($aggregated as $date => $totals) {
            $out[] = [
                'date' => $date,
                'total_cny' => round($totals['total_cny'], 2),
                'total_vnd' => $totals['total_vnd'],
                'total_empire_cny' => round($totals['total_empire_cny'], 2),
                'total_empire_vnd' => $totals['total_empire_vnd'],
            ];
        }

        return $out;
    }

    private function trimOldSnapshots(int $inventoryId): void
    {
        $maxDays = max(30, (int) config('cs2price.inventory_snapshot_days', 90));
        $cutoff = now()->subDays($maxDays);

        InventoryValueSnapshot::query()
            ->where('inventory_id', $inventoryId)
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        InventoryItemSnapshot::query()
            ->where('inventory_id', $inventoryId)
            ->where('recorded_at', '<', $cutoff)
            ->delete();
    }

    public function tablesExist(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $exists = \Illuminate\Support\Facades\Schema::hasTable('inventory_value_snapshots')
                && \Illuminate\Support\Facades\Schema::hasTable('inventory_item_snapshots');
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'));
    }
}
