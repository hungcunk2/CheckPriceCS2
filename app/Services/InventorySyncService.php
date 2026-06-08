<?php

namespace App\Services;

use App\Models\User;
use App\Support\InventoryRefreshLimiter;
use App\Support\InventoryResultPersister;
use App\Support\InventorySyncStatus;
use App\Support\InventoryUrlMatcher;
use App\Support\SubscriptionSyncPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class InventorySyncService
{
    public function __construct(
        private TrackedInventoryStore $store,
        private InventoryPriceChecker $checker,
        private InventoryResultPersister $persister,
        private InventoryRefreshLimiter $refreshLimiter,
    ) {}

    /**
     * @param  Collection<int, object>  $dueRows
     * @return list<list<object>>
     */
    public function groupDueRows(Collection $dueRows, Collection $usersById): array
    {
        $groups = [];

        foreach ($dueRows as $row) {
            $isAdminInventory = $row->user_id === null;
            $plan = null;
            if (! $isAdminInventory && isset($usersById[(int) $row->user_id])) {
                $plan = $usersById[(int) $row->user_id]->subscription_plan;
            }

            $groupKey = $this->syncGroupKey($row, $plan, $isAdminInventory);
            $groups[$groupKey][] = $row;
        }

        return array_values($groups);
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array{ok: int, failed: int, messages: list<string>}
     */
    public function syncByInventoryIds(
        array $inventoryIds,
        bool $isManualRefresh,
        ?int $memberUserIdForLimiter = null,
        bool $trackStatus = false,
    ): array {
        $rows = [];
        foreach ($inventoryIds as $inventoryId) {
            $row = $this->store->find((int) $inventoryId);
            if ($row) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return ['ok' => 0, 'failed' => 0, 'messages' => ['Không tìm thấy kho.']];
        }

        if ($trackStatus) {
            foreach ($rows as $row) {
                InventorySyncStatus::markRunning((int) $row->id);
            }
        }

        $usersById = $this->usersForRows($rows);
        $groups = $this->groupDueRows(collect($rows), $usersById);
        if (count($groups) === 1) {
            return $this->syncGroup($groups[0], $usersById, $isManualRefresh, $memberUserIdForLimiter, $trackStatus);
        }

        $ok = 0;
        $failed = 0;
        $messages = [];
        foreach ($groups as $groupRows) {
            $partial = $this->syncGroup($groupRows, $usersById, $isManualRefresh, $memberUserIdForLimiter, $trackStatus);
            $ok += $partial['ok'];
            $failed += $partial['failed'];
            $messages = array_merge($messages, $partial['messages']);
        }

        return ['ok' => $ok, 'failed' => $failed, 'messages' => $messages];
    }

    /**
     * @param  list<object>  $rows
     * @param  Collection<int, User>  $usersById
     * @return array{ok: int, failed: int, messages: list<string>}
     */
    private function syncGroup(
        array $rows,
        Collection $usersById,
        bool $isManualRefresh,
        ?int $memberUserIdForLimiter,
        bool $trackStatus,
    ): array {
        $primary = $rows[0];
        $isAdminInventory = $primary->user_id === null;
        $plan = $this->planForRow($primary, $usersById);
        $forceFresh = SubscriptionSyncPolicy::requiresFreshSync($plan, $isAdminInventory);
        $empireMode = $isAdminInventory ? 'admin' : ($isManualRefresh ? 'member' : 'sync');

        try {
            $result = $this->checker->checkUrl(
                (string) $primary->url,
                $primary->label ?? null,
                refreshSteam: SubscriptionSyncPolicy::requiresFreshInventory($isManualRefresh),
                empireMode: $empireMode,
                forceFreshPrices: $forceFresh,
            );

            foreach ($rows as $row) {
                $payload = $result;
                $payload['label'] = $row->label ?? $payload['label'] ?? null;
                $payload['url'] = $row->url ?? $payload['url'];

                if ($row->user_id) {
                    $this->persister->persistForUser(
                        $payload,
                        (int) $row->user_id,
                        (int) $row->id,
                        (bool) ($row->is_public ?? false),
                    );
                } else {
                    $this->persister->persist($payload, (int) $row->id, (bool) ($row->is_public ?? false));
                }

                if ($trackStatus) {
                    InventorySyncStatus::markDone((int) $row->id, $this->statusPayloadFromResult($payload, $row));
                }
            }

            if ($isManualRefresh && $memberUserIdForLimiter !== null) {
                $user = $usersById->get($memberUserIdForLimiter) ?? User::query()->find($memberUserIdForLimiter);
                if ($user) {
                    $this->refreshLimiter->record($user);
                }
            }

            $label = $primary->label ?? $primary->url ?? ('#'.$primary->id);
            $message = sprintf(
                '%s: %d/%d skin có giá%s',
                $label,
                (int) $result['priced_count'],
                (int) $result['item_count'],
                $forceFresh ? ' (fresh)' : '',
            );

            return ['ok' => count($rows), 'failed' => 0, 'messages' => [$message]];
        } catch (RuntimeException $e) {
            foreach ($rows as $row) {
                if ($trackStatus) {
                    InventorySyncStatus::markFailed((int) $row->id, $e->getMessage());
                }
            }
            Log::warning('inventory-sync', ['inventory_ids' => array_map(fn ($r) => $r->id, $rows), 'error' => $e->getMessage()]);

            return ['ok' => 0, 'failed' => count($rows), 'messages' => [$e->getMessage()]];
        } catch (Throwable $e) {
            report($e);
            foreach ($rows as $row) {
                if ($trackStatus) {
                    InventorySyncStatus::markFailed((int) $row->id, 'Lỗi server khi đồng bộ.');
                }
            }

            return ['ok' => 0, 'failed' => count($rows), 'messages' => ['Lỗi server khi đồng bộ.']];
        }
    }

    /**
     * @param  list<object>  $rows
     * @return Collection<int, User>
     */
    private function usersForRows(array $rows): Collection
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            fn (object $row) => $row->user_id !== null ? (int) $row->user_id : null,
            $rows,
        ))));

        if ($userIds === []) {
            return collect();
        }

        return User::query()->whereIn('id', $userIds)->get()->keyBy('id');
    }

    private function planForRow(object $row, Collection $usersById): ?string
    {
        if ($row->user_id === null) {
            return null;
        }

        return $usersById->get((int) $row->user_id)?->subscription_plan;
    }

    private function syncGroupKey(object $row, ?string $plan, bool $isAdminInventory): string
    {
        $steamKey = trim((string) ($row->steam_id ?? ''));
        if ($steamKey === '') {
            $steamKey = InventoryUrlMatcher::steamIdFromUrlLocal((string) ($row->url ?? '')) ?? trim((string) ($row->url ?? ''));
        }

        $forceFresh = SubscriptionSyncPolicy::requiresFreshSync($plan, $isAdminInventory) ? '1' : '0';
        $empireMode = $isAdminInventory ? 'admin' : 'sync';

        return $steamKey.'|'.$forceFresh.'|'.$empireMode;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function statusPayloadFromResult(array $result, object $row): array
    {
        return [
            'message' => ! empty($result['inventory_empty'])
                ? 'Đã cập nhật — kho hiện chưa có item.'
                : sprintf(
                    'Đã cập nhật — %d/%d skin có giá.',
                    (int) $result['priced_count'],
                    (int) $result['item_count'],
                ),
            'inventory_id' => (int) $row->id,
            'item_count' => (int) $result['item_count'],
            'inventory_empty' => ! empty($result['inventory_empty']),
            'item_count_label' => ! empty($result['inventory_empty'])
                ? 'Kho hiện chưa có item'
                : (string) (int) $result['item_count'],
            'priced_count' => (int) $result['priced_count'],
            'empire_priced_count' => (int) ($result['empire_priced_count'] ?? 0),
            'total_cny' => (float) $result['total_cny'],
            'total_empire_cny' => (float) ($result['total_empire_cny'] ?? 0),
            'last_checked_at' => now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'),
        ];
    }
}
