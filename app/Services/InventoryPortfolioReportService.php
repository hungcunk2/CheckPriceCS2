<?php

namespace App\Services;

use App\Models\InventoryItemSnapshot;
use App\Models\InventoryValueSnapshot;
use App\Models\TrackedInventory;
use Carbon\Carbon;

class InventoryPortfolioReportService
{
    public function __construct(
        private InventorySnapshotStore $snapshots,
        private PriceHistoryStore $priceHistory,
        private ItemImageService $images,
        private TrackedInventoryStore $inventories,
    ) {}

    /** @var array<int, string> */
    private array $inventoryLabels = [];

    /** @var array<int, array{avatar_url: string|null}> */
    private array $inventoryMeta = [];

    /**
     * @return array{
     *   period_days: int,
     *   has_data: bool,
     *   summary: array<string, mixed>,
     *   trend: list<array<string, mixed>>,
     *   gainers: list<array<string, mixed>>,
     *   losers: list<array<string, mixed>>,
     *   added: list<array<string, mixed>>,
     *   removed: list<array<string, mixed>>
     * }
     */
    public function build(int $periodDays, ?int $userId = null, ?string $adminUsername = null): array
    {
        $periodDays = in_array($periodDays, [1, 7, 30], true) ? $periodDays : 7;
        $now = now($this->timezone());
        $since = $now->copy()->subDays($periodDays);

        $inventories = $this->resolveInventories($userId, $adminUsername);
        $inventoryIds = $inventories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->inventoryLabels = [];
        $this->inventoryMeta = [];

        foreach ($inventories as $inv) {
            $id = (int) $inv->id;
            $this->inventoryLabels[$id] = $this->inventoryLabel($inv);
            $this->inventoryMeta[$id] = [
                'avatar_url' => $this->images->avatarUrlForDisplay(
                    (string) ($inv->steam_id ?? ''),
                    $inv->steam_avatar_url ?? null,
                ),
            ];
        }

        $currentTotals = $this->aggregateCurrentTotals($inventories);
        $pastTotals = $this->aggregatePastTotals($inventories, $since);

        $movers = $this->collectPriceMovers($inventories, $since);
        $gainers = array_values(array_filter($movers, fn (array $row) => ($row['delta_cny'] ?? 0) > 0));
        $losers = array_values(array_filter($movers, fn (array $row) => ($row['delta_cny'] ?? 0) < 0));

        usort($gainers, fn ($a, $b) => ($b['delta_cny'] ?? 0) <=> ($a['delta_cny'] ?? 0));
        usort($losers, fn ($a, $b) => ($a['delta_cny'] ?? 0) <=> ($b['delta_cny'] ?? 0));

        [$added, $removed] = $this->collectCompositionChanges($inventories, $since);

        $trend = $this->snapshots->tablesExist()
            ? $this->snapshots->dailyPortfolioTotals($since->copy()->subDay(), $inventoryIds)
            : [];

        $hasPastSnapshot = $this->snapshots->tablesExist()
            && $inventoryIds !== []
            && InventoryValueSnapshot::query()
                ->whereIn('inventory_id', $inventoryIds)
                ->where('recorded_at', '<=', $since)
                ->exists();

        return [
            'period_days' => $periodDays,
            'scope' => [
                'user_id' => $userId,
                'admin_username' => $adminUsername,
                'inventory_count' => count($inventoryIds),
            ],
            'has_data' => $hasPastSnapshot,
            'summary' => $this->buildSummary($currentTotals, $pastTotals, $periodDays),
            'trend' => $trend,
            'gainers' => $this->enrichRows(array_slice($gainers, 0, 100)),
            'losers' => $this->enrichRows(array_slice($losers, 0, 100)),
            'added' => $this->enrichRows(array_slice($added, 0, 100)),
            'removed' => $this->enrichRows(array_slice($removed, 0, 100)),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrackedInventory>  $inventories
     * @return array{total_cny: float, total_vnd: int, total_empire_cny: float, total_empire_vnd: int, item_count: int}
     */
    private function aggregateCurrentTotals($inventories): array
    {
        $totals = [
            'total_cny' => 0.0,
            'total_vnd' => 0,
            'total_empire_cny' => 0.0,
            'total_empire_vnd' => 0,
            'item_count' => 0,
        ];

        foreach ($inventories as $inv) {
            $totals['total_cny'] += (float) ($inv->last_total_cny ?? 0);
            $totals['total_vnd'] += (int) ($inv->last_total_vnd ?? 0);
            $snap = is_array($inv->last_snapshot) ? $inv->last_snapshot : [];
            $totals['total_empire_cny'] += (float) ($snap['total_empire_cny'] ?? 0);
            $totals['total_empire_vnd'] += (int) ($snap['total_empire_vnd'] ?? 0);
            $totals['item_count'] += (int) ($inv->item_count ?? 0);
        }

        return $totals;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrackedInventory>  $inventories
     * @return array{total_cny: float, total_vnd: int, total_empire_cny: float, total_empire_vnd: int, item_count: int}
     */
    private function aggregatePastTotals($inventories, Carbon $since): array
    {
        $totals = [
            'total_cny' => 0.0,
            'total_vnd' => 0,
            'total_empire_cny' => 0.0,
            'total_empire_vnd' => 0,
            'item_count' => 0,
        ];

        $found = 0;

        foreach ($inventories as $inv) {
            $snap = $this->snapshots->closestValueSnapshot((int) $inv->id, $since);
            if ($snap === null) {
                continue;
            }
            $found++;
            $totals['total_cny'] += (float) $snap->total_cny;
            $totals['total_vnd'] += (int) $snap->total_vnd;
            $totals['total_empire_cny'] += (float) ($snap->total_empire_cny ?? 0);
            $totals['total_empire_vnd'] += (int) ($snap->total_empire_vnd ?? 0);
            $totals['item_count'] += (int) $snap->item_count;
        }

        if ($found === 0) {
            return array_merge($totals, ['missing' => true]);
        }

        return $totals;
    }

    /**
     * @param  array<string, float|int|bool>  $current
     * @param  array<string, float|int|bool>  $past
     * @return array<string, mixed>
     */
    private function buildSummary(array $current, array $past, int $periodDays): array
    {
        $missingPast = ! empty($past['missing']);

        return [
            'period_label' => $periodDays === 1 ? '24 giờ' : ($periodDays.' ngày'),
            'current' => [
                'total_cny' => round($current['total_cny'], 2),
                'total_vnd' => $current['total_vnd'],
                'total_empire_cny' => round($current['total_empire_cny'], 2),
                'total_empire_vnd' => $current['total_empire_vnd'],
                'item_count' => $current['item_count'],
            ],
            'past' => $missingPast ? null : [
                'total_cny' => round((float) $past['total_cny'], 2),
                'total_vnd' => (int) $past['total_vnd'],
                'total_empire_cny' => round((float) $past['total_empire_cny'], 2),
                'total_empire_vnd' => (int) $past['total_empire_vnd'],
                'item_count' => (int) $past['item_count'],
            ],
            'delta' => $missingPast ? null : [
                'total_cny' => round($current['total_cny'] - (float) $past['total_cny'], 2),
                'total_vnd' => $current['total_vnd'] - (int) $past['total_vnd'],
                'total_empire_cny' => round($current['total_empire_cny'] - (float) $past['total_empire_cny'], 2),
                'total_empire_vnd' => $current['total_empire_vnd'] - (int) $past['total_empire_vnd'],
                'item_count' => $current['item_count'] - (int) $past['item_count'],
            ],
            'delta_pct' => $missingPast ? null : [
                'total_cny' => $this->percentChange((float) $past['total_cny'], $current['total_cny']),
                'total_empire_cny' => $this->percentChange((float) $past['total_empire_cny'], $current['total_empire_cny']),
            ],
            'missing_past' => $missingPast,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrackedInventory>  $inventories
     * @return list<array<string, mixed>>
     */
    private function collectPriceMovers($inventories, Carbon $since): array
    {
        $movers = [];

        foreach ($inventories as $inv) {
            $currentItems = $this->currentItemMap($inv);
            $pastItems = $this->snapshots->itemSetAt((int) $inv->id, $since);
            $label = $this->inventoryLabels[(int) $inv->id] ?? ('#'.$inv->id);

            foreach ($currentItems as $assetId => $item) {
                $currentPrice = $item['buff_price_cny'] ?? null;
                if ($currentPrice === null) {
                    continue;
                }

                $refPrice = null;
                if (isset($pastItems[$assetId])) {
                    $refPrice = $pastItems[$assetId]->buff_price_cny;
                }

                if ($refPrice === null) {
                    $refPrice = $this->referenceUnitPriceFromHistory(
                        (string) ($item['market_hash_name'] ?? ''),
                        $since,
                    );
                }

                if ($refPrice === null || abs($currentPrice - $refPrice) < 0.01) {
                    continue;
                }

                $delta = round($currentPrice - $refPrice, 2);
                $amount = max(1, (int) ($item['amount'] ?? 1));

                $movers[] = [
                    'market_hash_name' => (string) ($item['market_hash_name'] ?? ''),
                    'display_name' => (string) ($item['display_name'] ?? $item['market_hash_name'] ?? ''),
                    'icon_url' => $item['icon_url'] ?? null,
                    'steam_icon_url' => $item['steam_icon_url'] ?? null,
                    'inventory_id' => (int) $inv->id,
                    'inventory_label' => $label,
                    'amount' => $amount,
                    'current_cny' => round((float) $currentPrice, 2),
                    'reference_cny' => round((float) $refPrice, 2),
                    'delta_cny' => $delta,
                    'delta_pct' => $this->percentChange((float) $refPrice, (float) $currentPrice),
                    'line_delta_cny' => round($delta * $amount, 2),
                ];
            }
        }

        return $movers;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrackedInventory>  $inventories
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function collectCompositionChanges($inventories, Carbon $since): array
    {
        $added = [];
        $removed = [];

        foreach ($inventories as $inv) {
            $current = $this->currentItemMap($inv);
            $past = $this->snapshots->itemSetAt((int) $inv->id, $since);
            $label = $this->inventoryLabels[(int) $inv->id] ?? ('#'.$inv->id);

            // Chưa có snapshot đầu kỳ → bỏ qua kho này (tránh liệt kê cả kho như "mới thêm").
            if ($past === []) {
                continue;
            }

            foreach ($current as $assetId => $item) {
                if (isset($past[$assetId])) {
                    continue;
                }

                $added[] = [
                    'market_hash_name' => (string) ($item['market_hash_name'] ?? ''),
                    'display_name' => (string) ($item['display_name'] ?? $item['market_hash_name'] ?? ''),
                    'icon_url' => $item['icon_url'] ?? null,
                    'steam_icon_url' => $item['steam_icon_url'] ?? null,
                    'inventory_id' => (int) $inv->id,
                    'inventory_label' => $label,
                    'amount' => max(1, (int) ($item['amount'] ?? 1)),
                    'line_total_cny' => isset($item['line_total_cny']) ? round((float) $item['line_total_cny'], 2) : null,
                    'line_total_empire_cny' => isset($item['line_total_empire_cny']) ? round((float) $item['line_total_empire_cny'], 2) : null,
                    'buff_price_cny' => isset($item['buff_price_cny']) ? round((float) $item['buff_price_cny'], 2) : null,
                ];
            }

            foreach ($past as $assetId => $row) {
                if (isset($current[$assetId])) {
                    continue;
                }

                $removed[] = [
                    'market_hash_name' => $row->market_hash_name,
                    'display_name' => $row->display_name ?? $row->market_hash_name,
                    'inventory_id' => (int) $inv->id,
                    'inventory_label' => $label,
                    'amount' => (int) $row->amount,
                    'line_total_cny' => $row->line_total_cny !== null ? round((float) $row->line_total_cny, 2) : null,
                    'line_total_empire_cny' => $row->line_total_empire_cny !== null ? round((float) $row->line_total_empire_cny, 2) : null,
                    'buff_price_cny' => $row->buff_price_cny !== null ? round((float) $row->buff_price_cny, 2) : null,
                ];
            }
        }

        usort($added, fn ($a, $b) => ($b['line_total_cny'] ?? 0) <=> ($a['line_total_cny'] ?? 0));
        usort($removed, fn ($a, $b) => ($b['line_total_cny'] ?? 0) <=> ($a['line_total_cny'] ?? 0));

        return [$added, $removed];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function currentItemMap(TrackedInventory $inv): array
    {
        $snap = is_array($inv->last_snapshot) ? $inv->last_snapshot : [];
        $items = $snap['items'] ?? [];
        $map = [];

        foreach ($items as $raw) {
            $item = (array) $raw;
            $assetId = trim((string) ($item['assetid'] ?? ''));
            if ($assetId === '') {
                continue;
            }

            $map[$assetId] = [
                'market_hash_name' => (string) ($item['market_hash_name'] ?? ''),
                'display_name' => (string) ($item['name'] ?? $item['market_hash_name'] ?? ''),
                'amount' => max(1, (int) ($item['amount'] ?? 1)),
                'buff_price_cny' => $item['buff_price_cny'] ?? null,
                'line_total_cny' => $item['line_total_cny'] ?? null,
                'line_total_empire_cny' => $item['line_total_empire_cny'] ?? null,
                'icon_url' => $item['icon_url'] ?? $item['steam_icon_url'] ?? null,
                'steam_icon_url' => $item['steam_icon_url'] ?? $item['icon_url'] ?? null,
            ];
        }

        if ($map !== [] || ! $this->snapshots->tablesExist()) {
            return $map;
        }

        $latestAt = InventoryItemSnapshot::query()
            ->where('inventory_id', $inv->id)
            ->orderByDesc('recorded_at')
            ->value('recorded_at');

        if ($latestAt === null) {
            return [];
        }

        $rows = InventoryItemSnapshot::query()
            ->where('inventory_id', $inv->id)
            ->where('recorded_at', $latestAt)
            ->get();

        foreach ($rows as $row) {
            $map[$row->asset_id] = [
                'market_hash_name' => $row->market_hash_name,
                'display_name' => $row->display_name ?? $row->market_hash_name,
                'amount' => (int) $row->amount,
                'buff_price_cny' => $row->buff_price_cny,
                'line_total_cny' => $row->line_total_cny,
                'line_total_empire_cny' => $row->line_total_empire_cny,
            ];
        }

        return $map;
    }

    private function referenceUnitPriceFromHistory(string $marketHashName, Carbon $since): ?float
    {
        if ($marketHashName === '') {
            return null;
        }

        $points = $this->priceHistory->points($marketHashName);
        $sinceIso = $since->toIso8601String();
        $candidate = null;

        foreach ($points as $point) {
            $at = (string) ($point['recorded_at'] ?? '');
            if ($at > $sinceIso) {
                break;
            }
            $candidate = (float) ($point['price_cny'] ?? 0);
        }

        return $candidate;
    }

    private function percentChange(float $from, float $to): ?float
    {
        if (abs($from) < 0.0001) {
            return null;
        }

        return round((($to - $from) / $from) * 100, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    /**
     * @return \Illuminate\Support\Collection<int, TrackedInventory>
     */
    private function resolveInventories(?int $userId, ?string $adminUsername)
    {
        if ($userId !== null) {
            return $this->inventories->modelsForUser($userId);
        }

        if ($adminUsername !== null && $adminUsername !== '') {
            return $this->inventories->modelsForAdmin($adminUsername);
        }

        return TrackedInventory::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    private function enrichRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $enriched = $this->images->enrichItemRowForDisplay([
                'market_hash_name' => (string) ($row['market_hash_name'] ?? ''),
                'icon_url' => $row['icon_url'] ?? $row['steam_icon_url'] ?? null,
                'steam_icon_url' => $row['steam_icon_url'] ?? $row['icon_url'] ?? null,
            ]);

            $row['icon_url'] = $enriched['icon_url'] ?? null;
            $row['steam_icon_hint'] = $enriched['steam_icon_hint'] ?? '';

            $invId = (int) ($row['inventory_id'] ?? 0);
            $row['inventory_avatar_url'] = $this->inventoryMeta[$invId]['avatar_url'] ?? null;

            return $row;
        }, $rows);
    }

    private function inventoryLabel(TrackedInventory $inv): string
    {
        $label = trim((string) ($inv->label ?? ''));
        if ($label !== '' && ! str_starts_with($label, 'http')) {
            return $label;
        }

        $persona = trim((string) ($inv->steam_persona_name ?? ''));
        if ($persona !== '') {
            return $persona;
        }

        return 'Kho #'.$inv->id;
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'));
    }
}
